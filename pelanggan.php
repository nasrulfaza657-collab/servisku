<?php
/**
 * SERVISKU - MANAJEMEN DATA PELANGGAN
 * Perbaikan: tombol aksi, hapus, CSRF, pencarian,
 * pagination, statistik, dan relasi riwayat servis.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/koneksi.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi PDO tidak ditemukan.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* ========================= HELPER ========================= */

function pelE($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function pelFlash(string $message, string $type = 'success'): void
{
    $_SESSION['pel_flash'] = [$message, $type];
}

function pelHasColumn(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    return (bool)$stmt->fetchColumn();
}

function pelInitial(string $name): string
{
    $words = preg_split('/\s+/u', trim($name));
    $result = '';

    foreach (array_slice($words ?: [], 0, 2) as $word) {
        $result .= function_exists('mb_substr')
            ? mb_substr($word, 0, 1, 'UTF-8')
            : substr($word, 0, 1);
    }

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($result, 'UTF-8')
        : strtoupper($result);
}

function pelDate($value): string
{
    if (empty($value) || !strtotime((string)$value)) {
        return '-';
    }

    return date('d/m/Y', strtotime((string)$value));
}

function pelUrl(int $page, string $search, string $sort): string
{
    return 'pelanggan.php?' . http_build_query([
        'hal' => $page,
        'cari' => $search,
        'urut' => $sort
    ]);
}

/* ========================= CSRF ========================= */

if (empty($_SESSION['csrf_pelanggan_admin'])) {
    $_SESSION['csrf_pelanggan_admin'] = bin2hex(random_bytes(32));
}

/* ========================= STRUKTUR DATABASE ========================= */

$hasPassword = pelHasColumn($pdo, 'pelanggan', 'password');
$hasEmail = pelHasColumn($pdo, 'pelanggan', 'email');
$hasAlamat = pelHasColumn($pdo, 'pelanggan', 'alamat');
$hasCreatedAt = pelHasColumn($pdo, 'pelanggan', 'created_at');
$hasLink = pelHasColumn($pdo, 'servis', 'id_pelanggan');
$hasServisPhone = pelHasColumn($pdo, 'servis', 'telepon');
$hasServisName = pelHasColumn($pdo, 'servis', 'nama_pelanggan');
$hasServisDate = pelHasColumn($pdo, 'servis', 'tanggal_servis');
$hasServisCreated = pelHasColumn($pdo, 'servis', 'created_at');
$hasServisStatus = pelHasColumn($pdo, 'servis', 'status');

$servisDateColumn = $hasServisDate
    ? 'sv.tanggal_servis'
    : ($hasServisCreated ? 'sv.created_at' : 'NULL');

$pelangganDateColumn = $hasCreatedAt
    ? 'p.created_at'
    : 'NULL';

/*
 * Hubungkan berdasarkan ID bila tersedia.
 * Untuk data lama, cocokkan nomor telepon jika kolomnya tersedia.
 */
$conditions = [];

if ($hasLink) {
    $conditions[] = 'sv.id_pelanggan = p.id';
}

if ($hasServisPhone) {
    $phoneExpr = "
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(TRIM(sv.telepon), ' ', ''),
                    '-', ''
                ),
                '(', ''
            ),
            ')', ''
        )
    ";

    $pelPhoneExpr = "
        REPLACE(
            REPLACE(
                REPLACE(
                    REPLACE(TRIM(p.telepon), ' ', ''),
                    '-', ''
                ),
                '(', ''
            ),
            ')', ''
        )
    ";

    $conditions[] = "(
        $phoneExpr = $pelPhoneExpr
        OR (
            LEFT($phoneExpr, 1) = '0'
            AND CONCAT('62', SUBSTRING($phoneExpr, 2)) = $pelPhoneExpr
        )
        OR (
            LEFT($pelPhoneExpr, 1) = '0'
            AND CONCAT('62', SUBSTRING($pelPhoneExpr, 2)) = $phoneExpr
        )
        OR (
            LEFT($phoneExpr, 2) = '62'
            AND CONCAT('0', SUBSTRING($phoneExpr, 3)) = $pelPhoneExpr
        )
        OR (
            LEFT($pelPhoneExpr, 2) = '62'
            AND CONCAT('0', SUBSTRING($pelPhoneExpr, 3)) = $phoneExpr
        )
    )";
}

$joinCondition = $conditions
    ? '(' . implode(' OR ', $conditions) . ')'
    : '1 = 0';

/* ========================= PROSES POST ========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        $sessionToken = (string)($_SESSION['csrf_pelanggan_admin'] ?? '');

        if (
            $sessionToken === '' ||
            !hash_equals($sessionToken, $submittedToken)
        ) {
            throw new RuntimeException(
                'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.'
            );
        }

        $action = (string)($_POST['aksi'] ?? '');

        /* ---------------- HAPUS PELANGGAN ---------------- */

        if ($action === 'hapus') {
            $id = filter_var(
                $_POST['hapus_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$id) {
                throw new RuntimeException('ID pelanggan tidak valid.');
            }

            $pdo->beginTransaction();

            /* Pastikan pelanggan memang ada. */
            $findCustomer = $pdo->prepare(
                'SELECT id FROM pelanggan WHERE id = ? FOR UPDATE'
            );
            $findCustomer->execute([$id]);

            if (!$findCustomer->fetchColumn()) {
                throw new RuntimeException('Pelanggan tidak ditemukan.');
            }

            /*
             * Periksa riwayat servis dengan ID pelanggan maupun
             * nomor telepon. Jangan menghapus riwayat servis.
             */
            $checkService = $pdo->prepare("
                SELECT COUNT(*)
                FROM servis sv
                JOIN pelanggan p ON $joinCondition
                WHERE p.id = ?
            ");
            $checkService->execute([$id]);

            if ((int)$checkService->fetchColumn() > 0) {
                throw new RuntimeException(
                    'Pelanggan masih memiliki riwayat servis. '
                    . 'Data tidak dihapus agar riwayat servis tetap aman.'
                );
            }

            $delete = $pdo->prepare('DELETE FROM pelanggan WHERE id = ?');
            $delete->execute([$id]);

            if ($delete->rowCount() < 1) {
                throw new RuntimeException('Data pelanggan gagal dihapus.');
            }

            $pdo->commit();

            pelFlash('Pelanggan berhasil dihapus.', 'success');
        }

        /* ---------------- IMPOR PELANGGAN ---------------- */

        elseif ($action === 'impor') {
            if (!$hasServisPhone || !$hasServisName) {
                throw new RuntimeException(
                    'Kolom telepon atau nama_pelanggan tidak ditemukan pada tabel servis.'
                );
            }

            $source = $pdo->query("
                SELECT nama_pelanggan AS nama, telepon
                FROM servis
                WHERE telepon IS NOT NULL
                  AND telepon <> ''
                GROUP BY telepon, nama_pelanggan
            ")->fetchAll(PDO::FETCH_ASSOC);

            $added = 0;
            $pdo->beginTransaction();

            $find = $pdo->prepare(
                'SELECT id FROM pelanggan WHERE telepon = ? LIMIT 1'
            );

            $insert = $pdo->prepare("
                INSERT INTO pelanggan (nama, telepon)
                VALUES (?, ?)
            ");

            foreach ($source as $item) {
                $phone = preg_replace('/\D+/', '', (string)$item['telepon']);

                if (str_starts_with($phone, '0062')) {
                    $phone = '62' . substr($phone, 4);
                } elseif (str_starts_with($phone, '0')) {
                    $phone = '62' . substr($phone, 1);
                } elseif (str_starts_with($phone, '8')) {
                    $phone = '62' . $phone;
                }

                if (!preg_match('/^62[0-9]{8,13}$/', $phone)) {
                    continue;
                }

                $name = trim((string)$item['nama']);

                if ($name === '') {
                    continue;
                }

                $find->execute([$phone]);

                if ($find->fetchColumn()) {
                    continue;
                }

                $insert->execute([$name, $phone]);
                $added++;
            }

            $pdo->commit();

            pelFlash("Impor selesai: $added pelanggan baru ditambahkan.");
        } else {
            throw new RuntimeException('Aksi tidak dikenali.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('ServisKu pelanggan: ' . $e->getMessage());

        $message = $e instanceof PDOException
            ? 'Operasi database gagal. Periksa relasi tabel dan constraint database.'
            : $e->getMessage();

        pelFlash($message, 'error');
    }

    header('Location: pelanggan.php');
    exit;
}

/* ========================= FLASH ========================= */

[$flash, $flashType] = $_SESSION['pel_flash'] ?? ['', 'success'];
unset($_SESSION['pel_flash']);

/* ========================= FILTER DAN PAGINATION ========================= */

$search = trim((string)($_GET['cari'] ?? ''));
$sort = (string)($_GET['urut'] ?? 'terbaru');

$orderOptions = [
    'terbaru' => $pelangganDateColumn . ' DESC, p.id DESC',
    'nama' => 'p.nama ASC',
    'servis' => 'jumlah_servis DESC, p.nama ASC',
    'terakhir' => 'terakhir_servis DESC, p.nama ASC'
];

if (!isset($orderOptions[$sort])) {
    $sort = 'terbaru';
}

$page = max(1, (int)($_GET['hal'] ?? 1));
$limit = 10;

$where = '';
$params = [];

if ($search !== '') {
    $searchConditions = ['p.nama LIKE ?', 'p.telepon LIKE ?'];
    $params = ['%' . $search . '%', '%' . $search . '%'];

    if ($hasEmail) {
        $searchConditions[] = 'p.email LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if ($hasAlamat) {
        $searchConditions[] = 'p.alamat LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $where = ' WHERE (' . implode(' OR ', $searchConditions) . ')';
}

/* ========================= DATA PELANGGAN ========================= */

$from = "
    FROM pelanggan p
    LEFT JOIN (
        SELECT
            p2.id AS pelanggan_id,
            COUNT(sv.id) AS jumlah_servis,
            MAX($servisDateColumn) AS terakhir_servis
        FROM pelanggan p2
        LEFT JOIN servis sv ON $joinCondition
        GROUP BY p2.id
    ) s ON s.pelanggan_id = p.id
";

/*
 * Alias p pada join servis perlu merujuk ke pelanggan yang benar.
 * Gunakan subquery terpisah agar alias pelanggan tidak ambigu.
 */
$serviceJoin = $hasLink
    ? 'sv.id_pelanggan = p2.id'
    : '1 = 0';

if ($hasLink && $hasServisPhone) {
    $serviceJoin = "(
        sv.id_pelanggan = p2.id
        OR (
            (sv.id_pelanggan IS NULL OR sv.id_pelanggan = 0)
            AND $joinCondition
        )
    )";
} elseif (!$hasLink && $hasServisPhone) {
    $serviceJoin = $joinCondition;
}

$from = "
    FROM pelanggan p
    LEFT JOIN (
        SELECT
            p2.id AS pelanggan_id,
            COUNT(sv.id) AS jumlah_servis,
            MAX($servisDateColumn) AS terakhir_servis
        FROM pelanggan p2
        LEFT JOIN servis sv ON (
            " . str_replace(
                ['sv.', 'p.'],
                ['sv.', 'p2.'],
                $serviceJoin
            ) . "
        )
        GROUP BY p2.id
    ) s ON s.pelanggan_id = p.id
";

$totalStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM pelanggan p $where"
);
$totalStatement->execute($params);
$total = (int)$totalStatement->fetchColumn();

$pages = max(1, (int)ceil($total / $limit));
$page = min($page, $pages);
$offset = ($page - 1) * $limit;

$emailSelect = $hasEmail ? 'p.email' : "'' AS email";
$alamatSelect = $hasAlamat ? 'p.alamat' : "'' AS alamat";
$createdSelect = $hasCreatedAt ? 'p.created_at' : 'NULL AS created_at';
$passwordSelect = $hasPassword ? 'p.password' : "NULL AS password";

$sql = "
    SELECT
        p.id,
        p.nama,
        p.telepon,
        $emailSelect,
        $alamatSelect,
        $createdSelect,
        $passwordSelect,
        COALESCE(s.jumlah_servis, 0) AS jumlah_servis,
        s.terakhir_servis
    $from
    $where
    ORDER BY {$orderOptions[$sort]}
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ========================= STATISTIK ========================= */

$totalCustomers = (int)$pdo->query(
    'SELECT COUNT(*) FROM pelanggan'
)->fetchColumn();

$newMonth = $hasCreatedAt
    ? (int)$pdo->query("
        SELECT COUNT(*)
        FROM pelanggan
        WHERE YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ")->fetchColumn()
    : 0;

$active = 0;

if ($hasServisStatus) {
    $activeStmt = $pdo->query("
        SELECT COUNT(DISTINCT p.id)
        FROM pelanggan p
        JOIN servis sv ON $joinCondition
        WHERE sv.status IN ('Antre', 'Pengerjaan')
    ");
    $active = (int)$activeStmt->fetchColumn();
}

$repeat = 0;

$pending = $hasPassword
    ? (int)$pdo->query("
        SELECT COUNT(*)
        FROM pelanggan
        WHERE password IS NULL OR password = ''
    ")->fetchColumn()
    : $totalCustomers;

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Pelanggan | ServisKu</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
:root{--navy:#142238;--blue:#0875f9;--text:#172d50;--muted:#7487a5;--border:#e2eafb;--bg:#f4f7fc}
*{box-sizing:border-box}
body{margin:0;font-family:Inter,Arial,sans-serif;color:var(--text);background:var(--bg)}
a{text-decoration:none;color:inherit}
button,input,select{font:inherit}
button{cursor:pointer}
.main{margin-left:250px;min-height:100vh}
.topbar{height:75px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:16px;padding:0 26px;position:sticky;top:0;z-index:50}
.menu-toggle{background:none;border:0;font-size:21px;color:#64748b}
.top-search{background:#f1f5fb;border-radius:10px;padding:12px 15px;color:#8195b3;font-size:13px}
.spacer{flex:1}
.content{padding:26px;min-height:calc(100vh - 75px);background:radial-gradient(ellipse at top right,#e3eeff,transparent 36%),var(--bg)}
.heading{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.heading-left{display:flex;align-items:center;gap:14px}
.heading-icon{width:52px;height:52px;display:grid;place-items:center;border-radius:15px;background:linear-gradient(135deg,#35aaff,#0872ed);color:white;font-size:21px}
.heading h1{margin:0;font-size:24px}
.heading p,.panel p{color:var(--muted);font-size:12px;margin:6px 0 0}
.heading-actions{display:flex;flex-wrap:wrap;gap:9px;align-items:center}
.btn{border:0;border-radius:10px;padding:12px 16px;font-weight:700;font-size:12px;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}
.primary{background:linear-gradient(110deg,#218bff,#0863ed);color:#fff}
.secondary{background:#fff;border:1px solid #d8e5f8;color:#1769cb}
.btn:hover{filter:brightness(.97)}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
.stat{background:#fff;border:1px solid var(--border);border-radius:14px;padding:19px;display:flex;align-items:center;gap:14px}
.stat-icon{height:50px;width:50px;flex-shrink:0;border-radius:14px;display:grid;place-items:center;color:#fff;font-size:21px}
.blue{background:linear-gradient(135deg,#35aaff,#0872ed)}
.yellow{background:linear-gradient(135deg,#ffd044,#ffa500)}
.purple{background:linear-gradient(135deg,#b879ff,#8742e9)}
.green{background:linear-gradient(135deg,#43dca5,#12a66b)}
.stat p{font-size:11px;color:#7186a7;margin:0}
.stat strong{display:block;font-size:29px;margin-top:5px}
.panel{background:#fff;border:1px solid var(--border);border-radius:15px;padding:21px}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-bottom:20px}
.panel h2{font-size:16px;margin:0}
.panel h2 i{color:var(--blue);margin-right:7px}
.count{background:#edf5ff;color:#1769cb;border-radius:8px;padding:9px 12px;font-size:11px;font-weight:700}
.filters{display:flex;flex-wrap:wrap;gap:10px;background:#f9fbff;border:1px solid #e9f0fa;padding:14px;border-radius:11px;margin-bottom:18px}
.filters input{flex:1;min-width:180px}
.filters input,.filters select{padding:11px 13px;border:1px solid #e1eaf7;border-radius:9px;outline:none;background:#fff;color:var(--text);font-size:12px}
.table-wrap{overflow-x:auto;border:1px solid #e5edf8;border-radius:11px}
table{width:100%;min-width:950px;border-collapse:collapse;font-size:11px}
thead{background:linear-gradient(100deg,#f0f5fd,#f8faff)}
th{text-align:left;padding:15px 12px;color:#304a70;font-size:10px;white-space:nowrap}
td{padding:15px 12px;border-top:1px solid #eaf0f8;color:#526b90;vertical-align:middle}
tbody tr:hover{background:#f5f9ff}
.cust{display:flex;align-items:center;gap:10px}
.avatar{width:39px;height:39px;flex-shrink:0;border-radius:11px;background:linear-gradient(135deg,#35aaff,#0872ed);display:grid;place-items:center;color:#fff;font-weight:800}
.cust strong{color:#263e61;font-size:12px}
.cust small{display:block;margin-top:4px;color:#8a9ab4}
.wa{color:#009c58;font-weight:700}
.pill{display:inline-block;padding:7px 10px;border-radius:20px;background:#eaf3ff;color:#1769cb;font-weight:800}
.pill.gray{background:#f0f4fa;color:#8295b2}
.pill.ok{background:#e8fff4;color:#08764d}
.pill.warn{background:#fff6dd;color:#9b6500}
.actions{display:flex;gap:6px;align-items:center}
.action{border:0;border-radius:7px;width:34px;height:34px;display:inline-grid;place-items:center;cursor:pointer;position:relative;z-index:2;pointer-events:auto}
.view{background:#eaf3ff;color:#0875f9}
.edit{background:#f0e9ff;color:#8352d9}
.del{background:#fff0f0;color:#e14b55}
.empty{text-align:center;padding:42px;color:#8b9db7}
.pagination{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-top:18px;color:#7186a7;font-size:11px}
.pages{display:flex;gap:5px}
.pages a,.pages span{min-width:32px;height:32px;display:grid;place-items:center;border:1px solid #e1eaf7;border-radius:7px;padding:0 7px}
.pages .current{background:#147df9;color:#fff;border-color:#147df9}
.alert{padding:14px 16px;border-radius:10px;margin-bottom:18px;font-size:12px}
.alert.success{background:#e8fff4;border:1px solid #b9f0d8;color:#08764d}
.alert.error{background:#fff0f1;border:1px solid #ffd2d5;color:#b42332}
.notice{margin-bottom:18px;padding:14px 16px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:10px;font-size:12px}
.footer{text-align:center;color:#8799b4;font-size:11px;margin-top:22px}
.modal-backdrop{display:none;position:fixed;inset:0;z-index:9999;background:rgba(9,25,55,.58);align-items:center;justify-content:center;padding:20px}
.modal-backdrop.show{display:flex}
.modal{background:#fff;border-radius:18px;padding:28px;max-width:420px;width:100%;text-align:center}
.modal-icon{background:#fff0f0;color:#e14b55;width:60px;height:60px;border-radius:18px;display:grid;place-items:center;margin:0 auto 16px;font-size:24px}
.modal h3{margin:0 0 10px}
.modal p{font-size:13px;line-height:1.8;color:#7186a7}
.modal-actions{display:flex;gap:10px;margin-top:22px}
.modal-actions button{flex:1;border:0;border-radius:9px;padding:12px;font-weight:700}
.cancel{background:#f0f4fa;color:#526b90}
.danger{background:#e14b55;color:#fff}
@media(max-width:1250px){.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:850px){.main{margin-left:0}.content{padding:17px}.topbar{padding:0 17px}.stats{gap:10px}}
@media(max-width:520px){.content{padding:12px}.topbar{height:67px;padding:0 12px}.top-search{display:none}.heading h1{font-size:20px}.stat{padding:12px;gap:9px}.stat-icon{height:40px;width:40px;font-size:17px}.stat strong{font-size:23px}.panel{padding:13px}.filters input{flex-basis:100%}}
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
<header class="topbar">
    <button type="button" class="menu-toggle" id="menuToggle" aria-label="Buka menu">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="top-search"><i class="fa-solid fa-magnifying-glass"></i> &nbsp;Manajemen data pelanggan</div>
    <div class="spacer"></div>
    <?php
    $profileHeader = __DIR__ . '/includes/profil_header.php';
    if (is_file($profileHeader)) require $profileHeader;
    ?>
</header>

<section class="content">
<div class="heading">
    <div class="heading-left">
        <div class="heading-icon"><i class="fa-solid fa-user-group"></i></div>
        <div>
            <h1>Data Pelanggan</h1>
            <p>Kelola pelanggan, akun login, dan riwayat servis motor.</p>
        </div>
    </div>

    <div class="heading-actions">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= pelE($_SESSION['csrf_pelanggan_admin']) ?>">
            <input type="hidden" name="aksi" value="impor">
            <button class="btn secondary" type="submit"><i class="fa-solid fa-file-import"></i> Impor dari Servis</button>
        </form>
        <a class="btn secondary" href="admin_aktivasi_pelanggan.php"><i class="fa-solid fa-key"></i> Aktivasi Akun</a>
        <a class="btn primary" href="tambah_pelanggan.php"><i class="fa-solid fa-plus"></i> Tambah Pelanggan</a>
    </div>
</div>

<?php if ($flash): ?>
<div class="alert <?= $flashType === 'error' ? 'error' : 'success' ?>">
    <i class="fa-solid fa-circle-<?= $flashType === 'error' ? 'exclamation' : 'check' ?>"></i>
    <?= pelE($flash) ?>
</div>
<?php endif; ?>

<?php if (!$hasPassword): ?>
<div class="notice">
    <i class="fa-solid fa-circle-info"></i>
    Kolom <strong>password</strong> belum ditemukan pada tabel pelanggan. Status akun ditampilkan sebagai belum aktif.
</div>
<?php endif; ?>

<div class="stats">
    <div class="stat"><div class="stat-icon blue"><i class="fa-solid fa-users"></i></div><div><p>Total Pelanggan</p><strong><?= $totalCustomers ?></strong></div></div>
    <div class="stat"><div class="stat-icon yellow"><i class="fa-solid fa-user-plus"></i></div><div><p>Baru Bulan Ini</p><strong><?= $newMonth ?></strong></div></div>
    <div class="stat"><div class="stat-icon purple"><i class="fa-solid fa-wrench"></i></div><div><p>Sedang Diservis</p><strong><?= $active ?></strong></div></div>
    <div class="stat"><div class="stat-icon green"><i class="fa-solid fa-key"></i></div><div><p>Belum Punya Akun</p><strong><?= $pending ?></strong></div></div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h2><i class="fa-solid fa-list"></i> Daftar Pelanggan</h2>
            <p>Riwayat servis dipertahankan agar data transaksi tetap aman.</p>
        </div>
        <span class="count"><?= $total ?> data ditemukan</span>
    </div>

    <form method="GET" class="filters">
        <input type="search" name="cari" placeholder="Cari nama, telepon, email, alamat..." value="<?= pelE($search) ?>">
        <select name="urut">
            <option value="terbaru" <?= $sort === 'terbaru' ? 'selected' : '' ?>>Terbaru</option>
            <option value="nama" <?= $sort === 'nama' ? 'selected' : '' ?>>Nama A–Z</option>
            <option value="servis" <?= $sort === 'servis' ? 'selected' : '' ?>>Servis terbanyak</option>
            <option value="terakhir" <?= $sort === 'terakhir' ? 'selected' : '' ?>>Servis terakhir</option>
        </select>
        <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        <a class="btn secondary" href="pelanggan.php">Reset</a>
    </form>

    <div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>No</th><th>Pelanggan</th><th>WhatsApp</th><th>Alamat</th>
            <th>Jumlah Servis</th><th>Terakhir</th><th>Akun Login</th><th>Aksi</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $index => $row): ?>
        <tr>
            <td><?= $offset + $index + 1 ?></td>
            <td>
                <div class="cust">
                    <span class="avatar"><?= pelE(pelInitial((string)$row['nama'])) ?></span>
                    <span>
                        <strong><?= pelE($row['nama']) ?></strong>
                        <small><?= pelE($row['email'] ?: 'Email belum diisi') ?></small>
                    </span>
                </div>
            </td>
            <td>
                <?php $phone = preg_replace('/\D+/', '', (string)$row['telepon']); ?>
                <a class="wa" target="_blank" rel="noopener noreferrer" href="https://wa.me/<?= pelE($phone) ?>">
                    <i class="fa-brands fa-whatsapp"></i> <?= pelE($row['telepon']) ?>
                </a>
            </td>
            <td><?= pelE($row['alamat'] ?: '-') ?></td>
            <td><span class="pill <?= (int)$row['jumlah_servis'] === 0 ? 'gray' : '' ?>"><?= (int)$row['jumlah_servis'] ?></span></td>
            <td><?= pelE(pelDate($row['terakhir_servis'])) ?></td>
            <td>
                <?php if ($hasPassword && !empty($row['password'])): ?>
                    <span class="pill ok">Aktif</span>
                <?php else: ?>
                    <span class="pill warn">Belum aktif</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="actions">
                    <a class="action view" title="Detail" href="detail_pelanggan.php?id=<?= (int)$row['id'] ?>"><i class="fa-solid fa-eye"></i></a>
                    <a class="action edit" title="Edit" href="edit_pelanggan.php?id=<?= (int)$row['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                    <button class="action del btn-delete" type="button" title="Hapus" data-id="<?= (int)$row['id'] ?>" data-nama="<?= pelE($row['nama']) ?>"><i class="fa-solid fa-trash"></i></button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
        <tr><td colspan="8" class="empty"><i class="fa-regular fa-folder-open"></i><br>Data pelanggan tidak ditemukan.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <?php if ($total > 0): ?>
    <div class="pagination">
        <span>Menampilkan <?= $offset + 1 ?>–<?= $offset + count($rows) ?> dari <?= $total ?> pelanggan</span>
        <div class="pages">
            <?php if ($page > 1): ?><a href="<?= pelE(pelUrl($page - 1, $search, $sort)) ?>">&lsaquo;</a><?php endif; ?>
            <?php for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++): ?>
                <?php if ($number === $page): ?>
                    <span class="current"><?= $number ?></span>
                <?php else: ?>
                    <a href="<?= pelE(pelUrl($number, $search, $sort)) ?>"><?= $number ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pages): ?><a href="<?= pelE(pelUrl($page + 1, $search, $sort)) ?>">&rsaquo;</a><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="footer">&copy; <?= date('Y') ?> ServisKu — Sistem Manajemen Bengkel Motor</div>
</section>
</main>

<div class="modal-backdrop" id="modalHapus" role="dialog" aria-modal="true" aria-labelledby="hapusTitle">
    <form class="modal" method="POST" action="pelanggan.php">
        <input type="hidden" name="csrf_token" value="<?= pelE($_SESSION['csrf_pelanggan_admin']) ?>">
        <input type="hidden" name="aksi" value="hapus">
        <input type="hidden" name="hapus_id" id="hapusId">

        <div class="modal-icon"><i class="fa-solid fa-trash-can"></i></div>
        <h3 id="hapusTitle">Hapus pelanggan?</h3>
        <p>Data pelanggan <strong id="hapusNama"></strong> akan dihapus jika tidak memiliki riwayat servis.</p>
        <div class="modal-actions">
            <button type="button" class="cancel" id="batalHapus">Batal</button>
            <button type="submit" class="danger">Ya, Hapus</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('appSidebar')
        || document.getElementById('sidebar')
        || document.querySelector('.sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });

        document.addEventListener('click', function (event) {
            if (window.innerWidth <= 850 &&
                !sidebar.contains(event.target) &&
                !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
            }
        });
    }

    const modal = document.getElementById('modalHapus');
    const idInput = document.getElementById('hapusId');
    const nameText = document.getElementById('hapusNama');
    const cancel = document.getElementById('batalHapus');

    document.querySelectorAll('.btn-delete').forEach(function (button) {
        button.addEventListener('click', function () {
            idInput.value = button.dataset.id || '';
            nameText.textContent = button.dataset.nama || '';
            modal.classList.add('show');
        });
    });

    cancel.addEventListener('click', function () {
        modal.classList.remove('show');
    });

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            modal.classList.remove('show');
        }
    });
});
</script>
</body>
</html>