<?php

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config/koneksi.php';





/* ===== CEK LOGIN ===== */

if (

    !isset($_SESSION['user_id']) &&

    !isset($_SESSION['id_user']) &&

    !isset($_SESSION['username'])

) {

    header('Location: login.php');

    exit;

}



/* ===== DATA USER ===== */

$namaUser = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Administrator';

$roleUser = $_SESSION['role'] ?? 'Admin';
$fotoUser = $_SESSION['foto_profil'] ?? '';
$userId = (int) ($_SESSION['user_id'] ?? $_SESSION['id_user'] ?? 0);
if ($userId > 0) {
    try {
        $stmtUser = $pdo->prepare('SELECT nama, role, foto_profil FROM users WHERE id = ? LIMIT 1');
        $stmtUser->execute([$userId]);
        $dataUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($dataUser) {
            $namaUser = $dataUser['nama'] ?: $namaUser;
            $roleUser = $dataUser['role'] ?: $roleUser;
            $fotoUser = $dataUser['foto_profil'] ?: '';
            $_SESSION['nama'] = $namaUser;
            $_SESSION['foto_profil'] = $fotoUser;
        }
    } catch (PDOException $e) {
        // Gunakan data sesi bila database user belum dapat dibaca.
    }
}
$fotoUser = str_replace('\\', '/', (string) $fotoUser);
$fotoValid = preg_match('~^uploads/profil/[a-zA-Z0-9._-]+$~', $fotoUser)
    && is_file(__DIR__ . '/' . $fotoUser);
$fotoUrl = $fotoValid ? $fotoUser . '?v=' . filemtime(__DIR__ . '/' . $fotoUser) : '';




/* ===== STATISTIK SERVIS ===== */

$totalServis = (int) $pdo->query(

    "SELECT COUNT(*) FROM servis"

)->fetchColumn();



$totalAntre = (int) $pdo->query(

    "SELECT COUNT(*) FROM servis WHERE status = 'Antre'"

)->fetchColumn();



$totalPengerjaan = (int) $pdo->query(

    "SELECT COUNT(*) FROM servis WHERE status = 'Pengerjaan'"

)->fetchColumn();



$totalSelesai = (int) $pdo->query(

    "SELECT COUNT(*) FROM servis WHERE status = 'Selesai'"

)->fetchColumn();



/* ===== SERVIS TERBARU ===== */

$stmt = $pdo->query("

    SELECT id, nomor_servis, nama_pelanggan, jenis_motor,

           keluhan, status, estimasi_selesai

    FROM servis

    ORDER BY created_at DESC

    LIMIT 8

");

$servisTerbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);



/* ===== FORMAT STATUS ===== */

function kelasStatus($status)

{

    return match ($status) {

        'Antre' => 'antre',

        'Pengerjaan' => 'pengerjaan',

        'Selesai' => 'selesai',

        default => 'antre'

    };

}



function e($value)

{

    return htmlspecialchars((string)($value ?? '-'), ENT_QUOTES, 'UTF-8');

}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard | ServisKu</title>



<link rel="preconnect" href="https://fonts.googleapis.com">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



<style>

:root {

    --navy: #142238;

    --navy-light: #1c304d;

    --blue: #0875f9;

    --text: #142b50;

    --muted: #7186a7;

    --border: #e0eafb;

    --bg: #f4f7fc;

    --white: #ffffff;

}



* {

    margin: 0;

    padding: 0;

    box-sizing: border-box;

}



body {

    font-family: 'Inter', sans-serif;

    color: var(--text);

    background: var(--bg);

    overflow-x: hidden;

}



a {

    color: inherit;

    text-decoration: none;

}



button,

input {

    font-family: inherit;

}





.brand {

    display: flex;

    align-items: center;

    gap: 13px;

    padding: 0 13px 35px;

}



.brand-icon {

    font-size: 36px;

    color: #ffffff;

}



.brand h2 {

    font-size: 27px;

    letter-spacing: -1.2px;

    font-weight: 800;

}



.brand p {

    font-size: 13px;

    color: #b9c9e0;

    margin-top: 4px;

}



.nav-menu {

    display: flex;

    flex-direction: column;

    gap: 8px;

}



.nav-menu a,

.logout {

    display: flex;

    align-items: center;

    gap: 17px;

    padding: 15px 16px;

    border-radius: 10px;

    color: #c8d5e8;

    font-size: 14px;

    font-weight: 500;

    transition: background .25s, color .25s, transform .25s;

}



.nav-menu a i,

.logout i {

    width: 22px;

    text-align: center;

    font-size: 19px;

}



.nav-menu a:hover {

    color: white;

    background: #ffffff12;

    transform: translateX(3px);

}



.nav-menu a.active {

    color: white;

    background: linear-gradient(110deg, #147df9, #075de3);

    box-shadow: 0 7px 20px #006aff35;

}



.nav-divider {

    height: 1px;

    margin: 12px 8px;

    background: #35445a;

}



.logout {

    margin-top: auto;

}



.logout:hover {

    background: #ffffff12;

    color: #ffffff;

}



/* ===== TOPBAR ===== */

.main {

    min-height: 100vh;

    margin-left: 260px;

}



.topbar {

    height: 78px;

    position: sticky;

    top: 0;

    z-index: 100;

    display: flex;

    align-items: center;

    gap: 23px;

    padding: 0 26px;

    background: #ffffffed;

    backdrop-filter: blur(16px);

    border-bottom: 1px solid var(--border);

    box-shadow: 0 4px 18px #163a6808;

}



.menu-toggle {

    border: none;

    background: none;

    color: #6680a5;

    font-size: 22px;

    cursor: pointer;

    transition: transform .2s;

}



.menu-toggle:hover {

    transform: scale(1.1);

}



.search {

    width: min(430px, 45%);

    display: flex;

    align-items: center;

    gap: 12px;

    padding: 12px 15px;

    border-radius: 11px;

    background: #f1f5fb;

    color: #7b91b1;

    border: 1px solid transparent;

    transition: border-color .2s, box-shadow .2s;

}



.search:focus-within {

    border-color: #a6cbff;

    box-shadow: 0 0 0 4px #0875f910;

}



.search input {

    width: 100%;

    min-width: 0;

    border: none;

    outline: none;

    background: transparent;

    font-size: 12px;

    color: var(--text);

}



.search input::placeholder {

    color: #8297b6;

}



.top-spacer {

    flex: 1;

}



.top-icon {

    position: relative;

    font-size: 21px;

    color: #6b84a7;

}



.profile {

    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    flex-shrink: 0;

}



.avatar {

    /* Foto selalu bulat dan tidak meregang pada layar apa pun. */
    width: 43px;
    height: 43px;
    min-width: 43px;
    min-height: 43px;
    flex: 0 0 43px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border-radius: 50%;
    aspect-ratio: 1 / 1;
    color: #315c94;
    background: linear-gradient(135deg, #dceaff, #eef5ff);
    font-size: 20px;

}

.avatar img {
    display: block;
    width: 100%;
    height: 100%;
    max-width: 100%;
    max-height: 100%;
    object-fit: cover;
    object-position: center;
    border-radius: inherit;
}



.profile strong {

    display: block;

    font-size: 13px;

}



.profile small {

    display: block;

    margin-top: 5px;

    color: #8498b7;

    font-size: 11px;

}



.profile > i {

    margin-left: 12px;

    font-size: 11px;

    color: #7d91af;

}



/* ===== CONTENT ===== */

.content {

    min-height: calc(100vh - 78px);

    padding: 20px 22px 30px;

    background:

        radial-gradient(ellipse at 100% 0%, #e4efff 0, transparent 35%),

        linear-gradient(145deg, #f7faff, #f0f5fc);

}



.welcome {

    position: relative;

    overflow: hidden;

    min-height: 125px;

    display: flex;

    align-items: center;

    gap: 20px;

    padding: 24px;

    margin-bottom: 20px;

    border: 1px solid #d9e7fc;

    border-radius: 15px;

    background:

        radial-gradient(circle at 95% 15%, #cfe4ff 0, transparent 32%),

        linear-gradient(115deg, #e3efff, #ffffff 66%, #e5f1ff);

    animation: fadeUp .6s ease both;

}



.welcome::after {

    content: '';

    position: absolute;

    width: 220px;

    height: 220px;

    right: 18%;

    top: -155px;

    border: 1px solid #b5d2f755;

    border-radius: 50%;

    box-shadow: 0 0 0 25px #b5d2f718, 0 0 0 50px #b5d2f710;

}



.welcome-icon {

    position: relative;

    z-index: 1;

    flex-shrink: 0;

    width: 49px;

    height: 49px;

    display: grid;

    place-items: center;

    color: white;

    background: linear-gradient(135deg, #3199ff, #0765eb);

    border-radius: 50%;

    box-shadow: 0 7px 20px #0875f92c;

    font-size: 22px;

}



.welcome-text {

    position: relative;

    z-index: 1;

}



.welcome h1 {

    font-size: clamp(19px, 2vw, 26px);

    letter-spacing: -.8px;

    margin-bottom: 8px;

}



.welcome p {

    max-width: 550px;

    color: #5b7cae;

    font-size: 13px;

    line-height: 1.7;

}



.welcome-date {

    position: relative;

    z-index: 1;

    min-width: 210px;

    margin-left: auto;

    padding: 12px 15px;

    border: 1px solid #d6e6ff;

    border-radius: 11px;

    background: #ffffffa8;

    color: #5b7cae;

    font-size: 12px;

    line-height: 1.8;

}



.welcome-date i {

    margin-right: 7px;

}



.welcome-date strong {

    margin-left: 22px;

    color: #2b4d7e;

    font-size: 14px;

}



/* ===== STAT CARDS ===== */

.stats {

    display: grid;

    grid-template-columns: repeat(4, minmax(0, 1fr));

    gap: 16px;

    margin-bottom: 21px;

}



.stat-card {

    position: relative;

    overflow: hidden;

    display: flex;

    align-items: flex-start;

    gap: 14px;

    min-width: 0;

    min-height: 145px;

    padding: 18px 16px;

    border: 1px solid var(--border);

    border-radius: 14px;

    background: white;

    box-shadow: 0 5px 18px #183c6b05;

    animation: fadeUp .65s ease both;

    transition: transform .3s, box-shadow .3s;

}



.stat-card::after {

    content: '';

    position: absolute;

    right: -30px;

    bottom: -50px;

    width: 110px;

    height: 110px;

    border-radius: 50%;

    background: currentColor;

    opacity: .035;

    transition: transform .35s;

}



.stat-card:hover {

    transform: translateY(-5px);

    box-shadow: 0 14px 30px #183c6b12;

}



.stat-card:hover::after {

    transform: scale(1.5);

}



.stat-card:nth-child(1) { animation-delay: .06s; }

.stat-card:nth-child(2) { animation-delay: .12s; }

.stat-card:nth-child(3) { animation-delay: .18s; }

.stat-card:nth-child(4) { animation-delay: .24s; }



.stat-card.blue-card {

    color: #1386fa;

    border-color: #c6e2ff;

    background: linear-gradient(135deg, #ffffff, #f1f8ff);

}



.stat-card.yellow-card {

    color: #f5a500;

    border-color: #ffe7ac;

    background: linear-gradient(135deg, #ffffff, #fffcf2);

}



.stat-card.purple-card {

    color: #9558f2;

    border-color: #e5d4ff;

    background: linear-gradient(135deg, #ffffff, #fbf7ff);

}



.stat-card.green-card {

    color: #1ab579;

    border-color: #bcefdc;

    background: linear-gradient(135deg, #ffffff, #f3fff9);

}



.stat-icon {

    position: relative;

    z-index: 1;

    flex-shrink: 0;

    width: 59px;

    height: 59px;

    display: grid;

    place-items: center;

    color: white;

    border-radius: 50%;

    font-size: 24px;

    box-shadow: inset 0 -4px 9px #00000010, 0 5px 12px #00000009;

    transition: transform .3s;

}



.stat-card:hover .stat-icon {

    transform: rotate(-7deg) scale(1.07);

}



.blue { background: linear-gradient(135deg, #35aaff, #0872ed); }

.yellow { background: linear-gradient(135deg, #ffd044, #ffa500); }

.purple { background: linear-gradient(135deg, #b879ff, #8742e9); }

.green { background: linear-gradient(135deg, #43dca5, #12a66b); }



.stat-info {

    position: relative;

    z-index: 1;

    min-width: 0;

    padding-top: 4px;

    color: var(--text);

}



.stat-info h3 {

    font-size: 13px;

    font-weight: 700;

    line-height: 1.6;

}



.stat-info strong {

    display: block;

    margin: 3px 0;

    font-size: 37px;

    font-weight: 800;

    letter-spacing: -1.5px;

    line-height: 1.3;

}



.stat-info p {

    color: var(--muted);

    font-size: 11px;

    line-height: 1.6;

}



/* ===== GRID & PANEL ===== */

.dashboard-grid {

    display: grid;

    grid-template-columns: minmax(0, 2.05fr) minmax(285px, .95fr);

    align-items: start;

    gap: 17px;

}



.panel {

    min-width: 0;

    padding: 17px;

    border: 1px solid var(--border);

    border-radius: 14px;

    background: #ffffff;

    box-shadow: 0 5px 18px #183c6b05;

    animation: fadeUp .7s ease both;

    transition: box-shadow .25s;

}



.panel:hover {

    box-shadow: 0 10px 30px #183c6b0c;

}



.panel-heading {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-bottom: 18px;

}



.panel-heading h2 {

    display: flex;

    align-items: center;

    gap: 11px;

    font-size: 15px;

    font-weight: 750;

}



.panel-heading h2 i {

    font-size: 20px;

}



.btn-outline {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 9px 12px;

    border: 1px solid #d6e5fa;

    border-radius: 8px;

    color: #0875f9;

    background: #ffffff;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;

    transition: *background* .2s, transform .2s;

}



.btn-outline:hover {

    background: #eef6ff;

    transform: translateY(-2px);

}



/* ===== TABLE ===== */

.table-wrap {

    width: 100%;

    overflow-x: auto;

    border: 1px solid #e5edf8;

    border-radius: 10px;

}



table {

    width: 100%;

    min-width: 800px;

    border-collapse: collapse;

    font-size: 10px;

}



thead {

    background: linear-gradient(100deg, #f1f6fd, #f7faff);

}



th {

    padding: 17px 10px;

    text-align: left;

    white-space: nowrap;

    color: #263d62;

    font-weight: 750;

}



td {

    padding: 13px 10px;

    border-top: 1px solid #e9f0fa;

    color: #49648c;

    line-height: 1.6;

    vertical-align: middle;

}



tbody tr {

    transition: *background* .2s;

}



tbody tr:hover {

    background: #f5f9ff;

}



.status {

    display: inline-block;

    min-width: 67px;

    padding: 6px 10px;

    border-radius: 30px;

    text-align: center;

    font-size: 10px;

    font-weight: 750;

    white-space: nowrap;

}



.status-antre {

    color: #bd7800;

    background: #fff0c2;

}



.status-pengerjaan {

    color: #7541db;

    background: #eee3ff;

}



.status-selesai {

    color: #078655;

    background: #d5f7e8;

}



.btn-detail {

    display: inline-block;

    padding: 8px 11px;

    color: white;

    background: linear-gradient(110deg, #218bff, #0863ed);

    border-radius: 6px;

    font-size: 10px;

    font-weight: 700;

    text-align: center;

    transition: transform .2s, box-shadow .2s;

}



.btn-detail:hover {

    transform: translateY(-2px);

    box-shadow: 0 5px 12px #0875f933;

}



.empty {

    padding: 30px;

    color: #8296b4;

    text-align: center;

}



/* ===== RIGHT COLUMN ===== */

.right-column {

    display: flex;

    flex-direction: column;

    gap: 16px;

}



.chart-container {

    position: relative;

    height: 225px;

    width: 100%;

}



.chart-center {

    position: absolute;

    inset: 0;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    pointer-events: none;

}



.chart-center strong {

    color: #172e52;

    font-size: 30px;

    font-weight: 800;

}



.chart-center span {

    margin-top: 4px;

    color: #8194b0;

    font-size: 11px;

}



.chart-legend {

    display: grid;

    grid-template-columns: repeat(3, minmax(0, 1fr));

    gap: 8px;

    margin-top: 13px;

}



.legend-item {

    padding: 10px 7px;

    border: 1px solid #eaf0f9;

    border-radius: 9px;

    text-align: center;

    background: #fbfdff;

}



.legend-item span {

    display: block;

    color: #6e84a4;

    font-size: 10px;

    white-space: nowrap;

}



.legend-item strong {

    display: block;

    margin-top: 6px;

    color: #1b3153;

    font-size: 18px;

}



.legend-dot {

    display: inline-block !important;

    width: 7px;

    height: 7px;

    margin-right: 4px;

    border-radius: 50%;

}



.dot-antre { background: #ffb51b; }

.dot-pengerjaan { background: #9454f4; }

.dot-selesai { background: #19b77a; }



.status-item {

    display: flex;

    align-items: center;

    gap: 13px;

    margin-top: 9px;

    padding: 12px 10px;

    border: 1px solid #e4edf9;

    border-radius: 10px;

    transition: transform .2s, border-color .2s, *background* .2s;

}



.status-item:hover {

    transform: translateX(3px);

    border-color: #c7dcfa;

    background: #fbfdff;

}



.status-item .stat-icon {

    width: 52px;

    height: 52px;

    font-size: 22px;

}



.status-item h3 {

    margin-bottom: 5px;

    color: #21395e;

    font-size: 12px;

}



.status-item p {

    color: #7488a7;

    font-size: 10px;

    line-height: 1.6;

}



/* ===== QUICK MENU ===== */

.quick-grid {

    display: grid;

    grid-template-columns: repeat(2, minmax(0, 1fr));

    gap: 12px;

}



.quick-link {

    display: flex;

    align-items: flex-start;

    gap: 10px;

    min-height: 83px;

    padding: 14px 11px;

    color: white;

    border-radius: 10px;

    transition: transform .25s, box-shadow .25s, filter .25s;

}



.quick-link:hover {

    transform: translateY(-4px);

    box-shadow: 0 9px 20px #172d4c20;

    filter: brightness(1.05);

}



.quick-link > i {

    flex-shrink: 0;

    margin-top: 1px;

    font-size: 22px;

}



.quick-link strong {

    display: block;

    margin-bottom: 6px;

    font-size: 11px;

    line-height: 1.5;

}



.quick-link small {

    display: block;

    font-size: 9px;

    line-height: 1.5;

    opacity: .92;

}



.q-blue { background: linear-gradient(125deg, #2ba3ff, #2580f6); }

.q-purple { background: linear-gradient(125deg, #a27bff, #815df2); }

.q-teal { background: linear-gradient(125deg, #0dc9b8, #00a99a); }

.q-orange { background: linear-gradient(125deg, #ffab2e, #ff8709); }



/* ===== ANIMASI ===== */

@keyframes fadeUp {

    from {

        opacity: 0;

        transform: translateY(20px);

    }

    to {

        opacity: 1;

        transform: translateY(0);

    }

}



@keyframes fadeIn {

    from { opacity: 0; }

    to { opacity: 1; }

}



@keyframes pulse {

    0%, 100% { box-shadow: 0 0 0 0 #0875f930; }

    50% { box-shadow: 0 0 0 8px #0875f900; }

}



.welcome-icon {

    animation: pulse 2.8s infinite;

}



/* ===== RESPONSIVE ===== */

@media (max-width: 1350px) {

    .sidebar {

        width: 235px;

    }



    .main {

        margin-left: 235px;

    }



    .stats {

        grid-template-columns: repeat(2, minmax(0, 1fr));

    }



    .dashboard-grid {

        grid-template-columns: 1fr;

    }



    .right-column {

        display: grid;

        grid-template-columns: repeat(2, minmax(0, 1fr));

        align-items: start;

    }

}



@media (max-width: 850px) {

    .sidebar {

        width: 260px;

        transform: translateX(-100%);

    }



    .sidebar.open {

        transform: translateX(0);

        box-shadow: 10px 0 35px #0003;

    }



    .main {

        margin-left: 0;

    }



    .topbar {

        padding: 0 16px;

        gap: 15px;

    }



    .content {

        padding: 15px;

    }



    .welcome {

        flex-wrap: wrap;

    }



    .welcome-date {

        margin-left: 69px;

    }



    .right-column {

        grid-template-columns: 1fr;

    }

}



@media (max-width: 520px) {

    .topbar {

        height: 68px;

        gap: 12px;

    }



    .search {

        flex: 1;

        width: auto;

        padding: 11px;

    }



    .search input {

        font-size: 11px;

    }



    .top-icon,

    .profile strong,

    .profile small,

    .profile > i {

        display: none;

    }



    .profile {

        gap: 0;

    }



    .avatar {
        width: 37px;
        height: 37px;
        min-width: 37px;
        min-height: 37px;
        flex-basis: 37px;
    }



    .content {

        padding: 12px;

    }



    .welcome {

        gap: 12px;

        padding: 17px;

    }



    .welcome h1 {

        font-size: 19px;

    }



    .welcome p {

        font-size: 11px;

    }



    .welcome-date {

        width: 100%;

        margin-left: 0;

    }



    .stats {

        gap: 10px;

    }



    .stat-card {

        gap: 10px;

        min-height: 112px;

        padding: 13px 10px;

    }



    .stat-icon {

        width: 43px;

        height: 43px;

        font-size: 18px;

    }



    .stat-info {

        padding-top: 2px;

    }



    .stat-info h3 {

        font-size: 11px;

    }



    .stat-info strong {

        font-size: 29px;

    }



    .stat-info p {

        font-size: 9px;

    }



    .panel {

        padding: 12px;

    }



    .panel-heading h2 {

        font-size: 13px;

    }



    .btn-outline {

        padding: 8px;

        font-size: 10px;

    }

}



@media (max-width: 350px) {

    .stats {

        grid-template-columns: 1fr;

    }



    .quick-grid {

        grid-template-columns: 1fr;

    }

}



@media (prefers-reduced-motion: reduce) {

    *,

    *::before,

    *::after {

        animation-duration: .01ms !important;

        animation-iteration-count: 1 !important;

        transition-duration: .01ms !important;

        scroll-behavior: auto !important;

    }

}

</style>

</head>



<body>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>



<!-- ===== SIDEBAR ===== -->

<!-- ===== MAIN ===== -->

<main class="main">



    <header class="topbar">

        <button type="button" class="menu-toggle" id="menuToggle"

                aria-label="Buka menu">

            <i class="fa-solid fa-bars"></i>

        </button>



        <div class="search">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input type="search" id="searchInput"

                   placeholder="Cari nomor servis, nama pelanggan...">

        </div>



        <div class="top-spacer"></div>



        <div class="top-icon">

            <i class="fa-regular fa-bell"></i>

        </div>



        <div class="profile">

            <div class="avatar">
                <?php if ($fotoValid): ?>
                    <img src="<?= e($fotoUrl) ?>" alt="Foto profil <?= e($namaUser) ?>" width="43" height="43" loading="eager" decoding="async">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>

            <div>

                <strong><?= e($namaUser) ?></strong>

                <small><?= e($roleUser) ?></small>

            </div>

            <i class="fa-solid fa-chevron-down"></i>

        </div>

    </header>



    <section class="content">



        <!-- ===== WELCOME ===== -->

        <div class="welcome">

            <div class="welcome-icon">

                <i class="fa-solid fa-gear"></i>

            </div>



            <div class="welcome-text">

                <h1>Selamat datang, <?= e($namaUser) ?>!</h1>

                <p>Kelola antrean servis dan pantau progres perbaikan sepeda motor pelanggan.</p>

            </div>



            <div class="welcome-date">

                <i class="fa-regular fa-calendar"></i>

                <span id="tanggal"></span><br>

                <strong id="jam"></strong>

            </div>

        </div>



        <!-- ===== STATISTIK ===== -->

        <div class="stats">



            <div class="stat-card blue-card">

                <div class="stat-icon blue">

                    <i class="fa-solid fa-clipboard-list"></i>

                </div>

                <div class="stat-info">

                    <h3>Total Servis</h3>

                    <strong class="count-up" data-target="<?= $totalServis ?>">0</strong>

                    <p>Semua data servis</p>

                </div>

            </div>



            <div class="stat-card yellow-card">

                <div class="stat-icon yellow">

                    <i class="fa-regular fa-clock"></i>

                </div>

                <div class="stat-info">

                    <h3>Menunggu Antrean</h3>

                    <strong class="count-up" data-target="<?= $totalAntre ?>">0</strong>

                    <p>Motor belum dikerjakan</p>

                </div>

            </div>



            <div class="stat-card purple-card">

                <div class="stat-icon purple">

                    <i class="fa-solid fa-wrench"></i>

                </div>

                <div class="stat-info">

                    <h3>Sedang Dikerjakan</h3>

                    <strong class="count-up" data-target="<?= $totalPengerjaan ?>">0</strong>

                    <p>Dalam proses perbaikan</p>

                </div>

            </div>



            <div class="stat-card green-card">

                <div class="stat-icon green">

                    <i class="fa-solid fa-check"></i>

                </div>

                <div class="stat-info">

                    <h3>Servis Selesai</h3>

                    <strong class="count-up" data-target="<?= $totalSelesai ?>">0</strong>

                    <p>Siap diambil pelanggan</p>

                </div>

            </div>



        </div>



        <!-- ===== DASHBOARD GRID ===== -->

        <div class="dashboard-grid">



            <!-- ===== TABEL SERVIS ===== -->

            <section class="panel">

                <div class="panel-heading">

                    <h2>

                        <i class="fa-solid fa-clipboard-list"></i>

                        Daftar Servis Terbaru

                    </h2>



                    <a href="data_servis.php" class="btn-outline">

                        Lihat Semua

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>

                </div>



                <div class="table-wrap">

                    <table id="servisTable">

                        <thead>

                            <tr>

                                <th>No</th>

                                <th>Nomor Servis</th>

                                <th>Nama Pelanggan</th>

                                <th>Jenis Motor</th>

                                <th>Keluhan</th>

                                <th>Status</th>

                                <th>Estimasi Selesai</th>

                                <th>Aksi</th>

                            </tr>

                        </thead>



                        <tbody>

                        <?php if ($servisTerbaru): ?>

                            <?php foreach ($servisTerbaru as $i => $servis): ?>

                                <?php

                                $estimasi = '-';



                                if (!empty($servis['estimasi_selesai'])) {

                                    $timestamp = strtotime($servis['estimasi_selesai']);



                                    $estimasi = $timestamp

                                        ? date('d/m/Y H:i', $timestamp)

                                        : $servis['estimasi_selesai'];

                                }

                                ?>

                                <tr>

                                    <td><?= $i + 1 ?></td>



                                    <td>

                                        <?= e($servis['nomor_servis']) ?>

                                    </td>



                                    <td>

                                        <?= e($servis['nama_pelanggan']) ?>

                                    </td>



                                    <td>

                                        <?= e($servis['jenis_motor']) ?>

                                    </td>



                                    <td>

                                        <?= e($servis['keluhan']) ?>

                                    </td>



                                    <td>

                                        <span class="status status-<?= kelasStatus($servis['status']) ?>">

                                            <?= e($servis['status']) ?>

                                        </span>

                                    </td>



                                    <td>

                                        <?= e($estimasi) ?>

                                    </td>



                                    <td>

                                        <a class="btn-detail"

                                           href="detail_servis.php?id=<?= (int)$servis['id'] ?>">

                                            Detail

                                        </a>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="8" class="empty">

                                    <i class="fa-regular fa-folder-open"></i>

                                    <br><br>

                                    Belum ada data servis.

                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>



            <!-- ===== KOLOM KANAN ===== -->

            <div class="right-column">



                <!-- ===== GRAFIK STATUS ===== -->

                <section class="panel">

                    <div class="panel-heading">

                        <h2>

                            <i class="fa-solid fa-chart-pie"></i>

                            Grafik Status Servis

                        </h2>

                    </div>



                    <div class="chart-container">

                        <canvas id="grafikStatus"></canvas>



                        <div class="chart-center">

                            <strong><?= $totalServis ?></strong>

                            <span>Total Servis</span>

                        </div>

                    </div>



                    <div class="chart-legend">

                        <div class="legend-item">

                            <span>

                                <i class="legend-dot dot-antre"></i>Antre

                            </span>

                            <strong><?= $totalAntre ?></strong>

                        </div>



                        <div class="legend-item">

                            <span>

                                <i class="legend-dot dot-pengerjaan"></i>Proses

                            </span>

                            <strong><?= $totalPengerjaan ?></strong>

                        </div>



                        <div class="legend-item">

                            <span>

                                <i class="legend-dot dot-selesai"></i>Selesai

                            </span>

                            <strong><?= $totalSelesai ?></strong>

                        </div>

                    </div>

                </section>



                <!-- ===== KETERANGAN STATUS ===== -->

                <section class="panel">

                    <div class="panel-heading">

                        <h2>

                            <i class="fa-solid fa-list-check"></i>

                            Status Servis

                        </h2>

                    </div>



                    <div class="status-item">

                        <div class="stat-icon yellow">

                            <i class="fa-regular fa-clock"></i>

                        </div>

                        <div>

                            <h3>Antre</h3>

                            <p>Menunggu giliran untuk dikerjakan</p>

                        </div>

                    </div>



                    <div class="status-item">

                        <div class="stat-icon purple">

                            <i class="fa-solid fa-wrench"></i>

                        </div>

                        <div>

                            <h3>Pengerjaan</h3>

                            <p>Sedang dalam proses perbaikan</p>

                        </div>

                    </div>



                    <div class="status-item">

                        <div class="stat-icon green">

                            <i class="fa-solid fa-check"></i>

                        </div>

                        <div>

                            <h3>Selesai</h3>

                            <p>Perbaikan selesai, siap diambil</p>

                        </div>

                    </div>

                </section>



                <!-- ===== MENU CEPAT ===== -->

                <section class="panel">

                    <div class="panel-heading">

                        <h2>

                            <i class="fa-solid fa-bolt"></i>

                            Menu Cepat

                        </h2>

                    </div>



                    <div class="quick-grid">



                        <a href="daftar.php" class="quick-link q-blue">

                            <i class="fa-solid fa-clipboard-list"></i>

                            <span>

                                <strong>Daftar Servis</strong>

                                <small>Tambah data servis baru</small>

                            </span>

                        </a>



                        <a href="cek_status.php" class="quick-link q-purple">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <span>

                                <strong>Cek Status</strong>

                                <small>Lacak nomor servis</small>

                            </span>

                        </a>



                        <a href="pelanggan.php" class="quick-link q-teal">

                            <i class="fa-solid fa-users"></i>

                            <span>

                                <strong>Data Pelanggan</strong>

                                <small>Kelola data pelanggan</small>

                            </span>

                        </a>



                        <a href="laporan.php" class="quick-link q-orange">

                            <i class="fa-solid fa-chart-column"></i>

                            <span>

                                <strong>Laporan</strong>

                                <small>Lihat laporan servis</small>

                            </span>

                        </a>



                    </div>

                </section>



            </div>

        </div>

    </section>

</main>



<script>

/* ===== SIDEBAR MOBILE ===== */

const sidebar = document.getElementById('sidebar');



document.getElementById('menuToggle').addEventListener('click', function () {

    sidebar.classList.toggle('open');

});



/* Tutup sidebar jika area di luar sidebar ditekan pada layar kecil */

document.querySelector('.main').addEventListener('click', function (event) {

    if (

        window.innerWidth <= 850 &&

        sidebar.classList.contains('open') &&

        !event.target.closest('#menuToggle')

    ) {

        sidebar.classList.remove('open');

    }

});



/* ===== TANGGAL DAN JAM ===== */

function updateClock() {

    const now = new Date();



    document.getElementById('tanggal').textContent =

        new Intl.DateTimeFormat('id-ID', {

            weekday: 'long',

            day: 'numeric',

            month: 'long',

            year: 'numeric'

        }).format(now);



    document.getElementById('jam').textContent =

        new Intl.DateTimeFormat('id-ID', {

            hour: '2-digit',

            minute: '2-digit',

            second: '2-digit',

            hour12: false

        }).format(now) + ' WIB';

}



updateClock();

setInterval(updateClock, 1000);



/* ===== ANIMASI ANGKA STATISTIK ===== */

document.querySelectorAll('.count-up').forEach(function (element) {

    const target = Number(element.dataset.target) || 0;

    const duration = 1000;

    const startTime = performance.now();



    function animateNumber(now) {

        const progress = Math.min((now - startTime) / duration, 1);

        const eased = 1 - Math.pow(1 - progress, 3);



        element.textContent = Math.round(target * eased);



        if (progress < 1) {

            requestAnimationFrame(animateNumber);

        } else {

            element.textContent = target;

        }

    }



    requestAnimationFrame(animateNumber);

});



/* ===== GRAFIK DONAT STATUS SERVIS ===== */

const canvasGrafik = document.getElementById('grafikStatus');



if (canvasGrafik && typeof Chart !== 'undefined') {

    new Chart(canvasGrafik, {

        type: 'doughnut',



        data: {

            labels: ['Antre', 'Pengerjaan', 'Selesai'],

            datasets: [{

                data: [

                    <?= $totalAntre ?>,

                    <?= $totalPengerjaan ?>,

                    <?= $totalSelesai ?>

                ],



                backgroundColor: [

                    '#ffb51b',

                    '#9454f4',

                    '#19b77a'

                ],



                borderColor: '#ffffff',

                borderWidth: 5,

                borderRadius: 5,

                spacing: 3,

                hoverOffset: 12

            }]

        },



        options: {

            responsive: true,

            maintainAspectRatio: false,

            cutout: '73%',



            animation: {

                animateRotate: true,

                animateScale: true,

                duration: 1400,

                easing: 'easeOutQuart'

            },



            plugins: {

                legend: {

                    display: false

                },



                tooltip: {

                    backgroundColor: '#142238',

                    titleFont: {

                        family: 'Inter',

                        size: 12

                    },

                    bodyFont: {

                        family: 'Inter',

                        size: 11

                    },

                    padding: 12,

                    cornerRadius: 8,



                    callbacks: {

                        label: function (context) {

                            return ' ' + context.label + ': ' +

                                context.raw + ' servis';

                        }

                    }

                }

            }

        }

    });

}



/* ===== PENCARIAN TABEL ===== */

document.getElementById('searchInput').addEventListener('input', function () {

    const keyword = this.value.toLowerCase().trim();



    document.querySelectorAll('#servisTable tbody tr').forEach(function (row) {

        row.style.display = row.textContent.toLowerCase().includes(keyword)

            ? ''

            : 'none';

    });

});

</script>



</body>

</html>