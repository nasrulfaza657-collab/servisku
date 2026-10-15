<?php
require __DIR__ . '/_pelanggan.php';

$id = prequire();
$pesan = '';
$jenisPesan = '';

$s = $pdo->prepare(
    'SELECT id, nama, foto_profil FROM pelanggan WHERE id = ?'
);
$s->execute([$id]);
$pelanggan = $s->fetch(PDO::FETCH_ASSOC);

if (!$pelanggan) {
    unset($_SESSION['pelanggan_id']);
    header('Location: login_pelanggan.php');
    exit;
}

if (empty($_SESSION['csrf_pelanggan'])) {
    $_SESSION['csrf_pelanggan'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_pelanggan'], $_POST['csrf_token'])
    ) {
        $pesan = 'Permintaan tidak valid. Silakan muat ulang halaman.';
        $jenisPesan = 'error';

    } elseif (isset($_POST['ubah_foto'])) {
        if (
            !isset($_FILES['foto']) ||
            $_FILES['foto']['error'] !== UPLOAD_ERR_OK
        ) {
            $pesan = 'Pilih foto terlebih dahulu.';
            $jenisPesan = 'error';

        } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {
            $pesan = 'Ukuran foto maksimal 2 MB.';
            $jenisPesan = 'error';

        } else {
            $tmp = $_FILES['foto']['tmp_name'];
            $info = @getimagesize($tmp);
            $mime = $info['mime'] ?? '';

            $ekstensi = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => ''
            };

            if ($ekstensi === '') {
                $pesan = 'Format foto harus JPG, PNG, atau WEBP.';
                $jenisPesan = 'error';

            } else {
                $folder = __DIR__ . '/uploads/profil';

                if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
                    $pesan = 'Folder penyimpanan foto gagal dibuat.';
                    $jenisPesan = 'error';

                } else {
                    $namaFile = 'pelanggan_' . $id . '_' .
                        bin2hex(random_bytes(8)) . '.' . $ekstensi;

                    if (move_uploaded_file($tmp, $folder . '/' . $namaFile)) {
                        $fotoLama = $pelanggan['foto_profil'];

                        $s = $pdo->prepare(
                            'UPDATE pelanggan
                             SET foto_profil = ?
                             WHERE id = ?'
                        );
                        $s->execute([$namaFile, $id]);

                        if ($fotoLama) {
                            $pathLama = $folder . '/' . basename($fotoLama);

                            if (is_file($pathLama)) {
                                unlink($pathLama);
                            }
                        }

                        header('Location: profil_pelanggan.php?foto=berhasil');
                        exit;

                    } else {
                        $pesan = 'Foto gagal disimpan. Periksa izin folder uploads.';
                        $jenisPesan = 'error';
                    }
                }
            }
        }

    } elseif (isset($_POST['ubah_password'])) {
        $passwordLama = $_POST['password_lama'] ?? '';
        $passwordBaru = $_POST['password_baru'] ?? '';
        $konfirmasi = $_POST['konfirmasi_password'] ?? '';

        $s = $pdo->prepare(
            'SELECT password FROM pelanggan WHERE id = ?'
        );
        $s->execute([$id]);
        $dataPassword = $s->fetch(PDO::FETCH_ASSOC);

        if (
            !$dataPassword ||
            !password_verify($passwordLama, $dataPassword['password'])
        ) {
            $pesan = 'Password lama tidak sesuai.';
            $jenisPesan = 'error';

        } elseif (strlen($passwordBaru) < 8) {
            $pesan = 'Password baru minimal 8 karakter.';
            $jenisPesan = 'error';

        } elseif ($passwordBaru !== $konfirmasi) {
            $pesan = 'Konfirmasi password tidak cocok.';
            $jenisPesan = 'error';

        } else {
            $hash = password_hash($passwordBaru, PASSWORD_DEFAULT);

            $s = $pdo->prepare(
                'UPDATE pelanggan SET password = ? WHERE id = ?'
            );
            $s->execute([$hash, $id]);

            $pesan = 'Password berhasil diubah.';
            $jenisPesan = 'success';
        }
    }
}

if (isset($_GET['foto']) && $_GET['foto'] === 'berhasil') {
    $pesan = 'Foto profil berhasil diperbarui.';
    $jenisPesan = 'success';
}

$s = $pdo->prepare(
    'SELECT id, nama, foto_profil FROM pelanggan WHERE id = ?'
);
$s->execute([$id]);
$pelanggan = $s->fetch(PDO::FETCH_ASSOC);

ppage('Profil Pelanggan');

$fotoUrl = !empty($pelanggan['foto_profil'])
    ? 'uploads/profil/' . rawurlencode(basename($pelanggan['foto_profil']))
    : '';
?>

<style>
.profil-wrap {
    max-width: 1000px;
    margin: 0 auto;
    padding: 10px 0 30px;
}

.profil-back {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 11px 17px;
    margin-bottom: 24px;
    border-radius: 11px;
    background: #64748b;
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: .2s ease;
}

.profil-back:hover {
    background: #475569;
    color: #fff;
    transform: translateX(-3px);
}

.profil-heading {
    margin-bottom: 24px;
}

.profil-heading h1 {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 8px;
}

.profil-heading p {
    opacity: .7;
    margin: 0;
    line-height: 1.7;
}

.profil-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.profil-card {
    padding: 25px;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 20px;
    background: var(--card, #fff);
    box-shadow: 0 8px 25px rgba(0, 0, 0, .04);
}

.profil-card h2 {
    font-size: 19px;
    font-weight: 800;
    margin: 0 0 8px;
}

.profil-subtitle {
    font-size: 13px;
    opacity: .65;
    line-height: 1.7;
    margin: 0 0 22px;
}

.profil-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--border, #e5e7eb);
    background: var(--surface, #f1f5f9);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.profil-avatar-fallback {
    font-size: 42px;
}

.profil-name {
    text-align: center;
    font-size: 17px;
    font-weight: 800;
    margin-bottom: 22px;
}

.profil-field {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 8px;
}

.profil-input {
    width: 100%;
    padding: 12px 14px;
    border-radius: 11px;
    border: 1px solid var(--border, #d1d5db);
    background: var(--surface, #fff);
    color: inherit;
    margin-bottom: 17px;
    box-sizing: border-box;
    outline: none;
}

.profil-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

.profil-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    width: 100%;
    border: 0;
    border-radius: 11px;
    padding: 13px 16px;
    background: #2563eb;
    color: white;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    transition: .2s;
}

.profil-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.profil-alert {
    padding: 13px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.6;
}

.profil-alert.success {
    background: #dcfce7;
    color: #166534;
}

.profil-alert.error {
    background: #fee2e2;
    color: #991b1b;
}

.profil-hint {
    font-size: 12px;
    opacity: .65;
    line-height: 1.6;
    margin-top: 12px;
}

@media (max-width: 700px) {
    .profil-grid {
        grid-template-columns: 1fr;
    }

    .profil-card {
        padding: 20px;
    }

    .profil-heading h1 {
        font-size: 24px;
    }
}
</style>

<div class="profil-wrap">

    <!-- Tombol kembali ke dashboard pelanggan -->
    <a href="dashboard_pelanggan.php" class="profil-back">
        ← Kembali ke Dashboard
    </a>

    <div class="profil-heading">
        <h1>Pengaturan Profil</h1>
        <p>Kelola foto profil dan keamanan akun ServisKu kamu.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="profil-alert <?= pe($jenisPesan) ?>">
            <?= pe($pesan) ?>
        </div>
    <?php endif; ?>

    <div class="profil-grid">

        <!-- KARTU FOTO PROFIL -->
        <section class="profil-card">
            <h2>📸 Foto Profil</h2>

            <p class="profil-subtitle">
                Gunakan foto agar akun kamu lebih mudah dikenali.
            </p>

            <?php if ($fotoUrl): ?>
                <img
                    class="profil-avatar"
                    src="<?= pe($fotoUrl) ?>"
                    alt="Foto profil pelanggan"
                >
            <?php else: ?>
                <div class="profil-avatar profil-avatar-fallback">
                    👤
                </div>
            <?php endif; ?>

            <div class="profil-name">
                <?= pe($pelanggan['nama']) ?>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= pe($_SESSION['csrf_pelanggan']) ?>"
                >

                <label class="profil-field" for="foto">
                    Pilih foto baru
                </label>

                <input
                    class="profil-input"
                    type="file"
                    id="foto"
                    name="foto"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >

                <button
                    class="profil-btn"
                    type="submit"
                    name="ubah_foto"
                >
                    Simpan Foto Profil
                </button>
            </form>

            <p class="profil-hint">
                Format JPG, PNG, atau WEBP. Maksimal 2 MB.
            </p>
        </section>

        <!-- KARTU UBAH PASSWORD -->
        <section class="profil-card">
            <h2>🔐 Ubah Password</h2>

            <p class="profil-subtitle">
                Gunakan password baru yang kuat dan tidak mudah ditebak.
            </p>

            <form method="POST" autocomplete="off">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= pe($_SESSION['csrf_pelanggan']) ?>"
                >

                <label class="profil-field" for="password_lama">
                    Password Lama
                </label>

                <input
                    class="profil-input"
                    type="password"
                    id="password_lama"
                    name="password_lama"
                    required
                    autocomplete="current-password"
                >

                <label class="profil-field" for="password_baru">
                    Password Baru
                </label>

                <input
                    class="profil-input"
                    type="password"
                    id="password_baru"
                    name="password_baru"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >

                <label class="profil-field" for="konfirmasi_password">
                    Konfirmasi Password Baru
                </label>

                <input
                    class="profil-input"
                    type="password"
                    id="konfirmasi_password"
                    name="konfirmasi_password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >

                <button
                    class="profil-btn"
                    type="submit"
                    name="ubah_password"
                >
                    Perbarui Password
                </button>
            </form>

            <p class="profil-hint">
                Password baru minimal 8 karakter. Kamu harus mengetahui
                password lama.
            </p>
        </section>

    </div>
</div>

<?php pend(); ?>