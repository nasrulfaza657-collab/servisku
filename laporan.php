<?php
/**
 * ==========================================================
 * SERVISKU - LAPORAN SERVIS
 * ==========================================================
 * Tema      : Modern Blue Navy
 * Sidebar   : includes/sidebar.php
 * Database  : PDO
 *
 * Tidak menggunakan includes/fitur.php
 * ==========================================================
 */

// ==========================================================
// SESSION
// ==========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================================
// KONEKSI DATABASE
// ==========================================================

// Sesuaikan jika nama file koneksi database berbeda.
$fileKoneksi = [
    __DIR__ . '/includes/koneksi.php',
    __DIR__ . '/koneksi.php',
    __DIR__ . '/config.php',
    __DIR__ . '/config/koneksi.php'
];

$pdo = null;
$koneksiDitemukan = false;
$errorDatabase = '';

foreach ($fileKoneksi as $file) {

    if (is_file($file)) {

        require_once $file;

        if (isset($pdo) && $pdo instanceof PDO) {
            $koneksiDitemukan = true;
            break;
        }
    }
}

if (!$koneksiDitemukan) {
    $errorDatabase = 'Koneksi database PDO tidak ditemukan. Periksa file koneksi ServisKu.';
}

// ==========================================================
// FUNGSI KEAMANAN OUTPUT
// ==========================================================

if (!function_exists('lapEsc')) {

    function lapEsc($value)
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

// ==========================================================
// VALIDASI TANGGAL
// ==========================================================

function lapValidTanggal($tanggal)
{
    if (!is_string($tanggal)) {
        return false;
    }

    $tanggal = trim($tanggal);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $tanggal
    );

    return $date !== false &&
        $date->format('Y-m-d') === $tanggal;
}

// ==========================================================
// PARAMETER FILTER
// ==========================================================

$from = $_GET['dari'] ?? date('Y-m-01');
$to = $_GET['sampai'] ?? date('Y-m-d');

$errorTanggal = '';

if (!lapValidTanggal($from) || !lapValidTanggal($to)) {

    $errorTanggal = 'Format tanggal tidak valid. Gunakan tanggal yang benar.';

} elseif ($from > $to) {

    $errorTanggal = 'Tanggal awal tidak boleh melebihi tanggal akhir.';
}

// ==========================================================
// DATA LAPORAN
// ==========================================================

$rows = [];

if ($koneksiDitemukan && $errorTanggal === '') {

    try {

        $sql = "
            SELECT
                nomor_servis,
                nama_pelanggan,
                telepon,
                nomor_polisi,
                jenis_motor,
                keluhan,
                status,
                estimasi_selesai,
                selesai_pada,
                created_at

            FROM servis

            WHERE created_at >= ?
              AND created_at < ?

            ORDER BY created_at DESC
        ";

        $tanggalSampaiEksklusif = (
            new DateTimeImmutable($to)
        )->modify('+1 day')->format('Y-m-d');

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $from . ' 00:00:00',
            $tanggalSampaiEksklusif . ' 00:00:00'
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $errorDatabase = 'Gagal mengambil laporan. Periksa koneksi dan struktur tabel servis.';
    }
}

// ==========================================================
// DOWNLOAD CSV
// ==========================================================

if (($_GET['unduh'] ?? '') === 'csv') {

    if ($errorTanggal !== '') {
        http_response_code(400);
        exit($errorTanggal);
    }

    if ($errorDatabase !== '') {
        http_response_code(500);
        exit($errorDatabase);
    }

    // Pastikan tidak ada output HTML sebelum header CSV.
    header('Content-Type: text/csv; charset=UTF-8');

    header(
        'Content-Disposition: attachment; filename="laporan-servis-' .
        $from . '-' . $to . '.csv"'
    );

    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');

    // UTF-8 BOM agar karakter terbaca di Excel.
    fwrite($out, "\xEF\xBB\xBF");

    $kolomCsv = [
        'nomor_servis',
        'nama_pelanggan',
        'telepon',
        'nomor_polisi',
        'jenis_motor',
        'keluhan',
        'status',
        'estimasi_selesai',
        'selesai_pada',
        'created_at'
    ];

    fputcsv($out, [
        'Nomor Servis',
        'Nama Pelanggan',
        'Telepon',
        'Nomor Polisi',
        'Jenis Motor',
        'Keluhan',
        'Status',
        'Estimasi Selesai',
        'Selesai Pada',
        'Tanggal Daftar'
    ]);

    foreach ($rows as $row) {

        $dataCsv = [];

        foreach ($kolomCsv as $kolom) {

            $value = (string) ($row[$kolom] ?? '');

            // Cegah formula injection saat CSV dibuka di Excel.
            if (preg_match('/^\s*[=+\-@]/u', $value)) {
                $value = "'" . $value;
            }

            $dataCsv[] = $value;
        }

        fputcsv($out, $dataCsv);
    }

    fclose($out);
    exit;
}

// ==========================================================
// STATISTIK LAPORAN
// ==========================================================

$totalServis = count($rows);

$totalAntre = 0;
$totalProses = 0;
$totalSelesai = 0;

foreach ($rows as $row) {

    $status = strtolower(
        trim((string) ($row['status'] ?? ''))
    );

    if ($status === 'antre') {

        $totalAntre++;

    } elseif (in_array(
        $status,
        ['proses', 'diproses', 'dikerjakan'],
        true
    )) {

        $totalProses++;

    } elseif (in_array(
        $status,
        ['selesai', 'diambil'],
        true
    )) {

        $totalSelesai++;
    }
}

// ==========================================================
// FORMAT TANGGAL
// ==========================================================

function lapTanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $timestamp = strtotime((string) $tanggal);

    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

// ==========================================================
// WARNA STATUS
// ==========================================================

function lapStatusClass($status)
{
    $status = strtolower(
        trim((string) $status)
    );

    switch ($status) {

        case 'antre':
            return 'status-antre';

        case 'proses':
        case 'diproses':
        case 'dikerjakan':
            return 'status-proses';

        case 'selesai':
            return 'status-selesai';

        case 'diambil':
            return 'status-diambil';

        case 'batal':
            return 'status-batal';

        default:
            return 'status-default';
    }
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Servis - ServisKu</title>

    <!-- FONT AWESOME -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <!-- GOOGLE FONT -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        /* ==========================================
           GLOBAL
        ========================================== */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;

            font-family: 'Inter', Arial, sans-serif;

            background: #f4f7fc;
            color: #1e293b;
        }

        /* ==========================================
           MAIN CONTENT
        ========================================== */

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ==========================================
           TOPBAR
        ========================================== */

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            margin-bottom: 25px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar h2 {
            margin: 0;

            color: #102a58;

            font-size: 23px;
            font-weight: 800;
        }

        .topbar p {
            margin: 6px 0 0;

            color: #64748b;

            font-size: 13px;
        }

        .topbar-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 11px 15px;

            background: #ffffff;

            border: 1px solid #e2e8f0;
            border-radius: 11px;

            color: #2563eb;

            font-size: 12px;
            font-weight: 700;
        }

        #sidebarToggle {
            display: none;
            place-items: center;

            width: 42px;
            height: 42px;

            border: 1px solid #e2e8f0;
            border-radius: 11px;

            background: white;
            color: #102a58;

            font-size: 19px;
            cursor: pointer;
        }

        /* ==========================================
           HERO
        ========================================== */

        .hero {
            position: relative;
            overflow: hidden;

            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;

            padding: 35px;
            margin-bottom: 25px;

            background: linear-gradient(
                120deg,
                #0b1f42,
                #174b9d,
                #2563eb
            );

            border-radius: 20px;

            box-shadow: 0 12px 30px rgba(15, 35, 75, .15);
        }

        .hero::after {
            content: "";

            position: absolute;

            width: 300px;
            height: 300px;

            right: -100px;
            top: -120px;

            border: 2px solid rgba(255, 255, 255, .15);
            border-radius: 50%;

            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            padding: 8px 14px;
            margin-bottom: 15px;

            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .2);

            border-radius: 30px;

            color: #dbeafe;

            font-size: 12px;
            font-weight: 700;
        }

        .hero h1 {
            margin: 0 0 12px;

            color: white;

            font-size: 29px;
            font-weight: 800;
        }

        .hero p {
            max-width: 620px;
            margin: 0;

            color: #dbeafe;

            font-size: 14px;
            line-height: 1.8;
        }

        .hero-icon {
            position: relative;
            z-index: 1;

            display: flex;
            align-items: center;
            justify-content: center;

            width: 90px;
            height: 90px;
            flex-shrink: 0;

            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .2);

            border-radius: 22px;

            color: white;
            font-size: 42px;
        }

        /* ==========================================
           STATISTIK
        ========================================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;

            margin-bottom: 25px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;

            padding: 22px;

            background: white;

            border: 1px solid #e2e8f0;
            border-radius: 16px;

            box-shadow: 0 5px 20px rgba(15, 35, 75, .04);
        }

        .stat-icon {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 48px;
            height: 48px;
            flex-shrink: 0;

            border-radius: 13px;
            font-size: 20px;
        }

        .stat-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-orange {
            background: #fff7ed;
            color: #ea580c;
        }

        .stat-purple {
            background: #f5f3ff;
            color: #7c3aed;
        }

        .stat-green {
            background: #ecfdf5;
            color: #059669;
        }

        .stat-info p {
            margin: 0 0 7px;

            color: #64748b;
            font-size: 12px;
        }

        .stat-info h3 {
            margin: 0;

            color: #102a58;

            font-size: 25px;
            font-weight: 800;
        }

        /* ==========================================
           CARD
        ========================================== */

        .card {
            margin-bottom: 25px;

            background: white;

            border: 1px solid #e2e8f0;
            border-radius: 18px;

            overflow: hidden;

            box-shadow: 0 8px 30px rgba(15, 35, 75, .05);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;

            padding: 25px 30px;

            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0 0 6px;

            color: #102a58;

            font-size: 18px;
            font-weight: 800;
        }

        .card-header p {
            margin: 0;

            color: #64748b;

            font-size: 13px;
            line-height: 1.6;
        }

        /* ==========================================
           FILTER FORM
        ========================================== */

        .filter-form {
            display: flex;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 18px;

            padding: 25px 30px;
        }

        .form-group {
            flex: 1;
            min-width: 180px;
        }

        .form-group label {
            display: block;

            margin-bottom: 9px;

            color: #334155;

            font-size: 13px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;

            padding: 13px 15px;

            border: 1px solid #dbe3f0;
            border-radius: 11px;

            background: #f8fafc;

            color: #1e293b;

            font-family: inherit;
            font-size: 14px;

            outline: none;
        }

        .form-group input:focus {
            border-color: #2563eb;

            box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
        }

        .filter-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* ==========================================
           BUTTON
        ========================================== */

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 13px 20px;

            border: 1px solid transparent;
            border-radius: 11px;

            font-family: inherit;
            font-size: 13px;
            font-weight: 700;

            text-decoration: none;

            cursor: pointer;

            transition:
                background .2s,
                transform .2s,
                box-shadow .2s;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(
                120deg,
                #2563eb,
                #1d4ed8
            );

            color: white;
        }

        .btn-success {
            background: #059669;
            color: white;
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-secondary {
            background: #f1f5f9;

            color: #475569;

            border-color: #e2e8f0;
        }

        /* ==========================================
           TABLE
        ========================================== */

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;

            white-space: nowrap;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 17px 20px;

            text-align: left;

            color: #64748b;

            font-size: 11px;
            font-weight: 800;

            text-transform: uppercase;

            letter-spacing: .5px;

            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 18px 20px;

            color: #334155;

            font-size: 13px;

            border-bottom: 1px solid #f1f5f9;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        .nomor-servis {
            color: #2563eb;
            font-weight: 800;
        }

        .nama-pelanggan {
            color: #102a58;
            font-weight: 700;
        }

        /* ==========================================
           STATUS BADGE
        ========================================== */

        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            padding: 7px 12px;

            border-radius: 30px;

            font-size: 11px;
            font-weight: 800;
        }

        .status-antre {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-proses {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-selesai {
            background: #ecfdf5;
            color: #047857;
        }

        .status-diambil {
            background: #f5f3ff;
            color: #6d28d9;
        }

        .status-batal {
            background: #fef2f2;
            color: #b91c1c;
        }

        .status-default {
            background: #f1f5f9;
            color: #475569;
        }

        /* ==========================================
           SEARCH
        ========================================== */

        .search-box {
            position: relative;
        }

        .search-box i {
            position: absolute;

            left: 14px;
            top: 50%;

            transform: translateY(-50%);

            color: #94a3b8;
        }

        .search-box input {
            width: 260px;

            padding: 12px 15px 12px 40px;

            border: 1px solid #dbe3f0;
            border-radius: 10px;

            background: #f8fafc;

            font-family: inherit;
            font-size: 13px;

            outline: none;
        }

        .search-box input:focus {
            border-color: #2563eb;

            box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
        }

        /* ==========================================
           EMPTY STATE
        ========================================== */

        .empty-state {
            padding: 60px 20px;

            text-align: center;

            color: #64748b;
        }

        .empty-state i {
            display: block;

            margin-bottom: 15px;

            color: #cbd5e1;

            font-size: 45px;
        }

        .empty-state h3 {
            margin: 0 0 10px;

            color: #102a58;

            font-size: 17px;
        }

        .empty-state p {
            margin: 0;

            font-size: 13px;
        }

        /* ==========================================
           ALERT
        ========================================== */

        .alert {
            display: flex;
            align-items: center;
            gap: 12px;

            padding: 17px 20px;

            margin-bottom: 25px;

            border: 1px solid #fecaca;
            border-radius: 12px;

            background: #fef2f2;
            color: #991b1b;

            font-size: 13px;
        }

        /* ==========================================
           TABLE FOOTER
        ========================================== */

        .table-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;

            padding: 18px 25px;

            border-top: 1px solid #e2e8f0;

            color: #64748b;

            font-size: 12px;
        }

        /* ==========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 1100px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 850px) {

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            #sidebarToggle {
                display: inline-grid;
            }

            .topbar-badge {
                display: none;
            }
        }

        @media (max-width: 768px) {

            .hero {
                padding: 25px;
            }

            .hero h1 {
                font-size: 23px;
            }

            .hero-icon {
                display: none;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;

                padding: 20px;
            }

            .form-group {
                width: 100%;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                width: 100%;
            }

            .card-header {
                padding: 20px;
            }

            .search-box,
            .search-box input {
                width: 100%;
            }

            .table-footer {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>

</head>

<body>

    <!-- ==========================================
         SIDEBAR ASLI SERVISKU
    ========================================== -->

    <?php
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <!-- ==========================================
         MAIN CONTENT
    ========================================== -->

    <main class="main-content">

        <div class="page-container">

            <!-- TOPBAR -->
            <div class="topbar">

                <div class="topbar-left">

                    <button
                        type="button"
                        id="sidebarToggle"
                        aria-label="Buka menu"
                    >
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>
                        <h2>Laporan Servis</h2>
                        <p>Dashboard / Laporan Servis</p>
                    </div>

                </div>

                <div class="topbar-badge">
                    <i class="fa-solid fa-chart-column"></i>
                    Laporan Bengkel
                </div>

            </div>

            <!-- HERO -->
            <div class="hero">

                <div class="hero-content">

                    <span class="hero-label">
                        <i class="fa-solid fa-chart-line"></i>
                        SERVISKU • LAPORAN BENGKEL
                    </span>

                    <h1>Laporan Data Servis</h1>

                    <p>
                        Pantau aktivitas servis kendaraan pelanggan,
                        filter berdasarkan periode tanggal,
                        dan unduh laporan dalam format CSV
                        untuk kebutuhan administrasi bengkel.
                    </p>

                </div>

                <div class="hero-icon">
                    <i class="fa-solid fa-chart-column"></i>
                </div>

            </div>

            <!-- ERROR -->
            <?php if ($errorTanggal !== ''): ?>

                <div class="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?= lapEsc($errorTanggal) ?>
                </div>

            <?php endif; ?>

            <?php if ($errorDatabase !== ''): ?>

                <div class="alert">
                    <i class="fa-solid fa-database"></i>
                    <?= lapEsc($errorDatabase) ?>
                </div>

            <?php endif; ?>

            <!-- STATISTIK -->
            <div class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon stat-blue">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>

                    <div class="stat-info">
                        <p>Total Servis</p>
                        <h3><?= number_format($totalServis) ?></h3>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-orange">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div class="stat-info">
                        <p>Servis Antre</p>
                        <h3><?= number_format($totalAntre) ?></h3>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-purple">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>

                    <div class="stat-info">
                        <p>Dalam Proses</p>
                        <h3><?= number_format($totalProses) ?></h3>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div class="stat-info">
                        <p>Servis Selesai</p>
                        <h3><?= number_format($totalSelesai) ?></h3>
                    </div>

                </div>

            </div>

            <!-- FILTER CARD -->
            <section class="card">

                <div class="card-header">

                    <div>
                        <h3>Filter Periode Laporan</h3>
                        <p>
                            Pilih rentang tanggal untuk menampilkan data servis.
                        </p>
                    </div>

                </div>

                <form method="GET" class="filter-form">

                    <div class="form-group">

                        <label for="dari">
                            Tanggal Dari
                        </label>

                        <input
                            type="date"
                            id="dari"
                            name="dari"
                            value="<?= lapEsc($from) ?>"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label for="sampai">
                            Tanggal Sampai
                        </label>

                        <input
                            type="date"
                            id="sampai"
                            name="sampai"
                            value="<?= lapEsc($to) ?>"
                            required
                        >

                    </div>

                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i class="fa-solid fa-filter"></i>
                            Tampilkan
                        </button>

                        <a
                            href="?dari=<?= urlencode((string) $from) ?>&amp;sampai=<?= urlencode((string) $to) ?>&amp;unduh=csv"
                            class="btn btn-success"
                        >
                            <i class="fa-solid fa-file-csv"></i>
                            Unduh CSV
                        </a>

                    </div>

                </form>

            </section>

            <!-- TABLE CARD -->
            <section class="card">

                <div class="card-header">

                    <div>
                        <h3>Daftar Laporan Servis</h3>

                        <p>
                            Menampilkan
                            <?= number_format($totalServis) ?>
                            data servis pada periode terpilih.
                        </p>
                    </div>

                    <div class="search-box">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="text"
                            id="searchLaporan"
                            placeholder="Cari laporan servis..."
                            aria-label="Cari laporan servis"
                        >

                    </div>

                </div>

                <?php if (empty($rows)): ?>

                    <div class="empty-state">

                        <i class="fa-solid fa-folder-open"></i>

                        <h3>Tidak Ada Data Servis</h3>

                        <p>
                            Belum ada data servis untuk periode
                            yang dipilih.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table id="tabelLaporan">

                            <thead>

                                <tr>
                                    <th>No</th>
                                    <th>Nomor Servis</th>
                                    <th>Pelanggan</th>
                                    <th>Nomor Polisi</th>
                                    <th>Jenis Motor</th>
                                    <th>Status</th>
                                    <th>Tanggal Daftar</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($rows as $index => $row): ?>

                                    <?php
                                    $status = $row['status'] ?? '-';
                                    $statusClass = lapStatusClass($status);
                                    ?>

                                    <tr class="laporan-row">

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <span class="nomor-servis">
                                                <?= lapEsc($row['nomor_servis']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="nama-pelanggan">
                                                <?= lapEsc($row['nama_pelanggan']) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= lapEsc($row['nomor_polisi']) ?>
                                        </td>

                                        <td>
                                            <?= lapEsc($row['jenis_motor']) ?>
                                        </td>

                                        <td>
                                            <span class="status-badge <?= lapEsc($statusClass) ?>">
                                                <?= lapEsc($status) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= lapEsc(lapTanggal($row['created_at'])) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <!-- EMPTY FILTER -->
                    <div
                        id="emptyFilter"
                        class="empty-state"
                        style="display:none;"
                    >

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <h3>Data Tidak Ditemukan</h3>

                        <p>
                            Tidak ada data yang sesuai dengan pencarian.
                        </p>

                    </div>

                    <!-- TABLE FOOTER -->
                    <div class="table-footer">

                        <span id="jumlahData">
                            Menampilkan <?= count($rows) ?> data servis
                        </span>

                        <span>
                            ServisKu © <?= date('Y') ?>
                        </span>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

    <!-- ==========================================
         JAVASCRIPT
    ========================================== -->

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            // ======================================
            // SIDEBAR MOBILE
            // ======================================

            const toggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');

            function closeSidebar() {

                if (sidebar) {
                    sidebar.classList.remove('open');
                }

                if (overlay) {
                    overlay.classList.remove('show');
                }
            }

            if (toggle && sidebar) {

                toggle.addEventListener('click', function () {

                    sidebar.classList.toggle('open');

                    if (overlay) {

                        overlay.classList.toggle(
                            'show',
                            sidebar.classList.contains('open')
                        );
                    }

                });
            }

            if (overlay) {

                overlay.addEventListener('click', closeSidebar);
            }

            window.addEventListener('resize', function () {

                if (window.innerWidth > 850) {
                    closeSidebar();
                }
            });

            // ======================================
            // PENCARIAN LAPORAN
            // ======================================

            const searchInput = document.getElementById('searchLaporan');
            const rows = document.querySelectorAll('.laporan-row');
            const jumlahData = document.getElementById('jumlahData');
            const emptyFilter = document.getElementById('emptyFilter');
            const tabelLaporan = document.getElementById('tabelLaporan');

            function filterLaporan() {

                const keyword = searchInput
                    ? searchInput.value.toLowerCase().trim()
                    : '';

                let visible = 0;

                rows.forEach(function (row) {

                    const text = row.textContent.toLowerCase();

                    if (text.includes(keyword)) {

                        row.style.display = '';
                        visible++;

                    } else {

                        row.style.display = 'none';
                    }

                });

                if (jumlahData) {

                    jumlahData.textContent =
                        'Menampilkan ' + visible + ' data servis';
                }

                if (emptyFilter) {

                    emptyFilter.style.display =
                        visible === 0 ? 'block' : 'none';
                }

                if (tabelLaporan) {

                    tabelLaporan.style.display =
                        visible === 0 ? 'none' : 'table';
                }
            }

            if (searchInput) {

                searchInput.addEventListener(
                    'input',
                    filterLaporan
                );
            }

        });

    </script>

</body>
</html>