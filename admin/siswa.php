<?php
/**
 * Admin — Manage Students
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Data Siswa';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete' && isset($_POST['id'])) {
        $stmt = $db->prepare("SELECT user_id FROM siswa WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $siswa = $stmt->fetch();
        if ($siswa) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$siswa['user_id']]);
            setFlash('success', 'Data siswa berhasil dihapus.');
        }
        redirect('/admin/siswa.php');
    }

    if ($action === 'assign') {
        $stmt = $db->prepare("UPDATE siswa SET penempatan_id = ?, pembimbing_id = ? WHERE id = ?");
        $stmt->execute([
            $_POST['penempatan_id'] ?: null,
            $_POST['pembimbing_id'] ?: null,
            $_POST['siswa_id']
        ]);
        setFlash('success', 'Penempatan siswa berhasil diperbarui.');
        redirect('/admin/siswa.php');
    }

    if ($action === 'add') {
        $namaLengkap = trim($_POST['nama_lengkap']);
        $username = strtolower(preg_replace('/\s+/', '_', $namaLengkap));
        $username = preg_replace('/[^a-z0-9_]/', '', $username);
        $result = registerUser([
            'username' => $username,
            'password' => $_POST['password'],
            'nama_lengkap' => $namaLengkap,
            'email' => $_POST['email'] ?? '',
            'nisn' => $_POST['nisn'],
            'kelas' => $_POST['kelas'],
            'jurusan' => 'Elektronika Industri',
            'no_hp' => $_POST['no_hp'] ?? '',
        ]);
        setFlash($result['success'] ? 'success' : 'danger', $result['message']);
        redirect('/admin/siswa.php');
    }

    if ($action === 'reset_password') {
        $stmt = $db->prepare("SELECT s.user_id, s.nisn, u.nama_lengkap FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $stmt->execute([$_POST['id']]);
        $data = $stmt->fetch();

        if ($data) {
            $newPassword = $data['nisn'];
            $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                $data['user_id']
            ]);
            setFlash('success', "Password <b>{$data['nama_lengkap']}</b> berhasil direset menjadi NISN (<b>{$newPassword}</b>).");
        } else {
            setFlash('danger', 'Data siswa tidak ditemukan.');
        }
        redirect('/admin/siswa.php');
    }
}

// Fetch students
$students = $db->query("
    SELECT s.*, u.nama_lengkap, u.username, u.email, u.is_active, u.foto,
           p.nama_perusahaan,
           pb.nama_lengkap AS pembimbing_nama
    FROM siswa s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN penempatan p ON s.penempatan_id = p.id
    LEFT JOIN users pb ON s.pembimbing_id = pb.id
    ORDER BY s.kelas ASC, u.nama_lengkap ASC
")->fetchAll();

// Fetch placements for dropdown
$placements = $db->query("SELECT id, nama_perusahaan FROM penempatan WHERE is_active = 1 ORDER BY nama_perusahaan")->fetchAll();
$pembimbings = $db->query("SELECT id, nama_lengkap FROM users WHERE role = 'pembimbing' AND is_active = 1 ORDER BY nama_lengkap")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="d-flex align-center justify-between mb-3" style="flex-wrap:wrap;gap:12px;">
        <div>
            <h2 style="font-size:1.2rem;font-weight:700;color:var(--text-heading);margin-bottom:4px;">
                <i class="fas fa-user-graduate" style="margin-right:8px;color:var(--primary);"></i>Daftar Siswa PKL
            </h2>
            <p style="font-size:.85rem;color:var(--text-muted);">
                Total <?= count($students) ?> siswa terdaftar
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="fas fa-plus"></i> Tambah Siswa
        </button>
    </div>

    <?php if (empty($students)): ?>
        <div class="card animate-item" style="text-align:center;padding:50px 24px;">
            <i class="fas fa-users" style="font-size:2.5rem;margin-bottom:12px;color:var(--border-light);"></i>
            <h3 style="font-size:1rem;color:var(--text-muted);font-weight:500;margin-bottom:4px;">Belum ada data siswa</h3>
            <p style="font-size:.85rem;color:var(--text-muted);">Klik "Tambah Siswa" untuk menambahkan data.</p>
        </div>
    <?php else: ?>
        <?php
        // Group students by kelas
        $groupedStudents = [];
        foreach ($students as $s) {
            $kelas = $s['kelas'] ?: 'Belum ditentukan';
            $groupedStudents[$kelas][] = $s;
        }
        ?>
        <?php foreach ($groupedStudents as $kelas => $kelasStudents): ?>
            <div style="margin-bottom:24px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <div style="width:6px;height:24px;border-radius:3px;background:var(--primary);"></div>
                    <h3 style="font-size:1rem;font-weight:700;color:var(--text-heading);">
                        <?= htmlspecialchars($kelas) ?>
                    </h3>
                    <span class="badge badge-primary" style="font-size:.72rem;">
                        <?= count($kelasStudents) ?> siswa
                    </span>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;">
                    <?php foreach ($kelasStudents as $s): ?>
                        <div class="card animate-item" style="padding:0;overflow:hidden;">
                            <!-- Photo header -->
                            <div style="padding:20px 20px 0;display:flex;gap:16px;align-items:flex-start;">
                                <?php if (!empty($s['foto']) && file_exists(__DIR__ . '/../uploads/profil/' . $s['foto'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/profil/<?= $s['foto'] ?>" alt="Foto"
                                        style="width:100px;height:100px;border-radius:12px;object-fit:cover;border:3px solid var(--border);flex-shrink:0;">
                                <?php else: ?>
                                    <div
                                        style="width:100px;height:100px;border-radius:12px;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:2rem;flex-shrink:0;box-shadow:0 4px 12px rgba(28,57,142,0.2);">
                                        <?= strtoupper(substr($s['nama_lengkap'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div style="flex:1;min-width:0;padding-top:4px;">
                                    <div style="font-weight:700;font-size:1rem;color:var(--text-heading);line-height:1.3;"
                                        title="<?= htmlspecialchars($s['nama_lengkap']) ?>">
                                        <?= htmlspecialchars($s['nama_lengkap']) ?>
                                    </div>
                                    <!-- Username removed -->
                                    <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px;font-size:.82rem;">
                                        <div style="color:var(--text-secondary);">
                                            <i class="fas fa-id-card" style="width:16px;color:var(--text-muted);"></i>
                                            NISN: <?= htmlspecialchars($s['nisn']) ?>
                                        </div>
                                        <div style="color:var(--text-secondary);">
                                            <i class="fas fa-graduation-cap" style="width:16px;color:var(--text-muted);"></i>
                                            <?= htmlspecialchars($s['jurusan']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Info body -->
                            <div style="padding:14px 20px;display:flex;flex-direction:column;gap:8px;font-size:.85rem;">
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-building"
                                        style="width:16px;text-align:center;color:var(--text-muted);font-size:.8rem;"></i>
                                    <span style="color:var(--text-secondary);">PKL:</span>
                                    <?php if ($s['nama_perusahaan']): ?>
                                        <span class="badge badge-success"
                                            style="margin-left:auto;font-size:.72rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                            title="<?= htmlspecialchars($s['nama_perusahaan']) ?>">
                                            <?= htmlspecialchars($s['nama_perusahaan']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="margin-left:auto;font-size:.72rem;">Belum</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i class="fas fa-chalkboard-teacher"
                                        style="width:16px;text-align:center;color:var(--text-muted);font-size:.8rem;"></i>
                                    <span style="color:var(--text-secondary);">Pembina:</span>
                                    <span style="margin-left:auto;font-size:.82rem;color:var(--text);">
                                        <?= htmlspecialchars($s['pembimbing_nama'] ?? '—') ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Card footer: actions -->
                            <div
                                style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;">
                                <button class="btn btn-outline btn-sm" title="Assign Penempatan"
                                    onclick="openAssign(<?= $s['id'] ?>, '<?= htmlspecialchars($s['nama_lengkap']) ?>', <?= $s['penempatan_id'] ?? 'null' ?>, <?= $s['pembimbing_id'] ?? 'null' ?>)">
                                    <i class="fas fa-map-pin" style="margin-right:4px;"></i> Assign
                                </button>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirmAction('Reset password siswa ini menjadi NISN?')">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-warning btn-sm" title="Reset Password ke NISN">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirmAction('Hapus siswa ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<!-- Add Student Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Tambah Siswa Baru</h3>
            <button class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap siswa"
                        required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">NISN * <span
                                style="font-weight:400;color:var(--text-muted);font-size:.75rem;">(digunakan sebagai
                                login)</span></label>
                        <input type="text" name="nisn" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kelas *</label>
                        <select name="kelas" class="form-control" required>
                            <option value="">-- Pilih Kelas --</option>
                            <option value="XII ELIN 1">XII ELIN 1</option>
                            <option value="XII ELIN 2">XII ELIN 2</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Jurusan</label>
                    <input type="text" class="form-control" value="Elektronika Industri" disabled style="opacity:.7;">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">No. HP</label>
                        <input type="text" name="no_hp" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required minlength="6"
                        placeholder="Min. 6 karakter">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Placement Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Assign Penempatan</h3>
            <button class="modal-close" onclick="closeModal('assignModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="siswa_id" id="assignSiswaId">
            <div class="modal-body">
                <p class="mb-2" style="color:var(--text-secondary);">Siswa: <strong id="assignSiswaName"></strong></p>
                <div class="form-group">
                    <label class="form-label">Tempat PKL</label>
                    <select name="penempatan_id" id="assignPenempatan" class="form-control">
                        <option value="">-- Pilih Penempatan --</option>
                        <?php foreach ($placements as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= htmlspecialchars($p['nama_perusahaan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Guru Pengarah</label>
                    <select name="pembimbing_id" id="assignPembimbing" class="form-control">
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach ($pembimbings as $pb): ?>
                            <option value="<?= $pb['id'] ?>">
                                <?= htmlspecialchars($pb['nama_lengkap']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('assignModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAssign(id, name, penempatanId, pembimbingId) {
        document.getElementById('assignSiswaId').value = id;
        document.getElementById('assignSiswaName').textContent = name;
        document.getElementById('assignPenempatan').value = penempatanId || '';
        document.getElementById('assignPembimbing').value = pembimbingId || '';
        openModal('assignModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>