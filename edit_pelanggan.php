<?php
require_once __DIR__ . '/includes/pelanggan_helper.php';

$idPelanggan = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$idPelanggan) {
    setFlash('Pelanggan tidak ditemukan.', 'error');
    header('Location: pelanggan.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM pelanggan WHERE id = ?');
$stmt->execute([$idPelanggan]);
$pelanggan = $stmt->fetch();

if (!$pelanggan) {
    setFlash('Pelanggan tidak ditemukan.', 'error');
    header('Location: pelanggan.php');
    exit;
}

$teleponLama = $pelanggan['telepon'];
$data = $pelanggan;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValid()) {
        $errors['umum'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
        $data = array_merge($data, array_intersect_key($_POST, $data));
    } else {
        [$data, $errors] = validasiPelanggan($pdo, $_POST, $idPelanggan);

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                $upd = $pdo->prepare(
                    'UPDATE pelanggan
                     SET nama = ?, telepon = ?, email = ?, alamat = ?, catatan = ?
                     WHERE id = ?'
                );
                $upd->execute([
                    $data['nama'],
                    $data['telepon'],
                    $data['email'] !== '' ? $data['email'] : null,
                    $data['alamat'] !== '' ? $data['alamat'] : null,
                    $data['catatan'] !== '' ? $data['catatan'] : null,
                    $idPelanggan,
                ]);

                /* Samakan nama & telepon di data servis milik pelanggan ini */
                if (isset($_POST['sinkron_servis'])) {
                    $sinkron = $pdo->prepare(
                        'UPDATE servis SET nama_pelanggan = ?, telepon = ?
                         WHERE ' . sqlTeleponServis() . ' = ?'
                    );
                    $sinkron->execute([$data['nama'], $data['telepon'], $teleponLama]);
                }

                $pdo->commit();

                setFlash('Data pelanggan "' . $data['nama'] . '" berhasil diperbarui.');
                header('Location: pelanggan.php');
                exit;
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors['umum'] = $ex->getCode() === '23000'
                    ? 'Nomor telepon ini sudah terdaftar.'
                    : 'Data gagal disimpan. Silakan coba lagi.';
            }
        }
    }
}

$modeEdit = true;

renderHead('Edit Pelanggan');
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="main">

    <?php renderTopbar('Manajemen data pelanggan', $namaUser, $roleUser); ?>

    <section class="content">

        <div class="page-heading">
            <div class="heading-left">
                <div class="heading-icon"><i class="fa-solid fa-user-pen"></i></div>
                <div>
                    <h1>Edit Pelanggan</h1>
                    <p>Perbarui data <?= e($pelanggan['nama']) ?>.</p>
                </div>
            </div>

            <a href="detail_pelanggan.php?id=<?= (int) $idPelanggan ?>" class="btn-secondary">
                <i class="fa-solid fa-eye"></i> Lihat Detail
            </a>
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
                    <p>Terdaftar sejak <?= e(formatTanggal($pelanggan['created_at'], false)) ?>.</p>
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
