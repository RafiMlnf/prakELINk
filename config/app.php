<?php
/**
 * Application Configuration
 * PRAKELINK — Sistem Monitoring Prakerin SMKN 2 Garut
 */

define('APP_NAME', 'PRAKELINK');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/pkl-tracking');
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB


// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
if (!is_dir(UPLOAD_DIR . 'jurnal/')) {
    mkdir(UPLOAD_DIR . 'jurnal/', 0777, true);
}
if (!is_dir(UPLOAD_DIR . 'presensi/')) {
    mkdir(UPLOAD_DIR . 'presensi/', 0777, true);
}
if (!is_dir(UPLOAD_DIR . 'foto/')) {
    mkdir(UPLOAD_DIR . 'foto/', 0777, true);
}
if (!is_dir(UPLOAD_DIR . 'pengajuan/')) {
    mkdir(UPLOAD_DIR . 'pengajuan/', 0777, true);
}

/**
 * Helper: Format tanggal Indonesia
 */
function formatTanggal($date)
{
    $bulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];
    $dt = new DateTime($date);
    return $dt->format('d') . ' ' . $bulan[(int) $dt->format('m')] . ' ' . $dt->format('Y');
}

/**
 * Helper: Format jam
 */
function formatJam($time)
{
    if (!$time)
        return '-';
    return date('H:i', strtotime($time));
}

/**
 * Helper: Upload file
 */
function uploadFile($file, $folder = 'foto')
{
    if ($file['error'] !== UPLOAD_ERR_OK)
        return null;
    if ($file['size'] > MAX_UPLOAD_SIZE)
        return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowed))
        return null;

    $filename = uniqid() . '_' . time() . '.' . $ext;
    $path = UPLOAD_DIR . $folder . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $path)) {
        return $folder . '/' . $filename;
    }
    return null;
}

/**
 * Helper: JSON response
 */
function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Helper: Redirect
 */
function redirect($url)
{
    header('Location: ' . BASE_URL . $url);
    exit;
}

/**
 * Helper: Flash message
 */
function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Helper: Calculate Haversine distance in meters
 */
function haversineDistance($lat1, $lng1, $lat2, $lng2)
{
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

/**
 * Helper: Add a notification for a user
 */
function addNotifikasi($userId, $tipe, $pesan, $link = null)
{
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifikasi (user_id, tipe, pesan, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $tipe, $pesan, $link]);
}

/**
 * Helper: Count unread notifications by type for a user
 */
function countUnreadNotifikasi($userId, $tipe = null)
{
    $db = getDB();
    if ($tipe) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND tipe = ? AND is_read = 0");
        $stmt->execute([$userId, $tipe]);
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
    }
    return (int) $stmt->fetchColumn();
}

/**
 * Helper: Mark notifications as read by type
 */
function markNotifikasiRead($userId, $tipe)
{
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifikasi SET is_read = 1 WHERE user_id = ? AND tipe = ? AND is_read = 0");
    $stmt->execute([$userId, $tipe]);
}
