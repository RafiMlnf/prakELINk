<?php
/**
 * Student — GPS-Based Attendance
 * Check-in / Check-out with location validation
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

$today = date('Y-m-d');

// Get today's attendance
$todayRecord = $db->prepare("SELECT * FROM presensi WHERE siswa_id = ? AND tanggal = ?");
$todayRecord->execute([$siswaId, $today]);
$todayRecord = $todayRecord->fetch();

// Handle check-in / check-out via API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $lat = (float) ($input['latitude'] ?? 0);
    $lng = (float) ($input['longitude'] ?? 0);

    if (!$placement) {
        jsonResponse(['success' => false, 'message' => 'Anda belum ditempatkan di lokasi PKL.']);
    }

    $distance = haversineDistance($lat, $lng, (float) $placement['latitude'], (float) $placement['longitude']);

    if ($action === 'checkin') {
        if ($todayRecord) {
            jsonResponse(['success' => false, 'message' => 'Anda sudah check-in hari ini.']);
        }
        if ($distance > $placement['radius_meter']) {
            jsonResponse([
                'success' => false,
                'message' => 'Anda berada di luar radius yang diizinkan (' . round($distance) . 'm dari lokasi, max ' . $placement['radius_meter'] . 'm).',
                'distance' => round($distance)
            ]);
        }

        $stmt = $db->prepare("INSERT INTO presensi (siswa_id, tanggal, jam_masuk, lat_masuk, lng_masuk, jarak_masuk, status) VALUES (?, ?, ?, ?, ?, ?, 'hadir')");
        $stmt->execute([$siswaId, $today, date('H:i:s'), $lat, $lng, round($distance, 2)]);

        jsonResponse(['success' => true, 'message' => 'Check-in berhasil! Jarak: ' . round($distance) . 'm', 'time' => date('H:i:s'), 'distance' => round($distance)]);
    }

    if ($action === 'checkout') {
        if (!$todayRecord) {
            jsonResponse(['success' => false, 'message' => 'Anda belum check-in hari ini.']);
        }
        if ($todayRecord['jam_keluar']) {
            jsonResponse(['success' => false, 'message' => 'Anda sudah check-out hari ini.']);
        }

        $stmt = $db->prepare("UPDATE presensi SET jam_keluar = ?, lat_keluar = ?, lng_keluar = ?, jarak_keluar = ? WHERE id = ?");
        $stmt->execute([date('H:i:s'), $lat, $lng, round($distance, 2), $todayRecord['id']]);

        jsonResponse(['success' => true, 'message' => 'Check-out berhasil! Jarak: ' . round($distance) . 'm', 'time' => date('H:i:s'), 'distance' => round($distance)]);
    }

    if ($action === 'izin' || $action === 'sakit') {
        if ($todayRecord) {
            jsonResponse(['success' => false, 'message' => 'Sudah ada data presensi hari ini.']);
        }
        $keterangan = trim($input['keterangan'] ?? '');
        $stmt = $db->prepare("INSERT INTO presensi (siswa_id, tanggal, status, keterangan) VALUES (?, ?, ?, ?)");
        $stmt->execute([$siswaId, $today, $action, $keterangan]);
        jsonResponse(['success' => true, 'message' => 'Data ' . $action . ' berhasil disimpan.']);
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
        <div class="alert alert-warning">
            <span><i class="fas fa-exclamation-triangle"></i> Anda belum ditempatkan di lokasi PKL. Hubungi guru/pengarah
                untuk
                penempatan.</span>
        </div>
    <?php endif; ?>

    <div class="presensi-container">
        <!-- Map -->
        <div class="card animate-item">
            <div class="card-header mb-0">
                <h3 class="card-title"><i class="fas fa-map-marked-alt"
                        style="margin-right:8px;color:var(--primary);"></i>Lokasi Anda</h3>
            </div>
            <div class="map-container mt-2" id="attendanceMap"></div>
            <div class="location-info mt-2" id="locationDetails">
                <div class="info-row">
                    <span class="info-label">Status GPS</span>
                    <span class="info-value" id="gpsStatus">Mendeteksi...</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lokasi PKL</span>
                    <span
                        class="info-value"><?= htmlspecialchars($placement['nama_perusahaan'] ?? 'Belum ada') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Jarak</span>
                    <span class="info-value" id="distanceValue">-</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Radius Max</span>
                    <span class="info-value"><?= $placement ? $placement['radius_meter'] . 'm' : '-' ?></span>
                </div>
            </div>
        </div>

        <!-- Check-in Panel -->
        <div class="card animate-item">
            <div class="card-header mb-0">
                <h3 class="card-title"><i class="fas fa-clock"
                        style="margin-right:8px;color:var(--primary-light);"></i>Presensi Hari Ini</h3>
            </div>

            <div class="checkin-status" id="checkinStatus">
                <?php if (!$todayRecord): ?>
                    <div class="status-icon idle" id="statusIcon"><i class="fas fa-fingerprint"></i></div>
                    <h3 style="color:var(--text-heading);margin-bottom:4px;">Belum Check-in</h3>
                    <p style="color:var(--text-muted);font-size:.85rem;">Aktifkan GPS dan tekan tombol di bawah</p>
                <?php elseif (!$todayRecord['jam_keluar']): ?>
                    <div class="status-icon in" id="statusIcon"><i class="fas fa-check"></i></div>
                    <h3 style="color:var(--success-light);margin-bottom:4px;">Sedang Bekerja</h3>
                    <p style="color:var(--text-muted);font-size:.85rem;">Check-in:
                        <?= formatJam($todayRecord['jam_masuk']) ?>
                    </p>
                <?php else: ?>
                    <div class="status-icon done" id="statusIcon"><i class="fas fa-check-double"></i></div>
                    <h3 style="color:var(--primary-light);margin-bottom:4px;">Selesai Hari Ini</h3>
                    <p style="color:var(--text-muted);font-size:.85rem;"><?= formatJam($todayRecord['jam_masuk']) ?> —
                        <?= formatJam($todayRecord['jam_keluar']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($placement): ?>
                <div id="actionButtons">
                    <?php if (!$todayRecord): ?>
                        <button class="btn btn-success checkin-btn" onclick="doCheckin()" id="checkinBtn">
                            <i class="fas fa-sign-in-alt"></i> Check-in
                        </button>
                        <div style="display:flex;gap:8px;margin-top:12px;">
                            <button class="btn btn-outline w-full" onclick="openModal('izinModal')"><i
                                    class="fas fa-envelope"></i> Izin</button>
                            <button class="btn btn-outline w-full" onclick="openModal('sakitModal')"><i
                                    class="fas fa-medkit"></i> Sakit</button>
                        </div>
                    <?php elseif (!$todayRecord['jam_keluar']): ?>
                        <button class="btn btn-danger checkin-btn" onclick="doCheckout()" id="checkoutBtn">
                            <i class="fas fa-sign-out-alt"></i> Check-out
                        </button>
                    <?php else: ?>
                        <p style="text-align:center;color:var(--text-muted);font-size:.85rem;">Presensi hari ini sudah selesai.
                            Silahkan presensi kembali esok hari.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Attendance History -->
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
                position: relative;
                transition: transform 0.2s;
            }

            .history-item:active {
                transform: scale(0.98);
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
                <i class="fas fa-clipboard-list"
                    style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 12px;"></i>
                <h3 style="font-size: 1rem; color: var(--text-muted);">Belum ada riwayat</h3>
            </div>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($history as $h): ?>
                    <div class="history-item animate-item">
                        <div class="history-header">
                            <div class="history-date">
                                <i class="far fa-calendar-alt" style="margin-right: 6px; color: var(--primary);"></i>
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
                                    <i class="fas fa-sign-in-alt text-success" style="margin-right: 4px;"></i>
                                    Masuk: <strong><?= formatJam($h['jam_masuk']) ?></strong>
                                </div>
                                <div>
                                    <i class="fas fa-sign-out-alt text-danger" style="margin-right: 4px;"></i>
                                    Keluar: <strong><?= formatJam($h['jam_keluar']) ?></strong>
                                </div>
                            </div>

                            <div class="history-meta">
                                <div style="color: var(--text-muted);">
                                    <i class="fas fa-map-marker-alt" style="margin-right: 4px;"></i>
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
                                <?php if ($h['keterangan']): ?>
                                    <div style="font-style: italic; color: var(--text-secondary);">
                                        "<?= htmlspecialchars($h['keterangan']) ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div
                                style="background: var(--bg-input); padding: 10px; border-radius: 8px; font-size: 0.85rem; color: var(--text-secondary);">
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
            <h3 class="modal-title">Pengajuan Izin</h3><button class="modal-close"
                onclick="closeModal('izinModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Keterangan *</label><textarea id="izinKeterangan"
                    class="form-control" rows="3" placeholder="Jelaskan alasan izin..." required></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('izinModal')">Batal</button>
            <button class="btn btn-primary" onclick="submitIzin('izin')"><i class="fas fa-check"></i> Kirim</button>
        </div>
    </div>
</div>

<!-- Sakit Modal -->
<div class="modal-overlay" id="sakitModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Pengajuan Sakit</h3><button class="modal-close"
                onclick="closeModal('sakitModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group"><label class="form-label">Keterangan *</label><textarea id="sakitKeterangan"
                    class="form-control" rows="3" placeholder="Jelaskan kondisi sakit..." required></textarea></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('sakitModal')">Batal</button>
            <button class="btn btn-warning" onclick="submitIzin('sakit')"><i class="fas fa-check"></i> Kirim</button>
        </div>
    </div>
</div>

<script>
    const placementData = <?= json_encode($placement ?: null) ?>;
    let map, userMarker, placeMarker, placeCircle, userPos = null;

    document.addEventListener('DOMContentLoaded', () => {
        const defaultLat = placementData ? parseFloat(placementData.latitude) : -6.2;
        const defaultLng = placementData ? parseFloat(placementData.longitude) : 106.8;

        map = L.map('attendanceMap').setView([defaultLat, defaultLng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        // Show placement location
        if (placementData) {
            placeMarker = L.marker([defaultLat, defaultLng], {
                icon: L.divIcon({
                    html: '<div style="background:var(--primary);color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 8px rgba(99,102,241,.5)"><i class="fas fa-building"></i></div>',
                    className: '', iconSize: [30, 30], iconAnchor: [15, 15]
                })
            }).addTo(map).bindPopup(`<strong>${placementData.nama_perusahaan}</strong><br>${placementData.alamat}`);

            placeCircle = L.circle([defaultLat, defaultLng], {
                radius: parseInt(placementData.radius_meter),
                color: '#6366f1', fillColor: '#6366f1', fillOpacity: 0.1, weight: 2, dashArray: '5, 10'
            }).addTo(map);
        }

        trackLocation();
    });

    function trackLocation() {
        if (!navigator.geolocation) {
            document.getElementById('gpsStatus').textContent = 'GPS tidak didukung';
            document.getElementById('gpsStatus').style.color = 'var(--danger-light)';
            return;
        }

        // Check if running on secure context (HTTPS or localhost)
        if (!window.isSecureContext) {
            document.getElementById('gpsStatus').innerHTML = '<span style="color:var(--danger-light);">⚠️ HTTPS diperlukan</span>';
            document.getElementById('distanceValue').innerHTML = '<a href="#" onclick="showHttpsHelp()" style="color:var(--warning);font-size:.78rem;text-decoration:underline;">Lihat solusi</a>';
            return;
        }

        navigator.geolocation.watchPosition(
            (pos) => {
                userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                document.getElementById('gpsStatus').textContent = 'Aktif ✓';
                document.getElementById('gpsStatus').style.color = 'var(--success-light)';

                if (userMarker) {
                    userMarker.setLatLng([userPos.lat, userPos.lng]);
                } else {
                    userMarker = L.marker([userPos.lat, userPos.lng], {
                        icon: L.divIcon({
                            html: '<div style="background:var(--success);color:white;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 2px 8px rgba(16,185,129,.5);border:2px solid white;"><i class="fas fa-user"></i></div>',
                            className: '', iconSize: [26, 26], iconAnchor: [13, 13]
                        })
                    }).addTo(map).bindPopup('Posisi Anda');
                }

                if (placementData) {
                    const dist = haversineDistance(userPos.lat, userPos.lng, parseFloat(placementData.latitude), parseFloat(placementData.longitude));
                    const distEl = document.getElementById('distanceValue');
                    distEl.textContent = formatDistance(dist);
                    distEl.className = dist <= parseInt(placementData.radius_meter) ? 'info-value distance-ok' : 'info-value distance-far';
                }
            },
            (err) => {
                let msg = 'Error: ' + err.message;
                if (err.code === 1) msg = 'GPS ditolak — izinkan akses lokasi di browser';
                if (err.code === 2) msg = 'GPS tidak tersedia';
                if (err.code === 3) msg = 'GPS timeout — coba lagi';
                document.getElementById('gpsStatus').textContent = msg;
                document.getElementById('gpsStatus').style.color = 'var(--danger-light)';
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
        );
    }

    function showHttpsHelp() {
        showToast('⚠️ GPS memerlukan HTTPS! Akses via localhost di PC, atau gunakan HTTPS/ngrok.', 'warning', 6000);
    }

    // Toast notification — replaces alert() to prevent mobile zoom issues
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
        setTimeout(() => {
            toast.style.animation = 'toastOut .3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    // Add toast animations
    if (!document.getElementById('toastStyles')) {
        const style = document.createElement('style');
        style.id = 'toastStyles';
        style.textContent = `
            @keyframes toastIn { from { opacity:0; transform:translateY(-20px) scale(.95); } to { opacity:1; transform:translateY(0) scale(1); } }
            @keyframes toastOut { from { opacity:1; transform:translateY(0) scale(1); } to { opacity:0; transform:translateY(-20px) scale(.95); } }
        `;
        document.head.appendChild(style);
    }

    async function doCheckin() {
        const btn = document.getElementById('checkinBtn');
        if (!userPos) { showToast('Menunggu GPS... Pastikan lokasi aktif.', 'warning'); return; }
        btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Memproses...';
        try {
            const res = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'checkin', latitude: userPos.lat, longitude: userPos.lng }) });
            const data = await res.json();
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showToast(data.message, 'danger', 4000); btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check-in'; }
        } catch (e) { showToast('Error: ' + e.message, 'danger'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Check-in'; }
    }

    async function doCheckout() {
        const btn = document.getElementById('checkoutBtn');
        if (!userPos) { showToast('Menunggu GPS... Pastikan lokasi aktif.', 'warning'); return; }
        if (!confirm('Yakin ingin check-out?')) return;
        btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Memproses...';
        try {
            const res = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'checkout', latitude: userPos.lat, longitude: userPos.lng }) });
            const data = await res.json();
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showToast(data.message, 'danger', 4000); btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check-out'; }
        } catch (e) { showToast('Error: ' + e.message, 'danger'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Check-out'; }
    }

    async function submitIzin(type) {
        const keterangan = document.getElementById(type + 'Keterangan').value.trim();
        if (!keterangan) { showToast('Keterangan wajib diisi.', 'warning'); return; }
        try {
            const res = await fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: type, keterangan: keterangan, latitude: 0, longitude: 0 }) });
            const data = await res.json();
            if (data.success) { showToast(data.message, 'success'); setTimeout(() => location.reload(), 1500); }
            else { showToast(data.message, 'danger', 4000); }
        } catch (e) { showToast('Error: ' + e.message, 'danger'); }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>