<?php
/**
 * ==========================================================
 * SERVISKU - DASHBOARD PELANGGAN
 * ==========================================================
 */

/* 1. MEMUAT HELPER PELANGGAN */

$helper = __DIR__ . '/_pelanggan.php';

if (!is_file($helper)) {
    $helper = dirname(__DIR__) . '/_pelanggan.php';
}

if (!is_file($helper)) {
    exit('File _pelanggan.php tidak ditemukan.');
}

require_once $helper;

/* 2. VALIDASI LOGIN */

$id = prequire();

/* 3. FUNGSI BANTUAN */

if (!function_exists('dashboardKolomAda')) {
    function dashboardKolomAda($pdo, $tabel, $kolom)
    {
        $sql = "
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tabel, $kolom]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('statusPelangganClass')) {
    function statusPelangganClass($status)
    {
        switch ($status) {
            case 'Antre':
                return 'status-antre';

            case 'Pengerjaan':
                return 'status-pengerjaan';

            case 'Selesai':
                return 'status-selesai';

            default:
                return 'status-default';
        }
    }
}

if (!function_exists('dashboardTanggal')) {
    function dashboardTanggal($tanggal)
    {
        if (empty($tanggal)) {
            return 'Belum ditentukan';
        }

        $waktu = strtotime($tanggal);

        if ($waktu === false) {
            return '-';
        }

        return date('d/m/Y H:i', $waktu);
    }
}

/* 4. PERIKSA STRUKTUR DATABASE */

$adaFotoProfil = dashboardKolomAda(
    $pdo,
    'pelanggan',
    'foto_profil'
);

$adaIdPelanggan = dashboardKolomAda(
    $pdo,
    'servis',
    'id_pelanggan'
);

/* 5. AMBIL DATA PELANGGAN */

$kolomFoto = $adaFotoProfil ? ', foto_profil' : '';

$sqlPelanggan = "
    SELECT
        id,
        nama,
        telepon,
        email,
        alamat
        $kolomFoto
    FROM pelanggan
    WHERE id = ?
    LIMIT 1
";

$stmt = $pdo->prepare($sqlPelanggan);
$stmt->execute([$id]);

$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    unset($_SESSION['pelanggan_id']);

    header('Location: login_pelanggan.php');
    exit;
}

$u['foto_profil'] = $u['foto_profil'] ?? '';

/* 6. AMBIL RIWAYAT SERVIS */

$rows = [];
$pesanServis = '';

if ($adaIdPelanggan) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                nomor_servis,
                nomor_polisi,
                jenis_motor,
                keluhan,
                status,
                estimasi_selesai,
                selesai_pada,
                created_at
            FROM servis
            WHERE id_pelanggan = ?
            ORDER BY id DESC
        ");

        $stmt->execute([$id]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $pesanServis = 'Riwayat servis belum dapat dimuat.';
    }
} else {
    $pesanServis = 'Data servis belum terhubung dengan akun pelanggan. Silakan hubungi petugas bengkel.';
}

/* 7. HITUNG STATISTIK */

$totalServis = count($rows);
$totalAntre = 0;
$totalPengerjaan = 0;
$totalSelesai = 0;

foreach ($rows as $r) {
    $status = $r['status'] ?? '';

    if ($status === 'Antre') {
        $totalAntre++;
    } elseif ($status === 'Pengerjaan') {
        $totalPengerjaan++;
    } elseif ($status === 'Selesai') {
        $totalSelesai++;
    }
}

/* 8. FOTO PROFIL */

$fotoUrl = '';

if (!empty($u['foto_profil'])) {
    $namaFoto = basename($u['foto_profil']);

    $lokasiFoto = [
        [
            'file' => __DIR__ . '/uploads/profil/' . $namaFoto,
            'url'  => 'uploads/profil/' . rawurlencode($namaFoto)
        ],
        [
            'file' => dirname(__DIR__) . '/uploads/profil/' . $namaFoto,
            'url'  => '../uploads/profil/' . rawurlencode($namaFoto)
        ]
    ];

    foreach ($lokasiFoto as $foto) {
        if (is_file($foto['file'])) {
            $fotoUrl = $foto['url'];
            break;
        }
    }
}

/* 9. TAMPILKAN HALAMAN */

ppage('Dashboard Pelanggan');
?>

<style>
/* =====================================================
   SERVISKU - PREMIUM CUSTOMER DASHBOARD
===================================================== */

.customer-dashboard {
    --sk-navy: #10233f;
    --sk-blue: #2563eb;
    --sk-cyan: #06b6d4;
    --sk-text: #243b5a;
    --sk-muted: #8291a9;
    --sk-border: #e5edf8;

    max-width: 1450px;
    margin: 0 auto;
    padding: 8px 0 35px;
    font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
    animation: skFadeIn .6s ease both;
}

.customer-dashboard *,
.customer-dashboard *::before,
.customer-dashboard *::after {
    box-sizing: border-box;
}

/* SAMBUTAN */

.customer-dashboard .welcome-panel {
    position: relative;
    overflow: hidden;
    padding: 35px;
    margin-bottom: 27px;
    border-radius: 24px;
    border: 1px solid rgba(255,255,255,.15);
    background: linear-gradient(
        120deg,
        #10233f 0%,
        #173d70 48%,
        #1767b5 100%
    );
    color: #fff;
    box-shadow: 0 18px 40px rgba(16,35,63,.15);
    isolation: isolate;
}

.customer-dashboard .welcome-panel::before {
    content: "";
    position: absolute;
    width: 360px;
    height: 360px;
    right: -100px;
    top: -180px;
    border-radius: 50%;
    background: rgba(255,255,255,.07);
    z-index: -1;
}

.customer-dashboard .welcome-panel::after {
    content: "";
    position: absolute;
    width: 260px;
    height: 260px;
    right: 180px;
    bottom: -190px;
    border-radius: 50%;
    background: rgba(96,165,250,.16);
    z-index: -1;
}

.customer-dashboard .welcome-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 30px;
    flex-wrap: wrap;
}

.customer-dashboard .welcome-text {
    flex: 1;
    min-width: 220px;
}

.customer-dashboard .welcome-label {
    display: inline-flex;
    align-items: center;
    padding: 9px 13px;
    margin-bottom: 18px;
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 30px;
    background: rgba(255,255,255,.09);
    color: #d9ebff;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.customer-dashboard .welcome-title {
    margin: 0 0 13px;
    color: #fff;
    font-size: clamp(25px, 3vw, 35px);
    font-weight: 800;
    letter-spacing: -.8px;
    line-height: 1.35;
    overflow-wrap: anywhere;
}

.customer-dashboard .welcome-description {
    max-width: 560px;
    margin: 0;
    color: #d3e3f8;
    font-size: 13px;
    line-height: 1.9;
}

/* FOTO PROFIL */

.customer-dashboard .welcome-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.customer-dashboard .profile-container {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    padding: 4px;
    border-radius: 50%;
    background: linear-gradient(
        135deg,
        #60a5fa,
        #ffffff,
        #38bdf8,
        #818cf8
    );
    background-size: 300% 300%;
    box-shadow: 0 12px 30px rgba(0,0,0,.2);
    animation: skFloat 4s ease-in-out infinite,
               skGradient 6s ease infinite;
    transition: transform .3s ease, box-shadow .3s ease;
}

.customer-dashboard .profile-container:hover {
    transform: scale(1.05);
    box-shadow: 0 16px 40px rgba(0,0,0,.3);
}

.customer-dashboard .profile-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 112px;
    height: 112px;
    min-width: 112px;
    border-radius: 50%;
    border: 4px solid #173d70;
    background: #edf4ff;
    color: #64748b;
    font-size: 45px;
    object-fit: cover;
    object-position: center;
    overflow: hidden;
}

.customer-dashboard .profile-placeholder {
    background: linear-gradient(135deg, #dbeafe, #eff6ff);
}

/* TOMBOL */

.customer-dashboard .action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    border: 1px solid rgba(255,255,255,.22);
    border-radius: 12px;
    background: rgba(255,255,255,.12);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
    white-space: nowrap;
    backdrop-filter: blur(10px);
    transition: all .25s ease;
}

.customer-dashboard .action-btn:hover {
    transform: translateY(-3px);
    background: #fff;
    color: #173d70;
    border-color: #fff;
    box-shadow: 0 8px 22px rgba(0,0,0,.12);
}

/* INFORMASI */

.customer-dashboard .customer-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 25px;
    padding-top: 23px;
    border-top: 1px solid rgba(255,255,255,.16);
}

.customer-dashboard .info-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 11px 16px;
    border: 1px solid rgba(255,255,255,.16);
    border-radius: 11px;
    background: rgba(255,255,255,.09);
    color: #e5f1ff;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.customer-dashboard .info-pill span {
    font-size: 16px;
}

/* STATISTIK */

.customer-dashboard .stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.customer-dashboard .stat-card {
    position: relative;
    overflow: hidden;
    padding: 24px;
    border: 1px solid #e5edf8;
    border-radius: 19px;
    background: #fff;
    box-shadow: 0 6px 22px rgba(30,58,100,.035);
    transition: transform .3s ease, box-shadow .3s ease;
    animation: skSlideUp .6s ease both;
}

.customer-dashboard .stat-card:nth-child(1) {
    animation-delay: .05s;
    border-top: 3px solid #2563eb;
}

.customer-dashboard .stat-card:nth-child(2) {
    animation-delay: .12s;
    border-top: 3px solid #f59e0b;
}

.customer-dashboard .stat-card:nth-child(3) {
    animation-delay: .19s;
    border-top: 3px solid #8b5cf6;
}

.customer-dashboard .stat-card:nth-child(4) {
    animation-delay: .26s;
    border-top: 3px solid #10b981;
}

.customer-dashboard .stat-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 16px 35px rgba(30,58,100,.1);
}

.customer-dashboard .stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 20px;
}

.customer-dashboard .stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 53px;
    height: 53px;
    border-radius: 15px;
    font-size: 23px;
}

.customer-dashboard .stat-card:nth-child(1) .stat-icon {
    background: #eaf2ff;
}

.customer-dashboard .stat-card:nth-child(2) .stat-icon {
    background: #fff5df;
}

.customer-dashboard .stat-card:nth-child(3) .stat-icon {
    background: #f2ebff;
}

.customer-dashboard .stat-card:nth-child(4) .stat-icon {
    background: #e5fbf1;
}

.customer-dashboard .stat-indicator {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #2563eb;
    box-shadow: 0 0 0 5px rgba(37,99,235,.08);
}

.customer-dashboard .stat-number {
    display: block;
    margin-bottom: 8px;
    color: #172d50;
    font-size: 36px;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -1px;
}

.customer-dashboard .stat-label {
    margin: 0;
    color: #7b8da8;
    font-size: 12px;
    font-weight: 600;
}

/* RIWAYAT SERVIS */

.customer-dashboard .history-panel {
    overflow: hidden;
    border: 1px solid #e5edf8;
    border-radius: 21px;
    background: #fff;
    box-shadow: 0 8px 28px rgba(30,58,100,.04);
    animation: skSlideUp .7s ease both;
}

.customer-dashboard .history-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 27px;
    border-bottom: 1px solid #edf2fa;
}

.customer-dashboard .heading-title {
    margin: 0 0 8px;
    color: #172d50;
    font-size: 19px;
    font-weight: 800;
}

.customer-dashboard .heading-description {
    margin: 0;
    color: #8291a9;
    font-size: 12px;
    line-height: 1.7;
}

.customer-dashboard .total-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    border: 1px solid #d9e8ff;
    border-radius: 11px;
    background: #edf5ff;
    color: #1769cb;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

/* TABEL */

.customer-dashboard .table-scroll {
    width: 100%;
    overflow-x: auto;
}

.customer-dashboard table {
    width: 100%;
    min-width: 780px;
    border-collapse: collapse;
}

.customer-dashboard table thead {
    background: #f4f8fe;
}

.customer-dashboard table th {
    padding: 17px 21px;
    color: #587194;
    text-align: left;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .7px;
    text-transform: uppercase;
    white-space: nowrap;
}

.customer-dashboard table td {
    padding: 20px 21px;
    border-bottom: 1px solid #edf2fa;
    color: #5c718e;
    font-size: 12px;
    vertical-align: middle;
}

.customer-dashboard table tbody tr {
    transition: background .2s ease;
    animation: skRowIn .45s ease both;
}

.customer-dashboard table tbody tr:hover {
    background: #f6faff;
}

.customer-dashboard table tbody tr:last-child td {
    border-bottom: none;
}

.customer-dashboard .service-number {
    display: inline-block;
    padding: 8px 11px;
    border-radius: 8px;
    background: #edf5ff;
    color: #1769cb;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.customer-dashboard .motor-name {
    margin-bottom: 6px;
    color: #253d60;
    font-size: 12px;
    font-weight: 800;
}

.customer-dashboard .plate-number {
    color: #8a9bb3;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .4px;
}

.customer-dashboard .complaint-text {
    max-width: 250px;
    color: #607590;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

/* STATUS */

.customer-dashboard .status-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}

.customer-dashboard .status-chip::before {
    content: "";
    width: 7px;
    height: 7px;
    flex-shrink: 0;
    border-radius: 50%;
    background: currentColor;
}

.customer-dashboard .status-antre {
    color: #a66a08;
    background: #fff3d6;
}

.customer-dashboard .status-pengerjaan {
    color: #1769cb;
    background: #e8f2ff;
}

.customer-dashboard .status-selesai {
    color: #08764d;
    background: #e4f9ef;
}

.customer-dashboard .status-default {
    color: #64748b;
    background: #f1f5f9;
}

.customer-dashboard .estimate {
    color: #526b8e;
    font-size: 12px;
    line-height: 1.7;
    white-space: nowrap;
}

/* DATA KOSONG */

.customer-dashboard .empty-state {
    padding: 65px 20px;
    text-align: center;
}

.customer-dashboard .empty-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 23px;
    background: linear-gradient(135deg, #eaf3ff, #dbeafe);
    font-size: 34px;
    animation: skFloat 4s ease-in-out infinite;
}

.customer-dashboard .empty-state h3 {
    margin: 0 0 12px;
    color: #243b5a;
    font-size: 18px;
    font-weight: 800;
}

.customer-dashboard .empty-state p {
    max-width: 460px;
    margin: 0 auto;
    color: #8291a9;
    font-size: 13px;
    line-height: 1.9;
}

.customer-dashboard .history-footer {
    padding: 17px 25px;
    border-top: 1px solid #edf2fa;
    background: #fbfdff;
    color: #8a9bb3;
    font-size: 11px;
}

.customer-dashboard .dashboard-notice {
    margin: 20px;
    padding: 15px 18px;
    border-radius: 12px;
    background: #fff7ed;
    color: #9a3412;
    border: 1px solid #fed7aa;
    font-size: 13px;
    line-height: 1.7;
}

/* ANIMASI */

@keyframes skFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes skSlideUp {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes skRowIn {
    from {
        opacity: 0;
        transform: translateX(-8px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

@keyframes skFloat {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-7px);
    }
}

@keyframes skGradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* RESPONSIF */

@media (max-width: 1200px) {
    .customer-dashboard .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .customer-dashboard .welcome-panel {
        padding: 25px;
        border-radius: 19px;
    }

    .customer-dashboard .welcome-content {
        flex-direction: column;
        align-items: stretch;
        gap: 23px;
    }

    .customer-dashboard .welcome-actions {
        justify-content: flex-start;
    }

    .customer-dashboard .welcome-title {
        font-size: 27px;
    }

    .customer-dashboard .profile-icon {
        width: 90px;
        height: 90px;
        min-width: 90px;
        font-size: 36px;
    }

    .customer-dashboard .history-heading {
        padding: 21px;
    }

    .customer-dashboard .stat-card {
        padding: 19px;
    }

    .customer-dashboard .stat-number {
        font-size: 30px;
    }
}

@media (max-width: 480px) {
    .customer-dashboard .welcome-panel {
        padding: 21px;
    }

    .customer-dashboard .welcome-title {
        font-size: 24px;
    }

    .customer-dashboard .welcome-description {
        font-size: 12px;
    }

    .customer-dashboard .welcome-actions {
        gap: 10px;
    }

    .customer-dashboard .action-btn {
        padding: 11px 13px;
        font-size: 11px;
    }

    .customer-dashboard .customer-info {
        align-items: stretch;
        flex-direction: column;
    }

    .customer-dashboard .info-pill {
        width: 100%;
    }

    .customer-dashboard .stats-grid {
        gap: 11px;
    }

    .customer-dashboard .stat-card {
        padding: 15px;
        border-radius: 15px;
    }

    .customer-dashboard .stat-icon {
        width: 42px;
        height: 42px;
        font-size: 19px;
    }

    .customer-dashboard .stat-number {
        font-size: 27px;
    }

    .customer-dashboard .stat-label {
        font-size: 11px;
    }

    .customer-dashboard .heading-title {
        font-size: 17px;
    }

    .customer-dashboard .history-heading {
        padding: 18px;
    }
}

@media (max-width: 360px) {
    .customer-dashboard .welcome-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .customer-dashboard .profile-container {
        align-self: flex-start;
    }

    .customer-dashboard .action-btn {
        width: 100%;
    }

    .customer-dashboard .stat-card {
        padding: 12px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .customer-dashboard *,
    .customer-dashboard *::before,
    .customer-dashboard *::after {
        animation: none !important;
        transition: none !important;
    }
}
</style>

<div class="customer-dashboard">

    <!-- SAMBUTAN -->
    <section class="welcome-panel">

        <div class="welcome-content">

            <div class="welcome-text">

                <div class="welcome-label">
                    SERVISKU • CUSTOMER AREA
                </div>

                <h1 class="welcome-title">
                    Halo, <?= pe($u['nama']) ?> 👋
                </h1>

                <p class="welcome-description">
                    Selamat datang di dashboard pelanggan.
                    Pantau riwayat dan perkembangan servis motor kamu
                    dengan mudah melalui halaman ini.
                </p>

            </div>

            <div class="welcome-actions">

                <div class="profile-container">

                    <?php if ($fotoUrl !== ''): ?>

                        <img
                            src="<?= pe($fotoUrl) ?>"
                            alt="Foto profil <?= pe($u['nama']) ?>"
                            class="profile-icon"
                        >

                    <?php else: ?>

                        <div class="profile-icon profile-placeholder">
                            👤
                        </div>

                    <?php endif; ?>

                </div>

                <a class="action-btn" href="profil_pelanggan.php">
                    ⚙ Profil & Password
                </a>

                <a class="action-btn" href="logout_pelanggan.php">
                    Keluar ↗
                </a>

            </div>

        </div>

        <div class="customer-info">

            <div class="info-pill">
                <span>☎</span>
                <?= pe($u['telepon'] ?: '-') ?>
            </div>

            <div class="info-pill">
                <span>✉</span>
                <?= pe($u['email'] ?: '-') ?>
            </div>

        </div>

    </section>

    <!-- STATISTIK -->
    <section class="stats-grid">

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">📋</div>
                <span class="stat-indicator"></span>
            </div>

            <strong class="stat-number" data-count="<?= $totalServis ?>">
                <?= $totalServis ?>
            </strong>

            <p class="stat-label">Total Servis Saya</p>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">🕒</div>
                <span class="stat-indicator" style="background:#f59e0b"></span>
            </div>

            <strong class="stat-number" data-count="<?= $totalAntre ?>">
                <?= $totalAntre ?>
            </strong>

            <p class="stat-label">Menunggu Antrean</p>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">🔧</div>
                <span class="stat-indicator" style="background:#8b5cf6"></span>
            </div>

            <strong class="stat-number" data-count="<?= $totalPengerjaan ?>">
                <?= $totalPengerjaan ?>
            </strong>

            <p class="stat-label">Sedang Dikerjakan</p>
        </div>

        <div class="stat-card">
            <div class="stat-top">
                <div class="stat-icon">✓</div>
                <span class="stat-indicator" style="background:#10b981"></span>
            </div>

            <strong class="stat-number" data-count="<?= $totalSelesai ?>">
                <?= $totalSelesai ?>
            </strong>

            <p class="stat-label">Servis Selesai</p>
        </div>

    </section>

    <!-- RIWAYAT SERVIS -->
    <section class="history-panel">

        <div class="history-heading">

            <div>
                <h2 class="heading-title">
                    Riwayat & Status Servis
                </h2>

                <p class="heading-description">
                    Informasi servis motor yang terhubung ke akun kamu.
                </p>
            </div>

            <div class="total-badge">
                📁 <?= $totalServis ?> Data Servis
            </div>

        </div>

        <?php if ($pesanServis !== ''): ?>

            <div class="dashboard-notice">
                <?= pe($pesanServis) ?>
            </div>

        <?php endif; ?>

        <?php if (!$rows): ?>

            <div class="empty-state">

                <div class="empty-icon">🏍️</div>

                <h3>Belum Ada Riwayat Servis</h3>

                <p>
                    Belum ada servis yang terhubung ke akun ini.
                    Silakan hubungi petugas agar data servis
                    dihubungkan ke akun pelanggan kamu.
                </p>

            </div>

        <?php else: ?>

            <div class="table-scroll">

                <table>

                    <thead>
                        <tr>
                            <th>Nomor Servis</th>
                            <th>Motor</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th>Estimasi Selesai</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($rows as $index => $r): ?>

                            <tr style="animation-delay: <?= min($index * 0.045, 0.5) ?>s">

                                <td>
                                    <span class="service-number">
                                        <?= pe($r['nomor_servis'] ?? '-') ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="motor-name">
                                        <?= pe($r['jenis_motor'] ?? '-') ?>
                                    </div>

                                    <div class="plate-number">
                                        <?= pe($r['nomor_polisi'] ?? '-') ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="complaint-text">
                                        <?= pe($r['keluhan'] ?? '-') ?>
                                    </div>
                                </td>

                                <td>
                                    <span class="status-chip <?= statusPelangganClass($r['status'] ?? '') ?>">
                                        <?= pe($r['status'] ?? '-') ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="estimate">

                                        <?php if (($r['status'] ?? '') === 'Selesai'): ?>

                                            <?= !empty($r['selesai_pada'])
                                                ? pe(dashboardTanggal($r['selesai_pada']))
                                                : 'Sudah selesai' ?>

                                        <?php else: ?>

                                            <?= !empty($r['estimasi_selesai'])
                                                ? pe(dashboardTanggal($r['estimasi_selesai']))
                                                : 'Belum ditentukan' ?>

                                        <?php endif; ?>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <div class="history-footer">
                Menampilkan <?= $totalServis ?> riwayat servis milik akun kamu.
            </div>

        <?php endif; ?>

    </section>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = document.querySelectorAll(
        '.customer-dashboard .stat-number[data-count]'
    );

    counters.forEach(function (counter) {
        const target = parseInt(counter.dataset.count, 10) || 0;
        const duration = 850;
        const startTime = performance.now();

        function animateCount(currentTime) {
            const progress = Math.min(
                (currentTime - startTime) / duration,
                1
            );

            const eased = 1 - Math.pow(1 - progress, 4);

            counter.textContent = Math.floor(target * eased);

            if (progress < 1) {
                requestAnimationFrame(animateCount);
            } else {
                counter.textContent = target;
            }
        }

        requestAnimationFrame(animateCount);
    });
});
</script>

<?php
pend();
?>