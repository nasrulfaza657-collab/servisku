<?php
/**
 * Sesi + cookie "Ingat saya".
 *
 * - Cookie sesi (PHPSESSID) dibuat HttpOnly + SameSite=Lax (+ Secure di HTTPS).
 * - Cookie "remember_me" berisi "selector:validator". Yang disimpan di database
 *   hanya hash SHA-256 dari validator, jadi bocornya tabel tidak membocorkan login.
 * - Token dirotasi setiap dipakai dan dihapus saat logout.
 */

const REMEMBER_COOKIE = 'remember_me';
const REMEMBER_HARI = 30;

function authHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443);
}

function authStartSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => authHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function rememberSetCookie(string $nilai, int $kedaluwarsa): void
{
    setcookie(REMEMBER_COOKIE, $nilai, [
        'expires' => $kedaluwarsa,
        'path' => '/',
        'secure' => authHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function rememberEnsureTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            selector CHAR(24) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            UNIQUE KEY uq_remember_selector (selector),
            KEY idx_remember_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
}

/** Buat token baru untuk user dan pasang cookie-nya. */
function rememberIssue(PDO $pdo, int $userId): void
{
    rememberEnsureTable($pdo);

    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_HARI * 86400;

    $pdo->prepare('DELETE FROM remember_tokens WHERE expires_at < NOW()')->execute();
    $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
         VALUES (?, ?, ?, ?)'
    )->execute([
        $userId,
        $selector,
        hash('sha256', $validator),
        date('Y-m-d H:i:s', $expires),
    ]);

    rememberSetCookie($selector . ':' . $validator, $expires);
}

/** Hapus token yang sedang dipakai browser ini beserta cookie-nya. */
function rememberForget(PDO $pdo): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';

    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);

        try {
            $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')
                ->execute([$selector]);
        } catch (PDOException $e) {
            /* tabel belum ada: tidak ada yang perlu dihapus */
        }
    }

    rememberSetCookie('', time() - 3600);
    unset($_COOKIE[REMEMBER_COOKIE]);
}

/** Login otomatis dari cookie jika sesi belum ada. */
function rememberRestore(PDO $pdo): void
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE[REMEMBER_COOKIE])) {
        return;
    }

    $cookie = (string) $_COOKIE[REMEMBER_COOKIE];

    if (!preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) {
        rememberSetCookie('', time() - 3600);
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT user_id, token_hash FROM remember_tokens
             WHERE selector = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([$m[1]]);
        $row = $stmt->fetch();

        if (!$row || !hash_equals($row['token_hash'], hash('sha256', $m[2]))) {
            /* Token tidak dikenal / salah: bersihkan cookie */
            if ($row) {
                $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')
                    ->execute([$m[1]]);
            }
            rememberSetCookie('', time() - 3600);
            return;
        }

        $stmt = $pdo->prepare('SELECT id, nama, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$row['user_id']]);
        $user = $stmt->fetch();

        if (!$user) {
            rememberForget($pdo);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['role'] = $user['role'];

        /* Rotasi: token lama dibuang, token baru diterbitkan */
        $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?')
            ->execute([$m[1]]);
        rememberIssue($pdo, (int) $user['id']);
    } catch (PDOException $e) {
        /* tabel belum ada / galat DB: abaikan, user cukup login manual */
    }
}
