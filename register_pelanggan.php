<?php
require __DIR__ . '/_pelanggan.php';

// Jika sudah login, arahkan ke dashboard pelanggan
if (!empty($_SESSION['pelanggan_id'])) {
    header('Location: dashboard_pelanggan.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pcheck();
        
        $nama = trim((string)($_POST['nama'] ?? ''));
        $telepon = pphone((string)($_POST['telepon'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $alamat = trim((string)($_POST['alamat'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        
        if ($nama === '' || mb_strlen($nama) > 150) {
            throw new RuntimeException('Nama wajib diisi (maksimal 150 karakter).');
        }
        
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email tidak valid.');
        }
        
        if (strlen($pass) < 8 || strlen($pass) > 128) {
            throw new RuntimeException('Password harus 8–128 karakter.');
        }
        
        if (!hash_equals($pass, (string)($_POST['konfirmasi'] ?? ''))) {
            throw new RuntimeException('Konfirmasi password tidak sama.');
        }
        
        $pdo->beginTransaction();
        
        $s = $pdo->prepare('SELECT id, password FROM pelanggan WHERE telepon = ? LIMIT 1 FOR UPDATE');
        $s->execute([$telepon]);
        $old = $s->fetch(PDO::FETCH_ASSOC);
        
        if ($old) {
            // Nomor yang sudah didaftarkan admin tidak boleh diklaim tanpa verifikasi kepemilikan.
            throw new RuntimeException('Nomor sudah terdaftar. Jika didaftarkan admin, minta admin mengaktifkan akun setelah verifikasi nomor.');
        }
        
        $s = $pdo->prepare('INSERT INTO pelanggan (nama, telepon, email, alamat, password) VALUES (?, ?, ?, ?, ?)');
        $s->execute([
            $nama, 
            $telepon, 
            $email ?: null, 
            $alamat ?: null, 
            password_hash($pass, PASSWORD_DEFAULT)
        ]);
        
        $pdo->commit();
        $success = 'Pendaftaran berhasil. Silakan login.';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e instanceof PDOException ? 'Gagal mendaftar. Periksa data atau hubungi admin.' : $e->getMessage();
    }
}

ppage('Daftar Pelanggan');
?>

<h1>Daftar Akun Pelanggan</h1>
<p class="muted">
    Pendaftaran mandiri untuk pelanggan baru. Jika admin sudah mendaftarkan nomor Anda, hubungi admin untuk aktivasi.
</p>

<?php if ($error): ?>
    <div class="error"><?= pe($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="ok"><?= pe($success) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= pe(pcsrf()) ?>">
    
    <label>Nama lengkap</label>
    <input name="nama" maxlength="150" required value="<?= pe($_POST['nama'] ?? '') ?>">
    
    <label>Nomor WhatsApp</label>
    <input name="telepon" type="tel" placeholder="081234567890" required value="<?= pe($_POST['telepon'] ?? '') ?>">
    
    <label>Email (opsional)</label>
    <input name="email" type="email" value="<?= pe($_POST['email'] ?? '') ?>">
    
    <label>Alamat (opsional)</label>
    <textarea name="alamat"><?= pe($_POST['alamat'] ?? '') ?></textarea>
    
    <label>Password (minimal 8 karakter)</label>
    <input name="password" type="password" minlength="8" required autocomplete="new-password">
    
    <label>Ulangi password</label>
    <input name="konfirmasi" type="password" minlength="8" required autocomplete="new-password">
    
    <button type="submit">Daftar Sekarang</button> 
    <a class="btn alt" href="login_pelanggan.php">Sudah punya akun? Login</a>
</form>

<?php 
pend(); 
?>