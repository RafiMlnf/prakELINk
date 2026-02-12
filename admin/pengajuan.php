<?php
/**
 * Admin — Verifikasi Pengajuan PKL
 * Review, approve, reject, or request revision of student placement requests
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Pengajuan PKL';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pengajuanId = $_POST['pengajuan_id'] ?? 0;

    if ($action === 'review') {
        $status = $_POST['status'];
        $catatan = trim($_POST['catatan_admin'] ?? '');

        if (!in_array($status, ['disetujui', 'ditolak', 'revisi'])) {
            setFlash('danger', 'Status tidak valid.');
            redirect('/admin/pengajuan.php');
        }

        // Update pengajuan status
        $stmt = $db->prepare("
            UPDATE pengajuan_pkl SET 
                status = ?, catatan_admin = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $catatan, $_SESSION['user_id'], $pengajuanId]);

        // If approved, create penempatan & assign student
        if ($status === 'disetujui') {
            $pengajuan = $db->prepare("SELECT * FROM pengajuan_pkl WHERE id = ?")->execute([$pengajuanId]);
            $pengajuan = $db->prepare("SELECT * FROM pengajuan_pkl WHERE id = ?");
            $pengajuan->execute([$pengajuanId]);
            $pengajuan = $pengajuan->fetch();

            if ($pengajuan) {
                // Create penempatan record
                $stmt = $db->prepare("
                    INSERT INTO penempatan 
                    (nama_perusahaan, alamat, latitude, longitude, radius_meter, kontak_perusahaan, tanggal_mulai, tanggal_selesai)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $lat = $pengajuan['latitude'] ?: -6.20000000;
                $lng = $pengajuan['longitude'] ?: 106.84000000;
                $stmt->execute([
                    $pengajuan['nama_perusahaan'],
                    $pengajuan['alamat_perusahaan'],
                    $lat,
                    $lng,
                    50,
                    $pengajuan['no_telp_perusahaan'],
                    $pengajuan['tanggal_mulai'],
                    $pengajuan['tanggal_selesai']
                ]);
                $penempatanId = $db->lastInsertId();

                // Assign student to penempatan
                $db->prepare("UPDATE siswa SET penempatan_id = ? WHERE id = ?")
                    ->execute([$penempatanId, $pengajuan['siswa_id']]);
            }
        }

        $statusText = match ($status) {
            'disetujui' => 'disetujui dan penempatan dibuat',
            'ditolak' => 'ditolak',
            'revisi' => 'diminta revisi'
        };

        // Send notification to student
        $siswaUser = $db->prepare("
            SELECT u.id as user_id, p.nama_perusahaan 
            FROM pengajuan_pkl p 
            JOIN siswa s ON p.siswa_id = s.id 
            JOIN users u ON s.user_id = u.id 
            WHERE p.id = ?
        ");
        $siswaUser->execute([$pengajuanId]);
        $siswaUser = $siswaUser->fetch();
        if ($siswaUser) {
            $notifPesan = match ($status) {
                'disetujui' => 'Pengajuan PKL ke "' . $siswaUser['nama_perusahaan'] . '" telah disetujui!',
                'ditolak' => 'Pengajuan PKL ke "' . $siswaUser['nama_perusahaan'] . '" ditolak.',
                'revisi' => 'Pengajuan PKL ke "' . $siswaUser['nama_perusahaan'] . '" perlu direvisi.'
            };
            addNotifikasi($siswaUser['user_id'], 'pengajuan', $notifPesan, '/siswa/pengajuan.php');
        }

        setFlash('success', "Pengajuan berhasil $statusText.");
        redirect('/admin/pengajuan.php');
    }
}

// Filters
$filterStatus = $_GET['status'] ?? '';
$filterKelas = $_GET['kelas'] ?? '';

$where = "1=1";
$params = [];

if ($filterStatus) {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
}
if ($filterKelas) {
    $where .= " AND s.kelas = ?";
    $params[] = $filterKelas;
}

$stmt = $db->prepare("
    SELECT p.*, u.nama_lengkap as siswa_nama, u.foto as siswa_foto, s.nisn, s.kelas, s.jurusan,
           rv.nama_lengkap as reviewer_name
    FROM pengajuan_pkl p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN users rv ON p.reviewed_by = rv.id
    WHERE $where
    ORDER BY 
        CASE p.status WHEN 'pending' THEN 0 WHEN 'revisi' THEN 1 WHEN 'disetujui' THEN 2 ELSE 3 END,
        p.created_at DESC
");
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Stats
$totalPending = $db->query("SELECT COUNT(*) FROM pengajuan_pkl WHERE status = 'pending'")->fetchColumn();
$totalDisetujui = $db->query("SELECT COUNT(*) FROM pengajuan_pkl WHERE status = 'disetujui'")->fetchColumn();
$totalDitolak = $db->query("SELECT COUNT(*) FROM pengajuan_pkl WHERE status = 'ditolak'")->fetchColumn();
$totalRevisi = $db->query("SELECT COUNT(*) FROM pengajuan_pkl WHERE status = 'revisi'")->fetchColumn();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <!-- Stats -->
    <div class="stats-grid mb-3">
        <div class="stat-card warning animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Menunggu</h3>
                    <div class="stat-value">
                        <?= $totalPending ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="stat-card success animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Disetujui</h3>
                    <div class="stat-value">
                        <?= $totalDisetujui ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card info animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Revisi</h3>
                    <div class="stat-value">
                        <?= $totalRevisi ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-redo"></i></div>
            </div>
        </div>
        <div class="stat-card danger animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Ditolak</h3>
                    <div class="stat-value">
                        <?= $totalDitolak ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-signature" style="margin-right:8px;color:var(--primary);"></i>Daftar
                Pengajuan PKL
            </h3>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-form mb-3" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin-bottom:0;min-width:150px;">
                <label class="form-label" style="font-size:.75rem;">Status</label>
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Status</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="disetujui" <?= $filterStatus === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="revisi" <?= $filterStatus === 'revisi' ? 'selected' : '' ?>>Revisi</option>
                    <option value="ditolak" <?= $filterStatus === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;min-width:150px;">
                <label class="form-label" style="font-size:.75rem;">Kelas</label>
                <select name="kelas" class="form-control" onchange="this.form.submit()">
                    <option value="">Semua Kelas</option>
                    <option value="XII ELIN 1" <?= $filterKelas === 'XII ELIN 1' ? 'selected' : '' ?>>XII ELIN 1</option>
                    <option value="XII ELIN 2" <?= $filterKelas === 'XII ELIN 2' ? 'selected' : '' ?>>XII ELIN 2</option>
                </select>
            </div>
        </form>

        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>Belum ada pengajuan</h3>
                <p>Belum ada siswa yang mengajukan penempatan PKL.</p>
            </div>
        <?php else: ?>

            <?php foreach ($requests as $r): ?>
                <div class="card mb-2 animate-item" style="
            background:var(--bg-card);
            border-left:4px solid <?= match ($r['status']) { 'pending' => 'var(--primary)', 'disetujui' => 'var(--success)', 'ditolak' => 'var(--danger)', 'revisi' => 'var(--warning)', default => 'var(--border)'} ?>;
        ">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
                        <!-- Left: Info -->
                        <div style="flex:1;min-width:300px;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">
                                <?php if (!empty($r['siswa_foto']) && file_exists(__DIR__ . '/../uploads/profil/' . $r['siswa_foto'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/profil/<?= $r['siswa_foto'] ?>" alt="Foto"
                                        style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--border);">
                                <?php else: ?>
                                    <div
                                        style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--accent));display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.9rem;">
                                        <?= strtoupper(substr($r['siswa_nama'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <strong style="color:var(--text-heading);font-size:.95rem;">
                                        <?= htmlspecialchars($r['siswa_nama']) ?>
                                    </strong>
                                    <div style="font-size:.75rem;color:var(--text-muted);">
                                        <?= htmlspecialchars($r['kelas']) ?> —
                                        <?= htmlspecialchars($r['nisn']) ?>
                                    </div>
                                </div>
                                <?php
                                $bc = match ($r['status']) {
                                    'disetujui' => 'badge-success',
                                    'ditolak' => 'badge-danger',
                                    'revisi' => 'badge-warning',
                                    default => 'badge-info'
                                };
                                ?>
                                <span class="badge <?= $bc ?>" style="margin-left:auto;">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </div>

                            <div
                                style="background:var(--bg-input);border-radius:var(--radius-sm);padding:14px;margin-bottom:10px;">
                                <h4 style="font-size:.92rem;color:var(--text-heading);margin-bottom:8px;">
                                    <i class="fas fa-building" style="margin-right:6px;color:var(--primary);"></i>
                                    <?= htmlspecialchars($r['nama_perusahaan']) ?>
                                </h4>
                                <div
                                    style="display:grid;grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));gap:8px;font-size:.82rem;">
                                    <div><span style="color:var(--text-muted);">Alamat:</span>
                                        <?= htmlspecialchars($r['alamat_perusahaan']) ?>
                                    </div>
                                    <?php if ($r['bidang_usaha']): ?>
                                        <div><span style="color:var(--text-muted);">Bidang:</span>
                                            <?= htmlspecialchars($r['bidang_usaha']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($r['nama_pimpinan']): ?>
                                        <div><span style="color:var(--text-muted);">Pimpinan:</span>
                                            <?= htmlspecialchars($r['nama_pimpinan']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($r['no_telp_perusahaan']): ?>
                                        <div><span style="color:var(--text-muted);">Telp:</span>
                                            <?= htmlspecialchars($r['no_telp_perusahaan']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($r['email_perusahaan']): ?>
                                        <div><span style="color:var(--text-muted);">Email:</span>
                                            <?= htmlspecialchars($r['email_perusahaan']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <span style="color:var(--text-muted);">Periode:</span>
                                        <?= formatTanggal($r['tanggal_mulai']) ?> —
                                        <?= formatTanggal($r['tanggal_selesai']) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents -->
                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                <?php if ($r['surat_permohonan']): ?>
                                    <a href="<?= BASE_URL ?>/assets/uploads/<?= $r['surat_permohonan'] ?>" target="_blank"
                                        class="btn btn-outline btn-sm" style="font-size:.78rem;">
                                        <i class="fas fa-file-pdf"></i> Surat Permohonan
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="font-size:.72rem;"><i
                                            class="fas fa-exclamation-triangle"></i> Surat Permohonan belum ada</span>
                                <?php endif; ?>

                                <?php if ($r['surat_balasan']): ?>
                                    <a href="<?= BASE_URL ?>/assets/uploads/<?= $r['surat_balasan'] ?>" target="_blank"
                                        class="btn btn-outline btn-sm" style="font-size:.78rem;">
                                        <i class="fas fa-file-pdf"></i> Surat Balasan
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge-warning" style="font-size:.72rem;"><i
                                            class="fas fa-exclamation-triangle"></i> Surat Balasan belum ada</span>
                                <?php endif; ?>

                                <?php if ($r['dokumen_pendukung']): ?>
                                    <a href="<?= BASE_URL ?>/assets/uploads/<?= $r['dokumen_pendukung'] ?>" target="_blank"
                                        class="btn btn-outline btn-sm" style="font-size:.78rem;">
                                        <i class="fas fa-paperclip"></i> Dokumen Pendukung
                                    </a>
                                <?php endif; ?>
                            </div>

                            <?php if ($r['catatan_admin']): ?>
                                <div
                                    style="padding:10px 14px;background:rgba(99,102,241,0.06);border-radius:var(--radius-sm);font-size:.82rem;border-left:3px solid var(--primary);margin-top:8px;">
                                    <strong style="color:var(--primary-light);">Catatan:</strong>
                                    <?= nl2br(htmlspecialchars($r['catatan_admin'])) ?>
                                    <?php if ($r['reviewer_name']): ?>
                                        <span style="color:var(--text-muted);font-size:.72rem;margin-left:8px;">—
                                            <?= htmlspecialchars($r['reviewer_name']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div style="font-size:.72rem;color:var(--text-muted);margin-top:8px;">
                                Diajukan:
                                <?= formatTanggal($r['created_at']) ?>
                                <?php if ($r['reviewed_at']): ?>
                                    | Direview:
                                    <?= formatTanggal($r['reviewed_at']) ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right: Actions -->
                        <?php if ($r['status'] === 'pending' || $r['status'] === 'revisi'): ?>
                            <div style="display:flex;flex-direction:column;gap:6px;min-width:140px;">
                                <button class="btn btn-success btn-sm"
                                    onclick="openReview(<?= $r['id'] ?>, '<?= htmlspecialchars($r['siswa_nama']) ?>', '<?= htmlspecialchars($r['nama_perusahaan']) ?>', 'disetujui')">
                                    <i class="fas fa-check"></i> Setujui
                                </button>
                                <button class="btn btn-warning btn-sm"
                                    onclick="openReview(<?= $r['id'] ?>, '<?= htmlspecialchars($r['siswa_nama']) ?>', '<?= htmlspecialchars($r['nama_perusahaan']) ?>', 'revisi')">
                                    <i class="fas fa-redo"></i> Minta Revisi
                                </button>
                                <button class="btn btn-danger btn-sm"
                                    onclick="openReview(<?= $r['id'] ?>, '<?= htmlspecialchars($r['siswa_nama']) ?>', '<?= htmlspecialchars($r['nama_perusahaan']) ?>', 'ditolak')">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="reviewTitle">Verifikasi Pengajuan</h3>
            <button class="modal-close" onclick="closeModal('reviewModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="pengajuan_id" id="reviewId">
            <input type="hidden" name="status" id="reviewStatus">
            <div class="modal-body">
                <div id="reviewInfo"
                    style="margin-bottom:16px;padding:12px;background:var(--bg-input);border-radius:var(--radius-sm);font-size:.85rem;">
                </div>

                <div id="reviewWarning"
                    style="display:none;margin-bottom:16px;padding:12px;background:rgba(16,185,129,0.08);border-radius:var(--radius-sm);border:1px solid rgba(16,185,129,0.2);font-size:.85rem;color:var(--success-light);">
                    <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                    Menyetujui pengajuan akan otomatis membuat data penempatan dan mengassign siswa ke lokasi tersebut.
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan untuk Siswa</label>
                    <textarea name="catatan_admin" id="reviewCatatan" class="form-control" rows="4"
                        placeholder="Tambahkan catatan (wajib jika menolak atau meminta revisi)"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('reviewModal')">Batal</button>
                <button type="submit" class="btn" id="reviewSubmitBtn"><i class="fas fa-check"></i> Konfirmasi</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openReview(id, siswaName, perusahaan, status) {
        document.getElementById('reviewId').value = id;
        document.getElementById('reviewStatus').value = status;

        const titleMap = {
            'disetujui': '✅ Setujui Pengajuan',
            'ditolak': '❌ Tolak Pengajuan',
            'revisi': '🔄 Minta Revisi'
        };
        document.getElementById('reviewTitle').textContent = titleMap[status];

        document.getElementById('reviewInfo').innerHTML = `
        <div><strong>Siswa:</strong> ${siswaName}</div>
        <div><strong>Perusahaan:</strong> ${perusahaan}</div>
    `;

        // Show warning for approval
        document.getElementById('reviewWarning').style.display = status === 'disetujui' ? 'block' : 'none';

        // Set button style
        const btn = document.getElementById('reviewSubmitBtn');
        btn.className = 'btn ' + (status === 'disetujui' ? 'btn-success' : status === 'ditolak' ? 'btn-danger' : 'btn-warning');
        btn.innerHTML = `<i class="fas fa-check"></i> ${status === 'disetujui' ? 'Setujui' : status === 'ditolak' ? 'Tolak' : 'Minta Revisi'}`;

        // Catatan required for reject/revisi
        const catatan = document.getElementById('reviewCatatan');
        catatan.required = (status !== 'disetujui');
        catatan.placeholder = status === 'disetujui' ? 'Catatan opsional...' : 'Wajib: jelaskan alasan ' + (status === 'ditolak' ? 'penolakan' : 'revisi') + '...';
        catatan.value = '';

        openModal('reviewModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>