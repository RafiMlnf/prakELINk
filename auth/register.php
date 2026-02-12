<?php
/**
 * Registration Page (Siswa)
 */
require_once __DIR__ . '/../config/auth.php';

if (isLoggedIn()) {
    header('Location: ' . getDashboardUrl());
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');

    // Use NISN as username for consistency
    $username = preg_replace('/[^0-9]/', '', $nisn);

    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($namaLengkap) || empty($password) || empty($nisn) || empty($_POST['kelas'])) {
        $error = 'Semua field wajib harus diisi.';
    } elseif (!in_array($_POST['kelas'], ['XII ELIN 1', 'XII ELIN 2'])) {
        $error = 'Kelas tidak valid.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        $result = registerUser([
            'username' => $username,
            'password' => $password,
            'nama_lengkap' => $namaLengkap,
            'email' => trim($_POST['email'] ?? ''),
            'nisn' => $nisn,
            'kelas' => $_POST['kelas'],
            'jurusan' => 'Elektronika Industri',
            'no_hp' => trim($_POST['no_hp'] ?? ''),
        ]);

        if ($result['success']) {
            $success = 'Registrasi berhasil! Silakan login dengan NISN Anda.';
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Registrasi Siswa — PRAKELINK SMKN 2 Garut">
    <title>Registrasi — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <style>
        .login-split {
            display: flex;
            min-height: 100vh;
        }

        .login-left {
            flex: 1;
            display: flex;
            justify-content: center;
            padding: 40px 20px;
            background: #ffffff;
            overflow-y: auto;
        }

        .login-left-inner {
            width: 100%;
            max-width: 440px;
            margin: auto 0;
            /* Center vertically if space allows */
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

        .form-control:focus {
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
</head>

<body>
    <div class="login-split">
        <!-- Left: Form -->
        <div class="login-left">
            <div class="login-left-inner">
                <div style="text-align:center;margin-bottom:24px;">
                    <img src="<?= BASE_URL ?>/assets/img/logo2.svg" alt="Logo" class="brand-logo"
                        style="height:100px;width:auto;">
                </div>

                <h1 style="font-size:1.8rem;font-weight:800;color:#0f172a;margin-bottom:6px;text-align:left;">
                    Registrasi Siswa</h1>
                <p style="color:#64748b;font-size:.9rem;margin-bottom:32px;text-align:left;">Daftar akun baru untuk
                    akses sistem</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger" style="margin-bottom:20px;border-radius:12px;">
                        <span><i class="fas fa-exclamation-circle"></i> <?= $error ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success" style="margin-bottom:20px;border-radius:12px;">
                        <span><i class="fas fa-check-circle"></i> <?= $success ?></span>
                    </div>
                    <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-primary w-full" style="margin-bottom:20px;">
                        Login Sekarang
                    </a>
                <?php else: ?>

                    <form method="POST" action="">
                        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label"
                                    style="font-weight:600;color:#334155;font-size:.85rem;">NISN</label>
                                <input type="text" name="nisn" class="form-control" placeholder="10 digit NISN"
                                    value="<?= htmlspecialchars($_POST['nisn'] ?? '') ?>" required pattern="\d*"
                                    inputmode="numeric"
                                    style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                            </div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label"
                                    style="font-weight:600;color:#334155;font-size:.85rem;">Kelas</label>
                                <select name="kelas" class="form-control" required
                                    style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                                    <option value="">-- Pilih --</option>
                                    <option value="XII ELIN 1" <?= ($_POST['kelas'] ?? '') === 'XII ELIN 1' ? 'selected' : '' ?>>XII ELIN 1</option>
                                    <option value="XII ELIN 2" <?= ($_POST['kelas'] ?? '') === 'XII ELIN 2' ? 'selected' : '' ?>>XII ELIN 2</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:16px;">
                            <label class="form-label" style="font-weight:600;color:#334155;font-size:.85rem;">Nama
                                Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control"
                                placeholder="Awali nama dengan huruf besar (contoh: Andi Setiawan)"
                                value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required
                                style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                        </div>

                        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label"
                                    style="font-weight:600;color:#334155;font-size:.85rem;">Email</label>
                                <input type="email" name="email" class="form-control" placeholder="Email aktif"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                            </div>
                            <div class="form-group" style="margin-bottom:16px;">
                                <label class="form-label" style="font-weight:600;color:#334155;font-size:.85rem;">No.
                                    HP</label>
                                <input type="text" name="no_hp" class="form-control" placeholder="08..."
                                    value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>"
                                    style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                            </div>
                        </div>

                        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group" style="margin-bottom:24px;">
                                <label class="form-label"
                                    style="font-weight:600;color:#334155;font-size:.85rem;">Password</label>
                                <input type="password" name="password" class="form-control" placeholder="Min. 6 digit"
                                    required
                                    style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                            </div>
                            <div class="form-group" style="margin-bottom:24px;">
                                <label class="form-label"
                                    style="font-weight:600;color:#334155;font-size:.85rem;">Confirm</label>
                                <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi"
                                    required
                                    style="height:42px;border-radius:10px;border:1.5px solid #e2e8f0;background:#f8fafc;font-size:.9rem;">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary"
                            style="width:100%;height:48px;border-radius:12px;font-size:.95rem;font-weight:600;box-shadow:0 4px 12px rgba(28,57,142,0.25);">
                            <i class="fas fa-user-plus" style="margin-right:8px;"></i> Daftar Sekarang
                        </button>
                    </form>

                    <div style="text-align:center;margin-top:20px;font-size:.85rem;color:#64748b;">
                        Sudah punya akun? <a href="<?= BASE_URL ?>/auth/login.php"
                            style="color:#1c398e;font-weight:600;text-decoration:none;">Login di sini</a>
                    </div>
                <?php endif; ?>
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
</body>

</html>