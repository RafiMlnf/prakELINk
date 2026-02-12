<?php
/**
 * Admin — Manage Placements / Locations
 */
require_once __DIR__ . '/../config/auth.php';
requireRole('admin', 'pembimbing');

$db = getDB();
$pageTitle = 'Penempatan PKL';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $data = [
            trim($_POST['nama_perusahaan']),
            trim($_POST['alamat']),
            (float) $_POST['latitude'],
            (float) $_POST['longitude'],
            (int) $_POST['radius_meter'],
            trim($_POST['kontak_perusahaan'] ?? ''),
            $_POST['tanggal_mulai'],
            $_POST['tanggal_selesai'],
        ];

        if ($action === 'add') {
            $stmt = $db->prepare("INSERT INTO penempatan (nama_perusahaan, alamat, latitude, longitude, radius_meter, kontak_perusahaan, tanggal_mulai, tanggal_selesai) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute($data);
            setFlash('success', 'Penempatan berhasil ditambahkan.');
        } else {
            $data[] = (int) $_POST['id'];
            $stmt = $db->prepare("UPDATE penempatan SET nama_perusahaan=?, alamat=?, latitude=?, longitude=?, radius_meter=?, kontak_perusahaan=?, tanggal_mulai=?, tanggal_selesai=? WHERE id=?");
            $stmt->execute($data);
            setFlash('success', 'Penempatan berhasil diperbarui.');
        }
        redirect('/admin/penempatan.php');
    }

    if ($action === 'delete') {
        $db->prepare("UPDATE penempatan SET is_active = 0 WHERE id = ?")->execute([$_POST['id']]);
        setFlash('success', 'Penempatan berhasil dihapus.');
        redirect('/admin/penempatan.php');
    }
}

// Fetch placements
$placements = $db->query("
    SELECT p.*,
    (SELECT COUNT(*) FROM siswa s WHERE s.penempatan_id = p.id) as jumlah_siswa
    FROM penempatan p
    WHERE p.is_active = 1
    ORDER BY p.nama_perusahaan
")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-building" style="margin-right:8px;color:var(--primary);"></i>Lokasi
                Penempatan PKL</h3>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Lokasi
            </button>
        </div>

        <?php if (empty($placements)): ?>
            <div class="empty-state">
                <i class="fas fa-map-marked-alt"></i>
                <h3>Belum ada data penempatan</h3>
                <p>Tambahkan lokasi perusahaan untuk penempatan PKL siswa.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Perusahaan</th>
                            <th>Alamat</th>
                            <th>Koordinat</th>
                            <th>Radius</th>
                            <th>Periode</th>
                            <th>Siswa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($placements as $i => $p): ?>
                            <tr class="animate-item">
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($p['nama_perusahaan']) ?></strong>
                                    <?php if ($p['kontak_perusahaan']): ?>
                                        <div style="font-size:.75rem;color:var(--text-muted);">
                                            <?= htmlspecialchars($p['kontak_perusahaan']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:200px;"><?= htmlspecialchars($p['alamat']) ?></td>
                                <td style="font-size:.8rem;font-family:monospace;"><?= $p['latitude'] ?>, <?= $p['longitude'] ?>
                                </td>
                                <td><span class="badge badge-primary"><?= $p['radius_meter'] ?>m</span></td>
                                <td style="font-size:.8rem;"><?= date('d/m/Y', strtotime($p['tanggal_mulai'])) ?><br>s/d
                                    <?= date('d/m/Y', strtotime($p['tanggal_selesai'])) ?>
                                </td>
                                <td><span class="badge badge-info"><?= $p['jumlah_siswa'] ?> siswa</span></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-outline btn-sm" title="Edit"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display:inline;"
                                            onsubmit="return confirmAction('Hapus penempatan ini?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i
                                                    class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Map Preview -->
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-map" style="margin-right:8px;color:var(--primary);"></i>Peta Lokasi
            </h3>
        </div>
        <div class="map-container" id="adminMap" style="height:400px;"></div>
    </div>
</main>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="formModal">
    <div class="modal" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title" id="formModalTitle">Tambah Penempatan</h3>
            <button class="modal-close" onclick="closeModal('formModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama Perusahaan *</label>
                    <input type="text" name="nama_perusahaan" id="fNama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alamat *</label>
                    <textarea name="alamat" id="fAlamat" class="form-control" rows="2" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Latitude *</label>
                        <input type="number" name="latitude" id="fLat" class="form-control" step="any" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude *</label>
                        <input type="number" name="longitude" id="fLng" class="form-control" step="any" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Radius (meter) *</label>
                        <input type="number" name="radius_meter" id="fRadius" class="form-control" value="50" min="10"
                            required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Klik peta untuk set koordinat:</label>
                    <div id="modalMap"
                        style="height:250px;border-radius:var(--radius-sm);overflow:hidden;border:1px solid var(--border);">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tanggal Mulai *</label>
                        <input type="date" name="tanggal_mulai" id="fMulai" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Selesai *</label>
                        <input type="date" name="tanggal_selesai" id="fSelesai" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Kontak Perusahaan</label>
                    <input type="text" name="kontak_perusahaan" id="fKontak" class="form-control"
                        placeholder="No. telepon">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('formModal')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
    const locations = <?= json_encode($placements) ?>;
    let adminMap, modalMap, modalMarker, modalCircle;

    document.addEventListener('DOMContentLoaded', () => {
        adminMap = L.map('adminMap').setView([-6.2, 106.8], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(adminMap);

        const bounds = L.latLngBounds();

        locations.forEach(loc => {
            const pos = [parseFloat(loc.latitude), parseFloat(loc.longitude)];

            L.marker(pos, {
                icon: L.divIcon({
                    html: '<div style="background:#6366f1;color:white;width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;box-shadow:0 2px 8px rgba(99,102,241,.5);border:2px solid white;"><i class="fas fa-building"></i></div>',
                    className: '', iconSize: [30, 30], iconAnchor: [15, 15]
                })
            }).addTo(adminMap).bindPopup(`<strong>${loc.nama_perusahaan}</strong><br>${loc.alamat}<br><span style="color:#6366f1;">Radius: ${loc.radius_meter}m | ${loc.jumlah_siswa} siswa</span>`);

            L.circle(pos, {
                radius: parseInt(loc.radius_meter),
                color: '#6366f1', fillColor: '#6366f1', fillOpacity: 0.1, weight: 2, dashArray: '5, 10'
            }).addTo(adminMap);

            bounds.extend(pos);
        });

        if (locations.length > 0) {
            adminMap.fitBounds(bounds, { padding: [30, 30] });
        }
    });

    function initModalMap() {
        const lat = parseFloat(document.getElementById('fLat').value) || -6.2;
        const lng = parseFloat(document.getElementById('fLng').value) || 106.8;
        const radius = parseInt(document.getElementById('fRadius').value) || 50;

        setTimeout(() => {
            if (modalMap) {
                modalMap.remove();
                modalMap = null;
            }

            modalMap = L.map('modalMap').setView([lat, lng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(modalMap);

            modalMarker = L.marker([lat, lng], { draggable: true }).addTo(modalMap);

            modalCircle = L.circle([lat, lng], {
                radius: radius, color: '#1c398e', fillColor: '#1c398e', fillOpacity: 0.15, weight: 2
            }).addTo(modalMap);

            // Click map to set location
            modalMap.on('click', function (e) {
                modalMarker.setLatLng(e.latlng);
                modalCircle.setLatLng(e.latlng);
                document.getElementById('fLat').value = e.latlng.lat.toFixed(8);
                document.getElementById('fLng').value = e.latlng.lng.toFixed(8);
            });

            // Drag marker
            modalMarker.on('dragend', function (e) {
                const pos = e.target.getLatLng();
                modalCircle.setLatLng(pos);
                document.getElementById('fLat').value = pos.lat.toFixed(8);
                document.getElementById('fLng').value = pos.lng.toFixed(8);
            });

            // Radius change
            document.getElementById('fRadius').addEventListener('input', function () {
                modalCircle.setRadius(parseInt(this.value) || 100);
            });
        }, 300);
    }

    function openAddModal() {
        document.getElementById('formModalTitle').textContent = 'Tambah Penempatan';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('fNama').value = '';
        document.getElementById('fAlamat').value = '';
        document.getElementById('fLat').value = '-6.20876340';
        document.getElementById('fLng').value = '106.84559900';
        document.getElementById('fRadius').value = '100';
        document.getElementById('fMulai').value = '';
        document.getElementById('fSelesai').value = '';
        document.getElementById('fKontak').value = '';
        openModal('formModal');
        initModalMap();
    }

    function openEditModal(p) {
        document.getElementById('formModalTitle').textContent = 'Edit Penempatan';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = p.id;
        document.getElementById('fNama').value = p.nama_perusahaan;
        document.getElementById('fAlamat').value = p.alamat;
        document.getElementById('fLat').value = p.latitude;
        document.getElementById('fLng').value = p.longitude;
        document.getElementById('fRadius').value = p.radius_meter;
        document.getElementById('fMulai').value = p.tanggal_mulai;
        document.getElementById('fSelesai').value = p.tanggal_selesai;
        document.getElementById('fKontak').value = p.kontak_perusahaan || '';
        openModal('formModal');
        initModalMap();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>