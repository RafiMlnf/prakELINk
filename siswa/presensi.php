<?php
/**
 * Student — GPS-Based Attendance with Photo
 * Check-in / Check-out with location validation and photo capture
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('siswa');

$db = getDB();
$pageTitle = 'Presensi';
$siswaId = $_SESSION['siswa_id'];

// Get placement info
$placement = $db->prepare("
    SELECT p.* FROM penempatan p
    JOIN siswa s ON s.penempatan_id = p.id
    WHERE s.id = ? AND p.is_active = 1
");
$placement->execute([$siswaId]);
$placement = $placement->fetch();

// Get Approved Schedule
$schedule = $db->prepare("SELECT * FROM pengajuan_jam_kerja WHERE siswa_id = ? AND status = 'disetujui' ORDER BY created_at DESC LIMIT 1");
$schedule->execute([$siswaId]);
$schedule = $schedule->fetch();

$today = date('Y-m-d');

// Get today's attendance
$todayRecord = $db->prepare("SELECT * FROM presensi WHERE siswa_id = ? AND tanggal = ?");
$todayRecord->execute([$siswaId, $today]);
$todayRecord = $todayRecord->fetch();

// Helper: Save Base64 Image
function saveImage($base64Data, $type, $siswaId)
{
    if (!$base64Data)
        return null;
    $data = explode(',', $base64Data);
    if (count($data) < 2)
        return null;

    $imageData = base64_decode($data[1]);
    $fileName = date('Ymd_His') . '_' . $siswaId . '_' . $type . '.jpg';
    $path = __DIR__ . '/../assets/uploads/presensi/' . $fileName;

    if (!is_dir(dirname($path)))
        mkdir(dirname($path), 0777, true);
    file_put_contents($path, $imageData);
    return $fileName;
}

// Handle check-in / check-out via API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $lat = (float) ($input['latitude'] ?? 0);
    $lng = (float) ($input['longitude'] ?? 0);
    $foto = $input['foto'] ?? null;

    if (!$placement) {
        jsonResponse(['success' => false, 'message' => 'Anda belum ditempatkan di lokasi PKL.']);
    }

    if (!$schedule && $action === 'checkin') { // Only block checkin if no schedule
        // Brief: "Siswa tidak dapat melakukan absensi sebelum jam kerja disetujui."
        jsonResponse(['success' => false, 'message' => 'Anda belum memiliki Jam Kerja yang disetujui Admin. Silahkan ajukan jam kerja terlebih dahulu via menu Jam Kerja.']);
    }

    $distance = haversineDistance($lat, $lng, (float) $placement['latitude'], (float) $placement['longitude']);

    if ($action === 'checkin') {
        if ($todayRecord) {
            jsonResponse(['success' => false, 'message' => 'Anda sudah check-in hari ini.']);
        }
        if ($distance > $placement['radius_meter']) {
            jsonResponse([
                'success' => false,
                'message' => 'Anda berada di luar radius (' . round($distance) . 'm). Max: ' . $placement['radius_meter'] . 'm.',
                'distance' => round($distance)
            ]);
        }
        if (!$foto) {
            jsonResponse(['success' => false, 'message' => 'Foto selfie wajib diambil.']);
        }

        $imageName = saveImage($foto, 'masuk', $siswaId);

        // Late calculation (based on approved schedule)
        // This is minimal; usually we'd store 'status' = 'terlambat' if jam_masuk > schedule['jam_masuk'] + tolerance
        // For now, simpler implementation as requested ("mirip saja")

        $stmt = $db->prepare("INSERT INTO presensi (siswa_id, tanggal, jam_masuk, lat_masuk, lng_masuk, jarak_masuk, foto_masuk, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'hadir')");
        $stmt->execute([$siswaId, $today, date('H:i:s'), $lat, $lng, round($distance, 2), $imageName]);

        jsonResponse(['success' => true, 'message' => 'Check-in berhasil!', 'time' => date('H:i:s')]);
    }

    if ($action === 'checkout') {
        if (!$todayRecord) {
            jsonResponse(['success' => false, 'message' => 'Anda belum check-in hari ini.']);
        }
        if ($todayRecord['jam_keluar']) {
            jsonResponse(['success' => false, 'message' => 'Anda sudah check-out hari ini.']);
        }
        if ($todayRecord['status'] !== 'hadir') {
            jsonResponse(['success' => false, 'message' => 'Anda tidak perlu check-out untuk status: ' . ucfirst($todayRecord['status'])]);
        }
        if ($distance > $placement['radius_meter']) {
            jsonResponse([
                'success' => false,
                'message' => 'Anda berada di luar radius (' . round($distance) . 'm). Harap kembali ke lokasi PKL.',
                'distance' => round($distance)
            ]);
        }
        if (!$foto) {
            jsonResponse(['success' => false, 'message' => 'Foto selfie check-out wajib diambil.']);
        }

        $imageName = saveImage($foto, 'keluar', $siswaId);

        $stmt = $db->prepare("UPDATE presensi SET jam_keluar = ?, lat_keluar = ?, lng_keluar = ?, jarak_keluar = ?, foto_keluar = ? WHERE id = ?");
        $stmt->execute([date('H:i:s'), $lat, $lng, round($distance, 2), $imageName, $todayRecord['id']]);

        jsonResponse(['success' => true, 'message' => 'Check-out berhasil!', 'time' => date('H:i:s')]);
    }

    // ... (izin/sakit handlers remain similar, stripped for brevity unless requested to be robust)
    if ($action === 'izin' || $action === 'sakit') {
        if ($todayRecord)
            jsonResponse(['success' => false, 'message' => 'Sudah ada data presensi hari ini.']);
        $keterangan = trim($input['keterangan'] ?? '');
        $stmt = $db->prepare("INSERT INTO presensi (siswa_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?)");
        $stmt->execute([$siswaId, $today, $action, $keterangan]);
        jsonResponse(['success' => true, 'message' => 'Pengajuan ' . $action . ' berhasil.']);
    }

    jsonResponse(['success' => false, 'message' => 'Aksi tidak valid.']);
}

// History
$history = $db->prepare("SELECT * FROM presensi WHERE siswa_id = ? ORDER BY tanggal DESC LIMIT 30");
$history->execute([$siswaId]);
$history = $history->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <?php if (!$placement): ?>
        <div class="alert alert-warning animate-item">
            <i class="fas fa-exclamation-triangle"></i> Anda belum ditempatkan di lokasi PKL.
        </div>
    <?php elseif (!$schedule): ?>
        <div class="alert alert-danger animate-item">
            <i class="fas fa-clock"></i> <strong>Perhatian:</strong> Jam Kerja belum disetujui. Silahkan ajukan jam kerja di
            menu <a href="/siswa/jam_kerja.php" style="text-decoration:underline;">Jam Kerja</a> sebelum melakukan presensi.
        </div>
    <?php endif; ?>

    <div class="presensi-container">
        <!-- Card 1: Kamera & Aksi -->
        <div class="card animate-item">
            <div class="card-header mb-0">
                <h3 class="card-title"><i class="fas fa-camera"
                        style="margin-right:8px;color:var(--primary);"></i>Kamera & Presensi</h3>
                <?php if ($todayRecord): ?>
                    <?php
                    $statusLabel = match ($todayRecord['status']) {
                        'hadir' => $todayRecord['jam_keluar'] ? 'Selesai' : 'Sedang Bekerja',
                        'izin' => 'Izin',
                        'sakit' => 'Sakit',
                        default => ucfirst($todayRecord['status'])
                    };
                    $statusBadge = match ($todayRecord['status']) {
                        'hadir' => $todayRecord['jam_keluar'] ? 'badge-success' : 'badge-info',
                        'izin' => 'badge-info',
                        'sakit' => 'badge-warning',
                        default => 'badge-primary'
                    };
                    ?>
                    <span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span>
                <?php endif; ?>
            </div>

            <!-- Camera Preview -->
            <div class="camera-container"
                style="position:relative;width:100%;height:240px;background:#000;border-radius:8px;overflow:hidden;margin-top:12px;">
                <video id="camera" autoplay playsinline
                    style="width:100%;height:100%;object-fit:cover;transform:scaleX(-1);"></video>
                <div id="cameraOverlay"
                    style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.7);color:white;text-align:center;">
                    <span id="cameraStatusText">Mengakses Kamera...</span>
                </div>
                <button type="button" id="switchCamBtn" class="btn btn-sm btn-light"
                    style="position:absolute;top:10px;right:10px;z-index:20;display:none;">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <canvas id="snapshot" style="display:none;"></canvas>

            <!-- Action Buttons -->
            <div style="margin-top:14px;">
                <?php if (!$todayRecord): ?>
                    <?php if ($placement && $schedule): ?>
                        <button class="btn btn-success w-full" onclick="doCheckin()" id="checkinBtn"
                            style="padding:14px;font-size:.95rem;">
                            <i class="fas fa-camera"></i> Ambil Foto & Check-in
                        </button>
                        <div style="display:flex;gap:10px;margin-top:10px;">
                            <button class="btn btn-outline w-full" onclick="openModal('izinModal')"
                                style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                <i class="fas fa-envelope" style="color:var(--info);"></i> Izin
                            </button>
                            <button class="btn btn-outline w-full" onclick="openModal('sakitModal')"
                                style="display:flex;align-items:center;justify-content:center;gap:8px;">
                                <i class="fas fa-medkit" style="color:var(--warning);"></i> Sakit
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="text-align:center;padding:10px 0;color:var(--text-muted);font-size:.85rem;">
                            <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                            Lengkapi penempatan & jam kerja untuk mulai presensi.
                        </div>
                    <?php endif; ?>

                <?php elseif ($todayRecord['status'] === 'izin'): ?>
                    <div
                        style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:rgba(59,130,246,0.08);border-radius:var(--radius-sm);">
                        <i class="fas fa-envelope" style="font-size:1.2rem;color:var(--info);"></i>
                        <div>
                            <strong style="font-size:.9rem;color:var(--text-heading);">Izin Hari Ini</strong>
                            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;">
                                <?= htmlspecialchars($todayRecord['keterangan']) ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($todayRecord['status'] === 'sakit'): ?>
                    <div
                        style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:rgba(245,158,11,0.08);border-radius:var(--radius-sm);">
                        <i class="fas fa-medkit" style="font-size:1.2rem;color:var(--warning);"></i>
                        <div>
                            <strong style="font-size:.9rem;color:var(--text-heading);">Sakit Hari Ini</strong>
                            <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;">
                                <?= htmlspecialchars($todayRecord['keterangan']) ?>
                            </div>
                        </div>
                    </div>

                <?php elseif (!$todayRecord['jam_keluar']): ?>
                    <div
                        style="display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(16,185,129,0.08);border-radius:var(--radius-sm);margin-bottom:10px;">
                        <i class="fas fa-check-circle" style="color:var(--success);font-size:1.1rem;"></i>
                        <span style="font-size:.88rem;color:var(--text);">Masuk:
                            <strong><?= formatJam($todayRecord['jam_masuk']) ?></strong></span>
                    </div>
                    <button class="btn btn-danger w-full" onclick="doCheckout()" id="checkoutBtn"
                        style="padding:14px;font-size:.95rem;">
                        <i class="fas fa-camera"></i> Ambil Foto & Check-out
                    </button>

                <?php else: ?>
                    <div style="text-align:center;padding:16px 0;">
                        <i class="fas fa-check-double" style="font-size:1.8rem;color:var(--success);margin-bottom:8px;"></i>
                        <div style="font-size:.95rem;font-weight:600;color:var(--text-heading);">Presensi Selesai</div>
                        <div style="font-size:.8rem;color:var(--text-muted);margin-top:4px;">
                            <?= formatJam($todayRecord['jam_masuk']) ?> — <?= formatJam($todayRecord['jam_keluar']) ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card 2: Peta & GPS -->
        <div class="card animate-item">
            <div class="card-header mb-0">
                <h3 class="card-title"><i class="fas fa-map-marked-alt"
                        style="margin-right:8px;color:var(--primary);"></i>Lokasi</h3>
            </div>
            <div class="map-container mt-2" id="attendanceMap" style="height:220px;border-radius:var(--radius-sm);">
            </div>
            <div class="location-info mt-2">
                <div class="info-row">
                    <span class="info-label">Status GPS</span>
                    <span class="info-value" id="gpsStatus">Mendeteksi...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jarak</span>
                    <span class="info-value" id="distanceValue">-</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Riwayat Presensi -->
    <div class="card mt-3 animate-item">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-history" style="margin-right:8px;color:var(--primary);"></i>Riwayat
                Presensi</h3>
        </div>
        <style>
            .history-list {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .history-item {
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: var(--radius-sm);
                padding: 14px;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .history-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .history-date {
                font-weight: 600;
                color: var(--text-heading);
                font-size: 0.95rem;
            }

            .history-time {
                display: flex;
                justify-content: space-between;
                font-size: 0.85rem;
                color: var(--text-secondary);
                background: var(--bg-input);
                padding: 8px 12px;
                border-radius: 8px;
            }

            .history-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 0.8rem;
                margin-top: 4px;
            }
        </style>

        <?php if (empty($history)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:12px;"></i>
                <h3 style="font-size:1rem;color:var(--text-muted);">Belum ada riwayat</h3>
            </div>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($history as $h): ?>
                    <div class="history-item animate-item">
                        <div class="history-header">
                            <div class="history-date">
                                <i class="far fa-calendar-alt" style="margin-right:6px;color:var(--primary);"></i>
                                <?= formatTanggal($h['tanggal']) ?>
                            </div>
                            <?php
                            $statusMap = [
                                'hadir' => ['color' => 'success', 'label' => 'Hadir'],
                                'izin' => ['color' => 'info', 'label' => 'Izin'],
                                'sakit' => ['color' => 'warning', 'label' => 'Sakit'],
                                'alpha' => ['color' => 'danger', 'label' => 'Alpha']
                            ];
                            $st = $statusMap[$h['status']] ?? ['color' => 'primary', 'label' => $h['status']];
                            ?>
                            <span class="badge badge-<?= $st['color'] ?>"><?= $st['label'] ?></span>
                        </div>

                        <?php if ($h['status'] === 'hadir'): ?>
                            <div class="history-time">
                                <div>
                                    <i class="fas fa-sign-in-alt text-success" style="margin-right:4px;"></i>
                                    Masuk: <strong><?= formatJam($h['jam_masuk']) ?></strong>
                                </div>
                                <div>
                                    <i class="fas fa-sign-out-alt text-danger" style="margin-right:4px;"></i>
                                    Keluar: <strong><?= formatJam($h['jam_keluar']) ?></strong>
                                </div>
                            </div>
                            <div class="history-meta">
                                <div style="color:var(--text-muted);">
                                    <i class="fas fa-map-marker-alt" style="margin-right:4px;"></i>
                                    Jarak:
                                    <?php if ($h['jarak_masuk']): ?>
                                        <span
                                            class="<?= $h['jarak_masuk'] <= ($placement['radius_meter'] ?? 50) ? 'text-success' : 'text-danger' ?>">
                                            <?= round($h['jarak_masuk']) ?>m
                                        </span>
                                    <?php else:
                                        echo '-';
                                    endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div
                                style="background:var(--bg-input);padding:10px;border-radius:8px;font-size:0.85rem;color:var(--text-secondary);">
                                <strong>Keterangan:</strong> <?= htmlspecialchars($h['keterangan'] ?? '-') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Izin Modal -->
<div class="modal-overlay" id="izinModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-envelope" style="margin-right:8px;color:var(--info);"></i>Pengajuan
                Izin</h3>
            <button class="modal-close" onclick="closeModal('izinModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Keterangan <span style="color:var(--danger);">*</span></label>
                <textarea id="izinKeterangan" class="form-control" rows="3" placeholder="Jelaskan alasan izin..."
                    required></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('izinModal')">Batal</button>
            <button class="btn btn-primary" onclick="submitIzin('izin')"><i class="fas fa-check"></i> Kirim
                Izin</button>
        </div>
    </div>
</div>

<!-- Sakit Modal -->
<div class="modal-overlay" id="sakitModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-medkit"
                    style="margin-right:8px;color:var(--warning);"></i>Pengajuan Sakit</h3>
            <button class="modal-close" onclick="closeModal('sakitModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Keterangan <span style="color:var(--danger);">*</span></label>
                <textarea id="sakitKeterangan" class="form-control" rows="3" placeholder="Jelaskan kondisi sakit..."
                    required></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('sakitModal')">Batal</button>
            <button class="btn btn-warning" onclick="submitIzin('sakit')"><i class="fas fa-check"></i> Kirim
                Sakit</button>
        </div>
    </div>
</div>

<script>
    const placementData = <?= json_encode($placement ?: null) ?>;
    let map, userMarker, placeCircle, userPos = null;
    let videoStream = null;

    // ---- Toast Notification ----
    function showToast(msg, type = 'info', duration = 3500) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;display:flex;flex-direction:column;gap:8px;width:90%;max-width:420px;pointer-events:none;';
            document.body.appendChild(container);
        }
        const colors = { success: '#10b981', danger: '#ef4444', warning: '#f59e0b', info: '#6366f1' };
        const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
        const toast = document.createElement('div');
        toast.style.cssText = `background:${colors[type] || colors.info};color:white;padding:14px 18px;border-radius:12px;font-size:.88rem;font-weight:500;box-shadow:0 8px 30px rgba(0,0,0,.25);display:flex;align-items:center;gap:10px;pointer-events:auto;animation:toastIn .4s cubic-bezier(.16,1,.3,1);font-family:Inter,sans-serif;`;
        toast.innerHTML = `<i class="fas ${icons[type] || icons.info}"></i><span>${msg}</span>`;
        container.appendChild(toast);
        setTimeout(() => { toast.style.animation = 'toastOut .3s ease forwards'; setTimeout(() => toast.remove(), 300); }, duration);
    }
    // Toast animations
    if (!document.getElementById('toastStyles')) {
        const s = document.createElement('style'); s.id = 'toastStyles';
        s.textContent = `@keyframes toastIn{from{opacity:0;transform:translateY(-20px) scale(.95)}to{opacity:1;transform:translateY(0) scale(1)}}@keyframes toastOut{from{opacity:1;transform:translateY(0) scale(1)}to{opacity:0;transform:translateY(-20px) scale(.95)}}`;
        document.head.appendChild(s);
    }

    // ---- Camera Logic ----
    async function initCamera() {
        const video = document.getElementById('camera');
        const overlay = document.getElementById('cameraOverlay');
        const statusText = document.getElementById('cameraStatusText');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            video.srcObject = stream;
            videoStream = stream;
            overlay.style.display = 'none';
        } catch (err) {
            console.error('Camera Error:', err);
            statusText.textContent = 'Gagal akses kamera: ' + err.message;
            statusText.innerHTML += '<br><button class="btn btn-sm btn-primary mt-2" onclick="initCamera()">Coba Lagi</button>';
        }
    }

    function capturePhoto() {
        const video = document.getElementById('camera');
        const canvas = document.getElementById('snapshot');
        if (!videoStream) return null;
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.translate(canvas.width, 0);
        ctx.scale(-1, 1);
        ctx.drawImage(video, 0, 0);
        return canvas.toDataURL('image/jpeg', 0.8);
    }

    // ---- Map & GPS ----
    document.addEventListener('DOMContentLoaded', () => {
        initCamera();

        const defLat = placementData ? parseFloat(placementData.latitude) : -6.2;
        const defLng = placementData ? parseFloat(placementData.longitude) : 106.8;

        map = L.map('attendanceMap').setView([defLat, defLng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        if (placementData) {
            L.marker([defLat, defLng]).addTo(map).bindPopup(placementData.nama_perusahaan);
            L.circle([defLat, defLng], { radius: parseInt(placementData.radius_meter), color: '#6366f1', fillOpacity: 0.1 }).addTo(map);
        }

        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(
                (pos) => {
                    userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                    document.getElementById('gpsStatus').textContent = 'Akurat (' + Math.round(pos.coords.accuracy) + 'm)';
                    document.getElementById('gpsStatus').style.color = 'var(--success)';

                    if (userMarker) userMarker.setLatLng([userPos.lat, userPos.lng]);
                    else userMarker = L.marker([userPos.lat, userPos.lng]).addTo(map);

                    if (placementData) {
                        const d = haversineDistance(userPos.lat, userPos.lng, defLat, defLng);
                        document.getElementById('distanceValue').textContent = formatDistance(d);
                    }
                },
                (err) => {
                    document.getElementById('gpsStatus').textContent = 'GPS Error: ' + err.message;
                    document.getElementById('gpsStatus').style.color = 'var(--danger)';
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
            );
        }
    });

    // ---- Checkin ----
    async function doCheckin() {
        if (!userPos) { showToast('Menunggu GPS... Pastikan lokasi aktif.', 'warning'); return; }
        const photo = capturePhoto();
        if (!photo) { showToast('Gagal mengambil foto. Pastikan kamera aktif.', 'danger'); return; }

        const btn = document.getElementById('checkinBtn');
        btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Memproses...';

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'checkin', latitude: userPos.lat, longitude: userPos.lng, foto: photo })
            });
            const data = await res.json();
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showToast(data.message, 'danger', 4000); btn.disabled = false; btn.innerHTML = '<i class="fas fa-camera"></i> Ambil Foto & Check-in'; }
        } catch (e) { showToast('Error: ' + e.message, 'danger'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-camera"></i> Ambil Foto & Check-in'; }
    }

    // ---- Checkout ----
    async function doCheckout() {
        if (!userPos) { showToast('Menunggu GPS... Pastikan lokasi aktif.', 'warning'); return; }
        if (!confirm('Yakin ingin check-out?')) return;
        const photo = capturePhoto();
        if (!photo) { showToast('Gagal mengambil foto. Pastikan kamera aktif.', 'danger'); return; }

        const btn = document.getElementById('checkoutBtn');
        btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Memproses...';

        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'checkout', latitude: userPos.lat, longitude: userPos.lng, foto: photo })
            });
            const data = await res.json();
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showToast(data.message, 'danger', 4000); btn.disabled = false; btn.innerHTML = '<i class="fas fa-camera"></i> Ambil Foto & Check-out'; }
        } catch (e) { showToast('Error: ' + e.message, 'danger'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-camera"></i> Ambil Foto & Check-out'; }
    }

    // ---- Submit Izin / Sakit ----
    async function submitIzin(type) {
        const keterangan = document.getElementById(type + 'Keterangan').value.trim();
        if (!keterangan) { showToast('Keterangan wajib diisi.', 'warning'); return; }
        try {
            const res = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: type, keterangan: keterangan, latitude: 0, longitude: 0 })
            });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                closeModal(type + 'Modal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message, 'danger', 4000);
            }
        } catch (e) { showToast('Error: ' + e.message, 'danger'); }
    }

    // ---- Utils ----
    function haversineDistance(lat1, lon1, lat2, lon2) {
        const R = 6371e3; const p1 = lat1 * Math.PI / 180; const p2 = lat2 * Math.PI / 180;
        const dLat = (lat2 - lat1) * Math.PI / 180; const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(p1) * Math.cos(p2) * Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }
    function formatDistance(m) { return m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(2) + ' km'; }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>