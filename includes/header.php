<?php
/**
 * Header Include
 * Shared across all pages
 */
if (session_status() === PHP_SESSION_NONE)
    session_start();
$user = currentUser();
$flash = getFlash();
$pageTitle = $pageTitle ?? 'PRAKELINK';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description"
        content="PRAKELINK — Sistem Monitoring Prakerin SMKN 2 Garut | Presensi Lokasi & Jurnal Digital">
    <title>
        <?= htmlspecialchars($pageTitle) ?> —
        <?= APP_NAME ?>
    </title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

    <!-- App CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">

    <!-- Dark Mode: apply saved theme before render to prevent flash -->
    <script>
        (function () { var t = localStorage.getItem('theme'); if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark'); })();
    </script>
</head>

<body>
    <div class="app-container">