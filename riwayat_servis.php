<?php
/**
 * SERVISKU - RIWAYAT SERVIS
 * Tema Modern Navy Blue
 * Menggunakan Sidebar Asli
 */

// ==========================================
// SESSION
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// KONEKSI DATABASE
// ==========================================

$fileKoneksi = [
    __DIR__ . '/includes/koneksi.php',
    __DIR__ . '/koneksi.php',
    __DIR__ . '/config.php',
    __DIR__ . '/config/koneksi.php'
];

$pdo = null;
$koneksiDitemukan = false;

foreach ($fileKoneksi as $file) {
    if (is_file($file)) {
        require_once $file;

        if (isset($pdo) && $pdo instanceof PDO) {
            $koneksiDitemukan = true;
            break;
        }
    }
}

$errorDatabase = '';

if (!$koneksiDitemukan) {
    $errorDatabase = 'Koneksi database PDO tidak ditemukan. Periksa file koneksi database.';
}

// ==========================================
// FUNGSI KEAMANAN OUTPUT
// ==========================================

function svEsc($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// ==========================================
// AMBIL DATA RIWAYAT SERVIS
// ==========================================

$rows = [];

if ($koneksiDitemukan) {

    try {

        $sql = "
            SELECT
                r.*,
                s.nomor_servis,
                s.nama_pelanggan,
                u.nama AS petugas

            FROM riwayat_servis r

            INNER JOIN servis s
                ON s.id = r.id_servis

            LEFT JOIN users u
                ON u.id = r.dibuat_oleh

            ORDER BY
                r.created_at DESC,
                r.id DESC

            LIMIT 500
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $errorDatabase = 'Gagal mengambil data riwayat servis. Periksa struktur tabel database.';

    }
}

// ==========================================
// STATISTIK RIWAYAT
// ==========================================

$totalRiwayat = count($rows);

$totalAntre = 0;
$totalProses = 0;
$totalSelesai = 0;

foreach ($rows as $row) {

    $status = strtolower(trim($row['status_baru'] ?? ''));

    if ($status === 'antre') {
        $totalAntre++;
    }

    if (
        $status === 'proses' ||
        $status === 'diproses' ||
        $status === 'dikerjakan'
    ) {
        $totalProses++;
    }

    if ($status === 'selesai') {
        $totalSelesai++;
    }
}

// ==========================================
// FORMAT TANGGAL
// ==========================================

function svTanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $timestamp = strtotime($tanggal);

    if ($timestamp === false) {
        return '-';
    }

    return date('d/m/Y H:i', $timestamp);
}

// ==========================================
// WARNA STATUS
// ==========================================

function svStatusClass($status)
{
    $status = strtolower(trim((string) $status));

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Riwayat Servis - ServisKu</title>

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

        /* ======================================
           GLOBAL
        ====================================== */

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

        /* ======================================
           MAIN CONTENT
        ====================================== */

        .main-content {
            margin-left: 250px;
            padding: 30px;
            min-height: 100vh;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ======================================
           TOPBAR
        ====================================== */

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: 23px;
            font-weight: 800;
            color: #102a58;
        }

        .topbar p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        #sidebarToggle {
            display: none;
            place-items: center;
            width: 42px;
            height: 42px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #102a58;
            font-size: 19px;
            cursor: pointer;
        }

        /* ======================================
           HERO HEADER
        ====================================== */

        .hero {
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
            color: white;

            box-shadow: 0 12px 30px rgba(15, 35, 75, .15);
        }

        .hero h1 {
            margin: 0 0 12px;
            color: white;
            font-size: 28px;
            font-weight: 800;
        }

        .hero p {
            max-width: 600px;
            margin: 0;
            color: #dbeafe;
            font-size: 14px;
            line-height: 1.8;
        }

        .hero-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;

            width: 85px;
            height: 85px;

            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 20px;

            font-size: 36px;
        }

        /* ======================================
           STATISTIK
        ====================================== */

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
            flex-shrink: 0;

            width: 48px;
            height: 48px;

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
            font-size: 12px;
            color: #64748b;
        }

        .stat-info h3 {
            margin: 0;
            font-size: 25px;
            font-weight: 800;
            color: #102a58;
        }

        /* ======================================
           CARD
        ====================================== */

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            overflow: hidden;

            box-shadow: 0 8px 30px rgba(15, 35, 75, .05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;

            padding: 25px;

            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0 0 6px;
            font-size: 17px;
            font-weight: 800;
            color: #102a58;
        }

        .card-header p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }

        /* ======================================
           FILTER
        ====================================== */

        .filter-box {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

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

        .filter-box select {
            padding: 12px 15px;
            border: 1px solid #dbe3f0;
            border-radius: 10px;

            background: #f8fafc;
            color: #334155;

            font-family: inherit;
            font-size: 13px;
            outline: none;
        }

        .search-box input:focus,
        .filter-box select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
        }

        /* ======================================
           TABLE
        ====================================== */

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

        tbody tr {
            transition: background .2s;
        }

        tbody tr:hover {
            background: #f8fbff;
        }

        .nomor-servis {
            color: #2563eb;
            font-weight: 800;
        }

        .nama-pelanggan {
            font-weight: 700;
            color: #102a58;
        }

        .keterangan {
            max-width: 300px;
            white-space: normal;
            line-height: 1.6;
        }

        /* ======================================
           STATUS BADGE
        ====================================== */

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

        /* ======================================
           EMPTY STATE
        ====================================== */

        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .empty-state i {
            font-size: 45px;
            color: #cbd5e1;
            margin-bottom: 15px;
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

        /* ======================================
           FOOTER
        ====================================== */

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

        /* ======================================
           RESPONSIVE
        ====================================== */

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

            .card-header {
                align-items: stretch;
                flex-direction: column;
            }

            .filter-box {
                flex-direction: column;
            }

            .search-box input,
            .filter-box select {
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

    <!-- SIDEBAR ASLI SERVISKU -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <!-- MAIN CONTENT -->
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
                        <h2>Riwayat Servis</h2>
                        <p>Dashboard / Riwayat Servis</p>
                    </div>

                </div>

            </div>

            <!-- HERO -->
            <div class="hero">

                <div>

                    <h1>
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        Riwayat Aktivitas Servis
                    </h1>

                    <p>
                        Pantau seluruh aktivitas dan perubahan status
                        servis kendaraan pelanggan secara lengkap,
                        terstruktur, dan mudah dipahami.
                    </p>

                </div>

                <div class="hero-icon">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>

            </div>

            <!-- STATISTIK -->
            <div class="stats-grid">

                <div class="stat-card">

                    <div class="stat-icon stat-blue">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>

                    <div class="stat-info">
                        <p>Total Aktivitas</p>
                        <h3><?= number_format($totalRiwayat) ?></h3>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-orange">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>

                    <div class="stat-info">
                        <p>Status Antre</p>
                        <h3><?= number_format($totalAntre) ?></h3>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-purple">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                    </div>

                    <div class="stat-info">
                        <p>Status Proses</p>
                        <h3><?= number_format($totalProses) ?></h3>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon stat-green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>

                    <div class="stat-info">
                        <p>Status Selesai</p>
                        <h3><?= number_format($totalSelesai) ?></h3>
                    </div>

                </div>

            </div>

            <!-- CARD RIWAYAT -->
            <div class="card">

                <div class="card-header">

                    <div>
                        <h3>Daftar Aktivitas Servis</h3>
                        <p>Menampilkan maksimal 500 aktivitas terbaru.</p>
                    </div>

                    <div class="filter-box">

                        <div class="search-box">

                            <i class="fa-solid fa-magnifying-glass"></i>

                            <input
                                type="text"
                                id="searchRiwayat"
                                placeholder="Cari riwayat servis..."
                                aria-label="Cari riwayat servis"
                            >

                        </div>

                        <select id="filterStatus" aria-label="Filter status">

                            <option value="">Semua Status</option>
                            <option value="antre">Antre</option>
                            <option value="proses">Proses</option>
                            <option value="selesai">Selesai</option>
                            <option value="diambil">Diambil</option>
                            <option value="batal">Batal</option>

                        </select>

                    </div>

                </div>

                <?php if ($errorDatabase !== ''): ?>

                    <div class="empty-state">

                        <i class="fa-solid fa-triangle-exclamation"></i>

                        <h3>Terjadi Kesalahan</h3>

                        <p><?= svEsc($errorDatabase) ?></p>

                    </div>

                <?php elseif (empty($rows)): ?>

                    <div class="empty-state">

                        <i class="fa-solid fa-folder-open"></i>

                        <h3>Belum Ada Riwayat Servis</h3>

                        <p>
                            Aktivitas servis akan muncul setelah
                            terdapat pendaftaran atau perubahan status.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table id="tabelRiwayat">

                            <thead>

                                <tr>
                                    <th>No</th>
                                    <th>Waktu</th>
                                    <th>No. Servis</th>
                                    <th>Pelanggan</th>
                                    <th>Perubahan Status</th>
                                    <th>Keterangan</th>
                                    <th>Petugas</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($rows as $index => $r): ?>

                                    <?php
                                        $statusBaru = $r['status_baru'] ?? '-';
                                        $statusLama = $r['status_sebelumnya'] ?? '-';

                                        if (!$statusLama) {
                                            $statusLama = '-';
                                        }

                                        if (!$statusBaru) {
                                            $statusBaru = '-';
                                        }

                                        $classStatus = svStatusClass($statusBaru);
                                    ?>

                                    <tr
                                        class="riwayat-row"
                                        data-status="<?= svEsc(strtolower(trim($statusBaru))) ?>"
                                    >

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>
                                            <?= svEsc(svTanggal($r['created_at'] ?? null)) ?>
                                        </td>

                                        <td>
                                            <span class="nomor-servis">
                                                <?= svEsc($r['nomor_servis'] ?? '-') ?>
                                            </span>
                                        </td>

                                        <td>
                                            <span class="nama-pelanggan">
                                                <?= svEsc($r['nama_pelanggan'] ?? '-') ?>
                                            </span>
                                        </td>

                                        <td>

                                            <span class="status-badge status-default">
                                                <?= svEsc($statusLama) ?>
                                            </span>

                                            <i class="fa-solid fa-arrow-right"
                                               style="margin:0 8px;color:#94a3b8;"></i>

                                            <span class="status-badge <?= svEsc($classStatus) ?>">
                                                <?= svEsc($statusBaru) ?>
                                            </span>

                                        </td>

                                        <td class="keterangan">
                                            <?= svEsc($r['keterangan'] ?? '-') ?>
                                        </td>

                                        <td>
                                            <?= svEsc($r['petugas'] ?? '-') ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div
                        id="emptyFilter"
                        class="empty-state"
                        style="display:none;"
                    >

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <h3>Data Tidak Ditemukan</h3>

                        <p>
                            Tidak ada riwayat yang sesuai dengan pencarian.
                        </p>

                    </div>

                    <div class="table-footer">

                        <span id="jumlahData">
                            Menampilkan <?= count($rows) ?> aktivitas
                        </span>

                        <span>
                            ServisKu © <?= date('Y') ?>
                        </span>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </main>

    <!-- JAVASCRIPT -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ======================================
            // SIDEBAR MOBILE
            // ======================================

            const sidebar = document.getElementById('appSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const toggle = document.getElementById('sidebarToggle');

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
            // PENCARIAN DAN FILTER
            // ======================================

            const searchInput = document.getElementById('searchRiwayat');
            const filterStatus = document.getElementById('filterStatus');
            const rows = document.querySelectorAll('.riwayat-row');
            const jumlahData = document.getElementById('jumlahData');
            const emptyFilter = document.getElementById('emptyFilter');
            const tabelRiwayat = document.getElementById('tabelRiwayat');

            function filterRiwayat() {

                const keyword = searchInput
                    ? searchInput.value.toLowerCase().trim()
                    : '';

                const status = filterStatus
                    ? filterStatus.value.toLowerCase()
                    : '';

                let visible = 0;

                rows.forEach(function (row) {

                    const text = row.textContent.toLowerCase();
                    const rowStatus = row.dataset.status || '';

                    const cocokKeyword = text.includes(keyword);

                    let cocokStatus = true;

                    if (status !== '') {

                        if (status === 'proses') {
                            cocokStatus = [
                                'proses',
                                'diproses',
                                'dikerjakan'
                            ].includes(rowStatus);
                        } else {
                            cocokStatus = rowStatus === status;
                        }

                    }

                    if (cocokKeyword && cocokStatus) {

                        row.style.display = '';
                        visible++;

                    } else {

                        row.style.display = 'none';

                    }

                });

                if (jumlahData) {
                    jumlahData.textContent =
                        'Menampilkan ' + visible + ' aktivitas';
                }

                if (emptyFilter) {
                    emptyFilter.style.display =
                        visible === 0 ? 'block' : 'none';
                }

                if (tabelRiwayat) {
                    tabelRiwayat.style.display =
                        visible === 0 ? 'none' : 'table';
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterRiwayat);
            }

            if (filterStatus) {
                filterStatus.addEventListener('change', filterRiwayat);
            }

        });
    </script>

</body>
</html>