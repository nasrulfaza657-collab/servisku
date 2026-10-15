<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/koneksi.php';

// Cek login
if (
    empty($_SESSION['user_id']) &&
    empty($_SESSION['id_user']) &&
    empty($_SESSION['username'])
) {
    header('Location: login.php');
    exit;
}

// Ambil ID servis
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    header('Location: data_servis.php');
    exit;
}

// Ambil data servis
$stmt = $pdo->prepare("
    SELECT *
    FROM servis
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);

$servis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$servis) {
    http_response_code(404);
}

function detailEsc($value)
{
    return htmlspecialchars(
        (string) ($value ?? '-'),
        ENT_QUOTES,
        'UTF-8'
    );
}

function detailTanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $time = strtotime($tanggal);

    return $time ? date('d/m/Y H:i', $time) : '-';
}

function detailStatusClass($status)
{
    switch ($status) {
        case 'Antre':
            return 'antre';
        case 'Pengerjaan':
            return 'pengerjaan';
        case 'Selesai':
            return 'selesai';
        default:
            return 'antre';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail Servis | ServisKu</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f4f7fc;
            color: #172d50;
        }

        a {
            text-decoration: none;
        }

        .main {
            min-height: 100vh;
            margin-left: 250px;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;

            height: 75px;
            padding: 0 26px;

            display: flex;
            align-items: center;
            gap: 20px;

            background: #fff;
            border-bottom: 1px solid #e2eafb;
        }

        .menu-toggle {
            border: none;
            background: none;
            color: #647b9e;
            font-size: 22px;
            cursor: pointer;
        }

        .top-search {
            padding: 12px 16px;
            background: #f1f5fb;
            border-radius: 10px;
            color: #7186a7;
            font-size: 13px;
        }

        .top-search i {
            margin-right: 8px;
        }

        .top-spacer {
            flex: 1;
        }

        /* Foto profil */
        .topbar .servisku-user-avatar {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            max-width: 44px !important;
            max-height: 44px !important;
            border-radius: 50% !important;
            overflow: hidden !important;
            flex: 0 0 44px !important;
        }

        .topbar .servisku-user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
        }

        .content {
            padding: 28px;
        }

        .heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 24px;
        }

        .heading h1 {
            margin: 0;
            font-size: 25px;
            font-weight: 800;
        }

        .heading p {
            color: #7186a7;
            font-size: 13px;
            margin-bottom: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 12px 16px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
        }

        .btn-back {
            color: #315c94;
            background: white;
            border: 1px solid #dce7f7;
        }

        .btn-edit {
            color: white;
            background: #0875f9;
        }

        .heading-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            border: 1px solid #e2eafb;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
        }

        .card h2 {
            margin: 0 0 22px;
            font-size: 17px;
            font-weight: 800;
            padding-bottom: 15px;
            border-bottom: 1px solid #eaf0f8;
        }

        .card h2 i {
            color: #0875f9;
            margin-right: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .info-item {
            min-width: 0;
        }

        .info-label {
            display: block;
            color: #8495ae;
            font-size: 12px;
            margin-bottom: 9px;
        }

        .info-value {
            display: block;
            color: #172d50;
            font-size: 14px;
            font-weight: 700;
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-flex;
            align-items: center;
            padding: 8px 13px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 800;
        }

        .status.antre {
            background: #fff1c9;
            color: #bd7800;
        }

        .status.pengerjaan {
            background: #eee3ff;
            color: #7541db;
        }

        .status.selesai {
            background: #d5f7e8;
            color: #078655;
        }

        .description {
            padding: 16px;
            background: #f7faff;
            border: 1px solid #e5edf8;
            border-radius: 10px;
            line-height: 1.8;
            font-size: 13px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .not-found {
            padding: 55px 20px;
            text-align: center;
        }

        .not-found i {
            color: #94a9c7;
            font-size: 45px;
        }

        .not-found h2 {
            font-size: 20px;
            margin-top: 18px;
        }

        .not-found p {
            color: #7186a7;
            margin-bottom: 25px;
        }

        .footer {
            margin-top: 24px;
            text-align: center;
            color: #8799b4;
            font-size: 11px;
        }

        @media (max-width: 850px) {
            .main {
                margin-left: 0;
            }

            .content {
                padding: 18px;
            }

            #sidebar,
            #appSidebar,
            .sidebar {
                transform: translateX(-100%);
                transition: transform .25s;
            }

            #sidebar.open,
            #appSidebar.open,
            .sidebar.open {
                transform: translateX(0);
            }
        }

        @media (max-width: 520px) {
            .topbar {
                padding: 0 14px;
                gap: 10px;
            }

            .top-search {
                font-size: 11px;
            }

            .content {
                padding: 12px;
            }

            .heading h1 {
                font-size: 21px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .card {
                padding: 18px;
            }

            .topbar .servisku-user-info {
                display: none !important;
            }

            .topbar .servisku-user-avatar {
                width: 38px !important;
                height: 38px !important;
                min-width: 38px !important;
                min-height: 38px !important;
                max-width: 38px !important;
                max-height: 38px !important;
                flex-basis: 38px !important;
            }
        }
    </style>
</head>

<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">

    <!-- TOPBAR -->
    <header class="topbar">

        <button
            type="button"
            class="menu-toggle"
            id="menuToggle"
            aria-label="Buka menu"
        >
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="top-search">
            <i class="fa-solid fa-clipboard-list"></i>
            Detail data servis
        </div>

        <div class="top-spacer"></div>

        <?php
        if (is_file(__DIR__ . '/includes/profil_header.php')) {
            require __DIR__ . '/includes/profil_header.php';
        }
        ?>

    </header>

    <section class="content">

        <?php if (!$servis): ?>

            <div class="card not-found">

                <i class="fa-regular fa-folder-open"></i>

                <h2>Data Servis Tidak Ditemukan</h2>

                <p>
                    Data servis dengan ID tersebut tidak tersedia.
                </p>

                <a href="data_servis.php" class="btn btn-edit">
                    <i class="fa-solid fa-arrow-left"></i>
                    Kembali ke Data Servis
                </a>

            </div>

        <?php else: ?>

            <!-- HEADING -->
            <div class="heading">

                <div>
                    <h1>Detail Servis</h1>

                    <p>
                        Informasi lengkap layanan servis motor.
                    </p>
                </div>

                <div class="heading-actions">

                    <a
                        href="data_servis.php"
                        class="btn btn-back"
                    >
                        <i class="fa-solid fa-arrow-left"></i>
                        Kembali
                    </a>

                    <a
                        href="edit_servis.php?id=<?= (int) $servis['id'] ?>"
                        class="btn btn-edit"
                    >
                        <i class="fa-solid fa-pen"></i>
                        Edit Servis
                    </a>

                </div>

            </div>

            <!-- INFORMASI SERVIS -->
            <div class="card">

                <h2>
                    <i class="fa-solid fa-clipboard-list"></i>
                    Informasi Servis
                </h2>

                <div class="info-grid">

                    <div class="info-item">
                        <span class="info-label">Nomor Servis</span>
                        <span class="info-value">
                            <?= detailEsc($servis['nomor_servis']) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Status Servis</span>
                        <span class="status <?= detailStatusClass($servis['status']) ?>">
                            <?= detailEsc($servis['status']) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Tanggal Masuk</span>
                        <span class="info-value">
                            <?= detailTanggal($servis['created_at'] ?? null) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Estimasi Selesai</span>
                        <span class="info-value">
                            <?= detailTanggal($servis['estimasi_selesai'] ?? null) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Selesai Pada</span>
                        <span class="info-value">
                            <?= detailTanggal($servis['selesai_pada'] ?? null) ?>
                        </span>
                    </div>

                </div>

            </div>

            <!-- INFORMASI PELANGGAN -->
            <div class="card">

                <h2>
                    <i class="fa-solid fa-user"></i>
                    Informasi Pelanggan
                </h2>

                <div class="info-grid">

                    <div class="info-item">
                        <span class="info-label">Nama Pelanggan</span>
                        <span class="info-value">
                            <?= detailEsc($servis['nama_pelanggan']) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Nomor Telepon</span>
                        <span class="info-value">
                            <?= detailEsc($servis['telepon']) ?>
                        </span>
                    </div>

                </div>

            </div>

            <!-- INFORMASI MOTOR -->
            <div class="card">

                <h2>
                    <i class="fa-solid fa-motorcycle"></i>
                    Informasi Motor
                </h2>

                <div class="info-grid">

                    <div class="info-item">
                        <span class="info-label">Nomor Polisi</span>
                        <span class="info-value">
                            <?= detailEsc($servis['nomor_polisi']) ?>
                        </span>
                    </div>

                    <div class="info-item">
                        <span class="info-label">Jenis Motor</span>
                        <span class="info-value">
                            <?= detailEsc($servis['jenis_motor']) ?>
                        </span>
                    </div>

                </div>

            </div>

            <!-- KELUHAN -->
            <div class="card">

                <h2>
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    Keluhan Pelanggan
                </h2>

                <div class="description"><?= detailEsc($servis['keluhan']) ?></div>

            </div>

            <!-- CATATAN PETUGAS -->
            <div class="card">

                <h2>
                    <i class="fa-solid fa-note-sticky"></i>
                    Catatan Petugas
                </h2>

                <div class="description"><?= detailEsc($servis['catatan_petugas'] ?? null) ?></div>

            </div>

        <?php endif; ?>

        <footer class="footer">
            &copy; <?= date('Y') ?> ServisKu — Sistem Manajemen Bengkel Motor
        </footer>

    </section>

</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('menuToggle');

    const sidebar =
        document.getElementById('sidebar') ||
        document.getElementById('appSidebar') ||
        document.querySelector('.sidebar');

    if (!toggle || !sidebar) {
        return;
    }

    toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        sidebar.classList.toggle('open');
    });

    document.addEventListener('click', function (event) {
        if (
            window.innerWidth <= 850 &&
            sidebar.classList.contains('open') &&
            !sidebar.contains(event.target) &&
            !toggle.contains(event.target)
        ) {
            sidebar.classList.remove('open');
        }
    });
});
</script>

</body>
</html>