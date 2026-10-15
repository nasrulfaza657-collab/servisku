
<?php

$host = 'localhost';
$db   = 'db_servis_montor'; 
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    exit(
        'Koneksi database gagal. Pastikan MySQL aktif dan database db_servis_motor sudah dibuat.'
    );
}


/* Sesi aman + login otomatis dari cookie "Ingat saya" */
require_once __DIR__ . '/../includes/auth.php';
authStartSession();
rememberRestore($pdo);
