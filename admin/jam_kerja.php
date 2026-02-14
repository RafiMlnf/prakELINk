<?php
/**
 * Admin — Validasi Jam Kerja
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Validasi Jam Kerja';

// Handle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $status = $_POST['status'];
    $catatan = trim($_POST['catatan'] ?? '');

    $stmt = $db->prepare("UPDATE pengajuan_jam_kerja SET status = ?, catatan_admin = ? WHERE id = ?");
    if ($stmt->execute([$status, $catatan, $id])) {
        // Send notification
        $req = $db->prepare("SELECT s.user_id FROM pengajuan_jam_kerja p JOIN siswa s ON p.siswa_id = s.id WHERE p.id = ?");
        $req->execute([$id]);
        $req = $req->fetch();
        if ($req) {
            $msg = $status === 'disetujui' ? 'Pengajuan jam kerja Anda disetujui.' : 'Pengajuan jam kerja Anda ditolak/perlu revisi.';
            addNotifikasi($req['user_id'], 'jam_kerja', $msg, '/siswa/jam_kerja.php');
        }
        setFlash('success', 'Status pengajuan berhasil diperbarui.');
    } else {
        setFlash('danger', 'Gagal memperbarui status.');
    }
    redirect('/admin/jam_kerja.php');
}

// Filter
$filterStatus = $_GET['status'] ?? 'pending';
$where = "WHERE 1=1";
$params = [];

if ($filterStatus) {
    if ($filterStatus !== 'all') {
        $where .= " AND p.status = ?";
        $params[] = $filterStatus;
    }
}

// Fetch Data
$stmt = $db->prepare("
    SELECT p.*, s.nisn, u.nama_lengkap, s.kelas
    FROM pengajuan_jam_kerja p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    $where
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="card animate-item">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clock" style="margin-right:8px;color:var(--primary);"></i>Validasi
                Pengajuan Jam Kerja</h3>
        </div>

        <form method="GET" class="filter-bar" style="padding:0 0 16px;">
            <select name="status" class="form-control" onchange="this.form.submit()">
                <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>Semua</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                <option value="disetujui" <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Siswa</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="6" class="text-center">Tidak ada data.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td>
                                    <?= formatTanggal($r['created_at']) ?>
                                </td>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars($r['nama_lengkap']) ?>
                                    </strong><br>
                                    <span class="text-muted" style="font-size:0.8rem;">
                                        <?= htmlspecialchars($r['kelas']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= formatJam($r['jam_masuk']) ?>
                                </td>
                                <td>
                                    <?= formatJam($r['jam_pulang']) ?>
                                </td>
                                <td>
                                    <?php
                                    $badge = match ($r['status']) {
                                        'pending' => 'badge-warning',
                                        'disetujui' => 'badge-success',
                                        'ditolak' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>">
                                        <?= ucfirst($r['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick='openModalAction(<?= json_encode($r) ?>)'>
                                        <i class="fas fa-edit"></i> Proses
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Action Modal -->
<div class="modal-overlay" id="actionModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Proses Pengajuan</h3>
            <button class="modal-close" onclick="closeModal('actionModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="id" id="reqId">
            <div class="modal-body">
                <p>Proses pengajuan jam kerja untuk <strong id="reqName"></strong>?</p>
                <div class="form-group">
                    <label class="form-label">Keputusan</label>
                    <select name="status" class="form-control" required>
                        <option value="disetujui">Setujui</option>
                        <option value="ditolak">Tolak</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan (Opsional)</label>
                    <textarea name="catatan" class="form-control" rows="3"
                        placeholder="Alasan penolakan atau catatan lain..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('actionModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModalAction(data) {
        document.getElementById('reqId').value = data.id;
        document.getElementById('reqName').textContent = data.nama_lengkap;
        openModal('actionModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>