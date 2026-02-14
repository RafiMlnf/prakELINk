<?php
/**
 * Siswa — Pengajuan Jam Kerja
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Pengajuan Jam Kerja';
$siswaId = $_SESSION['siswa_id'];

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jamMasuk = $_POST['jam_masuk'];
    $jamPulang = $_POST['jam_pulang'];

    // Check if there is already a pending request
    $pendingCheck = $db->prepare("SELECT id FROM pengajuan_jam_kerja WHERE siswa_id = ? AND status = 'pending'");
    $pendingCheck->execute([$siswaId]);

    if ($pendingCheck->rowCount() > 0) {
        setFlash('warning', 'Anda masih memiliki pengajuan yang menunggu persetujuan.');
    } else {
        $stmt = $db->prepare("INSERT INTO pengajuan_jam_kerja (siswa_id, jam_masuk, jam_pulang, status) VALUES (?, ?, ?, 'pending')");
        if ($stmt->execute([$siswaId, $jamMasuk, $jamPulang])) {
            setFlash('success', 'Pengajuan jam kerja berhasil dikirim. Menunggu persetujuan admin.');
            // Add notification for admin could be added here if notification table supports it
        } else {
            setFlash('danger', 'Terjadi kesalahan sistem.');
        }
    }
    redirect('/siswa/jam_kerja.php');
}

// Get active/latest approved schedule
$activeSchedule = $db->prepare("SELECT * FROM pengajuan_jam_kerja WHERE siswa_id = ? AND status = 'disetujui' ORDER BY created_at DESC LIMIT 1");
$activeSchedule->execute([$siswaId]);
$activeSchedule = $activeSchedule->fetch();

// Check if there is a pending request
$pendingSchedule = $db->prepare("SELECT * FROM pengajuan_jam_kerja WHERE siswa_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$pendingSchedule->execute([$siswaId]);
$pendingSchedule = $pendingSchedule->fetch();

// Get history
$history = $db->prepare("SELECT * FROM pengajuan_jam_kerja WHERE siswa_id = ? ORDER BY created_at DESC");
$history->execute([$siswaId]);
$history = $history->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

// Mark jam_kerja notifications as read (AFTER sidebar renders badge count)
markNotifikasiRead($_SESSION['user_id'], 'jam_kerja');
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(300px, 1fr));gap:20px;align-items:start;">
        <div class="card animate-item" style="height:100%;padding:0;overflow:hidden;">
            <?php if ($activeSchedule):
                // Calculate duration
                $masuk = new DateTime($activeSchedule['jam_masuk']);
                $pulang = new DateTime($activeSchedule['jam_pulang']);
                $diff = $masuk->diff($pulang);
                $totalJam = $diff->h;
                $totalMenit = $diff->i;
                ?>
                <!-- Header with gradient -->
                <div style="background:linear-gradient(135deg, #1c398e, #2e4faf);padding:20px 24px;color:white;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div
                                style="width:40px;height:40px;background:rgba(255,255,255,0.2);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-clock" style="font-size:1.1rem;"></i>
                            </div>
                            <div>
                                <div
                                    style="font-size:.78rem;opacity:0.85;font-weight:500;text-transform:uppercase;letter-spacing:.5px;">
                                    Jadwal Aktif</div>
                                <div style="font-size:1.05rem;font-weight:700;">Jam Kerja PKL</div>
                            </div>
                        </div>
                        <span
                            style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:.72rem;font-weight:600;backdrop-filter:blur(4px);">
                            <i class="fas fa-check-circle" style="margin-right:4px;"></i>Disetujui
                        </span>
                    </div>
                </div>

                <!-- Time display -->
                <div style="padding:28px 24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                        <!-- Jam Masuk -->
                        <div style="text-align:center;flex:1;">
                            <div
                                style="width:48px;height:48px;margin:0 auto 10px;background:rgba(16,185,129,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-sign-in-alt" style="color:var(--success);font-size:1.1rem;"></i>
                            </div>
                            <div
                                style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-bottom:4px;">
                                Masuk</div>
                            <div style="font-size:1.6rem;font-weight:800;color:var(--text-heading);line-height:1;">
                                <?= formatJam($activeSchedule['jam_masuk']) ?>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div style="width:1px;height:40px;background:var(--border);"></div>

                        <!-- Jam Pulang -->
                        <div style="text-align:center;flex:1;">
                            <div
                                style="width:48px;height:48px;margin:0 auto 10px;background:rgba(239,68,68,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-sign-out-alt" style="color:var(--danger);font-size:1.1rem;"></i>
                            </div>
                            <div
                                style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);font-weight:600;margin-bottom:4px;">
                                Pulang</div>
                            <div style="font-size:1.6rem;font-weight:800;color:var(--text-heading);line-height:1;">
                                <?= formatJam($activeSchedule['jam_pulang']) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div style="padding:40px 24px;text-align:center;">
                    <div
                        style="width:64px;height:64px;margin:0 auto 16px;background:rgba(245,158,11,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-clock" style="font-size:1.6rem;color:var(--warning);"></i>
                    </div>
                    <h3 style="font-size:1rem;font-weight:700;color:var(--text-heading);margin-bottom:6px;">Belum Ada Jadwal
                        Aktif</h3>
                    <p style="font-size:.85rem;color:var(--text-muted);max-width:280px;margin:0 auto;">
                        Ajukan jam kerja terlebih dahulu agar dapat melakukan presensi harian.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card animate-item" style="height:100%;">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-edit" style="margin-right:8px;color:var(--primary);"></i>Ajukan
                    Perubahan Jam Kerja</h3>
            </div>
            <?php if ($pendingSchedule): ?>
                <div class="card-body">
                    <div class="alert alert-info" style="display:flex;align-items:center;margin:0;">
                        <i class="fas fa-hourglass-half fa-2x" style="margin-right:16px;color:var(--info);"></i>
                        <div>
                            <strong>Pengajuan sedang diproses</strong>
                            <div style="font-size:.82rem;color:var(--text-secondary);margin-top:4px;">
                                Jam Masuk: <strong><?= formatJam($pendingSchedule['jam_masuk']) ?></strong> —
                                Jam Pulang: <strong><?= formatJam($pendingSchedule['jam_pulang']) ?></strong>
                            </div>
                            <div style="font-size:.78rem;color:var(--text-muted);margin-top:6px;">
                                Menunggu persetujuan dari guru/admin. Anda dapat mengajukan kembali setelah pengajuan ini
                                diproses.
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" action="" class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Jam Masuk</label>
                            <input type="text" name="jam_masuk" class="form-control" required placeholder="HH:mm"
                                pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" maxlength="5"
                                value="<?= $activeSchedule ? formatJam($activeSchedule['jam_masuk']) : '08:00' ?>">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Jam Pulang</label>
                            <input type="text" name="jam_pulang" class="form-control" required placeholder="HH:mm"
                                pattern="([01]?[0-9]|2[0-3]):[0-5][0-9]" maxlength="5"
                                value="<?= $activeSchedule ? formatJam($activeSchedule['jam_pulang']) : '16:00' ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
                        <i class="fas fa-paper-plane" style="margin-right:8px;"></i> Kirim Pengajuan
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card animate-item mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history"
                    style="margin-right:8px;color:var(--text-secondary);"></i>Riwayat Pengajuan</h3>
        </div>

        <?php if (empty($history)): ?>
            <div class="empty-state" style="padding:30px;">
                <i class="fas fa-clipboard-list" style="font-size:2rem;color:var(--text-muted);margin-bottom:10px;"></i>
                <h3 style="font-size:.95rem;color:var(--text-muted);">Belum ada riwayat pengajuan</h3>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($history as $h):
                    $badge = match ($h['status']) {
                        'pending' => 'badge-warning',
                        'disetujui' => 'badge-success',
                        'ditolak' => 'badge-danger',
                        default => 'badge-secondary'
                    };
                    ?>
                    <div
                        style="padding:14px;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-card);">
                        <div
                            style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px;">
                            <div style="font-size:.82rem;color:var(--text-muted);">
                                <i class="far fa-calendar-alt" style="margin-right:4px;"></i>
                                <?= formatTanggal($h['created_at']) ?>
                            </div>
                            <span class="badge <?= $badge ?>"><?= ucfirst($h['status']) ?></span>
                        </div>
                        <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
                            <div style="display:flex;align-items:center;gap:6px;font-size:.88rem;">
                                <i class="fas fa-sign-in-alt" style="color:var(--success);"></i>
                                <span style="color:var(--text-secondary);">Masuk:</span>
                                <strong><?= formatJam($h['jam_masuk']) ?></strong>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;font-size:.88rem;">
                                <i class="fas fa-sign-out-alt" style="color:var(--danger);"></i>
                                <span style="color:var(--text-secondary);">Pulang:</span>
                                <strong><?= formatJam($h['jam_pulang']) ?></strong>
                            </div>
                        </div>
                        <?php if (!empty($h['catatan_admin'])): ?>
                            <div
                                style="margin-top:10px;padding:8px 12px;background:var(--bg-input);border-radius:6px;font-size:.8rem;color:var(--text-secondary);">
                                <i class="fas fa-comment-dots" style="margin-right:6px;color:var(--info);"></i>
                                <?= htmlspecialchars($h['catatan_admin']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>