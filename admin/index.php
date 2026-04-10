<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Dashboard';

// Stats
$totalSiswa = $db->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$totalPenempatan = $db->query("SELECT COUNT(*) FROM penempatan WHERE is_active = 1")->fetchColumn();

$today = date('Y-m-d');
$hadirHariIni = $db->prepare("SELECT COUNT(*) FROM presensi WHERE tanggal = ? AND status = 'hadir'");
$hadirHariIni->execute([$today]);
$hadirHariIni = $hadirHariIni->fetchColumn();

$jurnalPending = $db->query("SELECT COUNT(*) FROM jurnal WHERE status = 'pending'")->fetchColumn();
$pengajuanPending = $db->query("SELECT COUNT(*) FROM pengajuan_pkl WHERE status = 'pending'")->fetchColumn();
$jamKerjaPending = $db->query("SELECT COUNT(*) FROM pengajuan_jam_kerja WHERE status = 'pending'")->fetchColumn();

// Fetch placement locations for map
$locations = $db->query("
    SELECT p.*, COUNT(s.id) as jumlah_siswa
    FROM penempatan p
    LEFT JOIN siswa s ON s.penempatan_id = p.id
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY p.nama_perusahaan
")->fetchAll();

// Recent attendance
$recentPresensi = $db->query("
    SELECT p.*, s.nisn, u.nama_lengkap
    FROM presensi p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY p.created_at DESC
    LIMIT 10
")->fetchAll();

// Recent journals
$recentJurnal = $db->query("
    SELECT j.*, u.nama_lengkap
    FROM jurnal j
    JOIN siswa s ON j.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    ORDER BY j.created_at DESC
    LIMIT 5
")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <?php if ($pengajuanPending > 0): ?>
        <div class="card mb-3 animate-item"
            style="background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(99,102,241,0.05));border-color:rgba(245,158,11,0.25);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div
                        style="width:44px;height:44px;border-radius:12px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-file-signature" style="font-size:1.2rem;color:var(--warning);"></i>
                    </div>
                    <div>
                        <strong style="color:var(--text-heading);">Ada <?= $pengajuanPending ?> pengajuan PKL menunggu
                            verifikasi</strong>
                        <div style="font-size:.8rem;color:var(--text-muted);">Periksa berkas dan verifikasi pengajuan siswa
                        </div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/admin/pengajuan.php" class="btn btn-warning btn-sm">
                    <i class="fas fa-arrow-right"></i> Lihat Pengajuan
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($jamKerjaPending > 0): ?>
        <div class="card mb-3 animate-item"
            style="background:linear-gradient(135deg,rgba(16, 185, 129,0.1),rgba(6, 182, 212,0.05));border-color:rgba(16, 185, 129,0.25);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div
                        style="width:44px;height:44px;border-radius:12px;background:rgba(16, 185, 129,0.15);display:flex;align-items:center;justify-content:center;">
                        <i class="fas fa-clock" style="font-size:1.2rem;color:var(--success);"></i>
                    </div>
                    <div>
                        <strong style="color:var(--text-heading);">Ada <?= $jamKerjaPending ?> pengajuan Jam Kerja menunggu
                            validasi</strong>
                        <div style="font-size:.8rem;color:var(--text-muted);">Segera proses agar siswa dapat melakukan
                            presensi
                        </div>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/admin/jam_kerja.php" class="btn btn-success btn-sm">
                    <i class="fas fa-arrow-right"></i> Validasi Sekarang
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card primary animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Total Siswa</h3>
                    <div class="stat-value">
                        <?= $totalSiswa ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
            </div>
        </div>

        <div class="stat-card success animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Hadir Hari Ini</h3>
                    <div class="stat-value">
                        <?= $hadirHariIni ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-clipboard-check"></i></div>
            </div>
        </div>

        <div class="stat-card warning animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Jurnal Pending</h3>
                    <div class="stat-value">
                        <?= $jurnalPending ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>

        <div class="stat-card info animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Lokasi PKL</h3>
                    <div class="stat-value">
                        <?= $totalPenempatan ?>
                    </div>
                </div>
                <div class="stat-icon"><i class="fas fa-building"></i></div>
            </div>
        </div>
    </div>

    <!-- Attendance Chart using ApexCharts Heatmap -->
    <?php
    // Attendance Chart Month Filter
    $selectedMonth = filter_input(INPUT_GET, 'month') ?: date('Y-m');
    
    // Validate format YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }
    
    // Ensure it doesn't go below 2026
    $year = (int)substr($selectedMonth, 0, 4);
    if ($year < 2026) {
        $selectedMonth = '2026-01';
    }

    $chartStart = $selectedMonth . '-01';
    $chartEnd = date('Y-m-t', strtotime($chartStart));

    $chartDataQuery = $db->prepare("
        SELECT tanggal, 
               SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir,
               SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin,
               SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit,
               SUM(CASE WHEN status = 'alpha' THEN 1 ELSE 0 END) as alpha
        FROM presensi 
        WHERE tanggal BETWEEN ? AND ?
        GROUP BY tanggal
        ORDER BY tanggal ASC
    ");
    $chartDataQuery->execute([$chartStart, $chartEnd]);
    $chartData = $chartDataQuery->fetchAll();

    $dates = [];
    $hadir = [];
    $tidakHadir = [];
    
    $hadirData = [];
    $izinData = [];
    $sakitData = [];
    $alphaData = [];

    $period = new DatePeriod(
        new DateTime($chartStart),
        new DateInterval('P1D'),
        new DateTime(date('Y-m-d', strtotime($chartEnd . ' +1 day')))
    );

    $mappedData = [];
    foreach ($chartData as $d) {
        $mappedData[$d['tanggal']] = $d;
    }

    foreach ($period as $date) {
        $fmt = $date->format('Y-m-d');
        $dateLabel = $date->format('d M');
        $dates[] = $dateLabel;
        
        $hCount = isset($mappedData[$fmt]) ? (int) $mappedData[$fmt]['hadir'] : 0;
        $iCount = isset($mappedData[$fmt]) ? (int) $mappedData[$fmt]['izin'] : 0;
        $sCount = isset($mappedData[$fmt]) ? (int) $mappedData[$fmt]['sakit'] : 0;
        $aCount = isset($mappedData[$fmt]) ? (int) $mappedData[$fmt]['alpha'] : 0;

        // For ChartJS
        $hadir[] = $hCount;
        $tidakHadir[] = $iCount + $sCount + $aCount;

        // For ApexCharts Heatmap
        $hadirData[] = ['x' => $dateLabel, 'y' => $hCount];
        $izinData[] = ['x' => $dateLabel, 'y' => $iCount];
        $sakitData[] = ['x' => $dateLabel, 'y' => $sCount];
        $alphaData[] = ['x' => $dateLabel, 'y' => $aCount];
    }
    ?>

    <div class="card mb-3 animate-item">
        <div class="card-header" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <div>
                <h3 class="card-title"><i class="fas fa-chart-area" style="margin-right:8px;color:var(--primary);"></i>Statistik Kehadiran</h3>
                <p style="font-size:0.85rem;color:var(--text-muted);margin:0;">Overview kehadiran siswa bulan <?= formatBulanTahun($chartStart) ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <form method="GET" action="" style="display:flex;gap:8px;">
                    <input type="month" name="month" value="<?= htmlspecialchars($selectedMonth) ?>" min="2026-01" class="form-control" style="padding:6px 12px;border:1px solid var(--border-color);border-radius:var(--radius-sm);background:transparent;color:var(--text);font-family:inherit;font-size:0.8rem;width:auto;height:auto;">
                    <button type="submit" class="btn btn-primary btn-sm" style="padding:6px 12px;"><i class="fas fa-filter"></i> Filter</button>
                </form>
                <div class="btn-group" style="display:flex;gap:4px;">
                    <button type="button" class="btn btn-sm btn-outline active" id="btnShowLineChart" onclick="toggleChartMode('line')" style="padding:4px 12px;font-size:0.8rem;height:auto;">
                        <i class="fas fa-chart-line"></i> Grafik
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" id="btnShowHeatmap" onclick="toggleChartMode('heatmap')" style="padding:4px 12px;font-size:0.8rem;height:auto;">
                        <i class="fas fa-fire"></i> Heatmap
                    </button>
                </div>
            </div>
        </div>
        <div style="width:100%;padding:10px;">
            <!-- Line Chart Container -->
            <div id="lineChartContainer" style="height:350px;">
                <canvas id="attendanceChart"></canvas>
            </div>
            
            <!-- Heatmap Container (Hidden by default) -->
            <div id="heatmapContainer" style="display:none;width:100%;">
                <div id="attendanceHeatmap"></div>
            </div>
        </div>
    </div>

    <!-- Peta Lokasi PKL -->
    <div class="card mb-3 animate-item">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-map-marked-alt"
                    style="margin-right:8px;color:var(--primary);"></i>Peta Lokasi PKL</h3>
            <a href="<?= BASE_URL ?>/admin/penempatan.php" class="btn btn-outline btn-sm">Kelola Penempatan</a>
        </div>
        <div id="adminMap" style="height:400px;border-radius:var(--radius-sm);z-index:1;"></div>
        <?php if (empty($locations)): ?>
            <p style="text-align:center;padding:16px;color:var(--text-muted);font-size:.85rem;">Belum ada lokasi penempatan.
            </p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        // Global references
        let lineChartInstance = null;

        function toggleChartMode(mode) {
            const btnLine = document.getElementById('btnShowLineChart');
            const btnHeatmap = document.getElementById('btnShowHeatmap');
            const containerLine = document.getElementById('lineChartContainer');
            const containerHeatmap = document.getElementById('heatmapContainer');

            if (mode === 'line') {
                btnLine.classList.add('active');
                btnHeatmap.classList.remove('active');
                containerLine.style.display = 'block';
                containerHeatmap.style.display = 'none';
            } else {
                btnHeatmap.classList.add('active');
                btnLine.classList.remove('active');
                containerHeatmap.style.display = 'block';
                containerLine.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            // --- Init Chart.js Line Chart ---
            const ctx = document.getElementById('attendanceChart').getContext('2d');

            const gradientHadir = ctx.createLinearGradient(0, 0, 0, 400);
            gradientHadir.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
            gradientHadir.addColorStop(1, 'rgba(16, 185, 129, 0)');

            const gradientTidak = ctx.createLinearGradient(0, 0, 0, 400);
            gradientTidak.addColorStop(0, 'rgba(239, 68, 68, 0.4)');
            gradientTidak.addColorStop(1, 'rgba(239, 68, 68, 0)');

            lineChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($dates) ?>,
                    datasets: [
                        {
                            label: 'Hadir',
                            data: <?= json_encode(array_values($hadir)) ?>,
                            borderColor: '#10b981',
                            backgroundColor: gradientHadir,
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 0,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Tidak Hadir',
                            data: <?= json_encode(array_values($tidakHadir)) ?>,
                            borderColor: '#ef4444',
                            backgroundColor: gradientTidak,
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 0,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 8 } },
                        tooltip: { mode: 'index', intersect: false, backgroundColor: 'rgba(255, 255, 255, 0.9)', titleColor: '#1e293b', bodyColor: '#475569', borderColor: '#e2e8f0', borderWidth: 1, padding: 10, displayColors: true }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 10, color: '#94a3b8' } },
                        y: { beginAtZero: true, grid: { borderDash: [2, 4], color: '#f1f5f9' }, ticks: { stepSize: 1, color: '#94a3b8' } }
                    },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false }
                }
            });

            // --- Init ApexCharts Heatmap ---
            var options = {
                series: [{
                    name: 'Hadir',
                    data: <?= json_encode($hadirData) ?>
                },
                {
                    name: 'Izin',
                    data: <?= json_encode($izinData) ?>
                },
                {
                    name: 'Sakit',
                    data: <?= json_encode($sakitData) ?>
                },
                {
                    name: 'Alpha',
                    data: <?= json_encode($alphaData) ?>
                }],
                chart: {
                    height: 350,
                    type: 'heatmap',
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif'
                },
                plotOptions: {
                    heatmap: {
                        shadeIntensity: 0.5,
                        radius: 4,
                        useFillColorAsStroke: false,
                        colorScale: {
                            ranges: [{
                                from: 0,
                                to: 0,
                                color: '#f1f5f9',
                                name: 'Kosong'
                            },
                            {
                                from: 1,
                                to: 10,
                                color: '#38bdf8',
                                name: 'Rendah'
                            },
                            {
                                from: 11,
                                to: 50,
                                color: '#3b82f6',
                                name: 'Sedang'
                            },
                            {
                                from: 51,
                                to: 1000,
                                color: '#1e3a8a',
                                name: 'Tinggi'
                            }]
                        }
                    }
                },
                dataLabels: {
                    enabled: true,
                    style: {
                        colors: ['#fff']
                    }
                },
                stroke: {
                    width: 1,
                    colors: ['#fff']
                },
                xaxis: {
                    labels: {
                        style: {
                            colors: '#64748b',
                            fontSize: '12px'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            colors: '#64748b',
                            fontSize: '13px',
                            fontWeight: 600
                        }
                    }
                },
                tooltip: {
                    theme: 'light',
                    y: {
                        formatter: function(value) {
                            return value + " Siswa";
                        }
                    }
                }
            };

            var heatmapChart = new ApexCharts(document.querySelector("#attendanceHeatmap"), options);
            heatmapChart.render();
        });
    </script>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (function () {
            const locations = <?= json_encode(array_map(function ($loc) {
                return [
                    'lat' => (float) $loc['latitude'],
                    'lng' => (float) $loc['longitude'],
                    'nama' => $loc['nama_perusahaan'],
                    'alamat' => $loc['alamat'],
                    'radius' => (int) $loc['radius_meter'],
                    'siswa' => (int) $loc['jumlah_siswa']
                ];
            }, $locations)) ?>;

            if (locations.length === 0) return;

            const map = L.map('adminMap').setView([locations[0].lat, locations[0].lng], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap'
            }).addTo(map);

            const bounds = [];
            locations.forEach(loc => {
                const marker = L.marker([loc.lat, loc.lng]).addTo(map);
                marker.bindPopup(`
                <div style="font-family:Inter,sans-serif;min-width:180px;">
                    <strong style="font-size:.9rem;">${loc.nama}</strong><br>
                    <span style="font-size:.8rem;color:#64748b;">${loc.alamat}</span><br>
                    <hr style="margin:6px 0;border-color:#e2e8f0;">
                    <span style="font-size:.8rem;">👥 ${loc.siswa} siswa</span>
                    <span style="font-size:.8rem;margin-left:8px;">📍 Radius ${loc.radius}m</span>
                </div>
            `);
                L.circle([loc.lat, loc.lng], {
                    radius: loc.radius,
                    color: '#1c398e',
                    fillColor: '#1c398e',
                    fillOpacity: 0.1,
                    weight: 2
                }).addTo(map);
                bounds.push([loc.lat, loc.lng]);
            });

            if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [30, 30] });
            } else if (bounds.length === 1) {
                map.setView(bounds[0], 15);
            }
        })();
    </script>





    <div class="grid-2">
        <!-- Recent Attendance -->
        <div class="card animate-item">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"
                        style="margin-right:8px;color:var(--primary);"></i>Presensi Terkini</h3>
                <a href="<?= BASE_URL ?>/admin/presensi.php" class="btn btn-outline btn-sm">Lihat Semua</a>
            </div>
            <?php if (empty($recentPresensi)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Belum ada data presensi</h3>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Tanggal</th>
                                <th>Masuk</th>
                                <th>Keluar</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPresensi as $p): ?>
                                <tr>
                                    <td><strong>
                                            <?= htmlspecialchars($p['nama_lengkap']) ?>
                                        </strong></td>
                                    <td>
                                        <?= formatTanggal($p['tanggal']) ?>
                                    </td>
                                    <td>
                                        <?= formatJam($p['jam_masuk']) ?>
                                    </td>
                                    <td>
                                        <?= formatJam($p['jam_keluar']) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badgeClass = match ($p['status']) {
                                            'hadir' => 'badge-success',
                                            'izin' => 'badge-info',
                                            'sakit' => 'badge-warning',
                                            'alpha' => 'badge-danger',
                                            default => 'badge-primary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?>">
                                            <?= ucfirst($p['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Journals -->
        <div class="card animate-item">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-book" style="margin-right:8px;color:var(--primary);"></i>Jurnal
                    Terbaru</h3>
                <a href="<?= BASE_URL ?>/admin/jurnal.php" class="btn btn-outline btn-sm">Lihat Semua</a>
            </div>
            <?php if (empty($recentJurnal)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>Belum ada jurnal</h3>
                </div>
            <?php else: ?>
                <div class="jurnal-list">
                    <?php foreach ($recentJurnal as $j): ?>
                        <div class="jurnal-item" style="padding:14px;">
                            <div class="jurnal-content">
                                <h4>
                                    <?= htmlspecialchars($j['judul_kegiatan']) ?>
                                </h4>
                                <p style="font-size:.78rem;margin-bottom:4px;">
                                    <strong>
                                        <?= htmlspecialchars($j['nama_lengkap']) ?>
                                    </strong> •
                                    <?= formatTanggal($j['tanggal']) ?>
                                </p>
                                <span
                                    class="badge <?= $j['status'] === 'disetujui' ? 'badge-success' : ($j['status'] === 'revisi' ? 'badge-warning' : 'badge-info') ?>">
                                    <?= ucfirst($j['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>