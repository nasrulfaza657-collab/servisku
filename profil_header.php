<?php
/**
 * SERVISKU - PROFIL HEADER GLOBAL
 * Menampilkan nama dan foto profil terbaru dari database.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('profilHeaderEsc')) {
    function profilHeaderEsc($value)
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

$profilNama = $_SESSION['nama'] ?? 'Administrator';
$profilRole = $_SESSION['role'] ?? 'admin';
$profilFoto = $_SESSION['foto_profil'] ?? '';

/*
 * Gunakan koneksi PDO yang sudah dimuat oleh halaman.
 * Jika belum tersedia, ambil dari config/koneksi.php.
 */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $fileKoneksi = __DIR__ . '/../config/koneksi.php';

    if (is_file($fileKoneksi)) {
        require_once $fileKoneksi;
    }
}

/*
 * Ambil data terbaru dari database.
 */
if (
    isset($pdo) &&
    $pdo instanceof PDO &&
    !empty($_SESSION['user_id'])
) {
    try {
        $stmtProfil = $pdo->prepare(
            'SELECT nama, role, foto_profil
             FROM users
             WHERE id = ?
             LIMIT 1'
        );

        $stmtProfil->execute([
            (int) $_SESSION['user_id']
        ]);

        $dataProfil = $stmtProfil->fetch(PDO::FETCH_ASSOC);

        if ($dataProfil) {
            $profilNama = $dataProfil['nama'] ?: 'Administrator';
            $profilRole = $dataProfil['role'] ?: 'admin';
            $profilFoto = $dataProfil['foto_profil'] ?: '';

            $_SESSION['nama'] = $profilNama;
            $_SESSION['role'] = $profilRole;
            $_SESSION['foto_profil'] = $profilFoto;
        }
    } catch (PDOException $e) {
        // Gunakan data session jika query gagal.
    }
}

/*
 * Validasi lokasi foto.
 */
$profilFoto = str_replace('\\', '/', (string) $profilFoto);
$profilFoto = ltrim($profilFoto, '/');

$fotoValid = false;
$fotoUrl = '';

if (
    $profilFoto !== '' &&
    preg_match(
        '~^uploads/profil/[a-zA-Z0-9._-]+\.(jpg|jpeg|png|webp)$~i',
        $profilFoto
    )
) {
    $lokasiFoto = __DIR__ . '/../' . $profilFoto;

    if (is_file($lokasiFoto)) {
        $fotoValid = true;

        /*
         * Path absolut dari root project.
         * Cocok dipanggil dari halaman mana pun.
         */
        $fotoUrl = '/sistem-servis-motor/' . $profilFoto;
    }
}

$inisialProfil = strtoupper(
    substr(trim((string) $profilNama), 0, 1)
);
?>

<style>
    /* =====================================
       PROFIL HEADER GLOBAL SERVISKU
    ===================================== */

    .servisku-user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        flex-shrink: 0;
    }

    .servisku-user-avatar {
        width: 44px !important;
        height: 44px !important;

        min-width: 44px !important;
        min-height: 44px !important;

        max-width: 44px !important;
        max-height: 44px !important;

        flex: 0 0 44px !important;

        display: flex;
        align-items: center;
        justify-content: center;

        overflow: hidden;

        border-radius: 50% !important;
        aspect-ratio: 1 / 1;

        background: #e5efff;
        color: #1d4ed8;

        font-size: 20px;
        font-weight: 800;
    }

    .servisku-user-avatar img {
        display: block !important;

        width: 100% !important;
        height: 100% !important;

        min-width: 100% !important;
        min-height: 100% !important;

        max-width: 100% !important;
        max-height: 100% !important;

        object-fit: cover !important;
        object-position: center !important;

        border-radius: 50% !important;
    }

    .servisku-user-info {
        display: flex;
        flex-direction: column;
        gap: 4px;

        min-width: 0;
    }

    .servisku-user-name {
        color: #102a58;

        font-size: 13px;
        font-weight: 800;

        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;

        max-width: 160px;
    }

    .servisku-user-role {
        color: #64748b;

        font-size: 11px;
        font-weight: 500;

        text-transform: capitalize;
    }

    @media (max-width: 600px) {
        .servisku-user-profile {
            gap: 8px;
        }

        .servisku-user-avatar {
            width: 36px !important;
            height: 36px !important;

            min-width: 36px !important;
            min-height: 36px !important;

            max-width: 36px !important;
            max-height: 36px !important;

            flex-basis: 36px !important;
        }

        .servisku-user-name {
            max-width: 95px;
            font-size: 11px;
        }

        .servisku-user-role {
            font-size: 10px;
        }
    }
</style>

<div class="servisku-user-profile">

    <div class="servisku-user-avatar">

        <?php if ($fotoValid): ?>

            <img
                src="<?= profilHeaderEsc($fotoUrl) ?>?v=<?= filemtime($lokasiFoto) ?>"
                alt="Foto Profil"
            >

        <?php else: ?>

            <?= profilHeaderEsc($inisialProfil) ?>

        <?php endif; ?>

    </div>

    <div class="servisku-user-info">

        <span class="servisku-user-name">
            <?= profilHeaderEsc($profilNama) ?>
        </span>

        <span class="servisku-user-role">
            <?= profilHeaderEsc($profilRole) ?>
        </span>

    </div>

</div>