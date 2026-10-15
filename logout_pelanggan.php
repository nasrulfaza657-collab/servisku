<?php
require __DIR__ . '/_pelanggan.php';

// Hapus sesi data pelanggan
unset($_SESSION['pelanggan_id'], $_SESSION['pelanggan_nama']);

// Regenerasi ID sesi untuk keamanan tambahan
session_regenerate_id(true);

// Arahkan kembali ke halaman login pelanggan
header('Location: login_pelanggan.php');
exit;