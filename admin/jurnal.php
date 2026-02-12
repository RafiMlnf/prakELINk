<?php
/**
 * Admin — Review Journals
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Jurnal PKL';

// Handle review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'review') {
        $jurnalId = $_POST['jurnal_id'];
        $newStatus = $_POST['status'];
        $stmt = $db->prepare("UPDATE jurnal SET status = ?, catatan_pembimbing = ? WHERE id = ?");
        $stmt->execute([
            $newStatus,
            trim($_POST['catatan_pembimbing'] ?? ''),
            $jurnalId
        ]);

        // Send notification to student
        $siswaUser = $db->prepare("
            SELECT u.id as user_id, j.judul_kegiatan 
            FROM jurnal j 
            JOIN siswa s ON j.siswa_id = s.id 
            JOIN users u ON s.user_id = u.id 
            WHERE j.id = ?
        ");
        $siswaUser->execute([$jurnalId]);
        $siswaUser = $siswaUser->fetch();
        if ($siswaUser && in_array($newStatus, ['revisi', 'disetujui'])) {
            $notifPesan = match ($newStatus) {
                'revisi' => 'Jurnal "' . $siswaUser['judul_kegiatan'] . '" perlu direvisi.',
                'disetujui' => 'Jurnal "' . $siswaUser['judul_kegiatan'] . '" telah disetujui!'
            };
            addNotifikasi($siswaUser['user_id'], 'jurnal', $notifPesan, '/siswa/jurnal.php');
        }

        setFlash('success', 'Jurnal berhasil di-review.');
        redirect('/admin/jurnal.php');
    }
}

// Filters
$filterSiswa = $_GET['siswa_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($filterSiswa) {
    $where .= " AND j.siswa_id = ?";
    $params[] = $filterSiswa;
}
if ($filterStatus) {
    $where .= " AND j.status = ?";
    $params[] = $filterStatus;
}

$stmt = $db->prepare("
    SELECT j.*, u.nama_lengkap, s.kelas
    FROM jurnal j
    JOIN siswa s ON j.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    $where
    ORDER BY j.tanggal DESC
");
$stmt->execute($params);
$journals = $stmt->fetchAll();

$students = $db->query("SELECT s.id, u.nama_lengkap FROM siswa s JOIN users u ON s.user_id = u.id ORDER BY u.nama_lengkap")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-book" style="margin-right:8px;color:var(--primary);"></i>Review
                Jurnal PKL</h3>
        </div>

        <form method="GET" class="filter-bar" style="padding:0 0 16px;">
            <select name="siswa_id" class="form-control">
                <option value="">Semua Siswa</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $filterSiswa == $s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['nama_lengkap']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="form-control">
                <option value="">Semua Status</option>
                <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="disetujui" <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="revisi" <?= $filterStatus === 'revisi' ? 'selected' : '' ?>>Revisi</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <?php if (empty($journals)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>Tidak ada jurnal</h3>
            </div>
        <?php else: ?>
            <div class="jurnal-list">
                <?php foreach ($journals as $j):
                    $dt = new DateTime($j['tanggal']);
                    $bulanShort = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                    ?>
                    <div class="jurnal-item animate-item">
                        <div class="jurnal-date">
                            <div class="day">
                                <?= $dt->format('d') ?>
                            </div>
                            <div class="month">
                                <?= $bulanShort[(int) $dt->format('m') - 1] ?>
                            </div>
                        </div>
                        <div class="jurnal-content">
                            <h4>
                                <?= htmlspecialchars($j['judul_kegiatan']) ?>
                            </h4>
                            <p>
                                <?= htmlspecialchars(mb_substr($j['deskripsi_kegiatan'], 0, 150)) ?>...
                            </p>
                            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <span style="font-size:.78rem;color:var(--text-muted);">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($j['nama_lengkap']) ?> —
                                    <?= $j['kelas'] ?>
                                </span>
                                <?php
                                $bc = match ($j['status']) {
                                    'disetujui' => 'badge-success',
                                    'revisi' => 'badge-warning',
                                    default => 'badge-info'
                                };
                                ?>
                                <span class="badge <?= $bc ?>">
                                    <?= ucfirst($j['status']) ?>
                                </span>
                            </div>
                            <?php if ($j['catatan_pembimbing']): ?>
                                <div
                                    style="margin-top:8px;padding:8px 12px;background:var(--bg-input);border-radius:var(--radius-sm);font-size:.82rem;">
                                    <strong style="color:var(--primary-light);">Catatan:</strong>
                                    <?= htmlspecialchars($j['catatan_pembimbing']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="jurnal-actions">
                            <button class="btn btn-outline btn-sm" onclick='openReview(<?= json_encode($j) ?>)' title="Review">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($j['foto_kegiatan']): ?>
                                <a href="<?= BASE_URL ?>/assets/uploads/<?= $j['foto_kegiatan'] ?>" target="_blank"
                                    class="btn btn-outline btn-sm" title="Lihat Foto">
                                    <i class="fas fa-image"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($j['file_laporan']): ?>
                                <a href="<?= BASE_URL ?>/assets/uploads/<?= $j['file_laporan'] ?>" target="_blank"
                                    class="btn btn-outline btn-sm" title="File Laporan">
                                    <i class="fas fa-file-download"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Review Jurnal</h3>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="jurnal_id" id="reviewId">
            <div class="modal-body">
                <div style="margin-bottom:16px;padding:16px;background:var(--bg-input);border-radius:var(--radius-sm);">
                    <h4 id="reviewTitle" style="margin-bottom:6px;"></h4>
                    <p id="reviewDesc" style="font-size:.85rem;color:var(--text-secondary);white-space:pre-wrap;"></p>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="reviewStatus" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="disetujui">Disetujui</option>
                        <option value="revisi">Revisi</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Guru</label>
                    <textarea name="catatan_pembimbing" id="reviewCatatan" class="form-control" rows="3"
                        placeholder="Berikan catatan untuk siswa..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reviewModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Simpan Review</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReview(j) {
        document.getElementById('reviewId').value = j.id;
        document.getElementById('reviewTitle').textContent = j.judul_kegiatan;
        document.getElementById('reviewDesc').textContent = j.deskripsi_kegiatan;
        document.getElementById('reviewStatus').value = j.status;
        document.getElementById('reviewCatatan').value = j.catatan_pembimbing || '';
        openModal('reviewModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>