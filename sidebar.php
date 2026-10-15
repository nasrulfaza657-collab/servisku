<?php
$currentPage = basename($_SERVER['PHP_SELF']);

$namaUser = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Administrator';
$roleUser = $_SESSION['role'] ?? 'Admin';

$menus = [
    [
        'label' => 'Dashboard',
        'icon' => 'fa-table-cells-large',
        'url' => 'dashboard.php',
        'files' => ['dashboard.php', 'index.php']
    ],
    [
        'label' => 'Data Servis',
        'icon' => 'fa-bars',
        'url' => 'data_servis.php',
        'files' => ['data_servis.php', 'detail_servis.php', 'edit_servis.php']
    ],
    [
        'label' => 'Pelanggan',
        'icon' => 'fa-users',
        'url' => 'pelanggan.php',
        'files' => ['pelanggan.php', 'tambah_pelanggan.php', 'edit_pelanggan.php', 'detail_pelanggan.php']
    ],
    [
        'label' => 'Servis Baru',
        'icon' => 'fa-plus',
        'url' => 'daftar.php',
        'files' => ['daftar.php']
    ],
    [
        'label' => 'Riwayat Servis',
        'icon' => 'fa-rotate-left',
        'url' => 'riwayat_servis.php',
        'files' => ['riwayat_servis.php']
    ],
    [
        'label' => 'Notifikasi WA',
        'icon' => 'fa-envelope',
        'url' => 'notifikasi_wa.php',
        'files' => ['notifikasi_wa.php']
    ],
    [
        'label' => 'Laporan',
        'icon' => 'fa-table-list',
        'url' => 'laporan.php',
        'files' => ['laporan.php']
    ],
    [
        'label' => 'Pengaturan User',
        'icon' => 'fa-gear',
        'url' => 'pengaturan_user.php',
        'files' => ['pengaturan_user.php']
    ],
];
?>

<style>
:root {
    --sidebar-width: 250px;
    --sb-bg: #0b1f42;
    --sb-text: #d9dce3;
    --sb-orange: #2563eb;
}

* {
    box-sizing: border-box;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    z-index: 1000;
    width: var(--sidebar-width);
    padding: 22px 14px 18px;
    display: flex;
    flex-direction: column;
    overflow-y: auto;
    color: #fff;
    background: var(--sb-bg);
    border-right: 1px solid #ffffff0d;
    transition: transform .3s ease;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 0 14px 28px;
}

.sidebar-brand-icon {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 12px;
    color: #fff;
    background: linear-gradient(135deg, #60a5fa, #2563eb);
    box-shadow: 0 6px 18px #2563eb40;
    font-size: 19px;
}

.sidebar-brand h2 {
    margin: 0;
    color: #fff;
    font-size: 21px;
    font-weight: 800;
    letter-spacing: -.5px;
}

.sidebar-brand p {
    margin: 3px 0 0;
    color: #8a909c;
    font-size: 12px;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sidebar-nav a,
.sidebar-logout {
    position: relative;
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 15px 18px;
    border-radius: 14px;
    color: var(--sb-text);
    font-size: 15px;
    font-weight: 500;
    text-decoration: none;
    transition: background .2s ease, color .2s ease, transform .2s ease, box-shadow .2s ease;
}

.sidebar-nav a i,
.sidebar-logout i {
    width: 20px;
    text-align: center;
    font-size: 15px;
}

.sidebar-nav a:hover,
.sidebar-logout:hover {
    color: #fff;
    background: #ffffff0d;
    transform: translateX(3px);
}

.sidebar-nav a.active {
    color: #fff;
    background: linear-gradient(100deg, #2563eb, #1d4ed8);
    box-shadow: 0 8px 22px #2563eb33;
    transform: none;
}

.sidebar-nav a:focus-visible,
.sidebar-logout:focus-visible {
    outline: 2px solid var(--sb-orange);
    outline-offset: 2px;
}

.sidebar-divider {
    height: 1px;
    margin: 12px 8px;
    background: #ffffff14;
}

.sidebar-logout {
    margin-top: auto;
}

.sidebar-overlay {
    display: none;
}

.sidebar-toggle {
    display: inline-grid;
    place-items: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border: 0;
    border-radius: 9px;
    color: #6c83a4;
    background: transparent;
    font-size: 20px;
    cursor: pointer;
}

.sidebar-toggle:hover {
    background: #edf4ff;
}

@media (max-width: 850px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.open {
        transform: translateX(0);
        box-shadow: 10px 0 35px #0005;
    }

    .sidebar-overlay.show {
        display: block;
        position: fixed;
        inset: 0;
        z-index: 999;
        background: #0a0b0f80;
        backdrop-filter: blur(2px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .sidebar, .sidebar a { transition: none; }
}
</style>

<aside class="sidebar" id="appSidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="fa-solid fa-motorcycle"></i>
        </div>
        <div>
            <h2>ServisKu</h2>
            <p>Bengkel Motor</p>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menus as $menu): ?>
            <?php $isActive = in_array($currentPage, $menu['files'], true); ?>

            <a href="<?= htmlspecialchars($menu['url'], ENT_QUOTES, 'UTF-8') ?>"
               class="<?= $isActive ? 'active' : '' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid <?= htmlspecialchars($menu['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                <span><?= htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8') ?></span>
            </a>
        <?php endforeach; ?>

        <div class="sidebar-divider"></div>
    </nav>

    <a href="logout.php"
       class="sidebar-logout"
       onclick="return confirm('Yakin ingin logout?')">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </a>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
(function () {
    const sidebar = document.getElementById('appSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    function closeSidebar() {
        sidebar?.classList.remove('open');
        overlay?.classList.remove('show');
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            overlay.classList.toggle(
                'show',
                sidebar.classList.contains('open')
            );
        });
    }

    overlay?.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth > 850) {
            closeSidebar();
        }
    });
})();
</script>
