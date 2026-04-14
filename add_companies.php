<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$companies = [
    [
        'nama' => 'PT Pindad (Persero) Kiaracondong',
        'alamat' => 'Jl. Gatot Subroto No. 517, Kebon Jayanti, Kiaracondong, Kota Bandung, Jawa Barat 40284',
        'lat' => -6.923838,
        'lng' => 107.647360,
    ],
    [
        'nama' => 'PT LEN Industri (Persero) Pameungpeuk',
        'alamat' => 'Pameungpeuk, Kabupaten Garut, Jawa Barat',
        'lat' => -7.643800,
        'lng' => 107.689600,
    ]
];

foreach ($companies as $c) {
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM penempatan WHERE nama_perusahaan LIKE ?");
    $stmt->execute(['%' . $c['nama'] . '%']);
    if (!$stmt->fetch()) {
        $insert = $db->prepare("INSERT INTO penempatan (nama_perusahaan, alamat, latitude, longitude, radius_meter, is_active) VALUES (?, ?, ?, ?, 50, 1)");
        $insert->execute([$c['nama'], $c['alamat'], $c['lat'], $c['lng']]);
        echo "Inserted: " . $c['nama'] . "\n";
    } else {
        echo "Already exists: " . $c['nama'] . "\n";
    }
}
echo "Done.\n";
