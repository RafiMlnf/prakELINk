<?php
require_once __DIR__ . '/config/database.php';

$db = getDB();

echo "Seeding data...\n";

// Get students
$students = $db->query("SELECT id FROM siswa")->fetchAll(PDO::FETCH_COLUMN);

if (empty($students)) {
    echo "No students found to seed.\n";
    exit;
}

$startDate = new DateTime('-30 days');
$endDate = new DateTime();

$insertedPresensi = 0;
$insertedJurnal = 0;

$db->beginTransaction();
try {
    foreach ($students as $siswaId) {
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            // Skip weekends (Sunday)
            if ($currentDate->format('N') == 7) {
                $currentDate->modify('+1 day');
                continue;
            }

            $dateStr = $currentDate->format('Y-m-d');

            // Random status (mostly hadir)
            $rand = rand(1, 100);
            if ($rand <= 80) $status = 'hadir';
            elseif ($rand <= 90) $status = 'izin';
            elseif ($rand <= 95) $status = 'sakit';
            else $status = 'alpha';

            // Insert Presensi
            $jamMasuk = ($status == 'hadir') ? sprintf('%02d:%02d:00', rand(6, 7), rand(0, 59)) : null;
            $jamKeluar = ($status == 'hadir') ? sprintf('%02d:%02d:00', rand(15, 17), rand(0, 59)) : null;
            $lat = ($status == 'hadir') ? -6.20876340 + (rand(-100, 100) / 100000) : null;
            $lng = ($status == 'hadir') ? 106.84559900 + (rand(-100, 100) / 100000) : null;

            $stmt = $db->prepare("INSERT IGNORE INTO presensi (siswa_id, tanggal, status, jam_masuk, jam_keluar, lat_masuk, lng_masuk, lat_keluar, lng_keluar, jarak_masuk, jarak_keluar) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$siswaId, $dateStr, $status, $jamMasuk, $jamKeluar, $lat, $lng, $lat, $lng, 50, 50]);
            
            if ($stmt->rowCount() > 0) {
                $insertedPresensi++;
                
                // If hadir, insert Jurnal
                if ($status == 'hadir') {
                    $jurnalStatus = rand(0, 1) ? 'disetujui' : 'pending';
                    $judul = "Kegiatan PKL " . $dateStr;
                    $desc = "Melakukan pekerjaan lapangan sesuai instruksi supervisor. Berjalan dengan baik.";
                    
                    $db->prepare("INSERT INTO jurnal (siswa_id, tanggal, judul_kegiatan, deskripsi_kegiatan, status) VALUES (?, ?, ?, ?, ?)")
                       ->execute([$siswaId, $dateStr, $judul, $desc, $jurnalStatus]);
                    $insertedJurnal++;
                }
            }
            
            $currentDate->modify('+1 day');
        }
    }
    
    $db->commit();
    echo "Successfully inserted $insertedPresensi presensi and $insertedJurnal jurnal.\n";
} catch (Exception $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
}
