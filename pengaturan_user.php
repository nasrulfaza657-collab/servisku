<?php
/**
 * SERVISKU - PENGATURAN USER
 * Tanpa includes/fitur.php
 * Menggunakan sidebar asli ServisKu
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// KONEKSI DATABASE
// =====================================================

$fileKoneksi = [
    __DIR__ . '/config/koneksi.php',
    __DIR__ . '/includes/koneksi.php',
    __DIR__ . '/koneksi.php',
    __DIR__ . '/config.php'
];

$pdo = null;

foreach ($fileKoneksi as $file) {
    if (is_file($file)) {
        require_once $file;

        if (isset($pdo) && $pdo instanceof PDO) {
            break;
        }
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi database PDO tidak ditemukan.');
}

// =====================================================
// VALIDASI LOGIN
// =====================================================

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int) $_SESSION['user_id'];

// =====================================================
// FUNGSI KEAMANAN
// =====================================================

function profilEsc($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

if (empty($_SESSION['csrf_profil'])) {
    $_SESSION['csrf_profil'] = bin2hex(random_bytes(32));
}

// =====================================================
// AMBIL DATA USER
// =====================================================

$stmt = $pdo->prepare(
    'SELECT id, nama, username, role, foto_profil
     FROM users
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$uid]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    exit('Data pengguna tidak ditemukan.');
}

// =====================================================
// PROSES SIMPAN PROFIL
// =====================================================

$msg = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // Validasi CSRF
        $token = (string) ($_POST['csrf_token'] ?? '');

        if (
            $token === '' ||
            !hash_equals($_SESSION['csrf_profil'], $token)
        ) {
            throw new RuntimeException(
                'Token keamanan tidak valid. Muat ulang halaman.'
            );
        }

        // Validasi nama
        $nama = trim((string) ($_POST['nama'] ?? ''));

        if ($nama === '') {
            throw new RuntimeException('Nama wajib diisi.');
        }

        if (mb_strlen($nama, 'UTF-8') > 150) {
            throw new RuntimeException('Nama terlalu panjang.');
        }

        // Validasi password
        $password = (string) ($_POST['password'] ?? '');

        if ($password !== '' && strlen($password) < 8) {
            throw new RuntimeException(
                'Password baru minimal 8 karakter.'
            );
        }

        // =================================================
        // VALIDASI FOTO SEBELUM UPDATE DATABASE
        // =================================================

        $fotoBaru = null;
        $fotoLama = $user['foto_profil'] ?? null;

        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $upload = $_FILES['foto'];

            if ($upload['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException(
                    'Gagal mengunggah foto profil.'
                );
            }

            if ($upload['size'] > 2 * 1024 * 1024) {
                throw new RuntimeException(
                    'Ukuran foto maksimal 2 MB.'
                );
            }

            if (!is_uploaded_file($upload['tmp_name'])) {
                throw new RuntimeException(
                    'File unggahan tidak valid.'
                );
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($upload['tmp_name']);

            $jenisFoto = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $ext = $jenisFoto[$mime] ?? null;

            if (!$ext) {
                throw new RuntimeException(
                    'Foto harus berformat JPG, PNG, atau WEBP.'
                );
            }

            // Pastikan benar-benar file gambar
            if (@getimagesize($upload['tmp_name']) === false) {
                throw new RuntimeException(
                    'File yang dipilih bukan gambar valid.'
                );
            }

            // Folder penyimpanan
            $folder = __DIR__ . '/uploads/profil';

            if (
                !is_dir($folder) &&
                !mkdir($folder, 0755, true)
            ) {
                throw new RuntimeException(
                    'Gagal membuat folder foto profil.'
                );
            }

            $namaFile = bin2hex(random_bytes(16)) . '.' . $ext;

            $fotoBaru = 'uploads/profil/' . $namaFile;

            $lokasiTujuan = __DIR__ . '/' . $fotoBaru;

            if (
                !move_uploaded_file(
                    $upload['tmp_name'],
                    $lokasiTujuan
                )
            ) {
                throw new RuntimeException(
                    'Gagal menyimpan foto profil.'
                );
            }
        }

        // =================================================
        // UPDATE DATABASE
        // =================================================

        try {

            $pdo->beginTransaction();

            $sql = 'UPDATE users SET nama = ?';
            $params = [$nama];

            if ($password !== '') {
                $sql .= ', password = ?';
                $params[] = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );
            }

            if ($fotoBaru !== null) {
                $sql .= ', foto_profil = ?';
                $params[] = $fotoBaru;
            }

            $sql .= ' WHERE id = ?';
            $params[] = $uid;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Hapus file baru jika database gagal disimpan
            if (
                $fotoBaru !== null &&
                is_file(__DIR__ . '/' . $fotoBaru)
            ) {
                unlink(__DIR__ . '/' . $fotoBaru);
            }

            throw $e;
        }

        // =================================================
        // UPDATE SESSION AGAR PROFIL KANAN ATAS IKUT BERUBAH
        // =================================================

        $_SESSION['nama'] = $nama;

        if ($fotoBaru !== null) {
            $_SESSION['foto_profil'] = $fotoBaru;
        }

        // Refresh data user
        $stmt = $pdo->prepare(
            'SELECT id, nama, username, role, foto_profil
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        $stmt->execute([$uid]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Hapus foto lama setelah berhasil update
        if (
            $fotoBaru !== null &&
            !empty($fotoLama) &&
            $fotoLama !== $fotoBaru &&
            str_starts_with(
                str_replace('\\', '/', $fotoLama),
                'uploads/profil/'
            )
        ) {

            $lokasiLama = __DIR__ . '/' . $fotoLama;

            if (is_file($lokasiLama)) {
                @unlink($lokasiLama);
            }
        }

        $_SESSION['csrf_profil'] = bin2hex(random_bytes(32));

        $msg = 'Profil berhasil diperbarui!';
        $error = false;

    } catch (Throwable $e) {

        $msg = $e instanceof PDOException
            ? 'Gagal menyimpan perubahan ke database.'
            : $e->getMessage();

        $error = true;
    }
}

// =====================================================
// DATA TAMPILAN
// =====================================================

$fotoProfil = trim((string) ($user['foto_profil'] ?? ''));

$adaFoto = (
    $fotoProfil !== '' &&
    is_file(__DIR__ . '/' . $fotoProfil)
);

$inisial = strtoupper(
    substr(trim((string) $user['nama']), 0, 1)
);

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pengaturan User - ServisKu</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f4f7fc;
            font-family: 'Inter', Arial, sans-serif;
            color: #1e293b;
        }

        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            padding: 30px;
        }

        .page-container {
            max-width: 1100px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .topbar h2 {
            margin: 0;
            font-size: 23px;
            font-weight: 800;
            color: #102a58;
        }

        .topbar p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .hero {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            padding: 35px;
            margin-bottom: 25px;
            background: linear-gradient(120deg, #0b1f42, #174b9d, #2563eb);
            border-radius: 20px;
            color: white;
            box-shadow: 0 12px 30px rgba(15, 35, 75, .15);
        }

        .hero h1 {
            margin: 0 0 12px;
            font-size: 29px;
            font-weight: 800;
        }

        .hero p {
            margin: 0;
            color: #dbeafe;
            font-size: 14px;
            line-height: 1.8;
        }

        .hero-icon {
            display: grid;
            place-items: center;
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            background: rgba(255, 255, 255, .15);
            border-radius: 22px;
            font-size: 40px;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(15, 35, 75, .05);
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h3 {
            margin: 0 0 6px;
            color: #102a58;
            font-size: 18px;
        }

        .card-header p {
            margin: 0;
            color: #64748b;
            font-size: 13px;
        }

        .profile-banner {
            display: flex;
            align-items: center;
            gap: 22px;
            padding: 30px;
            background: linear-gradient(120deg, #f8fbff, #eff6ff);
            border-bottom: 1px solid #e2e8f0;
        }

        .avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .avatar {
            width: 105px;
            height: 105px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: 0 5px 20px rgba(15, 35, 75, .15);
        }

        .avatar-placeholder {
            display: grid;
            place-items: center;
            width: 105px;
            height: 105px;
            border-radius: 50%;
            border: 5px solid white;
            background: #1d4ed8;
            color: white;
            font-size: 40px;
            font-weight: 800;
        }

        .profile-name {
            margin: 0 0 8px;
            color: #102a58;
            font-size: 22px;
            font-weight: 800;
        }

        .profile-role {
            display: inline-block;
            padding: 7px 12px;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .profile-form {
            padding: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .form-group {
            min-width: 0;
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

        .form-group input {
            width: 100%;
            padding: 14px 16px;
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
            background: white;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, .1);
        }

        .form-group input:disabled {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }

        .form-hint {
            margin: 8px 0 0;
            font-size: 12px;
            color: #64748b;
            line-height: 1.6;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 28px;
            padding-top: 25px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 25px;
            border: 0;
            border-radius: 11px;
            background: linear-gradient(120deg, #2563eb, #1d4ed8);
            color: white;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(37, 99, 235, .2);
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .alert {
            padding: 17px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #ecfdf5;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 850px) {
            .main-content {
                margin-left: 0;
                padding: 18px;
            }
        }

        @media (max-width: 650px) {
            .hero {
                padding: 25px;
            }

            .hero h1 {
                font-size: 23px;
            }

            .hero-icon {
                display: none;
            }

            .profile-banner {
                flex-direction: column;
                text-align: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-full {
                grid-column: auto;
            }

            .profile-form {
                padding: 20px;
            }

            .form-actions,
            .btn-primary {
                width: 100%;
            }
        }

    </style>

</head>

<body>

    <!-- SIDEBAR ASLI SERVISKU -->
    <?php require_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">

        <div class="page-container">

            <div class="topbar">
                <div>
                    <h2>Pengaturan User</h2>
                    <p>Dashboard / Pengaturan User</p>
                </div>
            </div>

            <div class="hero">

                <div>
                    <h1>
                        <i class="fa-solid fa-user-gear"></i>
                        Pengaturan Profil
                    </h1>

                    <p>
                        Kelola informasi akun, ubah foto profil,
                        dan perbarui password untuk menjaga
                        keamanan akun ServisKu.
                    </p>
                </div>

                <div class="hero-icon">
                    <i class="fa-solid fa-user-gear"></i>
                </div>

            </div>

            <?php if ($msg !== ''): ?>

                <div class="alert <?= $error ? 'alert-error' : 'alert-success' ?>">
                    <?= profilEsc($msg) ?>
                </div>

            <?php endif; ?>

            <section class="card">

                <div class="card-header">
                    <h3>Profil Saya</h3>
                    <p>Informasi akun yang sedang digunakan.</p>
                </div>

                <div class="profile-banner">

                    <div class="avatar-wrapper">

                        <?php if ($adaFoto): ?>

                            <img
                                id="previewFoto"
                                class="avatar"
                                src="<?= profilEsc($fotoProfil) ?>?v=<?= time() ?>"
                                alt="Foto Profil"
                            >

                        <?php else: ?>

                            <div
                                id="placeholderFoto"
                                class="avatar-placeholder"
                            >
                                <?= profilEsc($inisial) ?>
                            </div>

                            <img
                                id="previewFoto"
                                class="avatar"
                                src=""
                                alt="Foto Profil"
                                style="display:none;"
                            >

                        <?php endif; ?>

                    </div>

                    <div>
                        <h3 class="profile-name">
                            <?= profilEsc($user['nama']) ?>
                        </h3>

                        <span class="profile-role">
                            <?= profilEsc($user['role']) ?>
                        </span>
                    </div>

                </div>

                <form
                    method="POST"
                    enctype="multipart/form-data"
                    class="profile-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= profilEsc($_SESSION['csrf_profil']) ?>"
                    >

                    <div class="form-grid">

                        <div class="form-group">

                            <label for="nama">Nama Lengkap</label>

                            <input
                                type="text"
                                id="nama"
                                name="nama"
                                value="<?= profilEsc($user['nama']) ?>"
                                maxlength="150"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label>Username</label>

                            <input
                                type="text"
                                value="<?= profilEsc($user['username']) ?>"
                                disabled
                            >

                        </div>

                        <div class="form-group">

                            <label>Peran / Role</label>

                            <input
                                type="text"
                                value="<?= profilEsc($user['role']) ?>"
                                disabled
                            >

                        </div>

                        <div class="form-group">

                            <label for="foto">Ganti Foto Profil</label>

                            <input
                                type="file"
                                id="foto"
                                name="foto"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <p class="form-hint">
                                JPG, PNG, atau WEBP. Maksimal 2 MB.
                            </p>

                        </div>

                        <div class="form-group form-full">

                            <label for="password">
                                Password Baru (Opsional)
                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="8"
                                autocomplete="new-password"
                                placeholder="Kosongkan jika tidak ingin mengganti password"
                            >

                            <p class="form-hint">
                                Minimal 8 karakter.
                                Kosongkan jika tidak ingin mengubah password.
                            </p>

                        </div>

                    </div>

                    <div class="form-actions">

                        <button type="submit" class="btn-primary">

                            <i class="fa-solid fa-floppy-disk"></i>

                            Simpan Perubahan

                        </button>

                    </div>

                </form>

            </section>

        </div>

    </main>

    <script>

        document.addEventListener('DOMContentLoaded', function () {

            const inputFoto = document.getElementById('foto');
            const previewFoto = document.getElementById('previewFoto');
            const placeholder = document.getElementById('placeholderFoto');

            if (inputFoto && previewFoto) {

                inputFoto.addEventListener('change', function () {

                    const file = this.files[0];

                    if (!file) {
                        return;
                    }

                    if (file.size > 2 * 1024 * 1024) {

                        alert('Ukuran foto maksimal 2 MB.');

                        this.value = '';

                        return;
                    }

                    const allowed = [
                        'image/jpeg',
                        'image/png',
                        'image/webp'
                    ];

                    if (!allowed.includes(file.type)) {

                        alert('Format foto harus JPG, PNG, atau WEBP.');

                        this.value = '';

                        return;
                    }

                    const reader = new FileReader();

                    reader.onload = function (event) {

                        previewFoto.src = event.target.result;
                        previewFoto.style.display = 'block';

                        if (placeholder) {
                            placeholder.style.display = 'none';
                        }

                    };

                    reader.readAsDataURL(file);

                });

            }

        });

    </script>

</body>
</html>