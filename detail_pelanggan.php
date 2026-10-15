<?php
require_once __DIR__ . '/includes/pelanggan_helper.php';

$idPelanggan = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

$pelanggan = false;
if ($idPelanggan) {
    $stmt = $pdo->prepare('SELECT * FROM pelanggan WHERE id = ?');
    $stmt->execute([$idPelanggan]);
    $pelanggan = $stmt->fetch();
}

if (!$pelanggan) {
    setFlash('Pelanggan tidak ditemukan.', 'error');
    header('Location: pelanggan.php');
    exit;
}

/* Riwayat servis: dicocokkan lewat nomor telepon */
$stmt = $pdo->prepare('
    SELECT id, nomor_servis, nomor_polisi, jenis_motor, keluhan,
           status, estimasi_selesai, created_at
    FROM servis
    WHERE ' . sqlTeleponServis() . ' = ?
    ORDER BY created_at DESC
');
$stmt->execute([$pelanggan['telepon']]);
$riwayat = $stmt->fetchAll();

$jumlah = ['Antre' => 0, 'Pengerjaan' => 0, 'Selesai' => 0];
foreach ($riwayat as $r) {
    $jumlah[$r['status']] = ($jumlah[$r['status']] ?? 0) + 1;
}

[$flashMessage, $flashType] = ambilFlash();

renderHead('Detail Pelanggan');
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="main">

    <?php renderTopbar('Manajemen data pelanggan', $namaUser, $roleUser); ?>

    <section class="content">

        <div class="page-heading">
            <div class="heading-left">
                <div class="heading-icon"><i class="fa-solid fa-id-card"></i></div>
                <div>
                    <h1>Detail Pelanggan</h1>
                    <p>Profil lengkap dan riwayat servis pelanggan.</p>
                </div>
            </div>

            <div class="heading-actions">
                <a href="pelanggan.php" class="btn-secondary">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </a>
                <a href="edit_pelanggan.php?id=<?= (int) $pelanggan['id'] ?>" class="btn-primary">
                    <i class="fa-solid fa-pen"></i> Edit Pelanggan
                </a>
            </div>
        </div>

        <?php if ($flashMessage !== ''): ?>
            <div class="alert <?= $flashType === 'error' ? 'error' : 'success' ?>">
                <i class="fa-solid <?= $flashType === 'error'
                    ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
                <?= e($flashMessage) ?>
            </div>
        <?php endif; ?>

        <section class="panel">
            <div class="profile-card">
                <div class="cust-avatar lg"><?= e(inisial($pelanggan['nama'])) ?></div>
                <div class="profile-info">
                    <h2><?= e($pelanggan['nama']) ?></h2>
                    <p class="since">
                        Terdaftar sejak <?= e(formatTanggal($pelanggan['created_at'], false)) ?>
                    </p>
                </div>
                <a href="<?= e(linkWhatsapp($pelanggan['telepon'])) ?>"
                   target="_blank" rel="noopener" class="btn-secondary">
                    <i class="fa-brands fa-whatsapp"></i> Chat WhatsApp
                </a>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <small>Telepon</small>
                    <span><?= e($pelanggan['telepon']) ?></span>
                </div>
                <div class="info-item">
                    <small>Email</small>
                    <span><?= e($pelanggan['email']) ?></span>
                </div>
                <div class="info-item full">
                    <small>Alamat</small>
                    <span><?= e($pelanggan['alamat']) ?></span>
                </div>
                <div class="info-item full">
                    <small>Catatan</small>
                    <span><?= e($pelanggan['catatan']) ?></span>
                </div>
            </div>
        </section>

        <div class="stats" style="margin-top:20px; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-clipboard-list"></i></div>
                <div class="stat-info"><p>Total Servis</p><strong><?= count($riwayat) ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fa-regular fa-clock"></i></div>
                <div class="stat-info"><p>Antre</p><strong><?= $jumlah['Antre'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-wrench"></i></div>
                <div class="stat-info"><p>Pengerjaan</p><strong><?= $jumlah['Pengerjaan'] ?></strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-check"></i></div>
                <div class="stat-info"><p>Selesai</p><strong><?= $jumlah['Selesai'] ?></strong></div>
            </div>
        </div>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Servis</h2>
                    <p>Seluruh servis dengan nomor telepon pelanggan ini.</p>
                </div>
                <a href="data_servis.php?cari=<?= urlencode($pelanggan['telepon']) ?>" class="btn-secondary">
                    <i class="fa-solid fa-up-right-from-square"></i> Buka di Data Servis
                </a>
            </div>

            <div class="table-wrap">
                <table style="min-width:760px">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nomor Servis</th>
                            <th>Nomor Polisi</th>
                            <th>Jenis Motor</th>
                            <th>Keluhan</th>
                            <th>Status</th>
                            <th>Masuk</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($riwayat): ?>
                        <?php foreach ($riwayat as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><span class="service-number"><?= e($r['nomor_servis']) ?></span></td>
                                <td><span class="plate"><?= e($r['nomor_polisi']) ?></span></td>
                                <td><?= e($r['jenis_motor']) ?></td>
                                <td><?= e($r['keluhan']) ?></td>
                                <td>
                                    <span class="status <?= statusClass($r['status']) ?>">
                                        <?= e($r['status']) ?>
                                    </span>
                                </td>
                                <td><?= e(formatTanggal($r['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty">
                                <i class="fa-regular fa-folder-open"></i>
                                <strong>Belum ada riwayat servis</strong>
                                <span>Servis akan muncul di sini jika nomor teleponnya sama.</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="footer">
            &copy; <?= date('Y') ?> ServisKu — Sistem Manajemen Bengkel Motor
        </footer>
    </section>
</main>

</body>
</html>
