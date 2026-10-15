<?php
/**
 * ==========================================================
 * SERVISKU - PENDAFTARAN SERVIS BARU
 * ==========================================================
 *
 * FITUR:
 * - Pelanggan Terdaftar
 * - Pelanggan Baru
 * - Pencarian pelanggan
 * - Simpan pelanggan baru otomatis
 * - Hubungkan servis dengan id_pelanggan
 * - Simpan riwayat servis
 * - Transaksi database
 * - CSRF Protection
 * - Responsive Blue Navy
 *
 * DATABASE:
 * - pelanggan
 * - servis
 * - riwayat_servis
 *
 * ==========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
   KONEKSI DATABASE
========================================================== */

require_once __DIR__ . '/config/koneksi.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi database PDO tidak ditemukan.');
}

$pdo->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

/* ==========================================================
   CEK LOGIN ADMIN / PETUGAS
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
   CSRF TOKEN
========================================================== */

if (empty($_SESSION['csrf_daftar'])) {
    $_SESSION['csrf_daftar'] = bin2hex(
        random_bytes(32)
    );
}

/* ==========================================================
   HELPER
========================================================== */

function sv_escape($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function sv_panjang($teks)
{
    return function_exists('mb_strlen')
        ? mb_strlen($teks, 'UTF-8')
        : strlen($teks);
}

/**
 * Normalisasi nomor WhatsApp ke format 628xxx.
 */
function sv_normalisasi_wa($nomor)
{
    $nomor = preg_replace(
        '/[^0-9+]/',
        '',
        trim((string) $nomor)
    );

    if (str_starts_with($nomor, '+62')) {

        $nomor = substr($nomor, 1);

    } elseif (str_starts_with($nomor, '0')) {

        $nomor = '62' . substr($nomor, 1);

    } elseif (str_starts_with($nomor, '8')) {

        $nomor = '62' . $nomor;

    }

    if (!preg_match('/^62[0-9]{8,13}$/', $nomor)) {
        throw new RuntimeException(
            'Nomor WhatsApp tidak valid. Contoh: 081234567890.'
        );
    }

    return $nomor;
}

/**
 * Validasi tanggal estimasi.
 */
function sv_validasi_tanggal($input)
{
    if ($input === '') {
        return null;
    }

    $tanggal = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $input
    );

    if (
        !$tanggal ||
        $tanggal->format('Y-m-d\TH:i') !== $input
    ) {
        throw new RuntimeException(
            'Format estimasi selesai tidak valid.'
        );
    }

    return $tanggal->format('Y-m-d H:i:s');
}

/* ==========================================================
   DATA FORM
========================================================== */

$kolom = [
    'mode_pelanggan',
    'id_pelanggan',
    'nama_pelanggan',
    'telepon',
    'email',
    'alamat',
    'nomor_polisi',
    'jenis_motor',
    'keluhan',
    'estimasi_selesai',
    'catatan_petugas'
];

$nilai = [];

foreach ($kolom as $kol) {

    $nilai[$kol] = trim(
        (string) ($_POST[$kol] ?? '')
    );

}

if ($nilai['mode_pelanggan'] === '') {
    $nilai['mode_pelanggan'] = 'terdaftar';
}

$pesan = '';
$gagal = false;

/* ==========================================================
   AMBIL DATA PELANGGAN
========================================================== */

$dataPelanggan = [];

try {

    $stmtPelanggan = $pdo->query(
        "SELECT
            id,
            nama,
            telepon,
            email,
            alamat
         FROM pelanggan
         ORDER BY nama ASC, id DESC"
    );

    $dataPelanggan = $stmtPelanggan->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $gagal = true;

    $pesan = 'Gagal mengambil data pelanggan.';

}

/* ==========================================================
   PROSES SIMPAN SERVIS
========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /* --------------------------------------------------
           VALIDASI CSRF
        -------------------------------------------------- */

        $token = (string) (
            $_POST['csrf_token'] ?? ''
        );

        if (
            $token === '' ||
            !hash_equals(
                $_SESSION['csrf_daftar'],
                $token
            )
        ) {
            throw new RuntimeException(
                'Token keamanan tidak valid. Silakan muat ulang halaman.'
            );
        }

        /* --------------------------------------------------
           VALIDASI MODE PELANGGAN
        -------------------------------------------------- */

        $mode = $nilai['mode_pelanggan'];

        if (!in_array(
            $mode,
            ['terdaftar', 'baru'],
            true
        )) {
            throw new RuntimeException(
                'Jenis pelanggan tidak valid.'
            );
        }

        /* --------------------------------------------------
           DATA KENDARAAN
        -------------------------------------------------- */

        $nomorPolisi = strtoupper(
            trim($nilai['nomor_polisi'])
        );

        $jenisMotor = trim(
            $nilai['jenis_motor']
        );

        $keluhan = trim(
            $nilai['keluhan']
        );

        $catatan = trim(
            $nilai['catatan_petugas']
        );

        if (
            $nomorPolisi === '' ||
            $jenisMotor === '' ||
            $keluhan === ''
        ) {
            throw new RuntimeException(
                'Nomor polisi, jenis motor, dan keluhan wajib diisi.'
            );
        }

        if (
            sv_panjang($nomorPolisi) > 30 ||
            sv_panjang($jenisMotor) > 150
        ) {
            throw new RuntimeException(
                'Nomor polisi atau jenis motor terlalu panjang.'
            );
        }

        $estimasi = sv_validasi_tanggal(
            $nilai['estimasi_selesai']
        );

        /* --------------------------------------------------
           MULAI TRANSAKSI
        -------------------------------------------------- */

        $pdo->beginTransaction();

        $idPelanggan = null;
        $namaPelanggan = '';
        $nomorWa = '';

        /* ==================================================
           MODE: PELANGGAN TERDAFTAR
        ================================================== */

        if ($mode === 'terdaftar') {

            $idPelanggan = filter_var(
                $nilai['id_pelanggan'],
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1
                    ]
                ]
            );

            if (!$idPelanggan) {
                throw new RuntimeException(
                    'Silakan pilih pelanggan terdaftar.'
                );
            }

            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    nama,
                    telepon
                 FROM pelanggan
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $idPelanggan
            ]);

            $pelanggan = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$pelanggan) {
                throw new RuntimeException(
                    'Pelanggan tidak ditemukan.'
                );
            }

            $idPelanggan = (int) $pelanggan['id'];

            $namaPelanggan = trim(
                (string) $pelanggan['nama']
            );

            $nomorWa = sv_normalisasi_wa(
                $pelanggan['telepon']
            );

        }

        /* ==================================================
           MODE: PELANGGAN BARU
        ================================================== */

        else {

            $namaPelanggan = trim(
                $nilai['nama_pelanggan']
            );

            $nomorWa = sv_normalisasi_wa(
                $nilai['telepon']
            );

            $email = trim(
                $nilai['email']
            );

            $alamat = trim(
                $nilai['alamat']
            );

            if ($namaPelanggan === '') {
                throw new RuntimeException(
                    'Nama pelanggan wajib diisi.'
                );
            }

            if (sv_panjang($namaPelanggan) > 150) {
                throw new RuntimeException(
                    'Nama pelanggan terlalu panjang.'
                );
            }

            if (
                $email !== '' &&
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                throw new RuntimeException(
                    'Format email tidak valid.'
                );
            }

            /* ----------------------------------------------
               CEK NOMOR SUDAH TERDAFTAR
            ---------------------------------------------- */

            /*
             * Mendukung nomor yang tersimpan sebagai:
             * 0812...
             * 62812...
             * +62812...
             */

            $nomorLokal = '0' . substr(
                $nomorWa,
                2
            );

            $nomorPlus = '+' . $nomorWa;

            $cek = $pdo->prepare(
                "SELECT
                    id,
                    nama
                 FROM pelanggan
                 WHERE telepon IN (?, ?, ?)
                 LIMIT 1"
            );

            $cek->execute([
                $nomorWa,
                $nomorLokal,
                $nomorPlus
            ]);

            $pelangganAda = $cek->fetch(
                PDO::FETCH_ASSOC
            );

            if ($pelangganAda) {

                throw new RuntimeException(
                    'Nomor WhatsApp sudah terdaftar atas nama ' .
                    $pelangganAda['nama'] .
                    '. Silakan gunakan pilihan Pelanggan Terdaftar.'
                );

            }

            /* ----------------------------------------------
               SIMPAN PELANGGAN BARU
            ---------------------------------------------- */

            $insertPelanggan = $pdo->prepare(
                "INSERT INTO pelanggan (
                    nama,
                    telepon,
                    email,
                    alamat
                ) VALUES (?, ?, ?, ?)"
            );

            $insertPelanggan->execute([
                $namaPelanggan,
                $nomorWa,
                $email !== '' ? $email : null,
                $alamat !== '' ? $alamat : null
            ]);

            $idPelanggan = (int) $pdo->lastInsertId();

            if ($idPelanggan <= 0) {
                throw new RuntimeException(
                    'Gagal mendapatkan ID pelanggan baru.'
                );
            }

        }

        /* ==================================================
           GENERATE NOMOR SERVIS
        ================================================== */

        $nomorServis =
            'SRV-' .
            date('ymd') .
            '-' .
            strtoupper(
                bin2hex(random_bytes(4))
            );

        /* ==================================================
           SIMPAN SERVIS
        ================================================== */

        /*
         * id_pelanggan digunakan untuk menghubungkan
         * servis dengan akun pelanggan.
         *
         * nama_pelanggan dan telepon tetap disimpan
         * agar kompatibel dengan data_servis.php.
         */

        $insertServis = $pdo->prepare(
            "INSERT INTO servis (
                id_pelanggan,
                nomor_servis,
                nama_pelanggan,
                telepon,
                nomor_polisi,
                jenis_motor,
                keluhan,
                status,
                estimasi_selesai,
                catatan_petugas
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $insertServis->execute([
            $idPelanggan,
            $nomorServis,
            $namaPelanggan,
            $nomorWa,
            $nomorPolisi,
            $jenisMotor,
            $keluhan,
            'Antre',
            $estimasi,
            $catatan
        ]);

        $idServis = (int) $pdo->lastInsertId();

        if ($idServis <= 0) {
            throw new RuntimeException(
                'Gagal mendapatkan ID servis.'
            );
        }

        /* ==================================================
           SIMPAN RIWAYAT SERVIS
        ================================================== */

        $petugas = $_SESSION['user_id']
            ?? $_SESSION['id_user']
            ?? null;

        $petugas = filter_var(
            $petugas,
            FILTER_VALIDATE_INT
        );

        if (!$petugas) {
            $petugas = null;
        }

        $insertRiwayat = $pdo->prepare(
            "INSERT INTO riwayat_servis (
                id_servis,
                status_baru,
                keterangan,
                dibuat_oleh
            ) VALUES (?, ?, ?, ?)"
        );

        $insertRiwayat->execute([
            $idServis,
            'Antre',
            'Servis baru didaftarkan melalui halaman Servis Baru.',
            $petugas
        ]);

        /* ==================================================
           COMMIT TRANSAKSI
        ================================================== */

        $pdo->commit();

        $_SESSION['flash_daftar'] =
            'Servis berhasil didaftarkan! Nomor servis: ' .
            $nomorServis;

        $_SESSION['csrf_daftar'] = bin2hex(
            random_bytes(32)
        );

        header(
            'Location: daftar.php?berhasil=1'
        );

        exit;

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $gagal = true;

        if ($e instanceof PDOException) {

            error_log(
                'ServisKu daftar.php: ' .
                $e->getMessage()
            );

            $pesan =
                'Gagal menyimpan data. Periksa struktur tabel ' .
                'pelanggan, servis, dan riwayat_servis.';

        } else {

            $pesan = $e->getMessage();

        }

    }

}

/* ==========================================================
   FLASH SUCCESS
========================================================== */

if (
    isset($_GET['berhasil']) &&
    !empty($_SESSION['flash_daftar'])
) {

    $pesan = $_SESSION['flash_daftar'];

    $gagal = false;

    unset(
        $_SESSION['flash_daftar']
    );

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

    <title>Servis Baru - ServisKu</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        /* ==================================================
           GLOBAL
        ================================================== */

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

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        /* ==================================================
           LAYOUT
        ================================================== */

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            padding: 30px;
        }

        .sv-container {
            max-width: 1150px;
            margin: 0 auto;
        }

        /* ==================================================
           TOPBAR
        ================================================== */

        .sv-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            margin-bottom: 25px;
        }

        .sv-topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sv-topbar h2 {
            margin: 0;

            font-size: 22px;
            font-weight: 800;

            color: #102a58;
        }

        .sv-topbar p {
            margin: 5px 0 0;

            font-size: 13px;
            color: #64748b;
        }

        .sv-topbar-badge {
            display: flex;
            align-items: center;
            gap: 8px;

            padding: 10px 15px;

            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;

            font-size: 12px;
            font-weight: 700;
            color: #2563eb;
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
        }

        /* ==================================================
           HERO
        ================================================== */

        .sv-hero {
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

            box-shadow:
                0 12px 30px rgba(15, 35, 75, .15);
        }

        .sv-hero::after {
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

        .sv-hero-content {
            position: relative;
            z-index: 1;
        }

        .sv-badge {
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

        .sv-hero h1 {
            margin: 0 0 12px;

            color: white;

            font-size: 29px;
            font-weight: 800;
            line-height: 1.3;
        }

        .sv-hero p {
            max-width: 580px;
            margin: 0;

            color: #dbeafe;

            font-size: 14px;
            line-height: 1.8;
        }

        .sv-hero-icon {
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

        /* ==================================================
           CARD
        ================================================== */

        .sv-card {
            overflow: hidden;

            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;

            box-shadow:
                0 8px 30px rgba(15, 35, 75, .05);
        }

        .sv-card-header {
            display: flex;
            align-items: center;
            gap: 15px;

            padding: 25px 30px;

            border-bottom: 1px solid #e2e8f0;
        }

        .sv-card-icon {
            display: grid;
            place-items: center;

            width: 48px;
            height: 48px;

            background: #eff6ff;
            color: #2563eb;

            border-radius: 13px;

            font-size: 21px;
        }

        .sv-card-header h2 {
            margin: 0 0 5px;

            color: #102a58;
            font-size: 18px;
            font-weight: 800;
        }

        .sv-card-header p {
            margin: 0;

            color: #64748b;
            font-size: 13px;
        }

        .sv-form {
            padding: 30px;
        }

        /* ==================================================
           SECTION
        ================================================== */

        .sv-section {
            margin-bottom: 30px;
        }

        .sv-section-title {
            display: flex;
            align-items: center;
            gap: 12px;

            margin: 0 0 22px;

            color: #102a58;
            font-size: 15px;
            font-weight: 800;
        }

        .sv-section-number {
            display: grid;
            place-items: center;

            width: 32px;
            height: 32px;

            background: #eff6ff;
            color: #2563eb;

            border-radius: 10px;

            font-size: 13px;
            font-weight: 800;
        }

        .sv-divider {
            height: 1px;
            margin: 30px 0;

            border: none;
            background: #e2e8f0;
        }

        /* ==================================================
           PILIHAN JENIS PELANGGAN
        ================================================== */

        .sv-customer-switch {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 14px;

            margin-bottom: 25px;
        }

        .sv-customer-option {
            display: flex;
            align-items: center;
            gap: 13px;

            padding: 19px;

            border: 2px solid #e2e8f0;
            border-radius: 14px;

            background: #f8fafc;

            text-align: left;

            transition: .2s;
        }

        .sv-customer-option:hover {
            border-color: #93c5fd;
            background: #eff6ff;
        }

        .sv-customer-option.active {
            border-color: #2563eb;
            background: #eff6ff;

            box-shadow:
                0 5px 18px rgba(37, 99, 235, .08);
        }

        .sv-option-icon {
            flex-shrink: 0;

            width: 43px;
            height: 43px;

            display: grid;
            place-items: center;

            border-radius: 11px;

            background: #dbeafe;
            color: #2563eb;

            font-size: 18px;
        }

        .sv-customer-option.active .sv-option-icon {
            background: #2563eb;
            color: white;
        }

        .sv-option-text strong {
            display: block;

            color: #102a58;
            font-size: 13px;
            font-weight: 800;
        }

        .sv-option-text span {
            display: block;
            margin-top: 5px;

            color: #64748b;
            font-size: 11px;
            line-height: 1.5;
        }

        .sv-customer-panel {
            padding: 22px;

            border: 1px solid #dbeafe;
            border-radius: 14px;

            background: #fbfdff;
        }

        .sv-customer-panel[hidden] {
            display: none !important;
        }

        /* ==================================================
           FORM
        ================================================== */

        .sv-grid {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 22px;
        }

        .sv-field {
            min-width: 0;
        }

        .sv-field label {
            display: block;
            margin-bottom: 9px;

            color: #334155;

            font-size: 13px;
            font-weight: 700;
        }

        .sv-required {
            color: #ef4444;
        }

        .sv-field input,
        .sv-field textarea,
        .sv-field select {
            display: block;
            width: 100%;

            padding: 14px 16px;

            border: 1px solid #dbe3f0;
            border-radius: 11px;

            background: #f8fafc;
            color: #1e293b;

            font-size: 14px;

            outline: none;

            transition: .2s;
        }

        .sv-field input:focus,
        .sv-field textarea:focus,
        .sv-field select:focus {
            border-color: #2563eb;
            background: white;

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, .1);
        }

        .sv-field input::placeholder,
        .sv-field textarea::placeholder {
            color: #94a3b8;
        }

        .sv-field textarea {
            min-height: 115px;
            resize: vertical;
            line-height: 1.6;
        }

        .sv-field input[readonly] {
            background: #edf2f9;
            color: #475569;
            cursor: not-allowed;
        }

        .sv-full {
            grid-column: 1 / -1;
        }

        .sv-hint {
            margin-top: 8px;

            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        /* ==================================================
           PENCARIAN PELANGGAN
        ================================================== */

        .sv-search-box {
            position: relative;
        }

        .sv-search-box > i {
            position: absolute;
            left: 16px;
            top: 50%;

            transform: translateY(-50%);

            color: #94a3b8;
        }

        .sv-search-box input {
            padding-left: 44px;
        }

        .sv-customer-results {
            max-height: 230px;
            overflow-y: auto;

            margin-top: 12px;

            border: 1px solid #e2e8f0;
            border-radius: 11px;

            background: white;
        }

        .sv-customer-item {
            display: flex;
            align-items: center;
            gap: 12px;

            width: 100%;

            padding: 13px 15px;

            border: 0;
            border-bottom: 1px solid #f1f5f9;

            background: white;

            text-align: left;
            cursor: pointer;
        }

        .sv-customer-item:last-child {
            border-bottom: 0;
        }

        .sv-customer-item:hover,
        .sv-customer-item.selected {
            background: #eff6ff;
        }

        .sv-customer-avatar {
            flex-shrink: 0;

            width: 38px;
            height: 38px;

            display: grid;
            place-items: center;

            border-radius: 11px;

            background: #dbeafe;
            color: #2563eb;

            font-size: 15px;
        }

        .sv-customer-detail strong {
            display: block;

            color: #102a58;
            font-size: 12px;
        }

        .sv-customer-detail span {
            display: block;
            margin-top: 4px;

            color: #64748b;
            font-size: 11px;
        }

        .sv-customer-empty {
            padding: 20px;

            text-align: center;

            color: #94a3b8;
            font-size: 12px;
        }

        .sv-selected-customer {
            display: flex;
            align-items: center;
            gap: 13px;

            margin-top: 15px;
            padding: 16px;

            border: 1px solid #bbf7d0;
            border-radius: 12px;

            background: #f0fdf4;
        }

        .sv-selected-customer[hidden] {
            display: none !important;
        }

        .sv-selected-customer > i {
            color: #16a34a;
            font-size: 22px;
        }

        .sv-selected-customer strong {
            display: block;

            color: #166534;
            font-size: 13px;
        }

        .sv-selected-customer span {
            display: block;
            margin-top: 5px;

            color: #15803d;
            font-size: 12px;
        }

        /* ==================================================
           BUTTON
        ================================================== */

        .sv-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 12px;

            margin-top: 30px;
            padding-top: 25px;

            border-top: 1px solid #e2e8f0;
        }

        .sv-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 14px 24px;

            border: 1px solid transparent;
            border-radius: 11px;

            font-size: 14px;
            font-weight: 700;

            text-decoration: none;

            transition: .2s;
        }

        .sv-btn:hover {
            transform: translateY(-2px);
        }

        .sv-btn-primary {
            background: linear-gradient(
                120deg,
                #2563eb,
                #1d4ed8
            );

            color: white;

            box-shadow:
                0 5px 15px rgba(37, 99, 235, .2);
        }

        .sv-btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border-color: #e2e8f0;
        }

        /* ==================================================
           ALERT
        ================================================== */

        .sv-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;

            padding: 17px 20px;
            margin-bottom: 25px;

            border: 1px solid transparent;
            border-radius: 13px;

            font-size: 14px;
            line-height: 1.6;
        }

        .sv-alert-success {
            background: #ecfdf5;
            color: #166534;
            border-color: #bbf7d0;
        }

        .sv-alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .sv-info {
            display: flex;
            align-items: center;
            gap: 12px;

            margin-top: 20px;
            padding: 17px 20px;

            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;

            color: #1e40af;
            font-size: 12px;
            line-height: 1.7;
        }

        /* ==================================================
           RESPONSIVE
        ================================================== */

        @media (max-width: 850px) {

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            #sidebarToggle {
                display: inline-grid;
            }

            .sv-topbar-badge {
                display: none;
            }

        }

        @media (max-width: 768px) {

            .sv-hero {
                padding: 25px;
                border-radius: 16px;
            }

            .sv-hero h1 {
                font-size: 23px;
            }

            .sv-hero-icon {
                display: none;
            }

            .sv-grid,
            .sv-customer-switch {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .sv-card-header {
                padding: 20px;
            }

            .sv-form {
                padding: 20px;
            }

            .sv-customer-panel {
                padding: 16px;
            }

            .sv-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .sv-btn {
                width: 100%;
            }

        }

    </style>

</head>

<body>

    <!-- SIDEBAR SERVISKU -->
    <?php
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="main-content">

        <div class="sv-container">

            <!-- ==========================================
                 TOPBAR
            ========================================== -->

            <div class="sv-topbar">

                <div class="sv-topbar-left">

                    <button
                        type="button"
                        id="sidebarToggle"
                        aria-label="Buka menu navigasi"
                    >
                        <i class="fa-solid fa-bars"></i>
                    </button>

                    <div>

                        <h2>Servis Baru</h2>

                        <p>
                            Dashboard / Pendaftaran Servis
                        </p>

                    </div>

                </div>

                <div class="sv-topbar-badge">

                    <i class="fa-solid fa-circle-check"></i>

                    Sistem ServisKu

                </div>

            </div>

            <!-- ==========================================
                 HERO
            ========================================== -->

            <div class="sv-hero">

                <div class="sv-hero-content">

                    <span class="sv-badge">

                        <i class="fa-solid fa-motorcycle"></i>

                        SERVISKU • BENGKEL MOTOR

                    </span>

                    <h1>
                        Pendaftaran Servis Baru
                    </h1>

                    <p>

                        Pilih pelanggan yang sudah terdaftar
                        atau daftarkan pelanggan baru.

                        Data servis akan otomatis terhubung
                        dengan akun pelanggan sehingga
                        dapat dilihat melalui dashboard pelanggan.

                    </p>

                </div>

                <div class="sv-hero-icon">

                    <i class="fa-solid fa-motorcycle"></i>

                </div>

            </div>

            <!-- ==========================================
                 ALERT
            ========================================== -->

            <?php if ($pesan !== ''): ?>

                <div class="sv-alert <?= $gagal
                    ? 'sv-alert-error'
                    : 'sv-alert-success'
                ?>">

                    <i class="fa-solid <?= $gagal
                        ? 'fa-circle-exclamation'
                        : 'fa-circle-check'
                    ?>"></i>

                    <div>

                        <?= sv_escape($pesan) ?>

                    </div>

                </div>

            <?php endif; ?>

            <!-- ==========================================
                 FORM CARD
            ========================================== -->

            <div class="sv-card">

                <div class="sv-card-header">

                    <div class="sv-card-icon">

                        <i class="fa-solid fa-clipboard-list"></i>

                    </div>

                    <div>

                        <h2>
                            Form Pendaftaran Servis
                        </h2>

                        <p>

                            Pilih pelanggan terlebih dahulu,
                            kemudian isi informasi kendaraan
                            dan keluhan servis.

                        </p>

                    </div>

                </div>

                <form
                    method="POST"
                    class="sv-form"
                    id="formServis"
                >

                    <!-- CSRF -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= sv_escape(
                            $_SESSION['csrf_daftar']
                        ) ?>"
                    >

                    <!-- MODE -->
                    <input
                        type="hidden"
                        name="mode_pelanggan"
                        id="mode_pelanggan"
                        value="<?= sv_escape(
                            $nilai['mode_pelanggan']
                        ) ?>"
                    >

                    <!-- ID PELANGGAN -->
                    <input
                        type="hidden"
                        name="id_pelanggan"
                        id="id_pelanggan"
                        value="<?= sv_escape(
                            $nilai['id_pelanggan']
                        ) ?>"
                    >

                    <!-- ==================================
                         INFORMASI PELANGGAN
                    ================================== -->

                    <div class="sv-section">

                        <h3 class="sv-section-title">

                            <span class="sv-section-number">
                                01
                            </span>

                            Informasi Pelanggan

                        </h3>

                        <!-- PILIH MODE -->

                        <div class="sv-customer-switch">

                            <button
                                type="button"
                                class="sv-customer-option"
                                id="pilihTerdaftar"
                            >

                                <span class="sv-option-icon">

                                    <i class="fa-solid fa-users"></i>

                                </span>

                                <span class="sv-option-text">

                                    <strong>
                                        Pelanggan Terdaftar
                                    </strong>

                                    <span>

                                        Pilih pelanggan yang sudah
                                        tersimpan di database ServisKu.

                                    </span>

                                </span>

                            </button>

                            <button
                                type="button"
                                class="sv-customer-option"
                                id="pilihBaru"
                            >

                                <span class="sv-option-icon">

                                    <i class="fa-solid fa-user-plus"></i>

                                </span>

                                <span class="sv-option-text">

                                    <strong>
                                        Pelanggan Baru
                                    </strong>

                                    <span>

                                        Daftarkan pelanggan baru
                                        sekaligus servis motornya.

                                    </span>

                                </span>

                            </button>

                        </div>

                        <!-- ==================================
                             PELANGGAN TERDAFTAR
                        ================================== -->

                        <div
                            class="sv-customer-panel"
                            id="panelTerdaftar"
                        >

                            <div class="sv-field">

                                <label for="cariPelanggan">

                                    Cari Pelanggan Terdaftar

                                    <span class="sv-required">*</span>

                                </label>

                                <div class="sv-search-box">

                                    <i class="fa-solid fa-magnifying-glass"></i>

                                    <input
                                        type="search"
                                        id="cariPelanggan"
                                        placeholder="Ketik nama atau nomor WhatsApp..."
                                        autocomplete="off"
                                    >

                                </div>

                                <p class="sv-hint">

                                    Klik nama pelanggan
                                    untuk memilihnya.

                                </p>

                            </div>

                            <div
                                class="sv-customer-results"
                                id="hasilPelanggan"
                            ></div>

                            <div
                                class="sv-selected-customer"
                                id="pelangganTerpilih"
                                hidden
                            >

                                <i class="fa-solid fa-circle-check"></i>

                                <div>

                                    <strong id="namaTerpilih"></strong>

                                    <span id="teleponTerpilih"></span>

                                </div>

                            </div>

                        </div>

                        <!-- ==================================
                             PELANGGAN BARU
                        ================================== -->

                        <div
                            class="sv-customer-panel"
                            id="panelBaru"
                            hidden
                        >

                            <div class="sv-grid">

                                <!-- NAMA -->

                                <div class="sv-field">

                                    <label for="nama_pelanggan">

                                        Nama Pelanggan

                                        <span class="sv-required">*</span>

                                    </label>

                                    <input
                                        type="text"
                                        name="nama_pelanggan"
                                        id="nama_pelanggan"
                                        maxlength="150"
                                        placeholder="Masukkan nama lengkap"
                                        value="<?= sv_escape(
                                            $nilai['nama_pelanggan']
                                        ) ?>"
                                    >

                                </div>

                                <!-- WHATSAPP -->

                                <div class="sv-field">

                                    <label for="telepon">

                                        Nomor WhatsApp

                                        <span class="sv-required">*</span>

                                    </label>

                                    <input
                                        type="tel"
                                        name="telepon"
                                        id="telepon"
                                        maxlength="20"
                                        placeholder="Contoh: 081234567890"
                                        value="<?= sv_escape(
                                            $nilai['telepon']
                                        ) ?>"
                                    >

                                    <p class="sv-hint">

                                        Gunakan nomor WhatsApp aktif pelanggan.

                                    </p>

                                </div>

                                <!-- EMAIL -->

                                <div class="sv-field">

                                    <label for="email">

                                        Email (Opsional)

                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        maxlength="150"
                                        placeholder="contoh@email.com"
                                        value="<?= sv_escape(
                                            $nilai['email']
                                        ) ?>"
                                    >

                                </div>

                                <!-- ALAMAT -->

                                <div class="sv-field">

                                    <label for="alamat">

                                        Alamat (Opsional)

                                    </label>

                                    <textarea
                                        name="alamat"
                                        id="alamat"
                                        rows="3"
                                        placeholder="Alamat pelanggan..."
                                    ><?= sv_escape(
                                        $nilai['alamat']
                                    ) ?></textarea>

                                </div>

                            </div>

                            <div class="sv-info">

                                <i class="fa-solid fa-circle-info"></i>

                                <span>

                                    Pelanggan baru akan otomatis
                                    tersimpan ke tabel pelanggan.

                                    Untuk login ke portal pelanggan,
                                    akun perlu diaktifkan terlebih dahulu.

                                </span>

                            </div>

                        </div>

                    </div>

                    <hr class="sv-divider">

                    <!-- ==================================
                         INFORMASI KENDARAAN
                    ================================== -->

                    <div class="sv-section">

                        <h3 class="sv-section-title">

                            <span class="sv-section-number">
                                02
                            </span>

                            Informasi Kendaraan

                        </h3>

                        <div class="sv-grid">

                            <!-- NOMOR POLISI -->

                            <div class="sv-field">

                                <label for="nomor_polisi">

                                    Nomor Polisi

                                    <span class="sv-required">*</span>

                                </label>

                                <input
                                    type="text"
                                    name="nomor_polisi"
                                    id="nomor_polisi"
                                    maxlength="30"
                                    placeholder="Contoh: M 1234 AB"
                                    value="<?= sv_escape(
                                        $nilai['nomor_polisi']
                                    ) ?>"
                                    required
                                >

                            </div>

                            <!-- JENIS MOTOR -->

                            <div class="sv-field">

                                <label for="jenis_motor">

                                    Jenis Motor

                                    <span class="sv-required">*</span>

                                </label>

                                <input
                                    type="text"
                                    name="jenis_motor"
                                    id="jenis_motor"
                                    maxlength="150"
                                    placeholder="Contoh: Honda Vario 125"
                                    value="<?= sv_escape(
                                        $nilai['jenis_motor']
                                    ) ?>"
                                    required
                                >

                            </div>

                        </div>

                    </div>

                    <hr class="sv-divider">

                    <!-- ==================================
                         DETAIL SERVIS
                    ================================== -->

                    <div class="sv-section">

                        <h3 class="sv-section-title">

                            <span class="sv-section-number">
                                03
                            </span>

                            Detail Servis

                        </h3>

                        <div class="sv-grid">

                            <!-- ESTIMASI -->

                            <div class="sv-field">

                                <label for="estimasi_selesai">

                                    Estimasi Selesai

                                </label>

                                <input
                                    type="datetime-local"
                                    name="estimasi_selesai"
                                    id="estimasi_selesai"
                                    value="<?= sv_escape(
                                        $nilai['estimasi_selesai']
                                    ) ?>"
                                >

                                <p class="sv-hint">

                                    Opsional. Tentukan perkiraan
                                    waktu servis selesai.

                                </p>

                            </div>

                            <!-- STATUS -->

                            <div class="sv-field">

                                <label for="status_awal">

                                    Status Awal Servis

                                </label>

                                <input
                                    type="text"
                                    id="status_awal"
                                    value="Antre"
                                    readonly
                                >

                                <p class="sv-hint">

                                    Status otomatis menjadi Antre.

                                </p>

                            </div>

                            <!-- KELUHAN -->

                            <div class="sv-field sv-full">

                                <label for="keluhan">

                                    Keluhan Pelanggan

                                    <span class="sv-required">*</span>

                                </label>

                                <textarea
                                    name="keluhan"
                                    id="keluhan"
                                    rows="4"
                                    placeholder="Jelaskan keluhan atau kerusakan motor..."
                                    required
                                ><?= sv_escape(
                                    $nilai['keluhan']
                                ) ?></textarea>

                            </div>

                            <!-- CATATAN -->

                            <div class="sv-field sv-full">

                                <label for="catatan_petugas">

                                    Catatan Petugas

                                </label>

                                <textarea
                                    name="catatan_petugas"
                                    id="catatan_petugas"
                                    rows="4"
                                    placeholder="Informasi tambahan untuk mekanik..."
                                ><?= sv_escape(
                                    $nilai['catatan_petugas']
                                ) ?></textarea>

                            </div>

                        </div>

                    </div>

                    <!-- ==================================
                         TOMBOL SIMPAN
                    ================================== -->

                    <div class="sv-actions">

                        <a
                            href="data_servis.php"
                            class="sv-btn sv-btn-secondary"
                        >

                            <i class="fa-solid fa-arrow-left"></i>

                            Kembali

                        </a>

                        <button
                            type="submit"
                            class="sv-btn sv-btn-primary"
                        >

                            <i class="fa-solid fa-floppy-disk"></i>

                            Simpan Servis

                        </button>

                    </div>

                </form>

            </div>

            <!-- ==========================================
                 INFO
            ========================================== -->

            <div class="sv-info">

                <i class="fa-solid fa-circle-info"></i>

                <span>

                    Data pelanggan tersimpan di menu Pelanggan.
                    Servis otomatis terhubung dengan ID pelanggan.
                    Status dan riwayat servis dapat ditampilkan
                    di dashboard pelanggan setelah akun diaktifkan.

                </span>

            </div>

        </div>

    </main>

    <!-- ==========================================
         DATA PELANGGAN DARI PHP
    ========================================== -->

    <script>

        const dataPelanggan = <?= json_encode(
            $dataPelanggan,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES |
            JSON_HEX_TAG |
            JSON_HEX_AMP |
            JSON_HEX_APOS |
            JSON_HEX_QUOT
        ) ?>;

        const modeAwal = <?= json_encode(
            $nilai['mode_pelanggan']
        ) ?>;

        const idAwal = <?= json_encode(
            $nilai['id_pelanggan']
        ) ?>;

    </script>

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                /* ==========================================
                   SIDEBAR
                ========================================== */

                const toggle =
                    document.getElementById(
                        'sidebarToggle'
                    );

                const sidebar =
                    document.getElementById('appSidebar') ||
                    document.getElementById('sidebar') ||
                    document.querySelector('.sidebar');

                const overlay =
                    document.getElementById(
                        'sidebarOverlay'
                    );

                if (toggle && sidebar) {

                    toggle.addEventListener(
                        'click',
                        function () {

                            sidebar.classList.toggle(
                                'open'
                            );

                            if (overlay) {

                                overlay.classList.toggle(
                                    'show',
                                    sidebar.classList.contains('open')
                                );

                            }

                        }
                    );

                }

                /* ==========================================
                   ELEMENT FORM
                ========================================== */

                const inputMode =
                    document.getElementById(
                        'mode_pelanggan'
                    );

                const inputId =
                    document.getElementById(
                        'id_pelanggan'
                    );

                const tombolTerdaftar =
                    document.getElementById(
                        'pilihTerdaftar'
                    );

                const tombolBaru =
                    document.getElementById(
                        'pilihBaru'
                    );

                const panelTerdaftar =
                    document.getElementById(
                        'panelTerdaftar'
                    );

                const panelBaru =
                    document.getElementById(
                        'panelBaru'
                    );

                const inputCari =
                    document.getElementById(
                        'cariPelanggan'
                    );

                const hasilPelanggan =
                    document.getElementById(
                        'hasilPelanggan'
                    );

                const pelangganTerpilih =
                    document.getElementById(
                        'pelangganTerpilih'
                    );

                const namaTerpilih =
                    document.getElementById(
                        'namaTerpilih'
                    );

                const teleponTerpilih =
                    document.getElementById(
                        'teleponTerpilih'
                    );

                const formServis =
                    document.getElementById(
                        'formServis'
                    );

                const namaBaru =
                    document.getElementById(
                        'nama_pelanggan'
                    );

                const teleponBaru =
                    document.getElementById(
                        'telepon'
                    );

                /* ==========================================
                   GANTI MODE
                ========================================== */

                function gantiMode(mode) {

                    inputMode.value = mode;

                    const terdaftar =
                        mode === 'terdaftar';

                    tombolTerdaftar.classList.toggle(
                        'active',
                        terdaftar
                    );

                    tombolBaru.classList.toggle(
                        'active',
                        !terdaftar
                    );

                    panelTerdaftar.hidden =
                        !terdaftar;

                    panelBaru.hidden =
                        terdaftar;

                    namaBaru.required =
                        !terdaftar;

                    teleponBaru.required =
                        !terdaftar;

                }

                tombolTerdaftar.addEventListener(
                    'click',
                    function () {

                        gantiMode('terdaftar');

                    }
                );

                tombolBaru.addEventListener(
                    'click',
                    function () {

                        gantiMode('baru');

                    }
                );

                /* ==========================================
                   RENDER DAFTAR PELANGGAN
                ========================================== */

                function renderPelanggan(keyword = '') {

                    hasilPelanggan.innerHTML = '';

                    const cari =
                        keyword.toLowerCase().trim();

                    const hasil =
                        dataPelanggan.filter(
                            function (p) {

                                const nama = String(
                                    p.nama || ''
                                ).toLowerCase();

                                const telepon = String(
                                    p.telepon || ''
                                ).toLowerCase();

                                return (
                                    nama.includes(cari) ||
                                    telepon.includes(cari)
                                );

                            }
                        );

                    if (hasil.length === 0) {

                        const kosong =
                            document.createElement(
                                'div'
                            );

                        kosong.className =
                            'sv-customer-empty';

                        kosong.textContent =
                            'Pelanggan tidak ditemukan. ' +
                            'Gunakan pilihan Pelanggan Baru.';

                        hasilPelanggan.appendChild(
                            kosong
                        );

                        return;

                    }

                    hasil.forEach(
                        function (pelanggan) {

                            const item =
                                document.createElement(
                                    'button'
                                );

                            item.type = 'button';

                            item.className =
                                'sv-customer-item';

                            if (
                                String(inputId.value) ===
                                String(pelanggan.id)
                            ) {

                                item.classList.add(
                                    'selected'
                                );

                            }

                            const avatar =
                                document.createElement(
                                    'span'
                                );

                            avatar.className =
                                'sv-customer-avatar';

                            const icon =
                                document.createElement(
                                    'i'
                                );

                            icon.className =
                                'fa-solid fa-user';

                            avatar.appendChild(
                                icon
                            );

                            const detail =
                                document.createElement(
                                    'span'
                                );

                            detail.className =
                                'sv-customer-detail';

                            const nama =
                                document.createElement(
                                    'strong'
                                );

                            nama.textContent =
                                pelanggan.nama || '-';

                            const nomor =
                                document.createElement(
                                    'span'
                                );

                            nomor.textContent =
                                pelanggan.telepon || '-';

                            detail.appendChild(
                                nama
                            );

                            detail.appendChild(
                                nomor
                            );

                            item.appendChild(
                                avatar
                            );

                            item.appendChild(
                                detail
                            );

                            item.addEventListener(
                                'click',
                                function () {

                                    pilihPelanggan(
                                        pelanggan
                                    );

                                }
                            );

                            hasilPelanggan.appendChild(
                                item
                            );

                        }
                    );

                }

                /* ==========================================
                   PILIH PELANGGAN
                ========================================== */

                function pilihPelanggan(pelanggan) {

                    inputId.value =
                        pelanggan.id;

                    namaTerpilih.textContent =
                        pelanggan.nama || '-';

                    teleponTerpilih.textContent =
                        pelanggan.telepon || '-';

                    pelangganTerpilih.hidden =
                        false;

                    renderPelanggan(
                        inputCari.value
                    );

                }

                /* ==========================================
                   PENCARIAN
                ========================================== */

                inputCari.addEventListener(
                    'input',
                    function () {

                        renderPelanggan(
                            this.value
                        );

                    }
                );

                /* ==========================================
                   VALIDASI FORM
                ========================================== */

                formServis.addEventListener(
                    'submit',
                    function (event) {

                        if (
                            inputMode.value === 'terdaftar' &&
                            inputId.value === ''
                        ) {

                            event.preventDefault();

                            alert(
                                'Silakan pilih pelanggan terdaftar terlebih dahulu.'
                            );

                            inputCari.focus();

                            return;

                        }

                    }
                );

                /* ==========================================
                   INISIALISASI
                ========================================== */

                gantiMode(
                    modeAwal === 'baru'
                        ? 'baru'
                        : 'terdaftar'
                );

                renderPelanggan();

                if (idAwal !== '') {

                    const pelangganAwal =
                        dataPelanggan.find(
                            function (p) {

                                return String(p.id) ===
                                    String(idAwal);

                            }
                        );

                    if (pelangganAwal) {

                        pilihPelanggan(
                            pelangganAwal
                        );

                    }

                }

            }
        );

    </script>

</body>
</html>