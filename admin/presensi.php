<?php
/**
 * Admin — Attendance Reports
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Rekap Presensi';

// Filters
$filterSiswa = $_GET['siswa_id'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterFrom = $_GET['from'] ?? date('Y-m-01');
$filterTo = $_GET['to'] ?? date('Y-m-d');

$where = "WHERE p.tanggal BETWEEN ? AND ?";
$params = [$filterFrom, $filterTo];

if ($filterSiswa) {
    $where .= " AND p.siswa_id = ?";
    $params[] = $filterSiswa;
}
if ($filterStatus) {
    $where .= " AND p.status = ?";
    $params[] = $filterStatus;
}

// Fetch Records
// SORTIR BY: Kelas ASC, Nama ASC ("disortir by urutan kelas/abjad")
$stmt = $db->prepare("
    SELECT p.*, u.nama_lengkap, s.nisn, s.kelas,
           pen.nama_perusahaan
    FROM presensi p
    JOIN siswa s ON p.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN penempatan pen ON s.penempatan_id = pen.id
    $where
    ORDER BY s.kelas ASC, u.nama_lengkap ASC, p.tanggal DESC
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Handle Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=Rekap_Presensi_" . date('Y-m-d_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/>
    <style>table{border-collapse:collapse;} td,th{border:1px solid #000;padding:5px;}</style>
    </head><body>';
    echo '<table>';
    echo '<thead>
            <tr style="background-color:#eee;">
                <th>No</th>
                <th>NISN</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Perusahaan</th>
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Keluar</th>
                <th>Status</th>
                <th>Keterangan</th>
                <th>Jarak Masuk</th>
            </tr>
          </thead>';
    echo '<tbody>';
    foreach ($records as $i => $r) {
        echo '<tr>';
        echo '<td>' . ($i + 1) . '</td>';
        echo "<td>'" . ($r['nisn']) . "</td>"; // Force string for NISN
        echo '<td>' . htmlspecialchars($r['nama_lengkap']) . '</td>';
        echo '<td>' . htmlspecialchars($r['kelas']) . '</td>';
        echo '<td>' . htmlspecialchars($r['nama_perusahaan'] ?? '-') . '</td>';
        echo '<td>' . $r['tanggal'] . '</td>';
        echo '<td>' . $r['jam_masuk'] . '</td>';
        echo '<td>' . $r['jam_keluar'] . '</td>';
        echo '<td>' . ucfirst($r['status']) . '</td>';
        echo '<td>' . htmlspecialchars($r['keterangan'] ?? '') . '</td>';
        echo '<td>' . ($r['jarak_masuk'] ? round($r['jarak_masuk']) . 'm' : '-') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

// Students for filter
$students = $db->query("SELECT s.id, u.nama_lengkap FROM siswa s JOIN users u ON s.user_id = u.id ORDER BY u.nama_lengkap")->fetchAll();

// Summary stats
$summary = ['hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpha' => 0];
foreach ($records as $r) {
    if (isset($summary[$r['status']]))
        $summary[$r['status']]++;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card success animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Hadir</h3>
                    <div class="stat-value"><?= $summary['hadir'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="stat-card info animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Izin</h3>
                    <div class="stat-value"><?= $summary['izin'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-envelope"></i></div>
            </div>
        </div>
        <div class="stat-card warning animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Sakit</h3>
                    <div class="stat-value"><?= $summary['sakit'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-medkit"></i></div>
            </div>
        </div>
        <div class="stat-card danger animate-item">
            <div class="stat-card-body">
                <div class="stat-info">
                    <h3>Alpha</h3>
                    <div class="stat-value"><?= $summary['alpha'] ?></div>
                </div>
                <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-clipboard-check"
                    style="margin-right:8px;color:var(--primary);"></i>Data Presensi</h3>
        </div>

        <!-- Filter & Export -->
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
                <option value="hadir" <?= $filterStatus === 'hadir' ? 'selected' : '' ?>>Hadir</option>
                <option value="izin" <?= $filterStatus === 'izin' ? 'selected' : '' ?>>Izin</option>
                <option value="sakit" <?= $filterStatus === 'sakit' ? 'selected' : '' ?>>Sakit</option>
                <option value="alpha" <?= $filterStatus === 'alpha' ? 'selected' : '' ?>>Alpha</option>
            </select>
            <input type="date" name="from" class="form-control" value="<?= $filterFrom ?>">
            <input type="date" name="to" class="form-control" value="<?= $filterTo ?>">

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-filter"></i> Filter
            </button>
            <button type="submit" name="export" value="excel" class="btn btn-success btn-sm" title="Download Excel">
                <i class="fas fa-file-excel"></i> Export
            </button>
        </form>

        <?php if (empty($records)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <h3>Tidak ada data presensi</h3>
                <p>Ubah filter untuk melihat data lain.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Penempatan</th>
                            <th>Tanggal</th>
                            <th>Masuk</th>
                            <th>Keluar</th>
                            <th>Jarak</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $i => $r):
                            // Prepare data for JSON handling (sanitized properly)
                            $r_safe = $r;
                            // Clean up sensitive fields if any, or large descriptions
                            ?>
                            <tr onclick='openDetail(<?= json_encode($r_safe, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                style="cursor:pointer;" title="Klik untuk melihat detail lengkap">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($r['nama_lengkap']) ?></strong>
                                    <div style="font-size:0.75rem;color:var(--text-muted);"><?= htmlspecialchars($r['nisn']) ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($r['kelas']) ?></td>
                                <td><?= htmlspecialchars($r['nama_perusahaan'] ?? '-') ?></td>
                                <td><?= formatTanggal($r['tanggal']) ?></td>
                                <td><?= formatJam($r['jam_masuk']) ?></td>
                                <td><?= formatJam($r['jam_keluar']) ?></td>
                                <td>
                                    <?php if ($r['jarak_masuk']): ?>
                                        <span class="<?= $r['jarak_masuk'] <= 200 ? 'text-success' : 'text-danger' ?>"
                                            style="font-size:.82rem;">
                                            <?= round($r['jarak_masuk']) ?>m
                                        </span>
                                    <?php else: ?> - <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $bc = match ($r['status']) {
                                        'hadir' => 'badge-success',
                                        'izin' => 'badge-info',
                                        'sakit' => 'badge-warning',
                                        'alpha' => 'badge-danger',
                                        default => 'badge-primary'
                                    };
                                    ?>
                                    <span class="badge <?= $bc ?>"><?= ucfirst($r['status']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Detail Modal -->
<div class="modal-overlay" id="detailModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3 class="modal-title">Detail Presensi</h3>
            <button class="modal-close" onclick="closeModal('detailModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div
                style="display:flex;align-items:center;margin-bottom:20px;gap:15px;padding-bottom:15px;border-bottom:1px solid #eee;">
                <div id="modalAvatar"
                    style="width:60px;height:60px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:1.5rem;color:#888;">
                    <!-- Initials or Img -->
                </div>
                <div>
                    <h4 id="modalName" style="margin:0;font-size:1.1rem;color:var(--text-heading);"></h4>
                    <div id="modalInfo" style="font-size:0.9rem;color:var(--text-muted);"></div>
                </div>
                <div id="modalStatus" style="margin-left:auto;"></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <!-- Masuk -->
                <div class="card" style="padding:15px;border:1px solid #eee;box-shadow:none;">
                    <h5
                        style="margin-top:0;color:var(--success);border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:10px;">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </h5>
                    <div style="font-size:1.2rem;font-weight:700;margin-bottom:5px;" id="modalJamMasuk">-</div>
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;" id="modalJarakMasuk">-
                    </div>
                    <div id="modalFotoMasuk"
                        style="width:100%;height:150px;background:#f5f5f5;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#ccc;">
                        <i class="fas fa-image" style="font-size:2rem;"></i>
                    </div>
                </div>

                <!-- Keluar -->
                <div class="card" style="padding:15px;border:1px solid #eee;box-shadow:none;">
                    <h5
                        style="margin-top:0;color:var(--danger);border-bottom:1px solid #eee;padding-bottom:8px;margin-bottom:10px;">
                        <i class="fas fa-sign-out-alt"></i> Keluar
                    </h5>
                    <div style="font-size:1.2rem;font-weight:700;margin-bottom:5px;" id="modalJamKeluar">-</div>
                    <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:10px;" id="modalJarakKeluar">-
                    </div>
                    <div id="modalFotoKeluar"
                        style="width:100%;height:150px;background:#f5f5f5;border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-content:center;color:#ccc;">
                        <i class="fas fa-image" style="font-size:2rem;"></i>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="font-size:0.85rem;color:var(--text-muted);">Keterangan /
                    Catatan</label>
                <div id="modalKeterangan"
                    style="background:#f8fafc;padding:12px;border-radius:8px;font-size:0.9rem;border:1px solid #eee;min-height:50px;">
                    -
                </div>
            </div>

            <div id="modalMapLink" style="margin-top:15px;text-align:right;">
                <!-- Link JS populated -->
            </div>
        </div>
    </div>
</div>

<script>
    function openDetail(data) {
        document.getElementById('modalName').textContent = data.nama_lengkap;
        document.getElementById('modalInfo').textContent = data.kelas + ' • ' + (data.nama_perusahaan || 'Belum Penempatan');

        // Status Badge
        const badgeClass = {
            'hadir': 'badge-success',
            'izin': 'badge-info',
            'sakit': 'badge-warning',
            'alpha': 'badge-danger'
        }[data.status] || 'badge-primary';

        document.getElementById('modalStatus').innerHTML = `<span class="badge ${badgeClass}">${data.status.toUpperCase()}</span>`;

        // Avatar
        document.getElementById('modalAvatar').textContent = data.nama_lengkap.charAt(0).toUpperCase();

        // Masuk
        document.getElementById('modalJamMasuk').textContent = data.jam_masuk || '--:--';
        document.getElementById('modalJarakMasuk').textContent = data.jarak_masuk ? Math.round(data.jarak_masuk) + ' meter dari lokasi' : 'Tidak ada data lokasi';

        const fotoMasukDiv = document.getElementById('modalFotoMasuk');
        if (data.foto_masuk) {
            fotoMasukDiv.innerHTML = `<img src="<?= BASE_URL ?>/uploads/presensi/${data.foto_masuk}" style="width:100%;height:100%;object-fit:cover;">`;
        } else {
            fotoMasukDiv.innerHTML = '<span style="font-size:0.8rem;">Tidak ada foto</span>';
        }

        // Keluar
        document.getElementById('modalJamKeluar').textContent = data.jam_keluar || '--:--';
        const fotoKeluarDiv = document.getElementById('modalFotoKeluar');
        if (data.foto_keluar) {
            fotoKeluarDiv.innerHTML = `<img src="<?= BASE_URL ?>/uploads/presensi/${data.foto_keluar}" style="width:100%;height:100%;object-fit:cover;">`;
        } else {
            fotoKeluarDiv.innerHTML = '<span style="font-size:0.8rem;">Tidak ada foto</span>';
        }

        // Keterangan
        document.getElementById('modalKeterangan').textContent = data.keterangan || '-';

        // Map Link
        const mapContainer = document.getElementById('modalMapLink');
        if (data.latitude_masuk && data.longitude_masuk) {
            mapContainer.innerHTML = `<a href="https://www.google.com/maps?q=${data.latitude_masuk},${data.longitude_masuk}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-map-marked-alt"></i> Lihat Lokasi di Maps</a>`;
        } else {
            mapContainer.innerHTML = '';
        }

        openModal('detailModal');
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>