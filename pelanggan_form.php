<?php
/**
 * Form bersama untuk tambah & edit pelanggan.
 * Variabel yang dibutuhkan: $data (array), $errors (array),
 * $modeEdit (bool), $idPelanggan (int|null), $teleponLama (string|null)
 */
$aksiUrl = $modeEdit
    ? 'edit_pelanggan.php?id=' . (int) $idPelanggan
    : 'tambah_pelanggan.php';

function fieldClass(array $errors, string $name): string
{
    return 'input-wrap' . (isset($errors[$name]) ? ' invalid' : '');
}
?>
<form method="POST" action="<?= e($aksiUrl) ?>" novalidate id="formPelanggan">
    <?= csrfField() ?>

    <div class="form-grid">

        <div class="form-group">
            <label for="nama">Nama Pelanggan <span class="req">*</span></label>
            <div class="<?= fieldClass($errors, 'nama') ?>">
                <i class="fa-solid fa-user"></i>
                <input type="text" id="nama" name="nama" maxlength="100"
                       placeholder="Contoh: Budi Santoso" required
                       value="<?= e($data['nama'], '') ?>">
            </div>
            <?php if (isset($errors['nama'])): ?>
                <span class="field-error"><?= e($errors['nama']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="telepon">Nomor Telepon / WhatsApp <span class="req">*</span></label>
            <div class="<?= fieldClass($errors, 'telepon') ?>">
                <i class="fa-brands fa-whatsapp"></i>
                <input type="tel" id="telepon" name="telepon" maxlength="20"
                       placeholder="081234567890" inputmode="tel" required
                       value="<?= e($data['telepon'], '') ?>">
            </div>
            <?php if (isset($errors['telepon'])): ?>
                <span class="field-error"><?= e($errors['telepon']) ?></span>
            <?php else: ?>
                <span class="field-hint">Format +62 atau 62 otomatis diubah menjadi 08…</span>
            <?php endif; ?>
        </div>

        <div class="form-group full">
            <label for="email">Email</label>
            <div class="<?= fieldClass($errors, 'email') ?>">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" id="email" name="email" maxlength="100"
                       placeholder="nama@email.com (opsional)"
                       value="<?= e($data['email'], '') ?>">
            </div>
            <?php if (isset($errors['email'])): ?>
                <span class="field-error"><?= e($errors['email']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group full">
            <label for="alamat">Alamat</label>
            <div class="<?= fieldClass($errors, 'alamat') ?>">
                <i class="fa-solid fa-location-dot"></i>
                <textarea id="alamat" name="alamat" maxlength="500"
                          placeholder="Alamat lengkap pelanggan (opsional)"><?= e($data['alamat'], '') ?></textarea>
            </div>
            <?php if (isset($errors['alamat'])): ?>
                <span class="field-error"><?= e($errors['alamat']) ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group full">
            <label for="catatan">Catatan</label>
            <div class="<?= fieldClass($errors, 'catatan') ?>">
                <i class="fa-regular fa-note-sticky"></i>
                <textarea id="catatan" name="catatan" maxlength="500"
                          placeholder="Misal: pelanggan langganan, lebih suka dihubungi lewat WhatsApp (opsional)"><?= e($data['catatan'], '') ?></textarea>
            </div>
            <?php if (isset($errors['catatan'])): ?>
                <span class="field-error"><?= e($errors['catatan']) ?></span>
            <?php endif; ?>
        </div>

        <?php if ($modeEdit): ?>
            <div class="form-group full">
                <label class="check-row">
                    <input type="checkbox" name="sinkron_servis" value="1"
                           <?= !isset($_POST['csrf_token']) || isset($_POST['sinkron_servis']) ? 'checked' : '' ?>>
                    <span>
                        Perbarui juga <strong>nama dan nomor telepon</strong> pada data servis
                        milik pelanggan ini, supaya riwayat servis tetap terhubung.
                    </span>
                </label>
            </div>
        <?php endif; ?>
    </div>

    <div class="form-actions">
        <a href="pelanggan.php" class="btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Batal
        </a>
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-floppy-disk"></i>
            <?= $modeEdit ? 'Simpan Perubahan' : 'Simpan Pelanggan' ?>
        </button>
    </div>
</form>
