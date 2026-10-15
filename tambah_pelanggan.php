<?php
require_once __DIR__ . '/includes/pelanggan_helper.php';

$data = ['nama' => '', 'telepon' => '', 'email' => '', 'alamat' => '', 'catatan' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $errors['umum'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
        $data = array_merge($data, array_intersect_key($_POST, $data));
    } else {
        [$data, $errors] = validasiPelanggan($pdo, $_POST);

        if (!$errors) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO pelanggan (nama, telepon, email, alamat, catatan)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $data['nama'],
                    $data['telepon'],
                    $data['email'] !== '' ? $data['email'] : null,
                    $data['alamat'] !== '' ? $data['alamat'] : null,
                    $data['catatan'] !== '' ? $data['catatan'] : null,
                ]);

                setFlash('Pelanggan "' . $data['nama'] . '" berhasil ditambahkan.');
                header('Location: pelanggan.php');
                exit;
            } catch (PDOException $ex) {
                /* 23000 = duplikat (balapan dengan permintaan lain) */
                $errors['umum'] = $ex->getCode() === '23000'
                    ? 'Nomor telepon ini sudah terdaftar.'
                    : 'Data gagal disimpan. Silakan coba lagi.';
            }
        }
    }
}

$modeEdit = false;
$idPelanggan = null;

renderHead('Tambah Pelanggan');
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="main">

    <?php renderTopbar('Manajemen data pelanggan', $namaUser, $roleUser); ?>

    <section class="content">

        <div class="page-heading">
            <div class="heading-left">
                <div class="heading-icon"><i class="fa-solid fa-user-plus"></i></div>
                <div>
                    <h1>Tambah Pelanggan</h1>
                    <p>Isi data pelanggan baru. Kolom bertanda * wajib diisi.</p>
                </div>
            </div>
        </div>

        <?php if (isset($errors['umum'])): ?>
            <div class="alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($errors['umum']) ?>
            </div>
        <?php elseif ($errors): ?>
            <div class="alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                Periksa kembali isian yang ditandai merah.
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><i class="fa-solid fa-address-card"></i> Informasi Pelanggan</h2>
                    <p>Nomor telepon dipakai untuk menghubungkan pelanggan dengan data servis.</p>
                </div>
            </div>

            <?php require __DIR__ . '/includes/pelanggan_form.php'; ?>
        </section>

        <footer class="footer">
            &copy; <?= date('Y') ?> ServisKu — Sistem Manajemen Bengkel Motor
        </footer>
    </section>
</main>

</body>
</html>
