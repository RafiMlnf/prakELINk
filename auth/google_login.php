<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/app.php';

// Generate state to protect against CSRF
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Bangun redirect URI yang absolut — harus PERSIS sama dengan yang didaftarkan di Google Cloud Console
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
$scheme   = $is_https ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
// GOOGLE_REDIRECT_URL sudah berisi path lengkap: /ELINA/auth/google_callback.php
$redirect_uri = $scheme . '://' . $host . GOOGLE_REDIRECT_URL;

// Set parameter untuk Google OAuth
$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'state' => $state,
    'prompt' => 'select_account'
];

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

header('Location: ' . $auth_url);
exit;
