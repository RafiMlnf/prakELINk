<?php
/**
 * Landing Page — Redirect to appropriate dashboard
 */
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    $user = currentUser();
    switch ($user['role']) {
        case 'admin':
        case 'pembimbing':
            redirect('/admin/index.php');
            break;
        case 'siswa':
            redirect('/siswa/index.php');
            break;
    }
} else {
    redirect('/auth/login.php');
}
