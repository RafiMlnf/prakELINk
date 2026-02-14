<?php
/**
 * Student — Profile Management
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Profil Saya';
$siswaId = $_SESSION['siswa_id'];

// Fetch full profile
$profile = $db->prepare("
    SELECT u.*, s.nisn, s.kelas, s.jurusan, s.no_hp,
           p.nama_perusahaan, p.alamat as alamat_pkl
    FROM users u
    JOIN siswa s ON s.user_id = u.id
    LEFT JOIN penempatan p ON s.penempatan_id = p.id
    WHERE s.id = ?
");
$profile->execute([$siswaId]);
$profile = $profile->fetch();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $namaLengkap = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email'] ?? '');
        $noHp = trim($_POST['no_hp'] ?? '');
        $userId = $profile['id'];

        // Handle Photo Upload (cropped base64 or fallback to file)
        $photoPath = $profile['foto'];
        $croppedData = $_POST['cropped_foto'] ?? '';

        if (!empty($croppedData)) {
            // Handle cropped base64 image
            $uploadDir = __DIR__ . '/../uploads/profil/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $data = explode(',', $croppedData);
            $imageData = base64_decode(end($data));

            if ($imageData !== false) {
                $newFileName = 'user_' . $userId . '_' . time() . '.jpg';
                $destination = $uploadDir . $newFileName;

                if (file_put_contents($destination, $imageData)) {
                    if ($profile['foto'] && file_exists($uploadDir . $profile['foto'])) {
                        unlink($uploadDir . $profile['foto']);
                    }
                    $photoPath = $newFileName;
                } else {
                    setFlash('danger', 'Gagal menyimpan foto.');
                    redirect('/siswa/profil.php');
                }
            }
        } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['foto']['tmp_name'];
            $fileName = $_FILES['foto']['name'];
            $fileSize = $_FILES['foto']['size'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png'];
            if (!in_array($fileExt, $allowedExts)) {
                setFlash('danger', 'Hanya format JPG, JPEG, dan PNG yang diperbolehkan.');
                redirect('/siswa/profil.php');
            }

            if ($fileSize > 2 * 1024 * 1024) {
                setFlash('danger', 'Ukuran foto maksimal 2MB.');
                redirect('/siswa/profil.php');
            }

            $uploadDir = __DIR__ . '/../uploads/profil/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = 'user_' . $userId . '_' . time() . '.' . $fileExt;
            $destination = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmp, $destination)) {
                if ($profile['foto'] && file_exists($uploadDir . $profile['foto'])) {
                    unlink($uploadDir . $profile['foto']);
                }
                $photoPath = $newFileName;
            } else {
                setFlash('danger', 'Gagal mengupload foto.');
                redirect('/siswa/profil.php');
            }
        }

        $db->prepare("UPDATE users SET nama_lengkap = ?, email = ?, foto = ? WHERE id = ?")->execute([
            $namaLengkap,
            $email,
            $photoPath,
            $userId
        ]);
        $db->prepare("UPDATE siswa SET no_hp = ? WHERE id = ?")->execute([
            $noHp,
            $siswaId
        ]);

        $_SESSION['nama_lengkap'] = $namaLengkap;
        $_SESSION['foto'] = $photoPath;

        setFlash('success', 'Profil berhasil diperbarui.');
        redirect('/siswa/profil.php');
    }

    if ($action === 'change_password') {
        $currentPw = $_POST['current_password'];
        $newPw = $_POST['new_password'];
        $confirmPw = $_POST['confirm_password'];

        $user = $db->prepare("SELECT password FROM users WHERE id = ?");
        $user->execute([$profile['id']]);
        $user = $user->fetch();

        if (!password_verify($currentPw, $user['password'])) {
            setFlash('danger', 'Password lama salah.');
        } elseif ($newPw !== $confirmPw) {
            setFlash('danger', 'Konfirmasi password baru tidak cocok.');
        } elseif (strlen($newPw) < 6) {
            setFlash('danger', 'Password baru minimal 6 karakter.');
        } else {
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([
                password_hash($newPw, PASSWORD_DEFAULT),
                $profile['id']
            ]);
            setFlash('success', 'Password berhasil diubah.');
        }
        redirect('/siswa/profil.php');
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<!-- Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
<style>
    .cropper-view-box,
    .cropper-face {
        border-radius: 50%;
    }

    .cropper-view-box {
        outline: 2px solid #fff;
        outline-offset: -2px;
    }

    .cropper-point {
        background-color: var(--primary);
    }

    .cropper-line {
        background-color: rgba(255, 255, 255, 0.3);
    }
</style>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="grid-2">
        <!-- Profile Card -->
        <div class="card animate-item">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-user-circle"
                        style="margin-right:8px;color:var(--primary);"></i>Informasi Profil</h3>
            </div>

            <form method="POST" enctype="multipart/form-data" id="profileForm">
                <input type="hidden" name="action" value="update_profile">
                <input type="hidden" name="cropped_foto" id="croppedFotoInput">

                <div style="text-align:center;margin-bottom:24px;position:relative;">
                    <div class="profile-avatar-container"
                        style="position:relative;width:120px;height:120px;margin:0 auto 16px;">
                        <?php if (!empty($profile['foto']) && file_exists(__DIR__ . '/../uploads/profil/' . $profile['foto'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/profil/<?= $profile['foto'] ?>" alt="Foto Profil"
                                id="avatarPreview"
                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;border:3px solid var(--primary-light);">
                        <?php else: ?>
                            <div id="avatarPlaceholder"
                                style="width:100%;height:100%;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;color:white;border:3px solid var(--primary-light);">
                                <?= strtoupper(substr($profile['nama_lengkap'], 0, 1)) ?>
                            </div>
                            <img src="" alt="Foto Profil" id="avatarPreview"
                                style="width:100%;height:100%;object-fit:cover;border-radius:50%;border:3px solid var(--primary-light);display:none;position:absolute;top:0;left:0;">
                        <?php endif; ?>

                        <label for="fotoInput"
                            style="position:absolute;bottom:0;right:0;background:var(--primary);color:white;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--bg-card);box-shadow:0 2px 5px rgba(0,0,0,0.2);z-index:2;">
                            <i class="fas fa-camera" style="font-size:0.8rem;"></i>
                        </label>
                        <input type="file" id="fotoInput" style="display:none;"
                            accept="image/png, image/jpeg, image/jpg">
                    </div>

                    <h3 style="color:var(--text-heading);margin-bottom:4px;">
                        <?= htmlspecialchars($profile['nama_lengkap']) ?>
                    </h3>


                    <div class="alert alert-warning"
                        style="display:inline-flex;padding:6px 12px;font-size:0.75rem;margin-bottom:0;">
                        <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>
                        Pastikan menggunakan foto profil FORMAL (Seragam/Kemeja).
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control"
                        value="<?= htmlspecialchars($profile['nama_lengkap']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                        value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">No. HP</label>
                    <input type="text" name="no_hp" class="form-control"
                        value="<?= htmlspecialchars($profile['no_hp'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">
            <!-- PKL Info -->
            <div class="card animate-item">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-id-card"
                            style="margin-right:8px;color:var(--primary);"></i>Data PKL</h3>
                </div>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <div class="info-row"
                        style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                        <span style="color:var(--text-muted);font-size:.85rem;">NISN</span>
                        <span style="font-weight:600;"><?= htmlspecialchars($profile['nisn']) ?></span>
                    </div>
                    <div class="info-row"
                        style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                        <span style="color:var(--text-muted);font-size:.85rem;">Kelas</span>
                        <span style="font-weight:600;"><?= htmlspecialchars($profile['kelas']) ?></span>
                    </div>
                    <div class="info-row"
                        style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                        <span style="color:var(--text-muted);font-size:.85rem;">Jurusan</span>
                        <span style="font-weight:600;"><?= htmlspecialchars($profile['jurusan']) ?></span>
                    </div>
                    <div class="info-row" style="display:flex;justify-content:space-between;padding:8px 0;">
                        <span style="color:var(--text-muted);font-size:.85rem;">Penempatan</span>
                        <span style="font-weight:600;">
                            <?= htmlspecialchars($profile['nama_perusahaan'] ?? 'Belum ditempatkan') ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card animate-item">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-lock"
                            style="margin-right:8px;color:var(--primary);"></i>Ganti Password</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label class="form-label">Password Lama</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password Baru</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-full">
                        <i class="fas fa-key"></i> Ubah Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Crop Modal -->
<div id="cropModal"
    style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center;">
    <div
        style="background:var(--bg-card);border-radius:16px;width:90%;max-width:500px;overflow:hidden;box-shadow:0 25px 60px rgba(0,0,0,0.4);">
        <div
            style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
            <h3 style="font-size:1rem;font-weight:700;color:var(--text-heading);margin:0;">
                <i class="fas fa-crop-alt" style="margin-right:8px;color:var(--primary);"></i>Crop Foto Profil
            </h3>
            <button type="button" onclick="closeCropModal()"
                style="background:none;border:none;font-size:1.2rem;color:var(--text-muted);cursor:pointer;padding:4px;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div style="padding:16px;background:#1a1a2e;">
            <div style="max-height:400px;overflow:hidden;">
                <img id="cropImage" src="" style="display:block;max-width:100%;">
            </div>
        </div>
        <div style="padding:12px 20px;display:flex;align-items:center;gap:12px;border-top:1px solid var(--border);">
            <i class="fas fa-search-minus" style="color:var(--text-muted);font-size:.8rem;"></i>
            <input type="range" id="zoomSlider" min="0.1" max="3" step="0.01" value="1"
                style="flex:1;accent-color:var(--primary);">
            <i class="fas fa-search-plus" style="color:var(--text-muted);font-size:.8rem;"></i>
            <div style="display:flex;gap:6px;margin-left:8px;">
                <button type="button" onclick="rotateCrop(-90)" class="btn btn-outline btn-sm" title="Putar Kiri">
                    <i class="fas fa-undo"></i>
                </button>
                <button type="button" onclick="rotateCrop(90)" class="btn btn-outline btn-sm" title="Putar Kanan">
                    <i class="fas fa-redo"></i>
                </button>
            </div>
        </div>
        <div
            style="padding:12px 20px 16px;display:flex;gap:10px;justify-content:flex-end;border-top:1px solid var(--border);">
            <button type="button" class="btn btn-outline" onclick="closeCropModal()">Batal</button>
            <button type="button" class="btn btn-primary" onclick="applyCrop()">
                <i class="fas fa-check" style="margin-right:6px;"></i>Simpan
            </button>
        </div>
    </div>
</div>

<!-- Cropper.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
<script>
    let cropper = null;

    document.getElementById('fotoInput').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const allowed = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!allowed.includes(file.type)) {
            alert('Hanya format JPG, JPEG, dan PNG yang diperbolehkan.');
            this.value = '';
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('Ukuran file maksimal 5MB.');
            this.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (ev) {
            const cropImage = document.getElementById('cropImage');
            cropImage.src = ev.target.result;

            const modal = document.getElementById('cropModal');
            modal.style.display = 'flex';

            if (cropper) { cropper.destroy(); cropper = null; }

            cropImage.onload = function () {
                cropper = new Cropper(cropImage, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.85,
                    cropBoxResizable: true,
                    cropBoxMovable: true,
                    guides: false,
                    center: true,
                    highlight: false,
                    background: false,
                    responsive: true,
                    ready: function () {
                        document.getElementById('zoomSlider').value = 1;
                    }
                });
            };
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('zoomSlider').addEventListener('input', function () {
        if (cropper) cropper.zoomTo(parseFloat(this.value));
    });

    function rotateCrop(deg) {
        if (cropper) cropper.rotate(deg);
    }

    function closeCropModal() {
        document.getElementById('cropModal').style.display = 'none';
        if (cropper) { cropper.destroy(); cropper = null; }
        document.getElementById('fotoInput').value = '';
    }

    function applyCrop() {
        if (!cropper) return;

        const canvas = cropper.getCroppedCanvas({
            width: 500,
            height: 500,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
        });

        const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

        // Set hidden input
        document.getElementById('croppedFotoInput').value = dataUrl;

        // Update avatar preview
        const preview = document.getElementById('avatarPreview');
        const placeholder = document.getElementById('avatarPlaceholder');
        preview.src = dataUrl;
        preview.style.display = '';
        if (placeholder) placeholder.style.display = 'none';

        // Close modal
        document.getElementById('cropModal').style.display = 'none';
        if (cropper) { cropper.destroy(); cropper = null; }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>