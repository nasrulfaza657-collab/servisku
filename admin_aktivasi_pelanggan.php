<?php
require __DIR__ . '/_pelanggan.php';

// Pastikan user sudah login
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Role admin wajib dicek dari database, bukan dari input form.
$s = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$s->execute([(int)$_SESSION['user_id']]);

if (strtolower((string)$s->fetchColumn()) !== 'admin') {
    http_response_code(403);
    exit('Khusus admin.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        pcheck();
        
        $id = filter_var($_POST['id_pelanggan'] ?? null, FILTER_VALIDATE_INT);
        $pass = (string)($_POST['password'] ?? '');
        
        if (!$id || strlen($pass) < 8 || strlen($pass) > 128) {
            throw new RuntimeException('Pilih pelanggan dan isi password 8–128 karakter.');
        }
        
        if (!hash_equals($pass, (string)($_POST['konfirmasi'] ?? ''))) {
            throw new RuntimeException('Konfirmasi password tidak cocok.');
        }
        
        // Admin harus memverifikasi identitas/kepemilikan nomor sebelum mengaktifkan.
        if (empty($_POST['verifikasi'])) {
            throw new RuntimeException('Verifikasi identitas pelanggan wajib dilakukan.');
        }
        
        $s = $pdo->prepare("UPDATE pelanggan SET password = ? WHERE id = ? AND (password IS NULL OR password = '')");
        $s->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        
        if ($s->rowCount() !== 1) {
            throw new RuntimeException('Akun tidak ditemukan atau sudah aktif. Reset password memerlukan alur terpisah.');
        }
        
        $success = 'Akun pelanggan diaktifkan. Berikan password kepada pelanggan melalui saluran aman dan minta segera menggantinya.';
    } catch (Throwable $e) {
        $error = $e instanceof PDOException ? 'Gagal mengaktifkan akun.' : $e->getMessage();
    }
}

// Ambil daftar pelanggan yang belum memiliki password
$customers = $pdo->query("SELECT id, nama, telepon FROM pelanggan WHERE password IS NULL OR password = '' ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

ppage('Aktivasi Akun oleh Admin');
?>

<h1>Aktivasi Akun Pelanggan</h1>
<p class="muted">
    Untuk pelanggan yang sebelumnya didaftarkan admin. Verifikasi identitas/nomor WhatsApp pelanggan sebelum menetapkan password. Jangan gunakan nomor telepon sebagai password.
</p>

<?php if ($error): ?>
    <div class="error"><?= pe($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="ok"><?= pe($success) ?></div>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf" value="<?= pe(pcsrf()) ?>">
    
    <label>Pelanggan belum aktif</label>
    <select name="id_pelanggan" required style="width: 100%; padding: 13px; border: 1px solid #cbd5e1; border-radius: 10px;">
        <option value="">Pilih pelanggan</option>
        <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= pe($c['nama'] . ' — ' . $c['telepon']) ?></option>
        <?php endforeach; ?>
    </select>
    
    <label>Password awal (8–128 karakter)</label>
    <input type="password" name="password" minlength="8" required autocomplete="new-password">
    
    <label>Konfirmasi password</label>
    <input type="password" name="konfirmasi" minlength="8" required autocomplete="new-password">
    
    <label style="font-weight: 400; margin-top: 20px;">
        <input type="checkbox" name="verifikasi" value="1" required style="width: auto; margin-right: 8px;">
        Saya telah memverifikasi identitas/kepemilikan nomor pelanggan.
    </label>
    
    <button type="submit">Aktifkan Akun</button>
</form>

<p style="margin-top: 20px;">
    <a href="dashboard.php">← Dashboard admin</a>
</p>

<?php 
pend(); 
?>