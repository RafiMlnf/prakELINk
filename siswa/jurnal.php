<?php
/**
 * Student — Digital Journal / Logbook
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Jurnal PKL';
$siswaId = $_SESSION['siswa_id'];

// Mark jurnal notifications as read
markNotifikasiRead($_SESSION['user_id'], 'jurnal');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $foto = null;
        if (isset($_FILES['foto_kegiatan']) && $_FILES['foto_kegiatan']['error'] === UPLOAD_ERR_OK) {
            $foto = uploadFile($_FILES['foto_kegiatan'], 'jurnal');
        }
        $laporan = null;
        if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] === UPLOAD_ERR_OK) {
            $laporan = uploadFile($_FILES['file_laporan'], 'jurnal');
        }

        $stmt = $db->prepare("INSERT INTO jurnal (siswa_id, tanggal, judul_kegiatan, deskripsi_kegiatan, foto_kegiatan, file_laporan) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $siswaId,
            $_POST['tanggal'],
            trim($_POST['judul_kegiatan']),
            trim($_POST['deskripsi_kegiatan']),
            $foto,
            $laporan
        ]);
        setFlash('success', 'Jurnal berhasil ditambahkan.');
        redirect('/siswa/jurnal.php');
    }

    if ($action === 'edit') {
        $foto = $_POST['existing_foto'] ?? null;
        if (isset($_FILES['foto_kegiatan']) && $_FILES['foto_kegiatan']['error'] === UPLOAD_ERR_OK) {
            $foto = uploadFile($_FILES['foto_kegiatan'], 'jurnal');
        }
        $laporan = $_POST['existing_laporan'] ?? null;
        if (isset($_FILES['file_laporan']) && $_FILES['file_laporan']['error'] === UPLOAD_ERR_OK) {
            $laporan = uploadFile($_FILES['file_laporan'], 'jurnal');
        }

        $stmt = $db->prepare("UPDATE jurnal SET tanggal = ?, judul_kegiatan = ?, deskripsi_kegiatan = ?, foto_kegiatan = ?, file_laporan = ?, status = 'pending' WHERE id = ? AND siswa_id = ?");
        $stmt->execute([
            $_POST['tanggal'],
            trim($_POST['judul_kegiatan']),
            trim($_POST['deskripsi_kegiatan']),
            $foto,
            $laporan,
            $_POST['jurnal_id'],
            $siswaId
        ]);
        setFlash('success', 'Jurnal berhasil diperbarui.');
        redirect('/siswa/jurnal.php');
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM jurnal WHERE id = ? AND siswa_id = ?")->execute([$_POST['jurnal_id'], $siswaId]);
        setFlash('success', 'Jurnal berhasil dihapus.');
        redirect('/siswa/jurnal.php');
    }
}

// Fetch journals
$journals = $db->prepare("SELECT * FROM jurnal WHERE siswa_id = ? ORDER BY tanggal DESC");
$journals->execute([$siswaId]);
$journals = $journals->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-book-open" style="margin-right:8px;color:var(--primary);"></i>Jurnal
                Kegiatan PKL</h3>
            <button class="btn btn-primary btn-sm" onclick="openModal('addJurnalModal')">
                <i class="fas fa-plus"></i> Tulis Jurnal
            </button>
        </div>

        <?php if (empty($journals)): ?>
            <div class="empty-state">
                <i class="fas fa-pen-fancy"></i>
                <h3>Belum ada jurnal</h3>
                <p>Mulai tulis jurnal harian PKL-mu sekarang!</p>
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
                                <?= htmlspecialchars(mb_substr($j['deskripsi_kegiatan'], 0, 200)) ?>
                                <?= mb_strlen($j['deskripsi_kegiatan']) > 200 ? '...' : '' ?>
                            </p>
                            <div style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
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
                                <?php if ($j['foto_kegiatan']): ?>
                                    <span class="badge badge-primary"><i class="fas fa-camera"></i> Foto</span>
                                <?php endif; ?>
                                <?php if ($j['file_laporan']): ?>
                                    <span class="badge badge-primary"><i class="fas fa-file-alt"></i> Laporan</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($j['catatan_pembimbing']): ?>
                                <div
                                    style="margin-top:10px;padding:10px 14px;background:var(--bg-input);border-radius:var(--radius-sm);font-size:.82rem;border-left:3px solid var(--primary);">
                                    <strong style="color:var(--primary-light);">Catatan Guru:</strong><br>
                                    <?= htmlspecialchars($j['catatan_pembimbing']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="jurnal-actions">
                            <button class="btn btn-outline btn-sm" title="Edit" onclick='editJurnal(<?= json_encode($j) ?>)'>
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($j['foto_kegiatan']): ?>
                                <a href="<?= BASE_URL ?>/assets/uploads/<?= $j['foto_kegiatan'] ?>" target="_blank"
                                    class="btn btn-outline btn-sm" title="Foto">
                                    <i class="fas fa-image"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($j['file_laporan']): ?>
                                <a href="<?= BASE_URL ?>/assets/uploads/<?= $j['file_laporan'] ?>" target="_blank"
                                    class="btn btn-outline btn-sm" title="File Laporan">
                                    <i class="fas fa-file-download"></i>
                                </a>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmAction('Hapus jurnal ini?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="jurnal_id" value="<?= $j['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" title="Hapus">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add Journal Modal -->
<div class="modal-overlay" id="addJurnalModal">
    <div class="modal" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">Tulis Jurnal Baru</h3>
            <button class="modal-close" onclick="closeModal('addJurnalModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal *</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Foto Kegiatan</label>
                        <input type="file" name="foto_kegiatan" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Judul Kegiatan *</label>
                    <input type="text" name="judul_kegiatan" class="form-control"
                        placeholder="Contoh: Membuat desain UI halaman login" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi Kegiatan *</label>
                    <textarea name="deskripsi_kegiatan" class="form-control" rows="5"
                        placeholder="Jelaskan kegiatan PKL hari ini secara detail..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">File Laporan</label>
                    <input type="file" name="file_laporan" class="form-control" accept=".pdf,.doc,.docx">
                    <small style="color:var(--text-muted);font-size:.75rem;">Format: PDF, DOC, DOCX (maks 5MB)</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addJurnalModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Jurnal</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Journal Modal -->
<div class="modal-overlay" id="editJurnalModal">
    <div class="modal" style="max-width:650px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Jurnal</h3>
            <button class="modal-close" onclick="closeModal('editJurnalModal')">&times;</button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="jurnal_id" id="editJurnalId">
            <input type="hidden" name="existing_foto" id="editExistingFoto">
            <input type="hidden" name="existing_laporan" id="editExistingLaporan">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal *</label>
                        <input type="date" name="tanggal" id="editTanggal" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ganti Foto</label>
                        <input type="file" name="foto_kegiatan" class="form-control" accept="image/*">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Judul Kegiatan *</label>
                    <input type="text" name="judul_kegiatan" id="editJudul" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi Kegiatan *</label>
                    <textarea name="deskripsi_kegiatan" id="editDeskripsi" class="form-control" rows="5"
                        required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Ganti File Laporan</label>
                    <input type="file" name="file_laporan" class="form-control" accept=".pdf,.doc,.docx">
                    <small id="editLaporanInfo" style="color:var(--text-muted);font-size:.75rem;"></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editJurnalModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Jurnal</button>
            </div>
        </form>
    </div>
</div>

<script>
    function editJurnal(j) {
        document.getElementById('editJurnalId').value = j.id;
        document.getElementById('editTanggal').value = j.tanggal;
        document.getElementById('editJudul').value = j.judul_kegiatan;
        document.getElementById('editDeskripsi').value = j.deskripsi_kegiatan;
        document.getElementById('editExistingFoto').value = j.foto_kegiatan || '';
        document.getElementById('editExistingLaporan').value = j.file_laporan || '';
        const laporanInfo = document.getElementById('editLaporanInfo');
        if (j.file_laporan) {
            laporanInfo.innerHTML = '<i class="fas fa-file-alt"></i> File laporan sudah ada. Upload baru untuk mengganti.';
        } else {
            laporanInfo.textContent = 'Format: PDF, DOC, DOCX (maks 5MB)';
        }
        openModal('editJurnalModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>