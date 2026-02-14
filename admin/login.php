<?php
/**
 * Admin / Operator Login Page
 * Separate login for admin and pembimbing roles only
 */
require_once __DIR__ . '/../config/auth.php';

// Already logged in? Redirect
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl());
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi.';
    } else {
        if (loginUser($username, $password)) {
            // Check if actually admin/pembimbing
            if (!in_array($_SESSION['role'], ['admin', 'pembimbing'])) {
                logoutUser();
                $error = 'Akun ini bukan akun Guru/Pengarah. Silakan login di halaman siswa.';
            } else {
                header('Location: ' . getDashboardUrl());
                exit;
            }
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin Login — PRAKELINK Sistem Monitoring Prakerin">
    <title>Admin Login —
        <?= APP_NAME ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>

<body>
    <div class="login-split">
        <!-- Left: Form -->
        <div class="login-left">
            <div class="login-left-inner">
                <div>
                    <img src="<?= BASE_URL ?>/assets/img/logo2.svg" alt="Logo" class="brand-logo"
                        style="height:150px;width:auto;">
                </div>

                <div
                    style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#e8edf7;border:1px solid #b8c5e0;border-radius:20px;font-size:.72rem;font-weight:600;color:#1c398e;letter-spacing:.5px;text-transform:uppercase;margin-bottom:20px;">
                    <i class="fas fa-shield-alt" style="font-size:.68rem;"></i> Guru / Pembimbing
                </div>

                <h1 style="font-size:1.8rem;font-weight:800;color:#0f172a;margin-bottom:6px;">Login Guru/Pembimbing
                </h1>
                <p style="color:#64748b;font-size:.9rem;margin-bottom:32px;">Masuk ke panel guru untuk mengelola sistem
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom:20px;border-radius:12px;">
                        <span><i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="adminLoginForm">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label"
                            style="font-weight:600;color:#334155;font-size:.85rem;">Username</label>
                        <div style="position:relative;">
                            <i class="fas fa-user-shield"
                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.9rem;"></i>
                            <input type="text" name="username" class="form-control"
                                style="padding-left:42px;height:48px;border-radius:12px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;"
                                placeholder="Masukkan username admin"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus
                                id="adminUsername">
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:24px;">
                        <label class="form-label"
                            style="font-weight:600;color:#334155;font-size:.85rem;">Password</label>
                        <div style="position:relative;">
                            <i class="fas fa-lock"
                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.9rem;"></i>
                            <input type="password" name="password" class="form-control"
                                style="padding-left:42px;padding-right:44px;height:48px;border-radius:12px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;"
                                placeholder="Masukkan password" required id="adminPassword">
                            <button type="button" onclick="togglePw(this)"
                                style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="adminLoginBtn"
                        style="width:100%;height:48px;border-radius:12px;font-size:.95rem;font-weight:600;gap:8px;box-shadow:0 4px 12px rgba(28,57,142,0.25);">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>

                <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;">
                    <a href="<?= BASE_URL ?>/auth/login.php"
                        style="font-size:.85rem;color:#1c398e;text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-arrow-left"></i> Login sebagai Siswa
                    </a>
                </div>
            </div>
        </div>

        <!-- Right: Branding -->
        <div class="login-right">
            <div class="login-right-shapes">
                <div class="lr-shape ls1"></div>
                <div class="lr-shape ls2"></div>
                <div class="lr-shape ls3"></div>
            </div>
            <div class="login-right-content">
                <h2 style="font-size:2.5rem;font-weight:800;color:white;margin-bottom:12px;line-height:1.2;">prakELINk
                </h2>
                <div
                    style="width: 60px; height: 4px; background: rgba(255,255,255,0.3); margin: 0 auto 24px; border-radius: 2px;">
                </div>
                <p
                    style="font-size:1.1rem;color:rgba(255,255,255,0.9);line-height:1.6;max-width:360px;margin-bottom:40px;">
                    Sistem informasi monitoring Praktik Kerja Industri SMKN 2 Garut untuk efisiensi dan transparansi
                    kegiatan siswa.
                </p>

                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;max-width:400px;margin:0 auto;">
                    <div
                        style="text-align:center;padding:16px;background:rgba(255,255,255,0.1);border-radius:12px;backdrop-filter:blur(5px);">
                        <i class="fas fa-map-marked-alt" style="font-size:1.5rem;color:white;margin-bottom:8px;"></i>
                        <div style="font-size:.75rem;color:rgba(255,255,255,0.8);font-weight:500;">Presensi Lokasi</div>
                    </div>
                    <div
                        style="text-align:center;padding:16px;background:rgba(255,255,255,0.1);border-radius:12px;backdrop-filter:blur(5px);">
                        <i class="fas fa-book-open" style="font-size:1.5rem;color:white;margin-bottom:8px;"></i>
                        <div style="font-size:.75rem;color:rgba(255,255,255,0.8);font-weight:500;">Jurnal Harian</div>
                    </div>
                    <div
                        style="text-align:center;padding:16px;background:rgba(255,255,255,0.1);border-radius:12px;backdrop-filter:blur(5px);">
                        <i class="fas fa-chart-line" style="font-size:1.5rem;color:white;margin-bottom:8px;"></i>
                        <div style="font-size:.75rem;color:rgba(255,255,255,0.8);font-weight:500;">Laporan Aktivitas
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .login-split {
            display: flex;
            min-height: 100vh;
        }

        .login-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            background: #ffffff;
        }

        .login-left-inner {
            width: 100%;
            max-width: 400px;
        }

        .login-right {
            flex: 1;
            background: #1c398e;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-right-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 40px;
        }

        .login-right-shapes {
            position: absolute;
            inset: 0;
            z-index: 1;
        }

        .lr-shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.12;
            filter: blur(60px);
        }

        .ls1 {
            width: 300px;
            height: 300px;
            background: #fff;
            top: -50px;
            right: -50px;
            animation: floatA 15s ease-in-out infinite alternate;
        }

        .ls2 {
            width: 250px;
            height: 250px;
            background: #ffcc00;
            bottom: -40px;
            left: -40px;
            animation: floatA 18s ease-in-out infinite alternate-reverse;
        }

        .ls3 {
            width: 200px;
            height: 200px;
            background: #fff;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: floatA 20s ease-in-out infinite alternate;
        }

        @keyframes floatA {
            0% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(20px, -20px) scale(1.1);
            }

            100% {
                transform: translate(-15px, 15px) scale(0.95);
            }
        }

        .login-left .form-control:focus {
            border-color: #1c398e !important;
            box-shadow: 0 0 0 3px rgba(28, 57, 142, 0.1);
            background: #fff !important;
        }

        @media (max-width: 768px) {
            .login-split {
                flex-direction: column;
            }

            .login-right {
                display: none;
            }

            .login-left {
                padding: 32px 24px;
                min-height: 100vh;
            }
        }
    </style>

    <script>
        function togglePw(btn) {
            const input = btn.parentElement.querySelector('input');
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>

</html>