<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/app.php';

if (isset($_GET['error'])) {
    setFlash('danger', 'Login via Google dibatalkan.');
    redirect('/auth/login.php');
}

if (!isset($_GET['code']) || !isset($_GET['state']) || $_GET['state'] !== ($_SESSION['google_oauth_state'] ?? '')) {
    setFlash('danger', 'Akses tidak valid atau sesi kadaluarsa.');
    redirect('/auth/login.php');
}

$code = $_GET['code'];

$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$redirect_uri = ($is_https ? "https" : "http") . "://" . $host . GOOGLE_REDIRECT_URL;

// Tentukan parameter untuk mendapatkan token
$post_params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirect_uri
];

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_params));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL for localhost/XAMPP
$result = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

$token_data = json_decode($result, true);

if (!isset($token_data['access_token'])) {
    $error_msg = 'Gagal mendapatkan akses token dari Google.';
    if ($curl_error)                          $error_msg .= ' cURL: ' . $curl_error;
    if (isset($token_data['error']))          $error_msg .= ' Google: ' . $token_data['error'];
    if (isset($token_data['error_description'])) $error_msg .= ' (' . $token_data['error_description'] . ')';

    setFlash('danger', $error_msg);
    redirect('/auth/login.php');
}

// Request data user
$access_token = $token_data['access_token'];
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass SSL for localhost/XAMPP
$user_info_json = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_info_json, true);

if (!isset($user_info['email'])) {
    setFlash('danger', 'Tidak dapat mengambil data email Google Anda.');
    redirect('/auth/login.php');
}

$email = $user_info['email'];
$google_id = $user_info['id'];
$foto = $user_info['picture'] ?? null;

// Cek apakah email sudah terdaftar di sistem kita
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if ($user) {
    // Daftarkan session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['foto'] = $user['foto'];

    // Jika belum ada foto, update pakai foto profil Google
    if (empty($user['foto']) && !empty($foto)) {
        $stmt_update = $db->prepare("UPDATE users SET foto = ? WHERE id = ?");
        $stmt_update->execute([$foto, $user['id']]);
        $_SESSION['foto'] = $foto;
    }

    if ($user['role'] === 'siswa') {
        $stmt2 = $db->prepare("SELECT id FROM siswa WHERE user_id = ?");
        $stmt2->execute([$user['id']]);
        $siswa = $stmt2->fetch();
        $_SESSION['siswa_id'] = $siswa ? $siswa['id'] : null;
    }

    header('Location: ' . getDashboardUrl());
    exit;
} else {
    // Akun belum ada => harus manual register atau admin daftarkan
    setFlash('danger', 'Email Google (' . htmlspecialchars($email) . ') belum terdaftar di sistem. Hubungi guru pembimbing.');
    redirect('/auth/login.php');
}
