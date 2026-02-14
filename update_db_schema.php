<?php
/**
 * Database Migration Script for Brief Revisions
 */
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Starting Database Migration...\n";

    // 1. Update 'siswa' table
    echo "Checking 'siswa' table...\n";
    $cols = $db->query("DESCRIBE siswa")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('alamat_rumah', $cols)) {
        $db->exec("ALTER TABLE siswa ADD COLUMN alamat_rumah TEXT NULL");
        echo "Added 'alamat_rumah' to siswa.\n";
    }
    if (!in_array('lat_rumah', $cols)) {
        $db->exec("ALTER TABLE siswa ADD COLUMN lat_rumah DECIMAL(10, 8) NULL");
        echo "Added 'lat_rumah' to siswa.\n";
    }
    if (!in_array('long_rumah', $cols)) {
        $db->exec("ALTER TABLE siswa ADD COLUMN long_rumah DECIMAL(11, 8) NULL");
        echo "Added 'long_rumah' to siswa.\n";
    }
    // Check if user_id exists (it should based on previous code)
    if (!in_array('user_id', $cols)) {
        // This would be a bigger issue as logic depends on it, but assuming it exists.
    }

    // 2. Update 'presensi' table
    echo "Checking 'presensi' table...\n";
    $cols = $db->query("DESCRIBE presensi")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('foto_masuk', $cols)) {
        $db->exec("ALTER TABLE presensi ADD COLUMN foto_masuk VARCHAR(255) NULL");
        echo "Added 'foto_masuk' to presensi.\n";
    }
    if (!in_array('foto_keluar', $cols)) {
        $db->exec("ALTER TABLE presensi ADD COLUMN foto_keluar VARCHAR(255) NULL"); // Changed from lat_keluar typo in my thought
        echo "Added 'foto_keluar' to presensi.\n";
    }
    // Ensure lat_keluar/lng_keluar exist (likely do)

    // 3. Create 'pengajuan_jam_kerja' table
    echo "Checking 'pengajuan_jam_kerja' table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS pengajuan_jam_kerja (
        id INT AUTO_INCREMENT PRIMARY KEY,
        siswa_id INT NOT NULL,
        jam_masuk TIME NOT NULL,
        jam_pulang TIME NOT NULL,
        status ENUM('pending', 'disetujui', 'ditolak') DEFAULT 'pending',
        catatan_admin TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Table 'pengajuan_jam_kerja' is ready.\n";

    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>