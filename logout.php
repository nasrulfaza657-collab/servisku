<?php
require_once __DIR__ . '/config/koneksi.php';

/* Hapus token "Ingat saya" di database + cookie, lalu akhiri sesi */
rememberForget($pdo);

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $p['path'],
        'domain' => $p['domain'],
        'secure' => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'] ?: 'Lax',
    ]);
}

session_destroy();

header('Location: login.php');
exit;
