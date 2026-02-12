<?php
/**
 * Login Page
 */
require_once __DIR__ . '/../config/auth.php';

// Already logged in? Redirect
if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl());
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ...
    $nisn = trim($_POST['nisn'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($nisn) || empty($password)) {
        $error = 'NISN dan password harus diisi.';
    } else {
        // Pass NISN directly to loginUser
        // logic in loginUser will handle looking up by NISN
        if (loginUser($nisn, $password)) {
            // Only allow siswa to login here
            if ($_SESSION['role'] !== 'siswa') {
                session_unset();
                session_destroy();
                session_start();
                $error = 'Akun Admin/Operator tidak bisa login di sini. <a href="' . BASE_URL . '/admin/login.php" style="color:var(--primary-light);text-decoration:underline;">Login Admin →</a>';
            } else {
                header('Location: ' . getDashboardUrl());
                exit;
            }
        } else {
            $error = 'NISN atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login ke PRAKELINK — Sistem Monitoring Prakerin SMKN 2 Garut">
    <title>Login —
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
                <h1 style="font-size:1.8rem;font-weight:800;color:#0f172a;margin-bottom:6px;">Selamat Datang</h1>
                <p style="color:#64748b;font-size:.9rem;margin-bottom:32px;">Masuk ke akun siswa untuk memulai</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom:20px;border-radius:12px;">
                        <span><i class="fas fa-exclamation-circle"></i>
                            <?= $error ?>
                        </span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <div class="form-group" style="margin-bottom:20px;">
                        <label class="form-label" style="font-weight:600;color:#334155;font-size:.85rem;">NISN</label>
                        <div style="position:relative;">
                            <i class="fas fa-id-card"
                                style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:0.9rem;"></i>
                            <input type="text" name="nisn" class="form-control"
                                style="padding-left:42px;height:48px;border-radius:12px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;"
                                placeholder="Masukkan NISN" value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>"
                                required autofocus id="loginNisn" pattern="\d*" inputmode="numeric">
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
                                placeholder="Masukkan password" required id="loginPassword">
                            <button type="button" onclick="togglePasswordVisibility(this)"
                                style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:#94a3b8;cursor:pointer;">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="loginBtn"
                        style="width:100%;height:48px;border-radius:12px;font-size:.95rem;font-weight:600;gap:8px;box-shadow:0 4px 12px rgba(28,57,142,0.25);">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </button>
                </form>

                <div style="text-align:center;margin-top:20px;font-size:.85rem;color:#64748b;">
                    Belum punya akun? <a href="<?= BASE_URL ?>/auth/register.php"
                        style="color:#1c398e;font-weight:600;text-decoration:none;">Daftar di sini</a>
                </div>

                <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;">
                    <a href="<?= BASE_URL ?>/admin/login.php"
                        style="font-size:.8rem;color:#94a3b8;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                        <i class="fas fa-shield-alt"></i>Login sebagai Pembina
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
                <div style="font-size:4rem;margin-bottom:20px;">🎓</div>
                <h2 style="font-size:2rem;font-weight:800;color:white;margin-bottom:12px;line-height:1.2;">PRAKELINK
                </h2>
                <p style="font-size:1.05rem;color:rgba(255,255,255,0.85);line-height:1.6;max-width:320px;">Monitoring
                    Prakerin siswa Elektronika Industri SMKN 2 Garut — Presensi berbasis lokasi & Jurnal Digital.</p>
                <div style="margin-top:32px;display:flex;gap:20px;">
                    <div style="text-align:center;">
                        <div style="font-size:1.6rem;font-weight:800;color:white;">📍</div>
                        <div style="font-size:.75rem;color:rgba(255,255,255,0.7);margin-top:4px;">Presensi GPS</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:1.6rem;font-weight:800;color:white;">📝</div>
                        <div style="font-size:.75rem;color:rgba(255,255,255,0.7);margin-top:4px;">Jurnal Digital</div>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:1.6rem;font-weight:800;color:white;">📊</div>
                        <div style="font-size:.75rem;color:rgba(255,255,255,0.7);margin-top:4px;">Monitoring</div>
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
            background: #c4b5fd;
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
        function togglePasswordVisibility(btn) {
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