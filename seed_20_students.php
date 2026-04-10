<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database.php';

$db = getDB();

echo "Starting massive seeding...\n";

$names = [
    "Budi Santoso", "Andi Permana", "Cici Anjani", "Dina Anggraini", 
    "Eko Prasetyo", "Fajar Nugraha", "Gilang Ramadhan", "Hana Pertiwi", 
    "Indra Wijaya", "Joko Susilo", "Kiki Amalia", "Lina Marlina", 
    "Mira Lesmana", "Nina Safitri", "Oka Antara", "Putra Pratama", 
    "Qori Aisyah", "Rizky Firmansyah", "Sari Indah", "Tono Sutomo"
];

$kelasList = ["XII ELIN 1", "XII ELIN 2"];

$db->beginTransaction();
try {
    // Check how many students we have
    $currSiswaCount = $db->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
    $toCreate = 20 - $currSiswaCount;
    echo "Current students: $currSiswaCount. Need to create: $toCreate\n";

    if ($toCreate > 0) {
        $lastNisn = $db->query("SELECT MAX(nisn) FROM siswa")->fetchColumn() ?: '1000000000';
        $nisnBase = (int) $lastNisn;

        for ($i = 0; $i < $toCreate; $i++) {
            $nameIdx = array_rand($names);
            $namaLengkap = $names[$nameIdx] . " " . rand(1, 999);
            $username = "siswa_" . time() . "_" . $i; // Ensure unique username
            $passwordRaw = "123456";
            $passwordHash = password_hash($passwordRaw, PASSWORD_DEFAULT);
            $email = "siswa_" . time() . "_" . $i . "@sekolah.com";
            $nisn = str_pad((string)($nisnBase + 1 + $i), 10, '0', STR_PAD_LEFT);
            $kelas = $kelasList[array_rand($kelasList)];

            // Insert User
            $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, 'siswa')");
            $stmt->execute([$username, $passwordHash, $namaLengkap, $email]);
            $userId = $db->lastInsertId();

            // Insert Siswa
            $stmt = $db->prepare("INSERT INTO siswa (user_id, nisn, kelas, jurusan, no_hp, penempatan_id, pembimbing_id) VALUES (?, ?, ?, 'Elektronika Industri', '0812345678', 1, 2)");
            $stmt->execute([$userId, $nisn, $kelas]);
        }
        echo "Created $toCreate new students.\n";
    }

    // Now seed presensi & jurnal for ALL students
    $students = $db->query("SELECT id FROM siswa")->fetchAll(PDO::FETCH_COLUMN);
    $startDate = new DateTime('-30 days');
    $endDate = new DateTime();

    $insertedPresensi = 0;
    $insertedJurnal = 0;

    foreach ($students as $siswaId) {
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            if ($currentDate->format('N') == 7) { 
                // Sunday
                $currentDate->modify('+1 day');
                continue;
            }

            $dateStr = $currentDate->format('Y-m-d');
            
            // Check if already seeded
            $exists = $db->prepare("SELECT id FROM presensi WHERE siswa_id = ? AND tanggal = ?");
            $exists->execute([$siswaId, $dateStr]);
            if ($exists->fetchColumn() > 0) {
                $currentDate->modify('+1 day');
                continue;
            }

            $rand = rand(1, 100);
            if ($rand <= 80) $status = 'hadir';
            elseif ($rand <= 90) $status = 'izin';
            elseif ($rand <= 95) $status = 'sakit';
            else $status = 'alpha';

            $jamMasuk = ($status == 'hadir') ? sprintf('%02d:%02d:00', rand(6, 7), rand(0, 59)) : null;
            $jamKeluar = ($status == 'hadir') ? sprintf('%02d:%02d:00', rand(15, 17), rand(0, 59)) : null;
            $lat = ($status == 'hadir') ? -6.20876340 + (rand(-100, 100) / 100000) : null;
            $lng = ($status == 'hadir') ? 106.84559900 + (rand(-100, 100) / 100000) : null;

            $stmt = $db->prepare("INSERT IGNORE INTO presensi (siswa_id, tanggal, status, jam_masuk, jam_keluar, lat_masuk, lng_masuk, lat_keluar, lng_keluar, jarak_masuk, jarak_keluar) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$siswaId, $dateStr, $status, $jamMasuk, $jamKeluar, $lat, $lng, $lat, $lng, 50, 50]);
            
            if ($stmt->rowCount() > 0) {
                $insertedPresensi++;
                
                if ($status == 'hadir') {
                    $jurnalStatus = rand(0, 1) ? 'disetujui' : 'pending';
                    $judul = "Kegiatan PKL Harian";
                    $desc = "Melakukan pekerjaan terkait instalasi perangkat elektronik sesuai Standar Operasional Prosedur perusahaan.";
                    
                    $db->prepare("INSERT INTO jurnal (siswa_id, tanggal, judul_kegiatan, deskripsi_kegiatan, status) VALUES (?, ?, ?, ?, ?)")
                       ->execute([$siswaId, $dateStr, $judul, $desc, $jurnalStatus]);
                    $insertedJurnal++;
                }
            }
            $currentDate->modify('+1 day');
        }
    }
    
    $db->commit();
    echo "Done! Seeded $insertedPresensi presensi and $insertedJurnal jurnal records.\n";
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}
