<?php
/**
 * Helper bersama untuk modul Pelanggan.
 * Dipanggil SEBELUM includes/sidebar.php supaya redirect login bekerja
 * (sidebar mencetak HTML, sehingga header() tidak bisa dipakai setelahnya).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/koneksi.php';

/* ===== CEK LOGIN ===== */
if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['id_user']) &&
    !isset($_SESSION['username'])
) {
    header('Location: login.php');
    exit;
}

$namaUser = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'Administrator';
$roleUser = $_SESSION['role'] ?? 'Admin';

/* ===== TABEL PELANGGAN (dibuat otomatis jika belum ada) ===== */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pelanggan (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nama VARCHAR(100) NOT NULL,
        telepon VARCHAR(20) NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        alamat TEXT DEFAULT NULL,
        catatan TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
        updated_at TIMESTAMP NOT NULL DEFAULT current_timestamp()
            ON UPDATE current_timestamp(),
        PRIMARY KEY (id),
        UNIQUE KEY uq_pelanggan_telepon (telepon)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");

/* Ekspresi SQL untuk menyamakan format telepon di tabel servis
   (spasi, strip, titik, dan awalan +62 diubah menjadi 08xxx).
   $alias dipakai agar tidak ambigu saat di-JOIN dengan tabel pelanggan. */
function sqlTeleponServis(string $alias = ''): string
{
    $kolom = ($alias !== '' ? $alias . '.' : '') . 'telepon';

    return "REPLACE(REPLACE(REPLACE(REPLACE($kolom,' ',''),'-',''),'+62','0'),'.','')";
}

/* ===== HELPER UMUM ===== */
function e($value, $default = '-')
{
    $value = ($value === null || $value === '') ? $default : $value;
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalisasiTelepon(string $telepon): string
{
    $telepon = preg_replace('/[\s\-\.\(\)]/', '', $telepon);

    if (str_starts_with($telepon, '+62')) {
        $telepon = '0' . substr($telepon, 3);
    } elseif (str_starts_with($telepon, '62')) {
        $telepon = '0' . substr($telepon, 2);
    }

    return $telepon;
}

function teleponValid(string $telepon): bool
{
    return (bool) preg_match('/^0\d{8,14}$/', $telepon);
}

function linkWhatsapp(string $telepon): string
{
    return 'https://wa.me/62' . ltrim($telepon, '0');
}

function inisial(string $nama): string
{
    $kata = preg_split('/\s+/', trim($nama)) ?: [];
    $hasil = '';

    foreach (array_slice($kata, 0, 2) as $k) {
        $hasil .= mb_strtoupper(mb_substr($k, 0, 1));
    }

    return $hasil !== '' ? $hasil : '?';
}

function formatTanggal($tanggal, bool $denganJam = true): string
{
    if (empty($tanggal)) {
        return '-';
    }

    $ts = strtotime($tanggal);

    return $ts ? date($denganJam ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '-';
}

function statusClass($status): string
{
    return match ($status) {
        'Pengerjaan' => 'pengerjaan',
        'Selesai' => 'selesai',
        default => 'antre'
    };
}

/* ===== FLASH MESSAGE ===== */
function setFlash(string $pesan, string $tipe = 'success'): void
{
    $_SESSION['flash_message'] = $pesan;
    $_SESSION['flash_type'] = $tipe;
}

function ambilFlash(): array
{
    $pesan = $_SESSION['flash_message'] ?? '';
    $tipe = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);

    return [$pesan, $tipe];
}

/* ===== CSRF ===== */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfValid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . e(csrfToken()) . '">';
}

/* ===== VALIDASI FORM PELANGGAN =====
   Mengembalikan [data bersih, daftar error]. $idSendiri dipakai saat edit
   agar nomor telepon milik pelanggan itu sendiri tidak dianggap duplikat. */
function validasiPelanggan(PDO $pdo, array $input, ?int $idSendiri = null): array
{
    $data = [
        'nama' => trim($input['nama'] ?? ''),
        'telepon' => normalisasiTelepon(trim($input['telepon'] ?? '')),
        'email' => trim($input['email'] ?? ''),
        'alamat' => trim($input['alamat'] ?? ''),
        'catatan' => trim($input['catatan'] ?? ''),
    ];
    $errors = [];

    if ($data['nama'] === '') {
        $errors['nama'] = 'Nama pelanggan wajib diisi.';
    } elseif (mb_strlen($data['nama']) > 100) {
        $errors['nama'] = 'Nama maksimal 100 karakter.';
    }

    if ($data['telepon'] === '') {
        $errors['telepon'] = 'Nomor telepon wajib diisi.';
    } elseif (!teleponValid($data['telepon'])) {
        $errors['telepon'] = 'Nomor telepon tidak valid. Contoh: 081234567890.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id FROM pelanggan WHERE telepon = ? AND id <> ? LIMIT 1'
        );
        $stmt->execute([$data['telepon'], $idSendiri ?? 0]);

        if ($stmt->fetchColumn()) {
            $errors['telepon'] = 'Nomor telepon ini sudah terdaftar pada pelanggan lain.';
        }
    }

    if (
        $data['email'] !== '' &&
        (mb_strlen($data['email']) > 100 ||
         !filter_var($data['email'], FILTER_VALIDATE_EMAIL))
    ) {
        $errors['email'] = 'Format email tidak valid.';
    }

    if (mb_strlen($data['alamat']) > 500) {
        $errors['alamat'] = 'Alamat maksimal 500 karakter.';
    }

    if (mb_strlen($data['catatan']) > 500) {
        $errors['catatan'] = 'Catatan maksimal 500 karakter.';
    }

    return [$data, $errors];
}

/* ===== KOTAK PENCARIAN TOPBAR + LAYOUT ===== */
function renderTopbar(string $teks, string $nama, string $role): void
{
    ?>
    <header class="topbar">
        <button type="button" class="menu-toggle" id="sidebarToggle"
                aria-label="Buka menu">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="top-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <span><?= e($teks) ?></span>
        </div>

        <div class="top-spacer"></div>

        <div class="profile">
            <div class="avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <strong><?= e($nama) ?></strong>
                <small><?= e($role) ?></small>
            </div>
        </div>
    </header>
    <?php
}

function renderHead(string $judul): void
{
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($judul) ?> | ServisKu</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet"
 href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="assets/pelanggan.css">
</head>
<body>
    <?php
}
