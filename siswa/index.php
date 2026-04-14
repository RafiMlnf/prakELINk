<?php
/**
 * Student Dashboard
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Dashboard';
$siswaId = $_SESSION['siswa_id'];

// Get student info
$siswa = $db->prepare("
    SELECT s.*, p.nama_perusahaan, p.alamat as alamat_pkl, p.tanggal_mulai, p.tanggal_selesai,
           pb.nama_lengkap as pembimbing_nama
    FROM siswa s
    LEFT JOIN penempatan p ON s.penempatan_id = p.id
    LEFT JOIN users pb ON s.pembimbing_id = pb.id
    WHERE s.id = ?
");
$siswa->execute([$siswaId]);
$siswa = $siswa->fetch();

$today = date('Y-m-d');

// Today's attendance
$todayPresensi = $db->prepare("SELECT * FROM presensi WHERE siswa_id = ? AND tanggal = ?");
$todayPresensi->execute([$siswaId, $today]);
$todayPresensi = $todayPresensi->fetch();


// Recent journals
$recentJurnal = $db->prepare("SELECT * FROM jurnal WHERE siswa_id = ? ORDER BY tanggal DESC LIMIT 5");
$recentJurnal->execute([$siswaId]);
$recentJurnal = $recentJurnal->fetchAll();

// Calculate PKL progress
$progress = 0;
if ($siswa && $siswa['tanggal_mulai'] && $siswa['tanggal_selesai']) {
    $start = new DateTime($siswa['tanggal_mulai']);
    $end = new DateTime($siswa['tanggal_selesai']);
    $now = new DateTime();
    $totalDays = $start->diff($end)->days;
    $elapsed = $start->diff($now)->days;
    if ($now < $start)
        $progress = 0;
    elseif ($now > $end)
        $progress = 100;
    else
        $progress = min(100, round(($elapsed / max($totalDays, 1)) * 100));
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <!-- Welcome Card -->
    <div class="card mb-3 animate-item"
        style="background:linear-gradient(135deg, rgba(99,102,241,0.12), rgba(6,182,212,0.08));border-color:rgba(99,102,241,0.2);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div>
                <h2 style="font-size:1.3rem;font-weight:700;color:var(--text-heading);margin-bottom:8px;">
                    Selamat datang,
                    <?= htmlspecialchars($user['nama_lengkap']) ?>!
                </h2>
                <?php if ($siswa && $siswa['nama_perusahaan']): ?>
                    <p style="color:var(--text-secondary);font-size:.9rem;">

                        Penempatan PKL : <strong>
                            <?= htmlspecialchars($siswa['nama_perusahaan']) ?>
                        </strong>
                    </p>
                    <?php if ($siswa['pembimbing_nama']): ?>
                        <p style="color:var(--text-muted);font-size:.82rem;margin-top:4px;">
                            <i class="fas fa-chalkboard-teacher" style="margin-right:6px;"></i>
                            Pembina:
                            <?= htmlspecialchars($siswa['pembimbing_nama']) ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p style="color:var(--warning-light);font-size:.9rem;">
                        <i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i>
                        Belum ditempatkan. Hubungi guru/pembimbing.
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($progress > 0): ?>
                <div style="text-align:center;">
                    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:6px;">Progress PKL</div>
                    <div style="position:relative;width:80px;height:80px;">
                        <svg width="80" height="80" viewBox="0 0 80 80">
                            <circle cx="40" cy="40" r="34" fill="none" stroke="var(--border)" stroke-width="6" />
                            <circle cx="40" cy="40" r="34" fill="none" stroke="var(--primary)" stroke-width="6"
                                stroke-dasharray="<?= 2 * 3.14 * 34 ?>"
                                stroke-dashoffset="<?= 2 * 3.14 * 34 * (1 - $progress / 100) ?>"
                                transform="rotate(-90 40 40)" stroke-linecap="round" />
                        </svg>
                        <div
                            style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:700;color:var(--text-heading);">
                            <?= $progress ?>%
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>



    <?php
    // Fetch recent unread notifications
    $notifStmt = $db->prepare("SELECT * FROM notifikasi WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $notifStmt->execute([$_SESSION['user_id']]);
    $unreadNotifs = $notifStmt->fetchAll();
    ?>
    <?php if (!empty($unreadNotifs)): ?>
        <div class="card mb-3 animate-item" style="border-left:4px solid var(--danger);padding:0;overflow:hidden;">
            <div
                style="padding:14px 18px 10px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--border);">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span
                        style="width:28px;height:28px;border-radius:50%;background:var(--danger);display:inline-flex;align-items:center;justify-content:center;">
                        <i class="fas fa-bell" style="color:white;font-size:.75rem;"></i>
                    </span>
                    <strong style="font-size:.9rem;color:var(--text-heading);">Notifikasi Baru</strong>
                    <span class="badge badge-danger" style="font-size:.7rem;"><?= count($unreadNotifs) ?></span>
                </div>
            </div>
            <div style="padding:4px 0;">
                <?php foreach ($unreadNotifs as $notif): ?>
                    <?php
                    $notifIcon = match (true) {
                        str_contains($notif['pesan'], 'disetujui') => ['fas fa-check-circle', '#00b84c'],
                        str_contains($notif['pesan'], 'ditolak') => ['fas fa-times-circle', 'var(--danger)'],
                        default => ['fas fa-exclamation-circle', '#f59e0b']
                    };
                    ?>
                    <a href="<?= BASE_URL . ($notif['link'] ?? '#') ?>"
                        style="display:flex;align-items:center;gap:12px;padding:10px 18px;text-decoration:none;color:inherit;transition:background .15s;"
                        onmouseover="this.style.background='var(--bg-input)'" onmouseout="this.style.background='transparent'">
                        <i class="<?= $notifIcon[0] ?>" style="color:<?= $notifIcon[1] ?>;font-size:1.1rem;flex-shrink:0;"></i>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.82rem;color:var(--text);line-height:1.4;">
                                <?= htmlspecialchars($notif['pesan']) ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px;">
                                <?php
                                $diff = time() - strtotime($notif['created_at']);
                                if ($diff < 60)
                                    echo 'Baru saja';
                                elseif ($diff < 3600)
                                    echo floor($diff / 60) . ' menit lalu';
                                elseif ($diff < 86400)
                                    echo floor($diff / 3600) . ' jam lalu';
                                else
                                    echo formatTanggal($notif['created_at']);
                                ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right" style="color:var(--text-muted);font-size:.7rem;"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>



    <!-- Quick Actions & Recent Journal -->
    <div class="grid-2">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-content:start;" class="animate-item">
            <?php if (!$todayPresensi): ?>
            <style>
            @keyframes pulse-warning {
                0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(230, 25, 25, 0.6); }
                70% { transform: scale(1.02); box-shadow: 0 0 0 15px rgba(230, 25, 25, 0); }
                100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(230, 25, 25, 0); }
            }
            .btn-absen-box {
                background: var(--danger); /* Merah solid baru */
                animation: pulse-warning 2s infinite;
            }
            </style>
            <?php else: ?>
            <style>
            .btn-absen-box {
                background: var(--primary); /* Biru reguler solid */
            }
            .btn-absen-box:hover {
                transform: translateY(-4px);
                box-shadow: 0 10px 20px rgba(28, 57, 142, 0.2);
            }
            </style>
            <?php endif; ?>

            <a href="<?= BASE_URL ?>/siswa/presensi.php" class="btn-absen-box" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 16px;border-radius:20px;color:white;text-decoration:none;text-align:center;height:100%;transition:all .3s ease;">
                <div style="width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                    <i class="<?= !$todayPresensi ? 'fas fa-exclamation-circle' : 'fas fa-map-marker-alt' ?>" style="font-size:2rem;color:white;"></i>
                </div>
                <span style="font-size:1.15rem;font-weight:700;margin-bottom:4px;">Absen</span>
                <span style="font-size:.75rem;color:rgba(255,255,255,0.8);font-weight:500;">
                    <?= !$todayPresensi ? 'Wajib isi sekarang!' : 'Sudah Absen' ?>
                </span>
            </a>

            <a href="<?= BASE_URL ?>/siswa/jurnal.php" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:32px 16px;border-radius:20px;background:var(--success);color:white;text-decoration:none;text-align:center;height:100%;transition:all .3s ease;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 10px 20px rgba(0, 184, 76, 0.3)'" onmouseout="this.style.transform='none';this.style.boxShadow='none'">
                <div style="width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:16px;">
                    <i class="fas fa-book" style="font-size:2rem;color:white;"></i>
                </div>
                <span style="font-size:1.15rem;font-weight:700;margin-bottom:4px;">Jurnal</span>
                <span style="font-size:.75rem;color:rgba(255,255,255,0.8);font-weight:500;">Catat kegiatan hari ini</span>
            </a>
        </div>

        <div class="card animate-item">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book-open"
                        style="margin-right:8px;color:var(--primary);"></i>Jurnal Terbaru</h3>
                <a href="<?= BASE_URL ?>/siswa/jurnal.php" class="btn btn-outline btn-sm">Lihat Semua</a>
            </div>
            <?php if (empty($recentJurnal)): ?>
                <div class="empty-state" style="padding:30px;">
                    <i class="fas fa-pen-fancy"></i>
                    <h3>Belum ada jurnal</h3>
                    <p>Mulai tulis jurnal harian PKL-mu!</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentJurnal as $j): ?>
                    <div
                        style="padding:10px 0;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <div style="font-size:.88rem;font-weight:600;color:var(--text);">
                                <?= htmlspecialchars($j['judul_kegiatan']) ?>
                            </div>
                            <div style="font-size:.75rem;color:var(--text-muted);">
                                <?= formatTanggal($j['tanggal']) ?>
                            </div>
                        </div>
                        <?php
                        $bc = match ($j['status']) { 'disetujui' => 'badge-success', 'revisi' => 'badge-warning', default => 'badge-info'};
                        ?>
                        <span class="badge <?= $bc ?>">
                            <?= ucfirst($j['status']) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>