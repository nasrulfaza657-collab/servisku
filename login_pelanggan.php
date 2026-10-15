<?php
/**
 * ==========================================================
 * SERVISKU - LOGIN PELANGGAN
 * ==========================================================
 *
 * Mendukung nomor WhatsApp:
 * - 081234567890
 * - 6281234567890
 * - +6281234567890
 *
 * Database: db_servis_montor
 * Tabel: pelanggan
 *
 * ==========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
   KONEKSI DATABASE
========================================================== */

require_once __DIR__ . '/../config/koneksi.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi database tidak ditemukan.');
}

$pdo->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

/* ==========================================================
   JIKA SUDAH LOGIN
========================================================== */

if (!empty($_SESSION['pelanggan_id'])) {
    header('Location: dashboard_pelanggan.php');
    exit;
}

/* ==========================================================
   HELPER
========================================================== */

function loginEscape($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Normalisasi nomor WhatsApp Indonesia.
 *
 * 08xxx   => 628xxx
 * 8xxx    => 628xxx
 * +628xxx => 628xxx
 * 628xxx  => 628xxx
 */
function loginNormalisasiWA($nomor)
{
    $nomor = preg_replace(
        '/\D+/',
        '',
        trim((string) $nomor)
    );

    if (str_starts_with($nomor, '0')) {

        $nomor = '62' . substr($nomor, 1);

    } elseif (str_starts_with($nomor, '8')) {

        $nomor = '62' . $nomor;

    }

    if (!preg_match('/^628[0-9]{7,12}$/', $nomor)) {
        throw new RuntimeException(
            'Format nomor WhatsApp tidak valid.'
        );
    }

    return $nomor;
}

/* ==========================================================
   CSRF TOKEN
========================================================== */

if (empty($_SESSION['csrf_login_pelanggan'])) {
    $_SESSION['csrf_login_pelanggan'] =
        bin2hex(random_bytes(32));
}

/* ==========================================================
   VARIABEL
========================================================== */

$error = '';

$teleponInput = trim(
    (string) ($_POST['telepon'] ?? '')
);

/* ==========================================================
   PROSES LOGIN
========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /* ------------------------------------------
           VALIDASI CSRF
        ------------------------------------------ */

        $token = (string) (
            $_POST['csrf_token'] ?? ''
        );

        if (
            $token === '' ||
            !hash_equals(
                $_SESSION['csrf_login_pelanggan'],
                $token
            )
        ) {
            throw new RuntimeException(
                'Sesi formulir tidak valid. Silakan muat ulang halaman.'
            );
        }

        /* ------------------------------------------
           AMBIL INPUT
        ------------------------------------------ */

        $telepon = loginNormalisasiWA(
            $teleponInput
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        if ($password === '') {
            throw new RuntimeException(
                'Password wajib diisi.'
            );
        }

        /* ------------------------------------------
           VARIASI NOMOR WHATSAPP
        ------------------------------------------ */

        // Format 628xxx
        $nomor62 = $telepon;

        // Format 08xxx
        $nomor08 = '0' . substr(
            $telepon,
            2
        );

        // Format +628xxx
        $nomorPlus62 = '+' . $telepon;

        /* ------------------------------------------
           CARI PELANGGAN
        ------------------------------------------ */

        $stmt = $pdo->prepare(
            "SELECT
                id,
                nama,
                telepon,
                password
             FROM pelanggan
             WHERE telepon IN (?, ?, ?)
             LIMIT 1"
        );

        $stmt->execute([
            $nomor62,
            $nomor08,
            $nomorPlus62
        ]);

        $pelanggan = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        /* ------------------------------------------
           VALIDASI AKUN DAN PASSWORD
        ------------------------------------------ */

        if (
            !$pelanggan ||
            empty($pelanggan['password']) ||
            !password_verify(
                $password,
                $pelanggan['password']
            )
        ) {
            throw new RuntimeException(
                'Nomor WhatsApp atau password salah, atau akun belum diaktifkan.'
            );
        }

        /* ------------------------------------------
           LOGIN BERHASIL
        ------------------------------------------ */

        session_regenerate_id(true);

        $_SESSION['pelanggan_id'] = (int) (
            $pelanggan['id']
        );

        $_SESSION['pelanggan_nama'] = (
            $pelanggan['nama']
        );

        $_SESSION['pelanggan_telepon'] = (
            $pelanggan['telepon']
        );

        /* ------------------------------------------
           TOKEN BARU
        ------------------------------------------ */

        $_SESSION['csrf_login_pelanggan'] =
            bin2hex(random_bytes(32));

        /* ------------------------------------------
           REDIRECT
        ------------------------------------------ */

        header(
            'Location: dashboard_pelanggan.php'
        );

        exit;

    } catch (Throwable $e) {

        $error = $e instanceof PDOException
            ? 'Terjadi kesalahan database. Silakan coba lagi.'
            : $e->getMessage();

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

    <title>Login Pelanggan - ServisKu</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
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
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            font-family: 'Inter', Arial, sans-serif;

            background: linear-gradient(
                135deg,
                #0b1f42,
                #174b9d,
                #2563eb
            );
        }

        /* ==========================================
           CARD
        ========================================== */

        .login-card {
            width: 100%;
            max-width: 500px;

            padding: 45px;

            background: white;

            border-radius: 24px;

            box-shadow:
                0 20px 60px rgba(0, 0, 0, .2);
        }

        /* ==========================================
           LOGO
        ========================================== */

        .logo {
            width: 70px;
            height: 70px;

            display: grid;
            place-items: center;

            margin: 0 auto 25px;

            background: #eff6ff;
            color: #2563eb;

            border-radius: 20px;

            font-size: 32px;
        }

        /* ==========================================
           TITLE
        ========================================== */

        h1 {
            margin: 0;

            color: #102a58;

            font-size: 29px;
            font-weight: 800;

            text-align: center;
        }

        .subtitle {
            margin: 15px 0 35px;

            color: #64748b;

            font-size: 14px;
            line-height: 1.8;

            text-align: center;
        }

        /* ==========================================
           FORM
        ========================================== */

        .input-group {
            margin-bottom: 22px;
        }

        label {
            display: block;

            margin-bottom: 10px;

            color: #102a58;

            font-size: 14px;
            font-weight: 700;
        }

        .input-wrap {
            position: relative;
        }

        input {
            width: 100%;

            padding: 16px 17px;

            border: 1px solid #dbe3f0;
            border-radius: 12px;

            background: #f8fafc;

            color: #1e293b;

            outline: none;

            font: inherit;
            font-size: 14px;

            transition: .2s;
        }

        input:focus {
            border-color: #2563eb;

            background: white;

            box-shadow:
                0 0 0 4px rgba(37, 99, 235, .1);
        }

        input::placeholder {
            color: #94a3b8;
        }

        .password-input {
            padding-right: 55px;
        }

        .toggle-password {
            position: absolute;

            right: 12px;
            top: 50%;

            transform: translateY(-50%);

            width: 36px;
            height: 36px;

            border: none;

            background: transparent;

            color: #64748b;

            cursor: pointer;
        }

        .toggle-password:hover {
            color: #2563eb;
        }

        /* ==========================================
           BUTTON
        ========================================== */

        .btn-login {
            width: 100%;

            padding: 16px;

            border: none;
            border-radius: 12px;

            background: linear-gradient(
                120deg,
                #2563eb,
                #1d4ed8
            );

            color: white;

            font: inherit;

            font-size: 14px;
            font-weight: 700;

            cursor: pointer;

            transition: .2s;

            box-shadow:
                0 8px 20px rgba(37, 99, 235, .2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            opacity: .95;
        }

        /* ==========================================
           ALERT
        ========================================== */

        .alert {
            display: flex;
            align-items: flex-start;

            gap: 12px;

            padding: 16px;

            margin-bottom: 25px;

            border: 1px solid #fecaca;
            border-radius: 12px;

            background: #fef2f2;

            color: #991b1b;

            font-size: 13px;
            line-height: 1.7;
        }

        .alert i {
            margin-top: 3px;
        }

        /* ==========================================
           REGISTER
        ========================================== */

        .register {
            margin-top: 30px;

            text-align: center;

            font-size: 14px;
            color: #64748b;
        }

        .register a {
            color: #2563eb;

            font-weight: 700;

            text-decoration: none;
        }

        .register a:hover {
            text-decoration: underline;
        }

        /* ==========================================
           BACK
        ========================================== */

        .back {
            display: block;

            margin-top: 30px;

            text-align: center;

            color: #64748b;

            font-size: 13px;

            text-decoration: none;
        }

        .back:hover {
            color: #2563eb;
        }

        /* ==========================================
           RESPONSIVE
        ========================================== */

        @media (max-width: 480px) {

            .login-card {
                padding: 28px 22px;
            }

            h1 {
                font-size: 24px;
            }

            .subtitle {
                font-size: 13px;
            }

        }

    </style>

</head>

<body>

<div class="login-card">

    <!-- LOGO -->

    <div class="logo">

        <i class="fa-solid fa-motorcycle"></i>

    </div>

    <!-- TITLE -->

    <h1>Login Pelanggan</h1>

    <p class="subtitle">

        Selamat datang di ServisKu.

        Masuk untuk memantau status
        dan riwayat servis motor Anda.

    </p>

    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div class="alert">

            <i class="fa-solid fa-circle-exclamation"></i>

            <span>
                <?= loginEscape($error) ?>
            </span>

        </div>

    <?php endif; ?>

    <!-- FORM -->

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= loginEscape(
                $_SESSION['csrf_login_pelanggan']
            ) ?>"
        >

        <!-- WHATSAPP -->

        <div class="input-group">

            <label for="telepon">

                Nomor WhatsApp

            </label>

            <input
                type="tel"
                id="telepon"
                name="telepon"
                placeholder="Contoh: 081234567890"
                value="<?= loginEscape(
                    $teleponInput
                ) ?>"
                autocomplete="username"
                required
            >

        </div>

        <!-- PASSWORD -->

        <div class="input-group">

            <label for="password">

                Password

            </label>

            <div class="input-wrap">

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="password-input"
                    placeholder="Masukkan password"
                    autocomplete="current-password"
                    required
                >

                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                    aria-label="Tampilkan password"
                >

                    <i
                        class="fa-solid fa-eye"
                        id="passwordIcon"
                    ></i>

                </button>

            </div>

        </div>

        <!-- LOGIN BUTTON -->

        <button
            type="submit"
            class="btn-login"
        >

            <i class="fa-solid fa-right-to-bracket"></i>

            Masuk Sekarang

        </button>

    </form>

    <!-- REGISTER -->

    <div class="register">

        Belum punya akun?

        <a href="register_pelanggan.php">

            Daftar Sekarang

        </a>

    </div>

    <!-- ADMIN LOGIN -->

    <a
        href="login.php"
        class="back"
    >

        <i class="fa-solid fa-arrow-left"></i>

        Login Admin / Petugas

    </a>

</div>

<!-- JAVASCRIPT -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const toggle = document.getElementById(
            'togglePassword'
        );

        const password = document.getElementById(
            'password'
        );

        const icon = document.getElementById(
            'passwordIcon'
        );

        if (toggle && password && icon) {

            toggle.addEventListener(
                'click',
                function () {

                    const sedangTersembunyi =
                        password.type === 'password';

                    password.type = sedangTersembunyi
                        ? 'text'
                        : 'password';

                    icon.className = sedangTersembunyi
                        ? 'fa-solid fa-eye-slash'
                        : 'fa-solid fa-eye';

                    toggle.setAttribute(
                        'aria-label',
                        sedangTersembunyi
                            ? 'Sembunyikan password'
                            : 'Tampilkan password'
                    );

                }
            );

        }

    }
);

</script>

</body>
</html>