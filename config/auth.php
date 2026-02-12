<?php
/**
 * Authentication & Session Management
 * PRAKELINK — Authentication & Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/app.php';

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function currentUser()
{
    if (!isLoggedIn())
        return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama_lengkap' => $_SESSION['nama_lengkap'],
        'role' => $_SESSION['role'],
        'foto' => $_SESSION['foto'] ?? null,
    ];
}

/**
 * Require authentication — redirect to correct login if not logged in
 */
function requireLogin()
{
    if (!isLoggedIn()) {
        // Detect if accessing admin area
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '/admin/') !== false) {
            redirect('/admin/login.php');
        } else {
            redirect('/auth/login.php');
        }
    }
}

/**
 * Require specific role(s) — redirect to correct dashboard if wrong role
 */
function requireRole(...$roles)
{
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        setFlash('danger', 'Anda tidak memiliki akses ke halaman ini.');
        // Redirect to correct dashboard based on role
        switch ($_SESSION['role']) {
            case 'admin':
            case 'pembimbing':
                redirect('/admin/index.php');
                break;
            case 'siswa':
                redirect('/siswa/index.php');
                break;
            default:
                redirect('/auth/login.php');
        }
    }
}

/**
 * Get dashboard URL based on role
 */
function getDashboardUrl($role = null)
{
    $role = $role ?? ($_SESSION['role'] ?? '');
    return match ($role) {
        'admin', 'pembimbing' => BASE_URL . '/admin/index.php',
        'siswa' => BASE_URL . '/siswa/index.php',
        default => BASE_URL . '/auth/login.php',
    };
}

/**
 * Login user (by username or NISN)
 */
function loginUser($username, $password)
{
    $db = getDB();

    // Try username first
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // If not found by username, try NISN (for siswa)
    if (!$user) {
        $stmt = $db->prepare("
            SELECT u.* FROM users u
            JOIN siswa s ON s.user_id = u.id
            WHERE s.nisn = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]); // $username here is the raw input
        $user = $stmt->fetch();
    }

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['foto'] = $user['foto'];

        // If siswa, also load siswa_id
        if ($user['role'] === 'siswa') {
            $stmt2 = $db->prepare("SELECT id FROM siswa WHERE user_id = ?");
            $stmt2->execute([$user['id']]);
            $siswa = $stmt2->fetch();
            $_SESSION['siswa_id'] = $siswa ? $siswa['id'] : null;
        }

        return true;
    }
    return false;
}

/**
 * Logout user
 */
function logoutUser()
{
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

/**
 * Register new user
 */
function registerUser($data)
{
    $db = getDB();

    // Check if username exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Nama ini sudah terdaftar. Hubungi guru/pengarah jika ada masalah.'];
    }

    try {
        $db->beginTransaction();

        // Insert user
        $stmt = $db->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, 'siswa')");
        $stmt->execute([
            $data['username'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['nama_lengkap'],
            $data['email'] ?? null,
        ]);
        $userId = $db->lastInsertId();

        // Insert siswa profile
        $stmt = $db->prepare("INSERT INTO siswa (user_id, nisn, kelas, jurusan, no_hp) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $data['nisn'],
            $data['kelas'],
            $data['jurusan'],
            $data['no_hp'] ?? null,
        ]);

        $db->commit();
        return ['success' => true, 'message' => 'Registrasi berhasil! Silakan login.'];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => 'Gagal registrasi: ' . $e->getMessage()];
    }
}
