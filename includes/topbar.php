<!-- Top Navbar -->
<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="page-title">
            <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
        </h1>
    </div>
    <div class="topbar-right">
        <span class="topbar-date">
            <i class="far fa-calendar-alt"></i>
            <span id="liveDate">
                <?= formatTanggal(date('Y-m-d')) ?>
            </span>
        </span>
        <span class="topbar-time hide-on-mobile" id="liveClock"></span>

        <button id="pwaInstallBtn" class="topbar-btn" style="display:none; color: var(--primary);" title="Install Aplikasi" onclick="installPWA()">
            <i class="fas fa-download"></i>
        </button>

        <button class="topbar-btn" onclick="toggleDarkMode()" title="Ganti Tema">
            <i class="fas fa-moon" id="darkModeIcon"></i>
        </button>

        <a href="<?= BASE_URL ?>/auth/logout.php" class="topbar-btn danger hide-on-mobile" title="Logout">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?>" id="flashAlert">
        <span>
            <?= $flash['message'] ?>
        </span>
        <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
    </div>
<?php endif; ?>