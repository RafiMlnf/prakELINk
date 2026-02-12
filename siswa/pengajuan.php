<?php
/**
 * Student — Pengajuan Penempatan PKL
 * Submit placement requests with company documents + location
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Pengajuan PKL';
$siswaId = $_SESSION['siswa_id'];

// Mark pengajuan notifications as read
markNotifikasiRead($_SESSION['user_id'], 'pengajuan');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'submit') {
        // Upload documents
        $suratPermohonan = null;
        $suratBalasan = null;
        $dokPendukung = null;

        if (isset($_FILES['surat_permohonan']) && $_FILES['surat_permohonan']['error'] === UPLOAD_ERR_OK) {
            $suratPermohonan = uploadFile($_FILES['surat_permohonan'], 'pengajuan');
        }
        if (isset($_FILES['surat_balasan']) && $_FILES['surat_balasan']['error'] === UPLOAD_ERR_OK) {
            $suratBalasan = uploadFile($_FILES['surat_balasan'], 'pengajuan');
        }
        if (isset($_FILES['dokumen_pendukung']) && $_FILES['dokumen_pendukung']['error'] === UPLOAD_ERR_OK) {
            $dokPendukung = uploadFile($_FILES['dokumen_pendukung'], 'pengajuan');
        }

        $lat = (float) ($_POST['latitude'] ?? 0);
        $lng = (float) ($_POST['longitude'] ?? 0);

        $stmt = $db->prepare("
            INSERT INTO pengajuan_pkl 
            (siswa_id, nama_perusahaan, alamat_perusahaan, bidang_usaha, nama_pimpinan, 
             no_telp_perusahaan, email_perusahaan, latitude, longitude, tanggal_mulai, tanggal_selesai,
             surat_permohonan, surat_balasan, dokumen_pendukung)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $siswaId,
            trim($_POST['nama_perusahaan']),
            trim($_POST['alamat_perusahaan']),
            trim($_POST['bidang_usaha'] ?? ''),
            trim($_POST['nama_pimpinan'] ?? ''),
            trim($_POST['no_telp_perusahaan'] ?? ''),
            trim($_POST['email_perusahaan'] ?? ''),
            $lat ?: null,
            $lng ?: null,
            $_POST['tanggal_mulai'],
            $_POST['tanggal_selesai'],
            $suratPermohonan,
            $suratBalasan,
            $dokPendukung
        ]);

        setFlash('success', 'Pengajuan berhasil dikirim! Tunggu verifikasi guru/pengarah.');
        redirect('/siswa/pengajuan.php');
    }

    if ($action === 'edit') {
        $id = $_POST['pengajuan_id'];

        // Verify ownership & status
        $check = $db->prepare("SELECT * FROM pengajuan_pkl WHERE id = ? AND siswa_id = ? AND status IN ('pending', 'revisi')");
        $check->execute([$id, $siswaId]);
        if (!$check->fetch()) {
            setFlash('danger', 'Pengajuan tidak dapat diedit.');
            redirect('/siswa/pengajuan.php');
        }

        // Handle file updates
        $suratPermohonan = $_POST['existing_surat_permohonan'] ?? null;
        $suratBalasan = $_POST['existing_surat_balasan'] ?? null;
        $dokPendukung = $_POST['existing_dokumen_pendukung'] ?? null;

        if (isset($_FILES['surat_permohonan']) && $_FILES['surat_permohonan']['error'] === UPLOAD_ERR_OK) {
            $suratPermohonan = uploadFile($_FILES['surat_permohonan'], 'pengajuan');
        }
        if (isset($_FILES['surat_balasan']) && $_FILES['surat_balasan']['error'] === UPLOAD_ERR_OK) {
            $suratBalasan = uploadFile($_FILES['surat_balasan'], 'pengajuan');
        }
        if (isset($_FILES['dokumen_pendukung']) && $_FILES['dokumen_pendukung']['error'] === UPLOAD_ERR_OK) {
            $dokPendukung = uploadFile($_FILES['dokumen_pendukung'], 'pengajuan');
        }

        $lat = (float) ($_POST['latitude'] ?? 0);
        $lng = (float) ($_POST['longitude'] ?? 0);

        $stmt = $db->prepare("
            UPDATE pengajuan_pkl SET 
                nama_perusahaan = ?, alamat_perusahaan = ?, bidang_usaha = ?, 
                nama_pimpinan = ?, no_telp_perusahaan = ?, email_perusahaan = ?,
                latitude = ?, longitude = ?,
                tanggal_mulai = ?, tanggal_selesai = ?,
                surat_permohonan = ?, surat_balasan = ?, dokumen_pendukung = ?,
                status = 'pending', catatan_admin = NULL
            WHERE id = ? AND siswa_id = ?
        ");
        $stmt->execute([
            trim($_POST['nama_perusahaan']),
            trim($_POST['alamat_perusahaan']),
            trim($_POST['bidang_usaha'] ?? ''),
            trim($_POST['nama_pimpinan'] ?? ''),
            trim($_POST['no_telp_perusahaan'] ?? ''),
            trim($_POST['email_perusahaan'] ?? ''),
            $lat ?: null,
            $lng ?: null,
            $_POST['tanggal_mulai'],
            $_POST['tanggal_selesai'],
            $suratPermohonan,
            $suratBalasan,
            $dokPendukung,
            $id,
            $siswaId
        ]);

        setFlash('success', 'Pengajuan berhasil diperbarui.');
        redirect('/siswa/pengajuan.php');
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM pengajuan_pkl WHERE id = ? AND siswa_id = ? AND status IN ('pending', 'revisi')")
            ->execute([$_POST['pengajuan_id'], $siswaId]);
        setFlash('success', 'Pengajuan berhasil dihapus.');
        redirect('/siswa/pengajuan.php');
    }
}

// Fetch all requests
$requests = $db->prepare("
    SELECT p.*, u.nama_lengkap as reviewer_name 
    FROM pengajuan_pkl p 
    LEFT JOIN users u ON p.reviewed_by = u.id 
    WHERE p.siswa_id = ? 
    ORDER BY p.created_at DESC
");
$requests->execute([$siswaId]);
$requests = $requests->fetchAll();

// Check if student already has active placement
$hasPlacement = $db->prepare("SELECT penempatan_id FROM siswa WHERE id = ? AND penempatan_id IS NOT NULL");
$hasPlacement->execute([$siswaId]);
$hasPlacement = $hasPlacement->fetch();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <?php if ($hasPlacement): ?>
        <div class="alert alert-success mb-3">
            <span><i class="fas fa-check-circle"></i> Anda sudah memiliki penempatan PKL yang aktif.</span>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-file-signature" style="margin-right:8px;color:var(--primary-light);"></i>Pengajuan
                Penempatan PKL
            </h3>
            <?php if (!$hasPlacement): ?>
                <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Ajukan Baru
                </button>
            <?php endif; ?>
        </div>

        <!-- Info Steps -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
            <div
                style="flex:1;min-width:180px;padding:14px;background:rgba(99,102,241,0.08);border-radius:var(--radius);border:1px solid rgba(99,102,241,0.15);">
                <div style="font-size:.75rem;color:var(--primary-light);font-weight:600;margin-bottom:4px;">① Ajukan
                </div>
                <div style="font-size:.82rem;color:var(--text-secondary);">Isi data perusahaan, lokasi peta & upload
                    berkas</div>
            </div>
            <div
                style="flex:1;min-width:180px;padding:14px;background:rgba(245,158,11,0.08);border-radius:var(--radius);border:1px solid rgba(245,158,11,0.15);">
                <div style="font-size:.75rem;color:var(--warning);font-weight:600;margin-bottom:4px;">② Verifikasi</div>
                <div style="font-size:.82rem;color:var(--text-secondary);">Pembina memeriksa berkas perusahaan
                </div>
            </div>
            <div
                style="flex:1;min-width:180px;padding:14px;background:rgba(16,185,129,0.08);border-radius:var(--radius);border:1px solid rgba(16,185,129,0.15);">
                <div style="font-size:.75rem;color:var(--success-light);font-weight:600;margin-bottom:4px;">③ Hasil
                </div>
                <div style="font-size:.82rem;color:var(--text-secondary);">Disetujui : Sistem otomatis mendaftarkan
                    penempatan PKL dan lokasi presensi.</div>
            </div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt"></i>
                <h3>Belum ada pengajuan</h3>
                <p>Ajukan tempat PKL yang ingin Anda tuju dengan melengkapi data perusahaan, lokasi di peta, dan berkas yang
                    diperlukan.</p>
            </div>
        <?php else: ?>
            <div class="jurnal-list">
                <?php foreach ($requests as $r): ?>
                    <div class="jurnal-item animate-item"
                        style="<?= $r['status'] === 'disetujui' ? 'border-left:3px solid var(--success);' : ($r['status'] === 'ditolak' ? 'border-left:3px solid var(--danger);' : ($r['status'] === 'revisi' ? 'border-left:3px solid var(--warning);' : 'border-left:3px solid var(--primary);')) ?>">
                        <div class="jurnal-date" style="min-width:60px;">
                            <?php
                            $iconMap = ['pending' => 'fas fa-clock', 'disetujui' => 'fas fa-check-circle', 'ditolak' => 'fas fa-times-circle', 'revisi' => 'fas fa-redo'];
                            $colorMap = ['pending' => 'var(--primary-light)', 'disetujui' => 'var(--success-light)', 'ditolak' => 'var(--danger-light)', 'revisi' => 'var(--warning)'];
                            ?>
                            <i class="<?= $iconMap[$r['status']] ?>"
                                style="font-size:1.5rem;color:<?= $colorMap[$r['status']] ?>;"></i>
                        </div>
                        <div class="jurnal-content" style="flex:1;">
                            <h4 style="margin-bottom:6px;">
                                <i class="fas fa-building"
                                    style="margin-right:6px;color:var(--text-muted);font-size:.85rem;"></i>
                                <?= htmlspecialchars($r['nama_perusahaan']) ?>
                            </h4>
                            <p style="font-size:.82rem;color:var(--text-secondary);margin-bottom:6px;">
                                <i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>
                                <?= htmlspecialchars($r['alamat_perusahaan']) ?>
                            </p>
                            <?php if ($r['latitude'] && $r['longitude']): ?>
                                <p style="font-size:.75rem;color:var(--primary-light);margin-bottom:4px;">
                                    <i class="fas fa-crosshairs" style="margin-right:4px;"></i>
                                    Lokasi: <?= $r['latitude'] ?>, <?= $r['longitude'] ?> (radius 50m)
                                </p>
                            <?php endif; ?>
                            <?php if ($r['bidang_usaha']): ?>
                                <p style="font-size:.8rem;color:var(--text-muted);">
                                    <i class="fas fa-briefcase" style="margin-right:4px;"></i>
                                    <?= htmlspecialchars($r['bidang_usaha']) ?>
                                </p>
                            <?php endif; ?>
                            <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;">
                                <i class="fas fa-calendar" style="margin-right:4px;"></i>
                                <?= formatTanggal($r['tanggal_mulai']) ?> — <?= formatTanggal($r['tanggal_selesai']) ?>
                            </p>

                            <div style="margin-top:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                <?php $bc = match ($r['status']) { 'disetujui' => 'badge-success', 'ditolak' => 'badge-danger', 'revisi' => 'badge-warning', default => 'badge-info'}; ?>
                                <span class="badge <?= $bc ?>"><?= ucfirst($r['status']) ?></span>
                                <?php if ($r['surat_permohonan']): ?>
                                    <a href="<?= BASE_URL ?>/assets/uploads/<?= $r['surat_permohonan'] ?>" target="_blank"
                                        class="badge badge-primary" style="text-decoration:none;"><i class="fas fa-file-pdf"></i>
                                        Surat Permohonan</a>
                                <?php endif; ?>
                                <?php if ($r['surat_balasan']): ?>
                                    <a href="<?= BASE_URL ?>/assets/uploads/<?= $r['surat_balasan'] ?>" target="_blank"
                                        class="badge badge-primary" style="text-decoration:none;"><i class="fas fa-file-pdf"></i>
                                        Surat Balasan</a>
                                <?php endif; ?>
                                <?php if ($r['dokumen_pendukung']): ?>
                                    <a href="<?= BASE_URL ?>/assets/uploads/<?= $r['dokumen_pendukung'] ?>" target="_blank"
                                        class="badge badge-primary" style="text-decoration:none;"><i class="fas fa-paperclip"></i>
                                        Dokumen</a>
                                <?php endif; ?>
                            </div>

                            <?php if ($r['reviewer_name'] && $r['status'] !== 'pending'): ?>
                                <div style="margin-top:8px;font-size:.8rem;color:var(--text-secondary);">
                                    <i class="fas fa-user-check"
                                        style="margin-right:4px;color:<?= $r['status'] === 'disetujui' ? 'var(--success)' : ($r['status'] === 'ditolak' ? 'var(--danger)' : 'var(--warning)') ?>;"></i>
                                    <?= $r['status'] === 'disetujui' ? 'Disetujui' : ($r['status'] === 'ditolak' ? 'Ditolak' : 'Direview') ?>
                                    oleh <strong><?= htmlspecialchars($r['reviewer_name']) ?></strong>
                                    <?php if ($r['reviewed_at']): ?>
                                        <span style="color:var(--text-muted);margin-left:4px;">·
                                            <?= formatTanggal($r['reviewed_at']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($r['catatan_admin']): ?>
                                <div
                                    style="margin-top:12px;padding:10px 14px;background:var(--bg-input);border-radius:var(--radius-sm);font-size:.82rem;border-left:3px solid <?= $r['status'] === 'ditolak' ? 'var(--danger)' : 'var(--warning)' ?>;">
                                    <strong
                                        style="color:<?= $r['status'] === 'ditolak' ? 'var(--danger-light)' : 'var(--warning)' ?>;">Catatan
                                        Pembina:</strong><br>
                                    <?= nl2br(htmlspecialchars($r['catatan_admin'])) ?>
                                    <?php if ($r['reviewer_name']): ?>
                                        <div style="margin-top:6px;font-size:.75rem;color:var(--text-muted);">—
                                            <?= htmlspecialchars($r['reviewer_name']) ?>,
                                            <?= $r['reviewed_at'] ? formatTanggal($r['reviewed_at']) : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div style="font-size:.72rem;color:var(--text-muted);margin-top:8px;">Diajukan:
                                <?= formatTanggal($r['created_at']) ?>
                            </div>
                        </div>
                        <div class="jurnal-actions">
                            <?php if (in_array($r['status'], ['pending', 'revisi'])): ?>
                                <button class="btn btn-outline btn-sm" title="Edit" onclick='editPengajuan(<?= json_encode($r) ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;" onsubmit="return confirmAction('Hapus pengajuan ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="pengajuan_id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i
                                            class="fas fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Pengajuan Modal -->
<div class="modal-overlay" id="addPengajuanModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-file-signature"
                    style="margin-right:8px;color:var(--primary-light);"></i>Ajukan Penempatan PKL</h3>
            <button class="modal-close" onclick="closeModal('addPengajuanModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="latitude" id="addLat" value="">
            <input type="hidden" name="longitude" id="addLng" value="">
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <h4
                    style="font-size:.85rem;color:var(--primary-light);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-building" style="margin-right:6px;"></i>Data Perusahaan/Instansi
                </h4>
                <div class="form-group">
                    <label class="form-label">Nama Perusahaan/Instansi *</label>
                    <input type="text" name="nama_perusahaan" class="form-control"
                        placeholder="Contoh: PT Teknologi Nusantara" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap *</label>
                    <textarea name="alamat_perusahaan" class="form-control" rows="2"
                        placeholder="Alamat lengkap perusahaan" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Bidang Usaha</label>
                        <input type="text" name="bidang_usaha" class="form-control"
                            placeholder="Contoh: Teknologi Informasi">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Pimpinan</label>
                        <input type="text" name="nama_pimpinan" class="form-control"
                            placeholder="Nama direktur/pimpinan">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">No. Telepon Perusahaan</label>
                        <input type="text" name="no_telp_perusahaan" class="form-control" placeholder="021-xxxxxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Perusahaan</label>
                        <input type="email" name="email_perusahaan" class="form-control"
                            placeholder="hrd@perusahaan.com">
                    </div>
                </div>

                <h4
                    style="font-size:.85rem;color:var(--success-light);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-map-marker-alt" style="margin-right:6px;"></i>Lokasi PKL (untuk presensi)
                </h4>
                <p style="font-size:.8rem;color:var(--text-muted);margin-bottom:10px;">
                    <i class="fas fa-info-circle" style="margin-right:4px;color:var(--info);"></i>
                    Klik peta untuk menentukan lokasi perusahaan. Titik ini akan menjadi pusat radius absensi (50m).
                </p>
                <div id="addMap"
                    style="height:250px;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);margin-bottom:8px;">
                </div>
                <div id="addMapInfo" style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px;">
                    <i class="fas fa-crosshairs" style="margin-right:4px;"></i> Belum dipilih — klik peta untuk set
                    lokasi
                </div>

                <h4
                    style="font-size:.85rem;color:var(--accent);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-calendar-alt" style="margin-right:6px;"></i>Periode PKL
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai *</label>
                        <input type="date" name="tanggal_mulai" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai *</label>
                        <input type="date" name="tanggal_selesai" class="form-control" required>
                    </div>
                </div>

                <h4
                    style="font-size:.85rem;color:var(--warning);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-paperclip" style="margin-right:6px;"></i>Upload Berkas
                </h4>
                <div class="form-group">
                    <label class="form-label">Surat Permohonan PKL</label>
                    <input type="file" name="surat_permohonan" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small style="color:var(--text-muted);font-size:.75rem;">PDF, JPG, PNG, DOC (max 5MB)</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Surat Balasan Perusahaan</label>
                    <input type="file" name="surat_balasan" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small style="color:var(--text-muted);font-size:.75rem;">Upload jika sudah ada surat balasan</small>
                </div>
                <div class="form-group">
                    <label class="form-label">Dokumen Pendukung Lainnya</label>
                    <input type="file" name="dokumen_pendukung" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                    <small style="color:var(--text-muted);font-size:.75rem;">Profil perusahaan, MoU, dll
                        (opsional)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addPengajuanModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim
                    Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Pengajuan Modal -->
<div class="modal-overlay" id="editPengajuanModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-edit" style="margin-right:8px;color:var(--warning);"></i>Edit
                Pengajuan</h3>
            <button class="modal-close" onclick="closeModal('editPengajuanModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="pengajuan_id" id="editId">
            <input type="hidden" name="latitude" id="editLat">
            <input type="hidden" name="longitude" id="editLng">
            <input type="hidden" name="existing_surat_permohonan" id="editExSuratPermohonan">
            <input type="hidden" name="existing_surat_balasan" id="editExSuratBalasan">
            <input type="hidden" name="existing_dokumen_pendukung" id="editExDokPendukung">
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <h4
                    style="font-size:.85rem;color:var(--primary-light);margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-building" style="margin-right:6px;"></i>Data Perusahaan/Instansi
                </h4>
                <div class="form-group">
                    <label class="form-label">Nama Perusahaan/Instansi *</label>
                    <input type="text" name="nama_perusahaan" id="editNamaPerusahaan" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat Lengkap *</label>
                    <textarea name="alamat_perusahaan" id="editAlamat" class="form-control" rows="2"
                        required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Bidang Usaha</label>
                        <input type="text" name="bidang_usaha" id="editBidang" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Pimpinan</label>
                        <input type="text" name="nama_pimpinan" id="editPimpinan" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">No. Telepon</label>
                        <input type="text" name="no_telp_perusahaan" id="editTelp" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email_perusahaan" id="editEmail" class="form-control">
                    </div>
                </div>

                <h4
                    style="font-size:.85rem;color:var(--success-light);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-map-marker-alt" style="margin-right:6px;"></i>Lokasi PKL
                </h4>
                <div id="editMap"
                    style="height:250px;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);margin-bottom:8px;">
                </div>
                <div id="editMapInfo" style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px;">
                    <i class="fas fa-crosshairs" style="margin-right:4px;"></i> Klik peta untuk mengubah lokasi
                </div>

                <h4
                    style="font-size:.85rem;color:var(--accent);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-calendar-alt" style="margin-right:6px;"></i>Periode PKL
                </h4>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai *</label>
                        <input type="date" name="tanggal_mulai" id="editMulai" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai *</label>
                        <input type="date" name="tanggal_selesai" id="editSelesai" class="form-control" required>
                    </div>
                </div>

                <h4
                    style="font-size:.85rem;color:var(--warning);margin:20px 0 12px;padding-bottom:8px;border-bottom:1px solid var(--border);">
                    <i class="fas fa-paperclip" style="margin-right:6px;"></i>Ganti Berkas (opsional)
                </h4>
                <div id="editFileInfo" style="margin-bottom:12px;"></div>
                <div class="form-group">
                    <label class="form-label">Surat Permohonan PKL</label>
                    <input type="file" name="surat_permohonan" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                <div class="form-group">
                    <label class="form-label">Surat Balasan Perusahaan</label>
                    <input type="file" name="surat_balasan" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
                <div class="form-group">
                    <label class="form-label">Dokumen Pendukung</label>
                    <input type="file" name="dokumen_pendukung" class="form-control"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editPengajuanModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script>
    let addMap, addMarker, addCircle;
    let editMap, editMarker, editCircle;

    function openAddModal() {
        openModal('addPengajuanModal');
        setTimeout(() => {
            if (addMap) { addMap.remove(); addMap = null; }

            addMap = L.map('addMap').setView([-6.2, 106.8], 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(addMap);

            addMap.on('click', function (e) {
                const lat = e.latlng.lat.toFixed(8);
                const lng = e.latlng.lng.toFixed(8);

                document.getElementById('addLat').value = lat;
                document.getElementById('addLng').value = lng;

                if (addMarker) { addMap.removeLayer(addMarker); }
                if (addCircle) { addMap.removeLayer(addCircle); }

                addMarker = L.marker(e.latlng, { draggable: true }).addTo(addMap);
                addCircle = L.circle(e.latlng, {
                    radius: 50, color: '#1c398e', fillColor: '#1c398e', fillOpacity: 0.12, weight: 2, dashArray: '5, 10'
                }).addTo(addMap);

                addMarker.on('dragend', function (ev) {
                    const pos = ev.target.getLatLng();
                    document.getElementById('addLat').value = pos.lat.toFixed(8);
                    document.getElementById('addLng').value = pos.lng.toFixed(8);
                    addCircle.setLatLng(pos);
                    document.getElementById('addMapInfo').innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--success-light);"></i> Lokasi: ' + pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6) + ' <span class="badge badge-primary" style="margin-left:6px;">Radius 50m</span>';
                });

                document.getElementById('addMapInfo').innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--success-light);"></i> Lokasi: ' + lat + ', ' + lng + ' <span class="badge badge-primary" style="margin-left:6px;">Radius 50m</span>';
            });
        }, 300);
    }

    function editPengajuan(r) {
        document.getElementById('editId').value = r.id;
        document.getElementById('editNamaPerusahaan').value = r.nama_perusahaan;
        document.getElementById('editAlamat').value = r.alamat_perusahaan;
        document.getElementById('editBidang').value = r.bidang_usaha || '';
        document.getElementById('editPimpinan').value = r.nama_pimpinan || '';
        document.getElementById('editTelp').value = r.no_telp_perusahaan || '';
        document.getElementById('editEmail').value = r.email_perusahaan || '';
        document.getElementById('editLat').value = r.latitude || '';
        document.getElementById('editLng').value = r.longitude || '';
        document.getElementById('editMulai').value = r.tanggal_mulai;
        document.getElementById('editSelesai').value = r.tanggal_selesai;
        document.getElementById('editExSuratPermohonan').value = r.surat_permohonan || '';
        document.getElementById('editExSuratBalasan').value = r.surat_balasan || '';
        document.getElementById('editExDokPendukung').value = r.dokumen_pendukung || '';

        let fileHtml = '';
        if (r.surat_permohonan) fileHtml += '<span class="badge badge-primary" style="margin:2px;"><i class="fas fa-file"></i> Surat Permohonan ✓</span>';
        if (r.surat_balasan) fileHtml += '<span class="badge badge-primary" style="margin:2px;"><i class="fas fa-file"></i> Surat Balasan ✓</span>';
        if (r.dokumen_pendukung) fileHtml += '<span class="badge badge-primary" style="margin:2px;"><i class="fas fa-file"></i> Dokumen Pendukung ✓</span>';
        document.getElementById('editFileInfo').innerHTML = fileHtml || '<span style="font-size:.82rem;color:var(--text-muted);">Belum ada berkas yang diupload</span>';

        openModal('editPengajuanModal');

        setTimeout(() => {
            if (editMap) { editMap.remove(); editMap = null; }

            const lat = parseFloat(r.latitude) || -6.2;
            const lng = parseFloat(r.longitude) || 106.8;
            const hasLoc = r.latitude && r.longitude;

            editMap = L.map('editMap').setView([lat, lng], hasLoc ? 15 : 10);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(editMap);

            if (hasLoc) {
                editMarker = L.marker([lat, lng], { draggable: true }).addTo(editMap);
                editCircle = L.circle([lat, lng], {
                    radius: 50, color: '#1c398e', fillColor: '#1c398e', fillOpacity: 0.12, weight: 2, dashArray: '5, 10'
                }).addTo(editMap);

                editMarker.on('dragend', function (ev) {
                    const pos = ev.target.getLatLng();
                    document.getElementById('editLat').value = pos.lat.toFixed(8);
                    document.getElementById('editLng').value = pos.lng.toFixed(8);
                    editCircle.setLatLng(pos);
                    document.getElementById('editMapInfo').innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--success-light);"></i> Lokasi: ' + pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6) + ' <span class="badge badge-primary" style="margin-left:6px;">Radius 50m</span>';
                });

                document.getElementById('editMapInfo').innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--success-light);"></i> Lokasi: ' + lat.toFixed(6) + ', ' + lng.toFixed(6) + ' <span class="badge badge-primary" style="margin-left:6px;">Radius 50m</span>';
            }

            editMap.on('click', function (e) {
                document.getElementById('editLat').value = e.latlng.lat.toFixed(8);
                document.getElementById('editLng').value = e.latlng.lng.toFixed(8);

                if (editMarker) { editMap.removeLayer(editMarker); }
                if (editCircle) { editMap.removeLayer(editCircle); }

                editMarker = L.marker(e.latlng, { draggable: true }).addTo(editMap);
                editCircle = L.circle(e.latlng, {
                    radius: 50, color: '#1c398e', fillColor: '#1c398e', fillOpacity: 0.12, weight: 2, dashArray: '5, 10'
                }).addTo(editMap);

                editMarker.on('dragend', function (ev) {
                    const pos = ev.target.getLatLng();
                    document.getElementById('editLat').value = pos.lat.toFixed(8);
                    document.getElementById('editLng').value = pos.lng.toFixed(8);
                    editCircle.setLatLng(pos);
                    document.getElementById('editMapInfo').innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--success-light);"></i> Lokasi: ' + pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6) + ' <span class="badge badge-primary" style="margin-left:6px;">Radius 50m</span>';
                });

                document.getElementById('editMapInfo').innerHTML = '<i class="fas fa-check-circle" style="margin-right:4px;color:var(--success-light);"></i> Lokasi: ' + e.latlng.lat.toFixed(6) + ', ' + e.latlng.lng.toFixed(6) + ' <span class="badge badge-primary" style="margin-left:6px;">Radius 50m</span>';
            });
        }, 300);
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>