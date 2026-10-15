<?php

require_once __DIR__ . '/config/koneksi.php';

// Ganti dengan username user yang sudah terdaftar.
$username = 'admin';

// Ganti dengan password awal yang ingin digunakan.
$passwordBaru = 'ServisKu@2026';

// Enkripsi password.
$passwordHash = password_hash($passwordBaru, PASSWORD_DEFAULT);

// Update password berdasarkan username.
$stmt = $pdo->prepare("
    UPDATE users
    SET password = ?
    WHERE username = ?
");

$stmt->execute([
    $passwordHash,
    $username
]);

if ($stmt->rowCount() > 0) {
    echo 'Password berhasil dibuat untuk user: '
        . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
} else {
    echo 'User tidak ditemukan atau password tidak berubah.';
}