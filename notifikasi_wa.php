<?php
/**
 * ==========================================================
 * SERVISKU - NOTIFIKASI WHATSAPP
 * ==========================================================
 * Sidebar : includes/sidebar.php
 * Database: config/koneksi.php
 * API     : Fonnte
 *
 * FITUR:
 * - Pengiriman WhatsApp manual
 * - Template pesan otomatis
 * - Riwayat pengiriman manual dan otomatis
 * - Statistik notifikasi
 * - CSRF protection
 * - Pencegahan double submit
 * - Tampilan responsif
 * ==========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
   VALIDASI LOGIN
========================================================== */

if (
    empty($_SESSION['user_id']) &&
    empty($_SESSION['id_user']) &&
    empty($_SESSION['username'])
) {
    header('Location: login.php');
    exit;
}

/* ==========================================================
   KONEKSI DAN KONFIGURASI
========================================================== */

require_once __DIR__ . '/config/koneksi.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi database PDO tidak ditemukan.');
}

$fileKonfigurasi = __DIR__ . '/config/fonnte.php';
$fileFonnte = __DIR__ . '/includes/fonnte.php';

if (!is_file($fileKonfigurasi)) {
    exit(
        'File config/fonnte.php tidak ditemukan. ' .
        'Silakan buat konfigurasi token Fonnte terlebih dahulu.'
    );
}

if (!is_file($fileFonnte)) {
    exit(
        'File includes/fonnte.php tidak ditemukan. ' .
        'Silakan periksa fungsi pengiriman WhatsApp.'
    );
}

require_once $fileKonfigurasi;
require_once $fileFonnte;

if (!function_exists('skKirimWhatsApp')) {
    exit(
        'Fungsi skKirimWhatsApp() tidak ditemukan ' .
        'di includes/fonnte.php.'
    );
}

/* ==========================================================
   HELPER
========================================================== */

if (!function_exists('waEsc')) {
    function waEsc($value)
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

if (!function_exists('waTanggal')) {
    function waTanggal($tanggal)
    {
        if (empty($tanggal)) {
            return '-';
        }

        $waktu = strtotime((string) $tanggal);

        return $waktu !== false
            ? date('d/m/Y H:i', $waktu)
            : '-';
    }
}

if (!function_exists('waBadge')) {
    function waBadge($status)
    {
        switch (strtolower(trim((string) $status))) {
            case 'terkirim':
                return 'success';

            case 'gagal':
                return 'danger';

            default:
                return 'warning';
        }
    }
}

/* ==========================================================
   CSRF TOKEN
========================================================== */

if (empty($_SESSION['csrf_token_wa'])) {
    $_SESSION['csrf_token_wa'] = bin2hex(
        random_bytes(32)
    );
}

/* ==========================================================
   VARIABEL HALAMAN
========================================================== */

$msg = '';
$error = false;

$servisList = [];
$logs = [];

$selectedServis = trim(
    (string) ($_POST['id_servis'] ?? '')
);

$selectedJenis = trim(
    (string) ($_POST['jenis'] ?? 'Status Servis')
);

$isiPesan = trim(
    (string) ($_POST['pesan'] ?? '')
);

$jenisOptions = [
    'Status Servis',
    'Estimasi Berubah',
    'Motor Siap Diambil'
];

/* ==========================================================
   PROSES KIRIM WHATSAPP MANUAL
========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /* ------------------------------------------
           VALIDASI CSRF
        ------------------------------------------ */

        $tokenForm = (string) (
            $_POST['csrf_token'] ?? ''
        );

        if (
            $tokenForm === '' ||
            !hash_equals(
                $_SESSION['csrf_token_wa'],
                $tokenForm
            )
        ) {
            throw new RuntimeException(
                'Token keamanan tidak valid. ' .
                'Silakan muat ulang halaman.'
            );
        }

        /*
         * Token langsung diperbarui setelah validasi.
         * Ini membantu mencegah pengiriman ulang
         * dengan token form yang sama.
         */

        $_SESSION['csrf_token_wa'] = bin2hex(
            random_bytes(32)
        );

        /* ------------------------------------------
           VALIDASI ID SERVIS
        ------------------------------------------ */

        $idServis = filter_var(
            $_POST['id_servis'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1
                ]
            ]
        );

        if ($idServis === false || $idServis === null) {
            throw new RuntimeException(
                'Silakan pilih data servis terlebih dahulu.'
            );
        }

        /* ------------------------------------------
           VALIDASI JENIS NOTIFIKASI
        ------------------------------------------ */

        if (
            !in_array(
                $selectedJenis,
                $jenisOptions,
                true
            )
        ) {
            throw new RuntimeException(
                'Jenis notifikasi tidak valid.'
            );
        }

        /* ------------------------------------------
           VALIDASI ISI PESAN
        ------------------------------------------ */

        if ($isiPesan === '') {
            throw new RuntimeException(
                'Pesan WhatsApp tidak boleh kosong.'
            );
        }

        $panjangPesan = function_exists('mb_strlen')
            ? mb_strlen($isiPesan, 'UTF-8')
            : strlen($isiPesan);

        if ($panjangPesan > 3000) {
            throw new RuntimeException(
                'Pesan terlalu panjang. ' .
                'Maksimal 3000 karakter.'
            );
        }

        /* ------------------------------------------
           PASTIKAN SERVIS TERSEDIA
        ------------------------------------------ */

        $cekServis = $pdo->prepare(
            'SELECT
                id,
                nama_pelanggan,
                telepon
             FROM servis
             WHERE id = ?
             LIMIT 1'
        );

        $cekServis->execute([
            (int) $idServis
        ]);

        $dataServis = $cekServis->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$dataServis) {
            throw new RuntimeException(
                'Data servis tidak ditemukan.'
            );
        }

        if (
            trim(
                (string) ($dataServis['telepon'] ?? '')
            ) === ''
        ) {
            throw new RuntimeException(
                'Nomor WhatsApp pelanggan masih kosong.'
            );
        }

        /* ------------------------------------------
           KIRIM MELALUI FONNTE
        ------------------------------------------ */

        $hasil = skKirimWhatsApp(
            $pdo,
            (int) $idServis,
            $selectedJenis,
            $isiPesan
        );

        if (!is_array($hasil)) {
            throw new RuntimeException(
                'Respons fungsi pengiriman WhatsApp tidak valid.'
            );
        }

        $berhasil = !empty(
            $hasil['sukses']
        );

        $msg = (string) (
            $hasil['pesan']
            ?? (
                $berhasil
                    ? 'Permintaan pengiriman diterima Fonnte.'
                    : 'Pengiriman WhatsApp gagal.'
            )
        );

        $error = !$berhasil;

        /* ------------------------------------------
           RESET FORM JIKA BERHASIL
        ------------------------------------------ */

        if ($berhasil) {

            $_SESSION['flash_wa'] = [
                'pesan' => $msg,
                'error' => false
            ];

            header('Location: notifikasi_wa.php');
            exit;
        }

    } catch (Throwable $e) {

        $error = true;

        $msg = $e instanceof PDOException
            ? 'Terjadi kesalahan database saat memproses notifikasi.'
            : $e->getMessage();
    }
}

/* ==========================================================
   FLASH MESSAGE
========================================================== */

if (
    isset($_SESSION['flash_wa']) &&
    is_array($_SESSION['flash_wa'])
) {

    $flash = $_SESSION['flash_wa'];

    $msg = (string) (
        $flash['pesan'] ?? ''
    );

    $error = !empty(
        $flash['error']
    );

    unset($_SESSION['flash_wa']);
}

/* ==========================================================
   DAFTAR SERVIS
========================================================== */

try {

    $stmt = $pdo->query(
        'SELECT
            id,
            nomor_servis,
            nama_pelanggan,
            telepon,
            status,
            jenis_motor,
            nomor_polisi,
            estimasi_selesai
         FROM servis
         ORDER BY id DESC
         LIMIT 200'
    );

    $servisList = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $error = true;

    $msg = 'Gagal mengambil daftar data servis.';
}

/* ==========================================================
   RIWAYAT PENGIRIMAN
========================================================== */

try {

    $stmt = $pdo->query(
        'SELECT
            n.*,
            s.nomor_servis,
            s.nama_pelanggan
         FROM notifikasi_wa n
         LEFT JOIN servis s
            ON s.id = n.id_servis
         ORDER BY n.id DESC
         LIMIT 100'
    );

    $logs = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $error = true;

    $msg = 'Gagal mengambil riwayat notifikasi.';
}

/* ==========================================================
   STATISTIK SELURUH DATABASE
========================================================== */

$totalLog = 0;
$totalTerkirim = 0;
$totalGagal = 0;
$totalMenunggu = 0;

try {

    $stmt = $pdo->query(
        "SELECT
            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN status_pengiriman = 'terkirim'
                    THEN 1 ELSE 0
                END
            ) AS terkirim,

            SUM(
                CASE
                    WHEN status_pengiriman = 'gagal'
                    THEN 1 ELSE 0
                END
            ) AS gagal,

            SUM(
                CASE
                    WHEN status_pengiriman IS NULL
                      OR status_pengiriman NOT IN (
                          'terkirim',
                          'gagal'
                      )
                    THEN 1 ELSE 0
                END
            ) AS menunggu

         FROM notifikasi_wa"
    );

    $stat = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

    if ($stat) {

        $totalLog = (int) (
            $stat['total'] ?? 0
        );

        $totalTerkirim = (int) (
            $stat['terkirim'] ?? 0
        );

        $totalGagal = (int) (
            $stat['gagal'] ?? 0
        );

        $totalMenunggu = (int) (
            $stat['menunggu'] ?? 0
        );
    }

} catch (PDOException $e) {

    $error = true;

    $msg = 'Gagal mengambil statistik notifikasi.';
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifikasi WhatsApp | ServisKu</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        :root {
            --navy: #0b1f42;
            --blue: #2563eb;
            --text: #172b4d;
            --muted: #71819c;
            --border: #e2e8f0;
            --bg: #f4f7fc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        a {
            text-decoration: none;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-container {
            max-width: 1450px;
            margin: auto;
        }

        /* ==========================================
           TOPBAR
        ========================================== */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar h2 {
            margin: 0;
            font-size: 23px;
            font-weight: 800;
            color: #102a58;
        }

        .topbar p {
            margin: 7px 0 0;
            font-size: 12px;
            color: var(--muted);
        }

        .topbar-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 15px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--blue);
            font-size: 12px;
            font-weight: 700;
        }

        #sidebarToggle {
            display: none;
            width: 42px;
            height: 42px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: white;
            color: var(--navy);
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
            border-radius: 20px;
            color: white;
            background: linear-gradient(
                120deg,
                #0b1f42,
                #174b9d,
                #2563eb
            );
            box-shadow: 0 12px 30px #102a581a;
            animation: fadeUp .5s ease both;
        }

        .hero::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            right: -100px;
            top: -120px;
            border: 2px solid #ffffff29;
            border-radius: 50%;
            pointer-events: none;
        }

        .hero-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 14px;
            margin-bottom: 15px;
            border-radius: 30px;
            background: #ffffff24;
            border: 1px solid #ffffff30;
            font-size: 11px;
            font-weight: 800;
        }

        .hero h1 {
            margin: 0 0 12px;
            font-size: 29px;
            font-weight: 800;
        }

        .hero p {
            max-width: 650px;
            margin: 0;
            font-size: 13px;
            line-height: 1.9;
            color: #dbeafe;
        }

        .hero-icon {
            display: grid;
            place-items: center;
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            border: 1px solid #ffffff40;
            border-radius: 22px;
            background: #ffffff24;
            font-size: 42px;
            z-index: 1;
        }

        /* ==========================================
           STATS
        ========================================== */

        .stats-grid {
            display: grid;
            grid-template-columns:
                repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 22px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: 0 5px 20px #102a580a;
            transition: .25s;
            animation: fadeUp .6s ease both;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px #102a5815;
        }

        .stat-icon {
            display: grid;
            place-items: center;
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

        .stat-green {
            background: #ecfdf5;
            color: #059669;
        }

        .stat-red {
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-orange {
            background: #fff7ed;
            color: #ea580c;
        }

        .stat-info p {
            margin: 0 0 7px;
            color: var(--muted);
            font-size: 12px;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 26px;
            font-weight: 800;
            color: #102a58;
        }

        /* ==========================================
           CARD
        ========================================== */

        .card {
            margin-bottom: 25px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: white;
            box-shadow: 0 8px 30px #102a580a;
            animation: fadeUp .65s ease both;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
        }

        .card-header-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            flex-shrink: 0;
            border-radius: 13px;
            background: #eff6ff;
            color: #2563eb;
            font-size: 21px;
        }

        .card-header h3 {
            margin: 0 0 7px;
            color: #102a58;
            font-size: 18px;
            font-weight: 800;
        }

        .card-header p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
        }

        /* ==========================================
           FORM
        ========================================== */

        .wa-form {
            padding: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .form-full {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 9px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
        }

        .required {
            color: #ef4444;
        }

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #dbe3f0;
            border-radius: 11px;
            outline: none;
            background: #f8fafc;
            color: #1e293b;
            font-size: 13px;
            transition: .2s;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #2563eb;
            background: white;
            box-shadow: 0 0 0 4px #2563eb15;
        }

        .form-group textarea {
            min-height: 185px;
            resize: vertical;
            line-height: 1.8;
        }

        .form-hint {
            margin: 8px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 24px;
            margin-top: 25px;
            border-top: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 20px;
            border: 0;
            border-radius: 11px;
            font-size: 12px;
            font-weight: 800;
            transition: .25s;
        }

        .btn:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            color: white;
            background: linear-gradient(
                120deg,
                #2563eb,
                #1d4ed8
            );
            box-shadow: 0 5px 15px #2563eb33;
        }

        .btn-primary:disabled {
            opacity: .65;
            cursor: wait;
            transform: none;
        }

        .btn-secondary {
            color: #475569;
            background: #f1f5f9;
        }

        /* ==========================================
           ALERT
        ========================================== */

        .alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 17px 20px;
            margin-bottom: 25px;
            border-radius: 13px;
            font-size: 13px;
            line-height: 1.7;
            animation: fadeUp .35s ease both;
        }

        .alert-success {
            color: #166534;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
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
            min-width: 850px;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 17px 20px;
            text-align: left;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 18px 20px;
            color: #334155;
            font-size: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        tbody tr {
            transition: .2s;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        .service-number {
            color: #2563eb;
            font-weight: 800;
        }

        .customer-name {
            color: #102a58;
            font-weight: 700;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 30px;
            font-size: 10px;
            font-weight: 800;
            text-transform: capitalize;
        }

        .badge.success {
            background: #ecfdf5;
            color: #047857;
        }

        .badge.danger {
            background: #fef2f2;
            color: #b91c1c;
        }

        .badge.warning {
            background: #fff7ed;
            color: #c2410c;
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
            font-size: 42px;
        }

        .empty-state h3 {
            color: #102a58;
            font-size: 17px;
        }

        /* ==========================================
           FOOTER
        ========================================== */

        .table-footer {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 18px 25px;
            color: var(--muted);
            font-size: 11px;
        }

        .info-box {
            display: flex;
            gap: 12px;
            padding: 18px 20px;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e40af;
            font-size: 12px;
            line-height: 1.8;
        }

        /* ==========================================
           ANIMASI
        ========================================== */

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(18px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* ==========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 1100px) {

            .stats-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            #sidebarToggle {
                display: grid;
                place-items: center;
            }

            .topbar-badge {
                display: none;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
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

            .stats-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-full {
                grid-column: auto;
            }

            .card-header,
            .wa-form {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
            }

            .table-footer {
                flex-direction: column;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }

    </style>

</head>

<body>

    <!-- ==========================================
         SIDEBAR
    ========================================== -->

    <?php
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="main-content">

        <div class="page-container">

            <!-- ==================================
                 TOPBAR
            =================================== -->

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

                        <h2>Notifikasi WhatsApp</h2>

                        <p>
                            Dashboard / Notifikasi WhatsApp
                        </p>

                    </div>

                </div>

                <div class="topbar-badge">

                    <i class="fa-brands fa-whatsapp"></i>

                    Sistem Notifikasi

                </div>

            </div>

            <!-- ==================================
                 HERO
            =================================== -->

            <div class="hero">

                <div>

                    <div class="hero-label">

                        <i class="fa-brands fa-whatsapp"></i>

                        SERVISKU • NOTIFIKASI PELANGGAN

                    </div>

                    <h1>Notifikasi WhatsApp</h1>

                    <p>
                        Kirim informasi status servis,
                        perubahan estimasi pengerjaan,
                        dan pemberitahuan motor siap diambil
                        kepada pelanggan melalui WhatsApp.
                        Mendukung pengiriman manual dan otomatis.
                    </p>

                </div>

                <div class="hero-icon">

                    <i class="fa-brands fa-whatsapp"></i>

                </div>

            </div>

            <!-- ==================================
                 ALERT
            =================================== -->

            <?php if ($msg !== ''): ?>

                <div class="alert <?= $error
                    ? 'alert-error'
                    : 'alert-success' ?>">

                    <i class="fa-solid <?= $error
                        ? 'fa-circle-exclamation'
                        : 'fa-circle-check' ?>"></i>

                    <span>
                        <?= waEsc($msg) ?>
                    </span>

                </div>

            <?php endif; ?>

            <!-- ==================================
                 STATISTIK
            =================================== -->

            <div class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon stat-blue">
                        <i class="fa-solid fa-paper-plane"></i>
                    </div>

                    <div class="stat-info">

                        <p>Total Notifikasi</p>

                        <h3>
                            <?= number_format($totalLog) ?>
                        </h3>

                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div class="stat-info">

                        <p>Diterima API</p>

                        <h3>
                            <?= number_format($totalTerkirim) ?>
                        </h3>

                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-red">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </div>

                    <div class="stat-info">

                        <p>Gagal</p>

                        <h3>
                            <?= number_format($totalGagal) ?>
                        </h3>

                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-orange">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div class="stat-info">

                        <p>Menunggu</p>

                        <h3>
                            <?= number_format($totalMenunggu) ?>
                        </h3>

                    </div>

                </div>

            </div>

            <!-- ==================================
                 FORM PENGIRIMAN MANUAL
            =================================== -->

            <section class="card">

                <div class="card-header">

                    <div class="card-header-icon">

                        <i class="fa-brands fa-whatsapp"></i>

                    </div>

                    <div>

                        <h3>Kirim Notifikasi WhatsApp</h3>

                        <p>
                            Pilih servis pelanggan,
                            jenis notifikasi,
                            kemudian periksa pesan sebelum dikirim.
                        </p>

                    </div>

                </div>

                <form
                    method="POST"
                    action="notifikasi_wa.php"
                    class="wa-form"
                    id="formWhatsApp"
                >

                    <!-- CSRF -->

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= waEsc(
                            $_SESSION['csrf_token_wa']
                        ) ?>"
                    >

                    <div class="form-grid">

                        <!-- PILIH SERVIS -->

                        <div class="form-group">

                            <label for="id_servis">

                                Pilih Servis

                                <span class="required">*</span>

                            </label>

                            <select
                                name="id_servis"
                                id="id_servis"
                                required
                            >

                                <option value="">
                                    -- Pilih Data Servis --
                                </option>

                                <?php foreach ($servisList as $s): ?>

                                    <option
                                        value="<?= (int) $s['id'] ?>"

                                        data-nama="<?= waEsc(
                                            $s['nama_pelanggan']
                                        ) ?>"

                                        data-nomor="<?= waEsc(
                                            $s['nomor_servis']
                                        ) ?>"

                                        data-status="<?= waEsc(
                                            $s['status']
                                        ) ?>"

                                        data-motor="<?= waEsc(
                                            $s['jenis_motor']
                                        ) ?>"

                                        data-plat="<?= waEsc(
                                            $s['nomor_polisi']
                                        ) ?>"

                                        data-estimasi="<?= waEsc(
                                            $s['estimasi_selesai']
                                        ) ?>"

                                        <?= $selectedServis === (string) $s['id']
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= waEsc(
                                            $s['nomor_servis'] .
                                            ' - ' .
                                            $s['nama_pelanggan'] .
                                            ' (' .
                                            $s['telepon'] .
                                            ')'
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <p class="form-hint">
                                Pilih pelanggan penerima notifikasi.
                            </p>

                        </div>

                        <!-- JENIS NOTIFIKASI -->

                        <div class="form-group">

                            <label for="jenis">

                                Jenis Notifikasi

                                <span class="required">*</span>

                            </label>

                            <select
                                name="jenis"
                                id="jenis"
                                required
                            >

                                <?php foreach ($jenisOptions as $jenis): ?>

                                    <option
                                        value="<?= waEsc($jenis) ?>"

                                        <?= $selectedJenis === $jenis
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= waEsc($jenis) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                            <p class="form-hint">
                                Tentukan jenis informasi untuk pelanggan.
                            </p>

                        </div>

                        <!-- PESAN -->

                        <div class="form-group form-full">

                            <label for="pesan">

                                Pesan WhatsApp

                                <span class="required">*</span>

                            </label>

                            <textarea
                                name="pesan"
                                id="pesan"
                                maxlength="3000"
                                required
                                placeholder="Pesan akan dibuat otomatis setelah memilih servis..."
                            ><?= waEsc($isiPesan) ?></textarea>

                            <p class="form-hint">
                                Template dibuat otomatis,
                                tetapi tetap bisa diedit sebelum dikirim.
                            </p>

                        </div>

                    </div>

                    <!-- TOMBOL -->

                    <div class="form-actions">

                        <a
                            href="data_servis.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Kembali

                        </a>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="kirimButton"
                        >

                            <i class="fa-brands fa-whatsapp"></i>

                            Kirim WhatsApp

                        </button>

                    </div>

                </form>

            </section>

            <!-- ==================================
                 RIWAYAT PENGIRIMAN
            =================================== -->

            <section class="card">

                <div class="card-header">

                    <div class="card-header-icon">

                        <i class="fa-solid fa-clock-rotate-left"></i>

                    </div>

                    <div>

                        <h3>Riwayat Pengiriman WhatsApp</h3>

                        <p>
                            Menampilkan maksimal 100 notifikasi terbaru,
                            baik pengiriman manual maupun otomatis.
                        </p>

                    </div>

                </div>

                <?php if (empty($logs)): ?>

                    <div class="empty-state">

                        <i class="fa-solid fa-envelope-open-text"></i>

                        <h3>Belum Ada Riwayat Pengiriman</h3>

                        <p>
                            Riwayat akan muncul setelah
                            ada percobaan pengiriman WhatsApp.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table>

                            <thead>

                                <tr>

                                    <th>No</th>
                                    <th>Waktu</th>
                                    <th>No. Servis</th>
                                    <th>Pelanggan</th>
                                    <th>Nomor Tujuan</th>
                                    <th>Jenis Notifikasi</th>
                                    <th>Status</th>

                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($logs as $index => $log): ?>

                                    <?php

                                    $status = $log['status_pengiriman']
                                        ?? 'menunggu';

                                    ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= waEsc(
                                                waTanggal(
                                                    $log['created_at'] ?? null
                                                )
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="service-number">

                                                <?= waEsc(
                                                    $log['nomor_servis'] ?? '-'
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>

                                            <span class="customer-name">

                                                <?= waEsc(
                                                    $log['nama_pelanggan'] ?? '-'
                                                ) ?>

                                            </span>

                                        </td>

                                        <td>
                                            <?= waEsc(
                                                $log['telepon_tujuan'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= waEsc(
                                                $log['jenis_notifikasi'] ?? '-'
                                            ) ?>
                                        </td>

                                        <td>

                                            <span class="badge <?= waEsc(
                                                waBadge($status)
                                            ) ?>">

                                                <?= waEsc($status) ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="table-footer">

                        <span>
                            Menampilkan <?= count($logs) ?>
                            notifikasi terbaru
                        </span>

                        <span>
                            ServisKu © <?= date('Y') ?>
                        </span>

                    </div>

                <?php endif; ?>

            </section>

            <!-- ==================================
                 INFORMASI
            =================================== -->

            <div class="info-box">

                <i class="fa-solid fa-circle-info"></i>

                <span>

                    Pengiriman WhatsApp menggunakan API Fonnte.

                    Pastikan token telah dikonfigurasi,
                    perangkat WhatsApp terhubung,
                    dan pelanggan telah menyetujui
                    penerimaan notifikasi.

                    Status "terkirim" pada halaman ini
                    berarti permintaan diterima API,
                    bukan jaminan pesan telah dibaca pelanggan.

                </span>

            </div>

        </div>

    </main>

    <!-- ==========================================
         JAVASCRIPT
    ========================================== -->

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            /* ======================================
               SIDEBAR MOBILE
            ====================================== */

            const toggle =
                document.getElementById('sidebarToggle');

            const sidebar =
                document.getElementById('appSidebar') ||
                document.getElementById('sidebar') ||
                document.querySelector('.sidebar');

            const overlay =
                document.getElementById('sidebarOverlay');

            function tutupSidebar() {

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

                document.addEventListener('click', function (event) {

                    if (
                        window.innerWidth <= 850 &&
                        sidebar.classList.contains('open') &&
                        !sidebar.contains(event.target) &&
                        !toggle.contains(event.target)
                    ) {
                        tutupSidebar();
                    }

                });

                window.addEventListener('resize', function () {

                    if (window.innerWidth > 850) {
                        tutupSidebar();
                    }

                });
            }

            if (overlay) {

                overlay.addEventListener(
                    'click',
                    tutupSidebar
                );
            }

            /* ======================================
               TEMPLATE PESAN OTOMATIS
            ====================================== */

            const servisSelect =
                document.getElementById('id_servis');

            const jenisSelect =
                document.getElementById('jenis');

            const pesanTextarea =
                document.getElementById('pesan');

            if (
                !servisSelect ||
                !jenisSelect ||
                !pesanTextarea
            ) {
                return;
            }

            let pesanDiedit =
                pesanTextarea.value.trim() !== '';

            function buatTemplate() {

                const option =
                    servisSelect.options[
                        servisSelect.selectedIndex
                    ];

                if (!option || !option.value) {

                    if (!pesanDiedit) {
                        pesanTextarea.value = '';
                    }

                    return;
                }

                const nama =
                    option.dataset.nama || 'Pelanggan';

                const nomor =
                    option.dataset.nomor || '-';

                const status =
                    option.dataset.status || '-';

                const motor =
                    option.dataset.motor || '-';

                const plat =
                    option.dataset.plat || '-';

                const estimasi =
                    option.dataset.estimasi || '-';

                const jenis = jenisSelect.value;

                let pesan = '';

                /* ----------------------------------
                   STATUS SERVIS
                ---------------------------------- */

                if (jenis === 'Status Servis') {

                    pesan =
                        '🏍️ *SERVISKU — BENGKEL MOTOR*\n\n' +
                        'Halo, Bapak/Ibu *' + nama + '*! 👋\n\n' +
                        'Kami ingin menginformasikan perkembangan servis motor Anda.\n\n' +
                        '📋 *Nomor Servis:* ' + nomor + '\n' +
                        '🏍️ *Motor:* ' + motor + '\n' +
                        '🔢 *Nomor Polisi:* ' + plat + '\n' +
                        '🔧 *Status Saat Ini:* ' + status + '\n\n' +
                        'Terima kasih telah mempercayakan motor Anda kepada *ServisKu*.';

                }

                /* ----------------------------------
                   ESTIMASI BERUBAH
                ---------------------------------- */

                else if (jenis === 'Estimasi Berubah') {

                    pesan =
                        '🏍️ *SERVISKU — BENGKEL MOTOR*\n\n' +
                        'Halo, Bapak/Ibu *' + nama + '*! 👋\n\n' +
                        'Kami ingin menginformasikan perubahan estimasi servis.\n\n' +
                        '📋 *Nomor Servis:* ' + nomor + '\n' +
                        '🏍️ *Motor:* ' + motor + '\n' +
                        '📅 *Estimasi Selesai:* ' + estimasi + '\n\n' +
                        'Mohon maaf atas perubahan jadwal.\n' +
                        'Terima kasih atas pengertiannya.\n\n' +
                        '*ServisKu — Bengkel Motor*';

                }

                /* ----------------------------------
                   MOTOR SIAP DIAMBIL
                ---------------------------------- */

                else if (jenis === 'Motor Siap Diambil') {

                    pesan =
                        '🏍️ *SERVISKU — BENGKEL MOTOR*\n\n' +
                        'Halo, Bapak/Ibu *' + nama + '*! 👋\n\n' +
                        '🎉 *KABAR BAIK!*\n\n' +
                        'Servis motor Anda telah *SELESAI*.\n\n' +
                        '📋 *Nomor Servis:* ' + nomor + '\n' +
                        '🏍️ *Motor:* ' + motor + '\n' +
                        '🔢 *Nomor Polisi:* ' + plat + '\n\n' +
                        'Motor Anda sudah siap diambil di bengkel kami.\n\n' +
                        'Terima kasih telah menggunakan layanan *ServisKu*. 🙏';

                }

                pesanTextarea.value = pesan;
            }

            /* ======================================
               PILIH SERVIS
            ====================================== */

            servisSelect.addEventListener('change', function () {

                pesanDiedit = false;

                buatTemplate();

            });

            /* ======================================
               PILIH JENIS NOTIFIKASI
            ====================================== */

            jenisSelect.addEventListener('change', function () {

                pesanDiedit = false;

                buatTemplate();

            });

            /* ======================================
               EDIT PESAN MANUAL
            ====================================== */

            pesanTextarea.addEventListener('input', function () {

                pesanDiedit = true;

            });

            /* ======================================
               TEMPLATE SAAT HALAMAN DIBUKA
            ====================================== */

            if (
                !pesanDiedit &&
                servisSelect.value
            ) {
                buatTemplate();
            }

            /* ======================================
               CEGAH DOUBLE SUBMIT
            ====================================== */

            const form =
                document.getElementById('formWhatsApp');

            const kirimButton =
                document.getElementById('kirimButton');

            if (form && kirimButton) {

                let sedangMengirim = false;

                form.addEventListener('submit', function (event) {

                    if (sedangMengirim) {

                        event.preventDefault();

                        return;
                    }

                    if (!form.checkValidity()) {
                        return;
                    }

                    sedangMengirim = true;

                    kirimButton.disabled = true;

                    kirimButton.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin"></i> ' +
                        'Mengirim WhatsApp...';

                });
            }

        });

    </script>

</body>
</html>