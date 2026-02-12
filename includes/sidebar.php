<?php
/**
 * Sidebar Navigation
 * Dynamic based on user role
 */
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

$adminMenu = [
    ['icon' => 'fas fa-th-large', 'label' => 'Dashboard', 'url' => '/admin/index.php', 'page' => 'index'],
    ['icon' => 'fas fa-user-graduate', 'label' => 'Data Siswa', 'url' => '/admin/siswa.php', 'page' => 'siswa'],
    ['icon' => 'fas fa-file-signature', 'label' => 'Pengajuan PKL', 'url' => '/admin/pengajuan.php', 'page' => 'pengajuan'],
    ['icon' => 'fas fa-building', 'label' => 'Penempatan', 'url' => '/admin/penempatan.php', 'page' => 'penempatan'],
    ['icon' => 'fas fa-clipboard-check', 'label' => 'Presensi', 'url' => '/admin/presensi.php', 'page' => 'presensi'],
    ['icon' => 'fas fa-book', 'label' => 'Jurnal PKL', 'url' => '/admin/jurnal.php', 'page' => 'jurnal'],
    ['icon' => 'fas fa-chalkboard-teacher', 'label' => 'Kelola Guru', 'url' => '/admin/kelola-guru.php', 'page' => 'kelola-guru'],
];

$siswaMenu = [
    ['icon' => 'fas fa-th-large', 'label' => 'Dashboard', 'url' => '/siswa/index.php', 'page' => 'index'],
    ['icon' => 'fas fa-file-signature', 'label' => 'Pengajuan PKL', 'url' => '/siswa/pengajuan.php', 'page' => 'pengajuan'],
    ['icon' => 'fas fa-map-marker-alt', 'label' => 'Presensi', 'url' => '/siswa/presensi.php', 'page' => 'presensi'],
    ['icon' => 'fas fa-book-open', 'label' => 'Jurnal PKL', 'url' => '/siswa/jurnal.php', 'page' => 'jurnal'],
    ['icon' => 'fas fa-user-circle', 'label' => 'Profil', 'url' => '/siswa/profil.php', 'page' => 'profil'],
];

$pembimbingMenu = [
    ['icon' => 'fas fa-th-large', 'label' => 'Dashboard', 'url' => '/admin/index.php', 'page' => 'index'],
    ['icon' => 'fas fa-user-graduate', 'label' => 'Data Siswa', 'url' => '/admin/siswa.php', 'page' => 'siswa'],
    ['icon' => 'fas fa-file-signature', 'label' => 'Pengajuan PKL', 'url' => '/admin/pengajuan.php', 'page' => 'pengajuan'],
    ['icon' => 'fas fa-building', 'label' => 'Penempatan', 'url' => '/admin/penempatan.php', 'page' => 'penempatan'],
    ['icon' => 'fas fa-clipboard-check', 'label' => 'Presensi', 'url' => '/admin/presensi.php', 'page' => 'presensi'],
    ['icon' => 'fas fa-book', 'label' => 'Jurnal PKL', 'url' => '/admin/jurnal.php', 'page' => 'jurnal'],
];

$menu = [];
if ($user) {
    switch ($user['role']) {
        case 'admin':
            $menu = $adminMenu;
            break;
        case 'pembimbing':
            $menu = $pembimbingMenu;
            break;
        case 'siswa':
            $menu = $siswaMenu;
            break;
    }
}
?>
<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <img src="<?= BASE_URL ?>/assets/img/logo2.svg" alt="Logo" class="brand-logo"
                style="width:220px;height:auto;">
        </div>
        <button class="sidebar-close" id="sidebarClose" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <?php
    // Compute notification badges for siswa
    $notifBadges = [];
    if ($user && $user['role'] === 'siswa') {
        $notifBadges['pengajuan'] = countUnreadNotifikasi($user['id'], 'pengajuan');
        $notifBadges['jurnal'] = countUnreadNotifikasi($user['id'], 'jurnal');
    }
    ?>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($menu as $item): ?>
                <?php
                // Map page to notification type
                $notifCount = 0;
                if (isset($notifBadges[$item['page']])) {
                    $notifCount = $notifBadges[$item['page']];
                }
                ?>
                <li class="nav-item">
                    <a href="<?= BASE_URL . $item['url'] ?>"
                        class="nav-link <?= $currentPage === $item['page'] ? 'active' : '' ?>" style="position:relative;">
                        <i class="<?= $item['icon'] ?>"></i>
                        <span>
                            <?= $item['label'] ?>
                        </span>
                        <?php if ($notifCount > 0): ?>
                            <span class="notif-dot"><?= $notifCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar" style="overflow:hidden;">
                <?php if (!empty($user['foto']) && file_exists(__DIR__ . '/../uploads/profil/' . $user['foto'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/profil/<?= $user['foto'] ?>" alt="Foto"
                        style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <span style="font-weight:700;font-size:1rem;color:white;">
                        <?= strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 1)) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="user-details">
                <span class="user-name">
                    <?= htmlspecialchars($user['nama_lengkap'] ?? '') ?>
                </span>
                <span class="user-role">
                    <?php
                    $roleLabel = match ($user['role'] ?? '') {
                        'admin' => 'Admin',
                        'pembimbing' => 'Guru',
                        'siswa' => 'Siswa',
                        default => ucfirst($user['role'] ?? '')
                    };
                    ?>
                    <?= $roleLabel ?>
                </span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-btn" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>