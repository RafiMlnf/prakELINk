<?php
/**
 * Student — Unduh Kartu Tanda PKL
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Kartu Tanda PKL';
$siswaId = $_SESSION['siswa_id'];

$stmt = $db->prepare("
    SELECT s.*, p.nama_perusahaan, p.alamat as alamat_pkl, u.nama_lengkap, u.foto 
    FROM siswa s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN penempatan p ON s.penempatan_id = p.id 
    WHERE s.id = ?
");
$stmt->execute([$siswaId]);
$siswa = $stmt->fetch();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<!-- Library HTML2Canvas for element to image -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-id-card" style="margin-right:8px;color:var(--primary);"></i>Kartu Tanda PKL</h3>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;padding:30px 15px;">
            <p style="text-align:center;color:var(--text-secondary);margin-bottom:24px;max-width:400px;">
                Kartu ini merupakan tanda pengenal resmi Anda selama melaksanakan Praktik Kerja Lapangan (PKL). Anda dapat mengunduhnya sebagai berkas gambar.
            </p>

            <!-- ID Card UI -->
            <div id="id-card-wrapper" style="background:#fff;width:340px;border-radius:18px;box-shadow:0 15px 35px rgba(0,0,0,0.1);overflow:hidden;position:relative;border:1px solid #e2e8f0;font-family:'Inter', sans-serif;">
                
                <!-- Card Header -->
                <div style="background: linear-gradient(135deg, var(--primary), var(--accent)); height:110px; position:relative; overflow:hidden;">
                    <!-- Decorative shapes -->
                    <div style="position:absolute;top:-20px;left:-20px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.15);"></div>
                    <div style="position:absolute;bottom:-40px;right:-30px;width:140px;height:140px;border-radius:50%;background:rgba(255,255,255,0.1);"></div>
                    
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;color:white;">
                        <h1 style="font-size:1.3rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;margin:0;">KARTU TANDA PKL</h1>
                        <span style="font-size:0.75rem;font-weight:500;opacity:0.9;margin-top:2px;letter-spacing:0.5px;">SISTEM MONITORING ELINA</span>
                    </div>
                </div>

                <!-- Avatar -->
                <div style="display:flex;justify-content:center;margin-top:-50px;position:relative;z-index:2;">
                    <div style="width:100px;height:100px;background:#fff;border-radius:50%;padding:4px;box-shadow:0 4px 12px rgba(0,0,0,0.12);">
                        <?php if (!empty($siswa['foto']) && file_exists(__DIR__ . '/../uploads/profil/' . $siswa['foto'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/profil/<?= $siswa['foto'] ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" crossorigin="anonymous">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--primary),var(--accent));border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-size:2.5rem;font-weight:bold;">
                                <?= strtoupper(substr($siswa['nama_lengkap'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info Section -->
                <div style="padding:16px 24px 24px;text-align:center;">
                    <h2 style="font-size:1.25rem;font-weight:800;color:#1e293b;margin:0 0 6px;line-height:1.2;"><?= htmlspecialchars($siswa['nama_lengkap']) ?></h2>
                    <div style="display:inline-block;background:rgba(99,102,241,0.1);color:var(--primary);padding:4px 12px;border-radius:20px;font-size:0.8rem;font-weight:700;letter-spacing:0.5px;margin-bottom:20px;">
                        NISN: <?= htmlspecialchars($siswa['nisn']) ?>
                    </div>

                    <div style="background:#f8fafc;border-radius:14px;padding:14px;text-align:left;border:1px solid #f1f5f9;">
                        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(34,211,238,0.15);display:flex;align-items:center;justify-content:center;color:var(--accent);flex-shrink:0;">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <span style="display:block;font-size:0.65rem;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:0.5px;margin-bottom:2px;">Jurusan / Kelas</span>
                                <strong style="font-size:0.85rem;color:#334155;line-height:1.3;display:block;"><?= htmlspecialchars($siswa['jurusan'] . ' — ' . $siswa['kelas']) ?></strong>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-start;gap:12px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center;color:#10b981;flex-shrink:0;">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <span style="display:block;font-size:0.65rem;color:#94a3b8;text-transform:uppercase;font-weight:700;letter-spacing:0.5px;margin-bottom:2px;">Tempat Penugasan</span>
                                <strong style="font-size:0.85rem;color:<?= $siswa['nama_perusahaan'] ? 'var(--primary)' : 'var(--danger)' ?>;line-height:1.3;display:block;">
                                    <?= $siswa['nama_perusahaan'] ? htmlspecialchars($siswa['nama_perusahaan']) : 'Belum Ditempatkan' ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Footer Bar -->
                <div style="background:#f1f5f9;padding:12px;text-align:center;font-size:0.7rem;color:#64748b;border-top:1px solid #e2e8f0;font-weight:500;">
                    Berlaku selama masa Praktik Kerja Lapangan
                </div>
            </div>

            <!-- Action Button -->
            <button id="downloadBtn" class="btn btn-primary" style="margin-top:30px;padding:12px 32px;border-radius:30px;font-size:0.95rem;font-weight:600;box-shadow:0 4px 15px rgba(28, 57, 142, 0.3);transition:all 0.3s ease;">
                <i class="fas fa-download" style="margin-right:8px;"></i> Unduh Kartu (PNG)
            </button>
        </div>
    </div>
</main>

<script>
document.getElementById('downloadBtn').addEventListener('click', function() {
    const card = document.getElementById('id-card-wrapper');
    const btn = this;
    const originalText = btn.innerHTML;
    
    // UI state loading
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Memproses...';
    btn.style.opacity = '0.8';
    btn.style.cursor = 'not-allowed';
    
    html2canvas(card, {
        scale: 3, // High-Res export
        useCORS: true, 
        backgroundColor: '#ffffff',
        logging: false
    }).then(canvas => {
        const link = document.createElement('a');
        const studentName = '<?= preg_replace("/[^a-zA-Z0-9]+/", "_", strtolower($siswa['nama_lengkap'])) ?>';
        link.download = `Kartu_PKL_${studentName}.png`;
        link.href = canvas.toDataURL('image/png', 1.0);
        link.click();
        
        // Restore UI state
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    }).catch(err => {
        alert("Terjadi kesalahan saat membuat gambar kartu. Pastikan koneksi stabil.");
        btn.innerHTML = originalText;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
