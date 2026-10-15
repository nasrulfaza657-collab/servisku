
<?php
require_once 'config/koneksi.php';

$errors = [];
$success = false;
$nomorServis = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_pelanggan'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $plat = strtoupper(trim($_POST['nomor_polisi'] ?? ''));
    $motor = trim($_POST['jenis_motor'] ?? '');
    $keluhan = trim($_POST['keluhan'] ?? '');

    if (
        $nama === '' ||
        $telepon === '' ||
        $plat === '' ||
        $motor === '' ||
        $keluhan === ''
    ) {
        $errors[] = 'Semua kolom wajib diisi.';
    }

    if (!$errors) {
        do {
            $nomorServis = 'SRV-' . date('ymd') . '-'
                . strtoupper(bin2hex(random_bytes(3)));

            $check = $pdo->prepare(
                'SELECT COUNT(*) FROM servis WHERE nomor_servis = ?'
            );
            $check->execute([$nomorServis]);
        } while ((int) $check->fetchColumn() > 0);

        $stmt = $pdo->prepare(
            'INSERT INTO servis
            (nomor_servis, nama_pelanggan, telepon,
             nomor_polisi, jenis_motor, keluhan)
            VALUES (?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $nomorServis,
            $nama,
            $telepon,
            $plat,
            $motor,
            $keluhan
        ]);

        $success = true;
    }
}

$pageTitle = 'Daftar Servis';
require 'includes/header.php';
?>

<div class="page-intro">
    <span class="eyebrow">FORM PELANGGAN</span>
    <h1 class="h2 fw-bold mt-2">Pendaftaran servis</h1>
    <p class="text-secondary">
        Isi data motor dan keluhan kerusakan dengan benar.
    </p>
</div>

<?php if ($success): ?>

    <div class="alert alert-success border-0 rounded-4 p-4">
        <h2 class="h5 fw-bold">Pendaftaran berhasil!</h2>
        <p>Nomor servis Anda:</p>

        <div class="ticket-number">
            <?= htmlspecialchars($nomorServis) ?>
        </div>

        <p class="mt-3">
            Simpan nomor ini untuk mengecek status servis.
        </p>

        <a class="btn btn-success rounded-pill px-4"
           href="cek_status.php?nomor=<?= urlencode($nomorServis) ?>">
            Cek Status Servis
        </a>

        <button class="btn btn-outline-success rounded-pill ms-2"
                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($nomorServis, ENT_QUOTES) ?>')">
            Salin Nomor
        </button>
    </div>

<?php else: ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-panel">
        <div class="row g-3">

            <div class="col-md-6">
                <label class="form-label">Nama pelanggan</label>
                <input class="form-control" name="nama_pelanggan"
                       required
                       value="<?= htmlspecialchars($_POST['nama_pelanggan'] ?? '') ?>"
                       placeholder="Nama lengkap">
            </div>

            <div class="col-md-6">
                <label class="form-label">Nomor telepon</label>
                <input class="form-control" name="telepon"
                       required
                       value="<?= htmlspecialchars($_POST['telepon'] ?? '') ?>"
                       placeholder="08xxxxxxxxxx">
            </div>

            <div class="col-md-6">
                <label class="form-label">Nomor polisi</label>
                <input class="form-control text-uppercase"
                       name="nomor_polisi" required
                       value="<?= htmlspecialchars($_POST['nomor_polisi'] ?? '') ?>"
                       placeholder="L 1234 ABC">
            </div>

            <div class="col-md-6">
                <label class="form-label">Jenis / merek motor</label>
                <input class="form-control" name="jenis_motor"
                       required
                       value="<?= htmlspecialchars($_POST['jenis_motor'] ?? '') ?>"
                       placeholder="Contoh: Honda Beat 2022">
            </div>

            <div class="col-12">
                <label class="form-label">Keluhan atau kerusakan</label>
                <textarea class="form-control" name="keluhan"
                          rows="4" required
                          placeholder="Contoh: mesin sulit dinyalakan..."><?= htmlspecialchars($_POST['keluhan'] ?? '') ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-between
                        align-items-center flex-wrap gap-2 mt-4">
                <span class="small text-secondary">
                    Pastikan data kendaraan sudah benar.
                </span>
                <button class="btn btn-primary rounded-pill px-4"
                        type="submit">
                    Kirim Pendaftaran
                </button>
            </div>
        </div>
    </form>

<?php endif; ?>

<?php require 'includes/footer.php'; ?>
