<?php
/**
 * Helper Autentikasi & Utilitas Pelanggan
 * Terpisah dari session admin.
 */

// Mulai sesi jika belum aktif
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Muat koneksi database
require_once dirname(__DIR__) . '/config/koneksi.php';

// Validasi instance PDO
if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi PDO tidak tersedia.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Escape string untuk mencegah XSS
 */
function pe($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate atau ambil token CSRF khusus pelanggan
 */
function pcsrf(): string {
    return $_SESSION['csrf_pelanggan'] ??= bin2hex(random_bytes(32));
}

/**
 * Validasi token CSRF dari form POST
 */
function pcheck(): void {
    if (!hash_equals(pcsrf(), (string)($_POST['csrf'] ?? ''))) {
        throw new RuntimeException('Sesi formulir tidak valid. Muat ulang halaman.');
    }
}

/**
 * Validasi dan format nomor WhatsApp Indonesia
 */
function pphone(string $raw): string {
    $n = preg_replace('/[^0-9+]/', '', trim($raw));
    
    if (str_starts_with($n, '+62')) {
        $n = substr($n, 1);
    } elseif (str_starts_with($n, '0')) {
        $n = '62' . substr($n, 1);
    }
    
    if (!preg_match('/^62[0-9]{8,13}$/', $n)) {
        throw new RuntimeException('Nomor WhatsApp Indonesia tidak valid.');
    }
    
    return $n;
}

/**
 * Pastikan pelanggan sudah login, jika belum arahkan ke halaman login
 */
function prequire(): int {
    if (empty($_SESSION['pelanggan_id'])) {
        header('Location: login_pelanggan.php');
        exit;
    }
    return (int)$_SESSION['pelanggan_id'];
}

/**
 * Template Header HTML standar portal pelanggan
 */
function ppage(string $title): void {
    echo '<!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>' . pe($title) . ' | ServisKu</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; background: #f2f6fc; color: #15243d; font: 15px system-ui, Arial; }
            .wrap { max-width: 900px; margin: 50px auto; padding: 0 18px; }
            .card { background: white; border: 1px solid #dae4f1; border-radius: 20px; padding: 28px; box-shadow: 0 10px 32px #0b245012; }
            h1 { color: #112d5c; margin-top: 0; }
            .brand { color: #2563eb; font-weight: 800; font-size: 20px; margin-bottom: 18px; }
            label { display: block; margin: 16px 0 7px; font-weight: 650; }
            input, textarea { width: 100%; padding: 13px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; }
            button, .btn { display: inline-block; padding: 12px 18px; margin-top: 20px; background: #2563eb; color: #fff; border: 0; border-radius: 10px; font-weight: 700; text-decoration: none; cursor: pointer; }
            .btn.alt { background: #e9f0fa; color: #1e3a65; }
            a { color: #1d4ed8; }
            .error { padding: 12px; background: #fff0f0; color: #a11919; border-radius: 10px; }
            .ok { padding: 12px; background: #edfff4; color: #176b3d; border-radius: 10px; }
            .row { display: flex; gap: 12px; flex-wrap: wrap; }
            .muted { color: #64748b; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
            th { color: #334155; }
            @media(max-width: 600px) {
                .wrap { margin: 20px auto; }
                .card { padding: 20px; }
                table { font-size: 12px; }
            }
        </style>
    </head>
    <body>
    <div class="wrap">
        <div class="brand">🔧 ServisKu · Portal Pelanggan</div>
        <div class="card">';
}

/**
 * Template Footer HTML standar portal pelanggan
 */
function pend(): void {
    echo '</div></div></body></html>';
}