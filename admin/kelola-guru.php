<?php
/**
 * Kelola Guru / Pengarah
 * Only accessible by admin role
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$db = getDB();
$pageTitle = 'Kelola Guru';
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($namaLengkap) || empty($password)) {
            $error = 'Username, nama lengkap, dan password wajib diisi.';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
        } else {
            // Check duplicate username
            $check = $db->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            if ($check->fetch()) {
                $error = 'Username sudah digunakan.';
            } else {
                $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, 'pembimbing')");
                $stmt->execute([
                    $username,
                    password_hash($password, PASSWORD_DEFAULT),
                    $namaLengkap,
                    $email ?: null
                ]);
                setFlash('success', 'Akun guru "' . $namaLengkap . '" berhasil dibuat.');
                redirect('/admin/kelola-guru.php');
            }
        }
    }

    if ($action === 'edit') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($namaLengkap)) {
            $error = 'Nama lengkap wajib diisi.';
        } else {
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $error = 'Password minimal 6 karakter.';
                } else {
                    $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ? WHERE id = ? AND role = 'pembimbing'");
                    $stmt->execute([$namaLengkap, $email ?: null, password_hash($password, PASSWORD_DEFAULT), $userId]);
                }
            } else {
                $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ? AND role = 'pembimbing'");
                $stmt->execute([$namaLengkap, $email ?: null, $userId]);
            }
            if (empty($error)) {
                setFlash('success', 'Data guru berhasil diperbarui.');
                redirect('/admin/kelola-guru.php');
            }
        }
    }

    if ($action === 'toggle') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ? AND role = 'pembimbing'");
        $stmt->execute([$userId]);
        setFlash('success', 'Status akun guru berhasil diubah.');
        redirect('/admin/kelola-guru.php');
    }

    if ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        // Check if guru has assigned students
        $checkSiswa = $db->prepare("SELECT COUNT(*) FROM siswa WHERE pembimbing_id = ?");
        $checkSiswa->execute([$userId]);
        $jumlahSiswa = $checkSiswa->fetchColumn();

        if ($jumlahSiswa > 0) {
            setFlash('danger', 'Tidak dapat menghapus guru ini karena masih memiliki ' . $jumlahSiswa . ' siswa binaan.');
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'pembimbing'");
            $stmt->execute([$userId]);
            setFlash('success', 'Akun guru berhasil dihapus.');
        }
        redirect('/admin/kelola-guru.php');
    }
}

// Fetch all guru/pembimbing
$guruList = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM siswa s WHERE s.pembimbing_id = u.id) as jumlah_siswa
    FROM users u 
    WHERE u.role = 'pembimbing' 
    ORDER BY u.created_at DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <!-- Header -->
    <div class="d-flex align-center justify-between mb-3" style="flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:1.2rem;font-weight:700;color:var(--text-heading);margin-bottom:4px;">
                <i class="fas fa-chalkboard-teacher" style="margin-right:8px;color:var(--primary);"></i>Kelola Guru /
                Pengarah
            </h2>
            <p style="font-size:.85rem;color:var(--text-muted);">Buat dan kelola akun guru pembimbing PKL</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addGuruModal')">
            <i class="fas fa-plus"></i> Tambah Guru
        </button>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger mb-2">
            <span><i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Guru Cards -->
    <?php if (empty($guruList)): ?>
        <div class="card animate-item" style="text-align:center;padding:50px 24px;">
            <i class="fas fa-user-slash" style="font-size:2.5rem;margin-bottom:12px;color:var(--border-light);"></i>
            <h3 style="font-size:1rem;color:var(--text-muted);font-weight:500;margin-bottom:4px;">Belum ada akun guru</h3>
            <p style="font-size:.85rem;color:var(--text-muted);">Klik "Tambah Guru" untuk membuat akun guru baru.</p>
        </div>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
            <?php foreach ($guruList as $guru): ?>
                <div class="card animate-item"
                    style="padding:0;overflow:hidden;position:relative;<?= !$guru['is_active'] ? 'opacity:0.7;' : '' ?>">
                    <!-- Status ribbon -->
                    <?php if (!$guru['is_active']): ?>
                        <div style="position:absolute;top:12px;right:12px;">
                            <span class="badge badge-danger" style="font-size:.7rem;">Nonaktif</span>
                        </div>
                    <?php endif; ?>

                    <!-- Card top: avatar + name -->
                    <div
                        style="padding:24px 20px 16px;display:flex;align-items:center;gap:14px;border-bottom:1px solid var(--border);">
                        <div
                            style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:1.2rem;flex-shrink:0;box-shadow:0 4px 12px rgba(28,57,142,0.25);">
                            <?= strtoupper(substr($guru['nama_lengkap'], 0, 1)) ?>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:1rem;color:var(--text-heading);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                                title="<?= htmlspecialchars($guru['nama_lengkap']) ?>">
                                <?= htmlspecialchars($guru['nama_lengkap']) ?>
                            </div>
                            <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px;">
                                <i class="fas fa-chalkboard-teacher" style="margin-right:4px;color:var(--primary);"></i>Guru /
                                Pengarah
                            </div>
                        </div>
                    </div>

                    <!-- Card body: details -->
                    <div style="padding:16px 20px;">
                        <div style="display:flex;flex-direction:column;gap:10px;font-size:.85rem;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-user"
                                    style="width:16px;text-align:center;color:var(--text-muted);font-size:.8rem;"></i>
                                <span style="color:var(--text-secondary);">Username:</span>
                                <code
                                    style="background:var(--bg-input);padding:2px 8px;border-radius:4px;font-size:.82rem;margin-left:auto;"><?= htmlspecialchars($guru['username']) ?></code>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-envelope"
                                    style="width:16px;text-align:center;color:var(--text-muted);font-size:.8rem;"></i>
                                <span style="color:var(--text-secondary);">Email:</span>
                                <span
                                    style="margin-left:auto;color:var(--text);font-size:.82rem;"><?= $guru['email'] ? htmlspecialchars($guru['email']) : '<span style="color:var(--text-muted);">—</span>' ?></span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-user-graduate"
                                    style="width:16px;text-align:center;color:var(--text-muted);font-size:.8rem;"></i>
                                <span style="color:var(--text-secondary);">Siswa Binaan:</span>
                                <span class="badge badge-primary" style="margin-left:auto;"><?= $guru['jumlah_siswa'] ?>
                                    siswa</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-calendar"
                                    style="width:16px;text-align:center;color:var(--text-muted);font-size:.8rem;"></i>
                                <span style="color:var(--text-secondary);">Terdaftar:</span>
                                <span
                                    style="margin-left:auto;font-size:.82rem;color:var(--text-muted);"><?= formatTanggal($guru['created_at']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card footer: actions -->
                    <div
                        style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;">
                        <button class="btn btn-outline btn-sm" title="Edit" onclick='editGuru(<?= json_encode([
                            "id" => $guru["id"],
                            "username" => $guru["username"],
                            "nama_lengkap" => $guru["nama_lengkap"],
                            "email" => $guru["email"] ?? ""
                        ]) ?>)'>
                            <i class="fas fa-edit" style="margin-right:4px;"></i> Edit
                        </button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Ubah status akun ini?')">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= $guru['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $guru['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                title="<?= $guru['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <i class="fas <?= $guru['is_active'] ? 'fa-ban' : 'fa-check' ?>" style="margin-right:4px;"></i>
                                <?= $guru['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                            </button>
                        </form>
                        <?php if ($guru['jumlah_siswa'] == 0): ?>
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Hapus akun guru ini? Tindakan ini tidak dapat dibatalkan.')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= $guru['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Info Card -->
    <div class="card mt-2 animate-item" style="background:rgba(28,57,142,0.04);border-color:rgba(28,57,142,0.1);">
        <div style="display:flex;align-items:flex-start;gap:12px;">
            <i class="fas fa-info-circle" style="color:var(--primary);font-size:1.1rem;margin-top:2px;"></i>
            <div style="font-size:.85rem;color:var(--text-secondary);">
                <strong style="color:var(--text);">Catatan:</strong><br>
                • Akun guru hanya bisa dibuat oleh Admin. Guru tidak dapat membuat akun sendiri.<br>
                • Guru yang sudah memiliki siswa binaan tidak dapat dihapus.<br>
                • Password default yang dibuat bisa diganti oleh guru setelah login.
            </div>
        </div>
    </div>
</main>

<!-- Modal: Tambah Guru -->
<div class="modal-overlay" id="addGuruModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-user-plus"
                    style="margin-right:8px;color:var(--primary);"></i>Tambah Akun Guru</h3>
            <button class="modal-close" onclick="closeModal('addGuruModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control"
                        placeholder="Contoh: Budi Testing S.Kom., M.Kom." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" placeholder="Username untuk login" required>
                    <small style="color:var(--text-muted);font-size:.75rem;">Username harus unik dan tanpa spasi</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter"
                        required minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addGuruModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Guru -->
<div class="modal-overlay" id="editGuruModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-user-edit" style="margin-right:8px;color:var(--primary);"></i>Edit
                Akun Guru</h3>
            <button class="modal-close" onclick="closeModal('editGuruModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="editGuruId">
            <div class="modal-body" style="padding:24px;">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" id="editGuruNama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <input type="text" id="editGuruUsername" class="form-control" disabled style="opacity:0.6;">
                    <small style="color:var(--text-muted);font-size:.75rem;">Username tidak dapat diubah</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="editGuruEmail" class="form-control"
                        placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="password" class="form-control"
                        placeholder="Kosongkan jika tidak ingin mengubah" minlength="6">
                    <small style="color:var(--text-muted);font-size:.75rem;">Isi hanya jika ingin mengubah
                        password</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editGuruModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editGuru(data) {
        document.getElementById('editGuruId').value = data.id;
        document.getElementById('editGuruNama').value = data.nama_lengkap;
        document.getElementById('editGuruUsername').value = data.username;
        document.getElementById('editGuruEmail').value = data.email || '';
        openModal('editGuruModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>