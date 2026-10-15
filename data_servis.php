<?php
/**
 * ==========================================================
 * SERVISKU - DATA SERVIS + NOTIFIKASI WHATSAPP
 * ==========================================================
 *
 * Fitur:
 * - Statistik servis
 * - Pencarian dan filter
 * - Detail servis
 * - Edit menggunakan popup
 * - Hapus menggunakan popup
 * - Riwayat perubahan status
 * - Popup WhatsApp ketika status menjadi Selesai
 * - Kirim WhatsApp menggunakan Fonnte
 * - Riwayat pengiriman otomatis masuk notifikasi_wa
 * - Tampilan responsif
 *
 * Koneksi : config/koneksi.php
 * Sidebar : includes/sidebar.php
 * Profil  : includes/profil_header.php
 * Fonnte  : includes/fonnte.php
 * ==========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==========================================================
   LOGIN
========================================================== */

if (
    empty($_SESSION['user_id']) &&
    empty($_SESSION['id_user']) &&
    empty($_SESSION['username'])
) {
    header('Location: login.php');
    exit;
}

/* ==========================================================
   KONEKSI DAN FONNTE
========================================================== */

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/includes/fonnte.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Koneksi database PDO tidak ditemukan.');
}

$pdo->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

/* ==========================================================
   HELPER
========================================================== */

function dsEsc($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function dsTanggal($tanggal)
{
    if (empty($tanggal)) {
        return '-';
    }

    $timestamp = strtotime((string) $tanggal);

    return $timestamp
        ? date('d/m/Y H:i', $timestamp)
        : '-';
}

function dsStatusClass($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === 'selesai' || $status === 'diambil') {
        return 'selesai';
    }

    if (
        in_array(
            $status,
            ['pengerjaan', 'proses', 'diproses'],
            true
        )
    ) {
        return 'pengerjaan';
    }

    return 'antre';
}

function dsFlash($pesan, $tipe = 'success')
{
    $_SESSION['flash_message'] = $pesan;
    $_SESSION['flash_type'] = $tipe;
}

function dsRedirect()
{
    header('Location: data_servis.php');
    exit;
}

function dsIdValid($value)
{
    return filter_var(
        $value,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
}

/* ==========================================================
   CSRF
========================================================== */

if (empty($_SESSION['csrf_data_servis'])) {
    $_SESSION['csrf_data_servis'] = bin2hex(random_bytes(32));
}

/* ==========================================================
   PROSES POST
========================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = (string) ($_POST['csrf_token'] ?? '');

    if (
        $token === '' ||
        !hash_equals($_SESSION['csrf_data_servis'], $token)
    ) {
        dsFlash(
            'Token keamanan tidak valid. Silakan muat ulang halaman.',
            'error'
        );

        dsRedirect();
    }

    $aksi = trim((string) ($_POST['aksi'] ?? ''));

    try {

        /* ==================================================
           EDIT DATA SERVIS
        ================================================== */

        if ($aksi === 'edit') {

            $id = dsIdValid($_POST['id_servis'] ?? null);

            if (!$id) {
                throw new RuntimeException(
                    'ID servis tidak valid.'
                );
            }

            $nama = trim(
                (string) ($_POST['nama_pelanggan'] ?? '')
            );

            $telepon = trim(
                (string) ($_POST['telepon'] ?? '')
            );

            $polisi = strtoupper(
                trim((string) ($_POST['nomor_polisi'] ?? ''))
            );

            $motor = trim(
                (string) ($_POST['jenis_motor'] ?? '')
            );

            $keluhan = trim(
                (string) ($_POST['keluhan'] ?? '')
            );

            $status = trim(
                (string) ($_POST['status'] ?? '')
            );

            $estimasiInput = trim(
                (string) ($_POST['estimasi_selesai'] ?? '')
            );

            /* VALIDASI */

            if (
                $nama === '' ||
                $polisi === '' ||
                $motor === ''
            ) {
                throw new RuntimeException(
                    'Nama pelanggan, nomor polisi, dan jenis motor wajib diisi.'
                );
            }

            if (
                strlen($nama) > 150 ||
                strlen($telepon) > 30 ||
                strlen($polisi) > 30 ||
                strlen($motor) > 150
            ) {
                throw new RuntimeException(
                    'Ada data yang terlalu panjang.'
                );
            }

            if (
                !in_array(
                    $status,
                    ['Antre', 'Pengerjaan', 'Selesai'],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Status servis tidak valid.'
                );
            }

            /* FORMAT ESTIMASI */

            $estimasi = null;

            if ($estimasiInput !== '') {

                $tanggal = DateTime::createFromFormat(
                    '!Y-m-d\TH:i',
                    $estimasiInput
                );

                if (
                    !$tanggal ||
                    $tanggal->format('Y-m-d\TH:i') !== $estimasiInput
                ) {
                    throw new RuntimeException(
                        'Format tanggal estimasi tidak valid.'
                    );
                }

                $estimasi = $tanggal->format(
                    'Y-m-d H:i:s'
                );
            }

            /* TRANSAKSI */

            $pdo->beginTransaction();

            $cek = $pdo->prepare(
                'SELECT
                    id,
                    nomor_servis,
                    status,
                    selesai_pada
                 FROM servis
                 WHERE id = ?
                 FOR UPDATE'
            );

            $cek->execute([$id]);

            $lama = $cek->fetch(PDO::FETCH_ASSOC);

            if (!$lama) {
                throw new RuntimeException(
                    'Data servis tidak ditemukan.'
                );
            }

            $statusLama = trim(
                (string) ($lama['status'] ?? '')
            );

            /*
             * Popup hanya muncul ketika status sebelumnya
             * bukan Selesai, lalu berubah menjadi Selesai.
             */

            $baruSelesai = (
                strcasecmp($statusLama, 'Selesai') !== 0 &&
                strcasecmp($statusLama, 'Diambil') !== 0 &&
                $status === 'Selesai'
            );

            /*
             * Isi selesai_pada ketika baru selesai.
             * Jika status dibuka kembali, kosongkan.
             */

            $selesaiPada = $lama['selesai_pada'] ?? null;

            if ($baruSelesai) {

                $selesaiPada = date('Y-m-d H:i:s');

            } elseif ($status !== 'Selesai') {

                $selesaiPada = null;
            }

            /* UPDATE */

            $update = $pdo->prepare(
                'UPDATE servis SET
                    nama_pelanggan = ?,
                    telepon = ?,
                    nomor_polisi = ?,
                    jenis_motor = ?,
                    keluhan = ?,
                    status = ?,
                    estimasi_selesai = ?,
                    selesai_pada = ?
                 WHERE id = ?'
            );

            $update->execute([
                $nama,
                $telepon,
                $polisi,
                $motor,
                $keluhan,
                $status,
                $estimasi,
                $selesaiPada,
                $id
            ]);

            /* RIWAYAT PERUBAHAN STATUS */

            if (strcasecmp($statusLama, $status) !== 0) {

                $petugas = $_SESSION['user_id']
                    ?? $_SESSION['id_user']
                    ?? null;

                $petugas = filter_var(
                    $petugas,
                    FILTER_VALIDATE_INT
                );

                if (!$petugas) {
                    $petugas = null;
                }

                $riwayat = $pdo->prepare(
                    'INSERT INTO riwayat_servis
                        (
                            id_servis,
                            status_sebelumnya,
                            status_baru,
                            keterangan,
                            dibuat_oleh
                        )
                     VALUES (?, ?, ?, ?, ?)'
                );

                $riwayat->execute([
                    $id,
                    $statusLama,
                    $status,
                    'Status servis diperbarui melalui halaman Data Servis.',
                    $petugas
                ]);
            }

            $pdo->commit();

            dsFlash(
                'Data servis ' .
                $lama['nomor_servis'] .
                ' berhasil diperbarui.'
            );

            /*
             * SIMPAN INFORMASI POPUP KE SESSION
             *
             * Tidak langsung mengirim WhatsApp.
             * Pengguna harus memilih Kirim atau Lewati.
             */

            if ($baruSelesai) {

                $_SESSION['pending_wa_selesai'] = [
                    'id_servis' => (int) $id,
                    'nomor_servis' => (string) $lama['nomor_servis'],
                    'nama_pelanggan' => $nama,
                    'telepon' => $telepon,
                    'nonce' => bin2hex(random_bytes(16))
                ];
            }

        }

        /* ==================================================
           KIRIM WHATSAPP SERVIS SELESAI
        ================================================== */

        elseif ($aksi === 'kirim_wa_selesai') {

            $id = dsIdValid(
                $_POST['id_servis'] ?? null
            );

            $nonce = (string) (
                $_POST['wa_nonce'] ?? ''
            );

            $pending = $_SESSION['pending_wa_selesai']
                ?? null;

            if (
                !$id ||
                !is_array($pending) ||
                (int) ($pending['id_servis'] ?? 0) !== (int) $id ||
                $nonce === '' ||
                !hash_equals(
                    (string) ($pending['nonce'] ?? ''),
                    $nonce
                )
            ) {
                throw new RuntimeException(
                    'Permintaan WhatsApp tidak valid atau sudah digunakan.'
                );
            }

            /*
             * Hapus token popup sebelum memanggil API
             * supaya permintaan yang sama tidak bisa diulang.
             */

            unset($_SESSION['pending_wa_selesai']);

            /* AMBIL DATA TERBARU */

            $stmt = $pdo->prepare(
                'SELECT
                    id,
                    nomor_servis,
                    nama_pelanggan,
                    telepon,
                    nomor_polisi,
                    jenis_motor,
                    status
                 FROM servis
                 WHERE id = ?
                 LIMIT 1'
            );

            $stmt->execute([$id]);

            $servis = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$servis) {
                throw new RuntimeException(
                    'Data servis tidak ditemukan.'
                );
            }

            if (
                strcasecmp(
                    trim((string) $servis['status']),
                    'Selesai'
                ) !== 0
            ) {
                throw new RuntimeException(
                    'WhatsApp tidak dikirim karena status servis bukan Selesai.'
                );
            }

            /*
             * CEGAH PENGIRIMAN GANDA
             *
             * Jika sebelumnya sudah diterima Fonnte
             * untuk nomor tujuan yang sama, jangan kirim ulang.
             */

            $nomorTujuan = skFormatNomorWa(
                $servis['telepon']
            );

            $cekKirim = $pdo->prepare(
                "SELECT id
                 FROM notifikasi_wa
                 WHERE id_servis = ?
                   AND telepon_tujuan = ?
                   AND jenis_notifikasi = 'Motor Siap Diambil'
                   AND status_pengiriman = 'terkirim'
                 LIMIT 1"
            );

            $cekKirim->execute([
                $id,
                $nomorTujuan
            ]);

            if ($cekKirim->fetchColumn()) {

                dsFlash(
                    'Notifikasi motor siap diambil sudah pernah diterima Fonnte untuk nomor ini. Tidak dikirim ulang.'
                );

            } else {

                /* BUAT PESAN OTOMATIS */

                $pesan = skPesanServisSelesai(
                    $servis
                );

                /* KIRIM MELALUI HELPER FONNTE */

                $hasil = skKirimWhatsApp(
                    $pdo,
                    (int) $id,
                    'Motor Siap Diambil',
                    $pesan
                );

                if (!empty($hasil['sukses'])) {

                    dsFlash(
                        'WhatsApp untuk ' .
                        $servis['nama_pelanggan'] .
                        ' berhasil diterima oleh Fonnte. ' .
                        'Silakan cek riwayat di halaman Notifikasi WhatsApp.'
                    );

                } else {

                    dsFlash(
                        'WhatsApp belum berhasil dikirim: ' .
                        ($hasil['pesan'] ?? 'Kesalahan tidak diketahui.'),
                        'error'
                    );
                }
            }

        }

        /* ==================================================
           LEWATI WHATSAPP
        ================================================== */

        elseif ($aksi === 'lewati_wa_selesai') {

            $id = dsIdValid(
                $_POST['id_servis'] ?? null
            );

            $nonce = (string) (
                $_POST['wa_nonce'] ?? ''
            );

            $pending = $_SESSION['pending_wa_selesai']
                ?? null;

            if (
                !$id ||
                !is_array($pending) ||
                (int) ($pending['id_servis'] ?? 0) !== (int) $id ||
                $nonce === '' ||
                !hash_equals(
                    (string) ($pending['nonce'] ?? ''),
                    $nonce
                )
            ) {
                throw new RuntimeException(
                    'Permintaan tidak valid.'
                );
            }

            unset($_SESSION['pending_wa_selesai']);

            dsFlash(
                'Status servis berhasil disimpan. Pengiriman WhatsApp dilewati.'
            );

        }

        /* ==================================================
           HAPUS DATA SERVIS
        ================================================== */

        elseif ($aksi === 'hapus') {

            $id = dsIdValid(
                $_POST['hapus_id'] ?? null
            );

            if (!$id) {
                throw new RuntimeException(
                    'ID servis tidak valid.'
                );
            }

            $cek = $pdo->prepare(
                'SELECT nomor_servis
                 FROM servis
                 WHERE id = ?'
            );

            $cek->execute([$id]);

            $servis = $cek->fetch(
                PDO::FETCH_ASSOC
            );

            if (!$servis) {
                throw new RuntimeException(
                    'Data servis tidak ditemukan.'
                );
            }

            /*
             * Tidak menghapus riwayat/notifikasi secara paksa.
             * Foreign key database tetap dihormati.
             */

            $hapus = $pdo->prepare(
                'DELETE FROM servis WHERE id = ?'
            );

            $hapus->execute([$id]);

            if ($hapus->rowCount() < 1) {
                throw new RuntimeException(
                    'Data servis gagal dihapus.'
                );
            }

            dsFlash(
                'Data servis ' .
                $servis['nomor_servis'] .
                ' berhasil dihapus.'
            );

        } else {

            throw new RuntimeException(
                'Aksi tidak dikenal.'
            );
        }

        /* PERBARUI TOKEN CSRF */

        $_SESSION['csrf_data_servis'] = bin2hex(
            random_bytes(32)
        );

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException) {

            $pesan = $aksi === 'hapus'
                ? 'Data servis tidak dapat dihapus karena masih digunakan oleh data lain.'
                : 'Gagal memproses data. Periksa struktur database.';

        } else {

            $pesan = $e->getMessage();
        }

        dsFlash($pesan, 'error');
    }

    dsRedirect();
}

/* ==========================================================
   FLASH MESSAGE
========================================================== */

$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';

unset(
    $_SESSION['flash_message'],
    $_SESSION['flash_type']
);

/* ==========================================================
   POPUP WHATSAPP PENDING
========================================================== */

$pendingWa = $_SESSION['pending_wa_selesai']
    ?? null;

/* ==========================================================
   STATISTIK
========================================================== */

$totalServis = 0;
$totalAntre = 0;
$totalPengerjaan = 0;
$totalSelesai = 0;

try {

    $stat = $pdo->query(
        "SELECT
            COUNT(*) AS total,

            COALESCE(SUM(
                CASE WHEN LOWER(status) = 'antre'
                THEN 1 ELSE 0 END
            ), 0) AS antre,

            COALESCE(SUM(
                CASE WHEN LOWER(status) IN
                ('pengerjaan', 'proses', 'diproses')
                THEN 1 ELSE 0 END
            ), 0) AS pengerjaan,

            COALESCE(SUM(
                CASE WHEN LOWER(status) IN
                ('selesai', 'diambil')
                THEN 1 ELSE 0 END
            ), 0) AS selesai

         FROM servis"
    )->fetch(PDO::FETCH_ASSOC);

    $totalServis = (int) ($stat['total'] ?? 0);
    $totalAntre = (int) ($stat['antre'] ?? 0);
    $totalPengerjaan = (int) ($stat['pengerjaan'] ?? 0);
    $totalSelesai = (int) ($stat['selesai'] ?? 0);

} catch (PDOException $e) {

    $flashMessage = 'Gagal mengambil statistik servis.';
    $flashType = 'error';
}

/* ==========================================================
   FILTER
========================================================== */

$keyword = trim(
    (string) ($_GET['cari'] ?? '')
);

$statusFilter = trim(
    (string) ($_GET['status'] ?? '')
);

if (strlen($keyword) > 100) {
    $keyword = substr($keyword, 0, 100);
}

$sql = '
    SELECT
        id,
        nomor_servis,
        nama_pelanggan,
        telepon,
        nomor_polisi,
        jenis_motor,
        keluhan,
        status,
        estimasi_selesai,
        created_at
    FROM servis
    WHERE 1 = 1
';

$params = [];

if ($keyword !== '') {

    $sql .= '
        AND (
            nomor_servis LIKE ?
            OR nama_pelanggan LIKE ?
            OR telepon LIKE ?
            OR nomor_polisi LIKE ?
            OR jenis_motor LIKE ?
        )
    ';

    $like = '%' . $keyword . '%';

    $params = [
        $like,
        $like,
        $like,
        $like,
        $like
    ];
}

if ($statusFilter === 'Antre') {

    $sql .= " AND LOWER(status) = 'antre'";

} elseif ($statusFilter === 'Pengerjaan') {

    $sql .= "
        AND LOWER(status) IN
        ('pengerjaan', 'proses', 'diproses')
    ";

} elseif ($statusFilter === 'Selesai') {

    $sql .= "
        AND LOWER(status) IN
        ('selesai', 'diambil')
    ";
}

$sql .= ' ORDER BY created_at DESC, id DESC';

$dataServis = [];

try {

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $dataServis = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    $flashMessage = 'Gagal mengambil daftar servis.';
    $flashType = 'error';
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Data Servis | ServisKu</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <style>

        :root {
            --navy: #142238;
            --blue: #0875f9;
            --text: #172d50;
            --muted: #7487a5;
            --border: #e2eafb;
            --bg: #f4f7fc;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', Arial, sans-serif;
            color: var(--text);
            background: var(--bg);
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .main {
            min-height: 100vh;
            margin-left: 250px;
        }

        /* TOPBAR */

        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;

            height: 75px;
            padding: 0 26px;

            display: flex;
            align-items: center;
            gap: 20px;

            background: rgba(255,255,255,.97);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(15px);
        }

        .menu-toggle {
            border: 0;
            background: transparent;
            color: #6c83a4;
            font-size: 22px;
        }

        .top-search {
            display: flex;
            align-items: center;
            gap: 10px;

            width: 320px;
            max-width: 100%;

            padding: 11px 14px;
            border-radius: 10px;

            background: #f1f5fb;
            color: #8195b3;
            font-size: 13px;
        }

        .top-spacer {
            flex: 1;
        }

        /* PROFIL HEADER */

        .topbar .servisku-user-profile {
            display: flex;
            align-items: center;
            gap: 11px;
            flex-shrink: 0;
        }

        .topbar .servisku-user-avatar {
            width: 43px !important;
            height: 43px !important;
            min-width: 43px !important;
            min-height: 43px !important;
            max-width: 43px !important;
            max-height: 43px !important;

            display: flex !important;
            align-items: center;
            justify-content: center;

            border-radius: 50% !important;
            overflow: hidden;

            background: #e7f0ff;
            color: #315c94;
        }

        .topbar .servisku-user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            border-radius: 50% !important;
        }

        .topbar .servisku-user-name {
            display: block;
            max-width: 150px;

            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;

            font-size: 12px;
            font-weight: 800;
        }

        .topbar .servisku-user-role {
            display: block;
            margin-top: 5px;

            color: var(--muted);
            font-size: 11px;
        }

        /* CONTENT */

        .content {
            min-height: calc(100vh - 75px);
            padding: 25px;

            background:
                radial-gradient(
                    ellipse at top right,
                    #e3eeff,
                    transparent 36%
                ),
                var(--bg);
        }

        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 18px;

            margin-bottom: 22px;
        }

        .heading-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .heading-icon {
            width: 52px;
            height: 52px;

            display: grid;
            place-items: center;

            border-radius: 15px;

            background: linear-gradient(135deg, #35aaff, #0872ed);
            color: white;
            font-size: 21px;

            box-shadow: 0 8px 18px #0875f92a;
        }

        .page-heading h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
        }

        .page-heading p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 12px 17px;

            border: 0;
            border-radius: 10px;

            background: linear-gradient(110deg, #218bff, #0863ed);
            color: white;

            font-size: 12px;
            font-weight: 700;

            box-shadow: 0 6px 16px #0875f926;
            transition: .2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        /* STATS */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 22px;
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 14px;

            padding: 20px 17px;

            border: 1px solid var(--border);
            border-radius: 14px;

            background: white;
            box-shadow: 0 5px 18px #183c6b08;
        }

        .stat-icon {
            flex-shrink: 0;

            width: 52px;
            height: 52px;

            display: grid;
            place-items: center;

            border-radius: 15px;
            color: white;
            font-size: 21px;
        }

        .blue {
            background: linear-gradient(135deg, #35aaff, #0872ed);
        }

        .yellow {
            background: linear-gradient(135deg, #ffd044, #ffa500);
        }

        .purple {
            background: linear-gradient(135deg, #b879ff, #8742e9);
        }

        .green {
            background: linear-gradient(135deg, #43dca5, #12a66b);
        }

        .stat-info p {
            margin: 0;
            color: #7186a7;
            font-size: 11px;
            font-weight: 600;
        }

        .stat-info strong {
            display: block;
            margin-top: 5px;

            color: #14294b;
            font-size: 30px;
            font-weight: 800;
        }

        /* PANEL */

        .panel {
            padding: 21px;

            border: 1px solid var(--border);
            border-radius: 15px;

            background: white;
            box-shadow: 0 5px 20px #183c6b08;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;

            margin-bottom: 20px;
        }

        .panel-header h2 {
            display: flex;
            align-items: center;
            gap: 11px;

            margin: 0;

            font-size: 16px;
            font-weight: 800;
        }

        .panel-header h2 i {
            color: #147df9;
        }

        .panel-header p {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 11px;
        }

        .result-count {
            padding: 8px 12px;
            border-radius: 8px;

            background: #edf5ff;
            color: #1769cb;

            font-size: 11px;
            font-weight: 700;
        }

        /* FILTER */

        .filter-bar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 11px;

            margin-bottom: 18px;
            padding: 14px;

            border: 1px solid #e9f0fa;
            border-radius: 11px;

            background: #f9fbff;
        }

        .filter-search {
            flex: 1;
            min-width: 210px;

            display: flex;
            align-items: center;
            gap: 10px;

            padding: 11px 13px;

            border: 1px solid #e1eaf7;
            border-radius: 9px;

            background: white;
            color: #7c91af;
        }

        .filter-search input {
            width: 100%;
            min-width: 0;

            border: 0;
            outline: 0;

            background: transparent;
            color: var(--text);
            font-size: 12px;
        }

        .filter-bar select {
            min-width: 145px;
            padding: 11px 13px;

            border: 1px solid #e1eaf7;
            border-radius: 9px;

            background: white;
            color: #435c80;
            font-size: 12px;
        }

        .filter-button,
        .reset-button {
            padding: 11px 15px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 700;
        }

        .filter-button {
            border: 0;
            background: #147df9;
            color: white;
        }

        .reset-button {
            border: 1px solid #e1eaf7;
            background: white;
            color: #647b9e;
        }

        /* TABLE */

        .table-wrap {
            width: 100%;
            overflow-x: auto;

            border: 1px solid #e5edf8;
            border-radius: 11px;
        }

        table {
            width: 100%;
            min-width: 1060px;
            border-collapse: collapse;
            font-size: 11px;
        }

        thead {
            background: linear-gradient(100deg, #f0f5fd, #f8faff);
        }

        th {
            padding: 16px 12px;
            text-align: left;

            color: #304a70;
            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;

            border-top: 1px solid #eaf0f8;

            color: #526b90;
            line-height: 1.6;
            vertical-align: middle;
        }

        tbody tr:hover {
            background: #f5f9ff;
        }

        .service-number {
            color: #1769cb;
            font-weight: 800;
            white-space: nowrap;
        }

        .customer-name {
            color: #263e61;
            font-weight: 700;
        }

        .plate {
            display: inline-block;
            padding: 5px 8px;

            border-radius: 5px;

            background: #f0f4fa;
            color: #344c6d;

            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* STATUS */

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 7px 10px;
            border-radius: 30px;

            font-size: 10px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status::before {
            content: '';

            width: 6px;
            height: 6px;

            border-radius: 50%;
            background: currentColor;
        }

        .status.antre {
            color: #bd7800;
            background: #fff1c9;
        }

        .status.pengerjaan {
            color: #7541db;
            background: #eee3ff;
        }

        .status.selesai {
            color: #078655;
            background: #d5f7e8;
        }

        /* ACTION */

        .actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-action {
            width: 32px;
            height: 32px;

            display: inline-grid;
            place-items: center;

            border: 0;
            border-radius: 7px;

            font-size: 12px;
            transition: .2s;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .btn-view {
            color: #0875f9;
            background: #eaf3ff;
        }

        .btn-edit {
            color: #8352d9;
            background: #f0e9ff;
        }

        .btn-delete {
            color: #e14b55;
            background: #fff0f0;
        }

        /* ALERT */

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;

            margin-bottom: 18px;
            padding: 14px 16px;

            border: 1px solid transparent;
            border-radius: 10px;

            font-size: 12px;
            line-height: 1.6;
        }

        .alert.success {
            color: #08764d;
            background: #e8fff4;
            border-color: #b9f0d8;
        }

        .alert.error {
            color: #b42332;
            background: #fff0f1;
            border-color: #ffd2d5;
        }

        .empty {
            padding: 50px 20px;
            text-align: center;
            color: #8b9db7;
        }

        .empty i {
            display: block;
            margin-bottom: 14px;
            font-size: 35px;
        }

        .empty strong {
            display: block;
            margin-bottom: 7px;
            font-size: 13px;
        }

        .footer {
            margin-top: 22px;
            text-align: center;
            color: #8799b4;
            font-size: 10px;
        }

        /* MODAL */

        .sv-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 99999;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;

            background: rgba(7, 21, 48, .62);

            opacity: 0;
            visibility: hidden;
            pointer-events: none;

            backdrop-filter: blur(0);

            transition: .3s;
        }

        .sv-modal-backdrop.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            backdrop-filter: blur(7px);
        }

        .sv-modal {
            width: 100%;
            max-height: calc(100dvh - 40px);
            overflow-y: auto;

            border: 1px solid #e3ebf8;
            border-radius: 20px;

            background: white;
            box-shadow: 0 25px 80px rgba(5, 22, 51, .3);

            transform: translateY(30px) scale(.95);
            transition: .3s;
        }

        .sv-modal-backdrop.show .sv-modal {
            transform: translateY(0) scale(1);
        }

        .sv-modal-large {
            max-width: 720px;
        }

        .sv-modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;

            padding: 23px 26px;

            border-bottom: 1px solid #e8eef8;
        }

        .sv-modal-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .sv-modal-title-icon {
            flex-shrink: 0;

            width: 47px;
            height: 47px;

            display: grid;
            place-items: center;

            border-radius: 13px;

            background: linear-gradient(135deg, #35aaff, #0872ed);
            color: white;
            font-size: 19px;
        }

        .sv-modal-title h2 {
            margin: 0 0 5px;
            color: #14294b;
            font-size: 19px;
        }

        .sv-modal-title p {
            margin: 0;
            color: #7b8faa;
            font-size: 11px;
        }

        .sv-close {
            flex-shrink: 0;

            width: 35px;
            height: 35px;

            display: grid;
            place-items: center;

            border: 0;
            border-radius: 9px;

            background: #f1f5fb;
            color: #7487a5;
        }

        .sv-modal-body {
            padding: 25px 26px;
        }

        .sv-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .sv-field {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .sv-full {
            grid-column: 1 / -1;
        }

        .sv-field label {
            color: #344c6d;
            font-size: 12px;
            font-weight: 700;
        }

        .sv-field label span {
            color: #e14b55;
        }

        .sv-field input,
        .sv-field select,
        .sv-field textarea {
            width: 100%;
            min-width: 0;

            padding: 12px 13px;

            border: 1px solid #dfe8f6;
            border-radius: 10px;

            outline: none;

            background: #f9fbff;
            color: #172d50;

            font-size: 12px;
        }

        .sv-field textarea {
            resize: vertical;
            min-height: 90px;
        }

        .sv-field input:focus,
        .sv-field select:focus,
        .sv-field textarea:focus {
            border-color: #0875f9;
            box-shadow: 0 0 0 4px rgba(8, 117, 249, .09);
        }

        .sv-modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;

            padding: 19px 26px;

            border-top: 1px solid #e8eef8;
        }

        .sv-btn-cancel,
        .sv-btn-save,
        .sv-btn-danger,
        .sv-btn-whatsapp {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            padding: 12px 18px;

            border: 0;
            border-radius: 10px;

            font-size: 12px;
            font-weight: 700;
        }

        .sv-btn-cancel {
            background: #eef3fa;
            color: #526b90;
        }

        .sv-btn-save {
            background: linear-gradient(110deg, #218bff, #0863ed);
            color: white;
        }

        .sv-btn-danger {
            background: linear-gradient(110deg, #f36a72, #e03d4b);
            color: white;
        }

        /* DELETE MODAL */

        .sv-modal-delete {
            max-width: 430px;
            padding: 30px 28px 24px;
            text-align: center;
        }

        .sv-delete-icon {
            width: 70px;
            height: 70px;

            display: grid;
            place-items: center;

            margin: 0 auto 20px;

            border-radius: 20px;

            background: #fff0f1;
            color: #e14b55;

            font-size: 28px;
        }

        .sv-modal-delete h2 {
            margin: 0 0 12px;
            font-size: 21px;
        }

        .sv-delete-description {
            color: #7186a7;
            font-size: 13px;
            line-height: 1.7;
        }

        .sv-delete-info {
            display: flex;
            flex-direction: column;
            gap: 6px;

            margin: 20px 0;
            padding: 16px;

            border: 1px solid #e3ebf8;
            border-radius: 11px;

            background: #f7faff;
        }

        .sv-delete-info strong {
            color: #1769cb;
            font-size: 13px;
        }

        .sv-delete-info span {
            color: #526b90;
            font-size: 12px;
        }

        .sv-delete-warning {
            color: #cf4651;
            font-size: 11px;
            line-height: 1.6;
        }

        .sv-delete-actions {
            padding: 22px 0 0;
            border: 0;
        }

        .sv-delete-actions button {
            flex: 1;
        }

        /* ==============================================
           POPUP WHATSAPP SELESAI
        ============================================== */

        .sv-modal-wa {
            max-width: 460px;
            padding: 32px 28px 26px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sv-modal-wa::before {
            content: '';
            position: absolute;

            top: -85px;
            left: 50%;

            width: 260px;
            height: 260px;

            transform: translateX(-50%);

            border-radius: 50%;
            background: #e7fff2;

            z-index: 0;
        }

        .sv-wa-content {
            position: relative;
            z-index: 1;
        }

        .sv-wa-icon {
            width: 88px;
            height: 88px;

            display: grid;
            place-items: center;

            margin: 0 auto 22px;

            border-radius: 50%;

            background: linear-gradient(
                135deg,
                #39df8d,
                #0da968
            );

            color: white;
            font-size: 42px;

            box-shadow: 0 12px 30px rgba(18,166,107,.3);

            animation: svWaBounce 1s ease both;
        }

        @keyframes svWaBounce {
            0% {
                transform: scale(.3);
                opacity: 0;
            }

            55% {
                transform: scale(1.15);
                opacity: 1;
            }

            75% {
                transform: scale(.94);
            }

            100% {
                transform: scale(1);
            }
        }

        .sv-wa-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            padding: 7px 13px;

            border-radius: 30px;

            background: #e1fff0;
            color: #078655;

            font-size: 11px;
            font-weight: 800;

            margin-bottom: 14px;
        }

        .sv-modal-wa h2 {
            margin: 0 0 12px;

            color: #14294b;
            font-size: 24px;
            font-weight: 800;
        }

        .sv-wa-description {
            margin: 0;

            color: #7186a7;
            font-size: 13px;
            line-height: 1.8;
        }

        .sv-wa-customer {
            margin: 22px 0;
            padding: 17px;

            border: 1px solid #dcebe5;
            border-radius: 13px;

            background: #f5fffa;
            text-align: left;
        }

        .sv-wa-customer-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sv-wa-customer-icon {
            width: 42px;
            height: 42px;

            display: grid;
            place-items: center;

            border-radius: 11px;

            background: #dcf9e9;
            color: #0a965b;
            font-size: 17px;
        }

        .sv-wa-customer strong {
            display: block;

            color: #193a2c;
            font-size: 13px;
        }

        .sv-wa-customer span {
            display: block;

            margin-top: 5px;

            color: #64877a;
            font-size: 11px;
        }

        .sv-wa-actions {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 10px;

            margin-top: 24px;
        }

        .sv-wa-actions form {
            margin: 0;
        }

        .sv-wa-actions button {
            width: 100%;
            min-height: 45px;
        }

        .sv-btn-whatsapp {
            background: linear-gradient(
                110deg,
                #27d879,
                #0da85f
            );

            color: white;

            box-shadow: 0 7px 18px rgba(13,168,95,.2);
        }

        .sv-btn-whatsapp:hover {
            filter: brightness(.96);
        }

        .sv-btn-whatsapp:disabled,
        .sv-btn-cancel:disabled {
            opacity: .6;
            cursor: wait;
        }

        .sv-wa-note {
            margin: 15px 0 0;

            color: #9aabc0;
            font-size: 10px;
            line-height: 1.6;
        }

        /* RESPONSIVE */

        @media (max-width: 1250px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 850px) {

            .main {
                margin-left: 0;
            }

            .content {
                padding: 17px;
            }

            .topbar {
                padding: 0 17px;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform .3s;
            }

            .sidebar.open {
                transform: translateX(0);
                box-shadow: 10px 0 35px #0003;
            }
        }

        @media (max-width: 600px) {

            .sv-modal-backdrop {
                padding: 12px;
            }

            .sv-modal {
                max-height: calc(100dvh - 24px);
            }

            .sv-modal-header,
            .sv-modal-body {
                padding: 19px;
            }

            .sv-form-grid {
                grid-template-columns: 1fr;
            }

            .sv-full {
                grid-column: auto;
            }

            .sv-modal-footer {
                padding: 16px 19px;
            }

            .sv-modal-delete {
                padding: 25px 20px 20px;
            }

            .sv-modal-wa {
                padding: 28px 19px 22px;
            }
        }

        @media (max-width: 520px) {

            .content {
                padding: 12px;
            }

            .topbar {
                gap: 10px;
                padding: 0 12px;
            }

            .top-search {
                flex: 1;
                width: auto;
            }

            .top-search span {
                display: none;
            }

            .topbar .servisku-user-info {
                display: none;
            }

            .page-heading h1 {
                font-size: 20px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 13px 10px;
                gap: 9px;
            }

            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 17px;
            }

            .stat-info strong {
                font-size: 25px;
            }

            .panel {
                padding: 13px;
            }

            .filter-search {
                flex-basis: 100%;
            }

            .filter-bar select {
                flex: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .sv-modal,
            .sv-modal-backdrop,
            .sv-wa-icon {
                animation: none;
                transition: none;
            }
        }

    </style>

</head>

<body>

    <!-- SIDEBAR -->

    <?php
    require_once __DIR__ . '/includes/sidebar.php';
    ?>

    <main class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <button
                type="button"
                class="menu-toggle"
                id="menuToggle"
                aria-label="Buka menu"
            >
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="top-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Manajemen data servis</span>
            </div>

            <div class="top-spacer"></div>

            <?php
            if (
                is_file(
                    __DIR__ . '/includes/profil_header.php'
                )
            ) {
                require __DIR__ . '/includes/profil_header.php';
            }
            ?>

        </header>

        <section class="content">

            <!-- HEADING -->

            <div class="page-heading">

                <div class="heading-left">

                    <div class="heading-icon">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>

                    <div>
                        <h1>Data Servis</h1>

                        <p>
                            Kelola, cari, dan pantau seluruh layanan servis motor.
                        </p>
                    </div>

                </div>

                <a href="daftar.php" class="btn-primary">
                    <i class="fa-solid fa-plus"></i>
                    Tambah Servis
                </a>

            </div>

            <!-- ALERT -->

            <?php if ($flashMessage !== ''): ?>

                <div class="alert <?= $flashType === 'error' ? 'error' : 'success' ?>">

                    <i class="fa-solid <?= $flashType === 'error'
                        ? 'fa-circle-exclamation'
                        : 'fa-circle-check' ?>"></i>

                    <span><?= dsEsc($flashMessage) ?></span>

                </div>

            <?php endif; ?>

            <!-- STATISTIK -->

            <div class="stats">

                <div class="stat-card">

                    <div class="stat-icon blue">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>

                    <div class="stat-info">
                        <p>Total Servis</p>
                        <strong><?= number_format($totalServis) ?></strong>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon yellow">
                        <i class="fa-regular fa-clock"></i>
                    </div>

                    <div class="stat-info">
                        <p>Menunggu Antrean</p>
                        <strong><?= number_format($totalAntre) ?></strong>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon purple">
                        <i class="fa-solid fa-wrench"></i>
                    </div>

                    <div class="stat-info">
                        <p>Sedang Dikerjakan</p>
                        <strong><?= number_format($totalPengerjaan) ?></strong>
                    </div>

                </div>

                <div class="stat-card">

                    <div class="stat-icon green">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div class="stat-info">
                        <p>Servis Selesai</p>
                        <strong><?= number_format($totalSelesai) ?></strong>
                    </div>

                </div>

            </div>

            <!-- PANEL -->

            <section class="panel">

                <div class="panel-header">

                    <div>

                        <h2>
                            <i class="fa-solid fa-list"></i>
                            Daftar Seluruh Servis
                        </h2>

                        <p>
                            Informasi pelanggan dan status pengerjaan motor.
                        </p>

                    </div>

                    <div class="result-count">
                        <i class="fa-solid fa-database"></i>
                        <?= count($dataServis) ?> data ditemukan
                    </div>

                </div>

                <!-- FILTER -->

                <form
                    method="GET"
                    action="data_servis.php"
                    class="filter-bar"
                >

                    <div class="filter-search">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            name="cari"
                            placeholder="Cari nomor servis, pelanggan, atau plat..."
                            value="<?= dsEsc($keyword) ?>"
                        >

                    </div>

                    <select name="status">

                        <option value="">
                            Semua Status
                        </option>

                        <option
                            value="Antre"
                            <?= $statusFilter === 'Antre' ? 'selected' : '' ?>
                        >
                            Antre
                        </option>

                        <option
                            value="Pengerjaan"
                            <?= $statusFilter === 'Pengerjaan' ? 'selected' : '' ?>
                        >
                            Pengerjaan
                        </option>

                        <option
                            value="Selesai"
                            <?= $statusFilter === 'Selesai' ? 'selected' : '' ?>
                        >
                            Selesai
                        </option>

                    </select>

                    <button
                        type="submit"
                        class="filter-button"
                    >
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <a
                        href="data_servis.php"
                        class="reset-button"
                    >
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </a>

                </form>

                <!-- TABLE -->

                <div class="table-wrap">

                    <table>

                        <thead>

                            <tr>
                                <th>No</th>
                                <th>Nomor Servis</th>
                                <th>Pelanggan</th>
                                <th>Telepon</th>
                                <th>Nomor Polisi</th>
                                <th>Jenis Motor</th>
                                <th>Keluhan</th>
                                <th>Status</th>
                                <th>Estimasi Selesai</th>
                                <th>Aksi</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php if ($dataServis): ?>

                                <?php foreach ($dataServis as $index => $row): ?>

                                    <tr>

                                        <td>
                                            <?= $index + 1 ?>
                                        </td>

                                        <td>

                                            <span class="service-number">
                                                <?= dsEsc($row['nomor_servis']) ?>
                                            </span>

                                        </td>

                                        <td>

                                            <span class="customer-name">
                                                <?= dsEsc($row['nama_pelanggan']) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?= dsEsc($row['telepon']) ?>
                                        </td>

                                        <td>

                                            <span class="plate">
                                                <?= dsEsc($row['nomor_polisi']) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?= dsEsc($row['jenis_motor']) ?>
                                        </td>

                                        <td>
                                            <?= dsEsc($row['keluhan']) ?>
                                        </td>

                                        <td>

                                            <span class="status <?= dsEsc(dsStatusClass($row['status'])) ?>">
                                                <?= dsEsc($row['status']) ?>
                                            </span>

                                        </td>

                                        <td>
                                            <?= dsEsc(dsTanggal($row['estimasi_selesai'])) ?>
                                        </td>

                                        <td>

                                            <div class="actions">

                                                <!-- DETAIL -->

                                                <a
                                                    href="detail_servis.php?id=<?= (int) $row['id'] ?>"
                                                    class="btn-action btn-view"
                                                    title="Detail servis"
                                                >
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>

                                                <!-- EDIT -->

                                                <button
                                                    type="button"
                                                    class="btn-action btn-edit buka-edit"
                                                    title="Edit servis"

                                                    data-id="<?= (int) $row['id'] ?>"
                                                    data-nomor="<?= dsEsc($row['nomor_servis']) ?>"
                                                    data-nama="<?= dsEsc($row['nama_pelanggan']) ?>"
                                                    data-telepon="<?= dsEsc($row['telepon']) ?>"
                                                    data-polisi="<?= dsEsc($row['nomor_polisi']) ?>"
                                                    data-motor="<?= dsEsc($row['jenis_motor']) ?>"
                                                    data-keluhan="<?= dsEsc($row['keluhan']) ?>"
                                                    data-status="<?= dsEsc($row['status']) ?>"
                                                    data-estimasi="<?= dsEsc($row['estimasi_selesai'] ?? '') ?>"
                                                >
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>

                                                <!-- HAPUS -->

                                                <button
                                                    type="button"
                                                    class="btn-action btn-delete buka-hapus"
                                                    title="Hapus servis"

                                                    data-id="<?= (int) $row['id'] ?>"
                                                    data-nomor="<?= dsEsc($row['nomor_servis']) ?>"
                                                    data-nama="<?= dsEsc($row['nama_pelanggan']) ?>"
                                                >
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="10" class="empty">

                                        <i class="fa-regular fa-folder-open"></i>

                                        <strong>
                                            Data servis tidak ditemukan
                                        </strong>

                                        <span>
                                            Coba ubah kata kunci atau filter status.
                                        </span>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <footer class="footer">
                &copy; <?= date('Y') ?>
                ServisKu — Sistem Manajemen Bengkel Motor
            </footer>

        </section>

    </main>

    <!-- ==================================================
         POPUP EDIT
    ================================================== -->

    <div
        class="sv-modal-backdrop"
        id="modalEdit"
        aria-hidden="true"
    >

        <div
            class="sv-modal sv-modal-large"
            role="dialog"
            aria-modal="true"
            aria-labelledby="judulEdit"
        >

            <div class="sv-modal-header">

                <div class="sv-modal-title">

                    <div class="sv-modal-title-icon">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>

                    <div>

                        <h2 id="judulEdit">
                            Edit Data Servis
                        </h2>

                        <p>
                            Perbarui informasi pelanggan dan pengerjaan motor.
                        </p>

                    </div>

                </div>

                <button
                    type="button"
                    class="sv-close"
                    data-close-modal
                    aria-label="Tutup"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>

            </div>

            <form
                method="POST"
                action="data_servis.php"
                id="formEdit"
            >

                <input
                    type="hidden"
                    name="aksi"
                    value="edit"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= dsEsc($_SESSION['csrf_data_servis']) ?>"
                >

                <input
                    type="hidden"
                    name="id_servis"
                    id="edit_id"
                >

                <div class="sv-modal-body">

                    <div class="sv-form-grid">

                        <div class="sv-field">

                            <label for="edit_nomor">
                                Nomor Servis
                            </label>

                            <input
                                type="text"
                                id="edit_nomor"
                                readonly
                                style="background:#eef3fa;cursor:not-allowed;"
                            >

                        </div>

                        <div class="sv-field">

                            <label for="edit_nama">
                                Nama Pelanggan <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="nama_pelanggan"
                                id="edit_nama"
                                maxlength="150"
                                required
                            >

                        </div>

                        <div class="sv-field">

                            <label for="edit_telepon">
                                Nomor Telepon
                            </label>

                            <input
                                type="tel"
                                name="telepon"
                                id="edit_telepon"
                                maxlength="30"
                            >

                        </div>

                        <div class="sv-field">

                            <label for="edit_polisi">
                                Nomor Polisi <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="nomor_polisi"
                                id="edit_polisi"
                                maxlength="30"
                                required
                            >

                        </div>

                        <div class="sv-field">

                            <label for="edit_motor">
                                Jenis Motor <span>*</span>
                            </label>

                            <input
                                type="text"
                                name="jenis_motor"
                                id="edit_motor"
                                maxlength="150"
                                required
                            >

                        </div>

                        <div class="sv-field">

                            <label for="edit_status">
                                Status Servis <span>*</span>
                            </label>

                            <select
                                name="status"
                                id="edit_status"
                                required
                            >

                                <option value="Antre">
                                    Antre
                                </option>

                                <option value="Pengerjaan">
                                    Pengerjaan
                                </option>

                                <option value="Selesai">
                                    Selesai
                                </option>

                            </select>

                        </div>

                        <div class="sv-field">

                            <label for="edit_estimasi">
                                Estimasi Selesai
                            </label>

                            <input
                                type="datetime-local"
                                name="estimasi_selesai"
                                id="edit_estimasi"
                            >

                        </div>

                        <div class="sv-field sv-full">

                            <label for="edit_keluhan">
                                Keluhan
                            </label>

                            <textarea
                                name="keluhan"
                                id="edit_keluhan"
                                rows="3"
                            ></textarea>

                        </div>

                    </div>

                </div>

                <div class="sv-modal-footer">

                    <button
                        type="button"
                        class="sv-btn-cancel"
                        data-close-modal
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="sv-btn-save"
                    >
                        <i class="fa-solid fa-floppy-disk"></i>
                        Simpan Perubahan
                    </button>

                </div>

            </form>

        </div>

    </div>

    <!-- ==================================================
         POPUP HAPUS
    ================================================== -->

    <div
        class="sv-modal-backdrop"
        id="modalHapus"
        aria-hidden="true"
    >

        <div
            class="sv-modal sv-modal-delete"
            role="dialog"
            aria-modal="true"
            aria-labelledby="judulHapus"
        >

            <div class="sv-delete-icon">
                <i class="fa-solid fa-trash-can"></i>
            </div>

            <h2 id="judulHapus">
                Hapus Data Servis?
            </h2>

            <p class="sv-delete-description">
                Apakah kamu yakin ingin menghapus data servis berikut?
            </p>

            <div class="sv-delete-info">

                <strong id="hapus_nomor"></strong>
                <span id="hapus_nama"></span>

            </div>

            <p class="sv-delete-warning">

                <i class="fa-solid fa-triangle-exclamation"></i>

                Data yang dihapus tidak dapat dikembalikan.
                Jika masih digunakan oleh riwayat atau notifikasi,
                penghapusan mungkin ditolak database.

            </p>

            <form
                method="POST"
                action="data_servis.php"
            >

                <input
                    type="hidden"
                    name="aksi"
                    value="hapus"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= dsEsc($_SESSION['csrf_data_servis']) ?>"
                >

                <input
                    type="hidden"
                    name="hapus_id"
                    id="hapus_id"
                >

                <div class="sv-modal-footer sv-delete-actions">

                    <button
                        type="button"
                        class="sv-btn-cancel"
                        data-close-modal
                    >
                        Batal
                    </button>

                    <button
                        type="submit"
                        class="sv-btn-danger"
                    >
                        <i class="fa-solid fa-trash"></i>
                        Ya, Hapus
                    </button>

                </div>

            </form>

        </div>

    </div>

    <!-- ==================================================
         POPUP WHATSAPP KETIKA SERVIS SELESAI
    ================================================== -->

    <?php if (is_array($pendingWa)): ?>

        <div
            class="sv-modal-backdrop"
            id="modalWhatsApp"
            aria-hidden="true"
        >

            <div
                class="sv-modal sv-modal-wa"
                role="dialog"
                aria-modal="true"
                aria-labelledby="judulWhatsApp"
            >

                <div class="sv-wa-content">

                    <div class="sv-wa-icon">
                        <i class="fa-solid fa-check"></i>
                    </div>

                    <div class="sv-wa-badge">

                        <i class="fa-solid fa-circle-check"></i>

                        STATUS BERHASIL DIPERBARUI

                    </div>

                    <h2 id="judulWhatsApp">
                        Servis Selesai! 🎉
                    </h2>

                    <p class="sv-wa-description">

                        Motor pelanggan sudah selesai dikerjakan.

                        <br>

                        Apakah kamu ingin mengirim
                        <strong>notifikasi WhatsApp</strong>
                        agar pelanggan tahu motornya siap diambil?

                    </p>

                    <div class="sv-wa-customer">

                        <div class="sv-wa-customer-row">

                            <div class="sv-wa-customer-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>

                            <div>

                                <strong>
                                    <?= dsEsc($pendingWa['nama_pelanggan'] ?? '') ?>
                                </strong>

                                <span>
                                    <?= dsEsc($pendingWa['nomor_servis'] ?? '') ?>
                                    &bull;
                                    <?= dsEsc($pendingWa['telepon'] ?? '-') ?>
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="sv-wa-actions">

                        <!-- LEWATI -->

                        <form
                            method="POST"
                            action="data_servis.php"
                            class="form-wa-action"
                        >

                            <input
                                type="hidden"
                                name="aksi"
                                value="lewati_wa_selesai"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= dsEsc($_SESSION['csrf_data_servis']) ?>"
                            >

                            <input
                                type="hidden"
                                name="id_servis"
                                value="<?= (int) ($pendingWa['id_servis'] ?? 0) ?>"
                            >

                            <input
                                type="hidden"
                                name="wa_nonce"
                                value="<?= dsEsc($pendingWa['nonce'] ?? '') ?>"
                            >

                            <button
                                type="submit"
                                class="sv-btn-cancel"
                            >
                                Lewati
                            </button>

                        </form>

                        <!-- KIRIM WHATSAPP -->

                        <form
                            method="POST"
                            action="data_servis.php"
                            class="form-wa-action"
                        >

                            <input
                                type="hidden"
                                name="aksi"
                                value="kirim_wa_selesai"
                            >

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= dsEsc($_SESSION['csrf_data_servis']) ?>"
                            >

                            <input
                                type="hidden"
                                name="id_servis"
                                value="<?= (int) ($pendingWa['id_servis'] ?? 0) ?>"
                            >

                            <input
                                type="hidden"
                                name="wa_nonce"
                                value="<?= dsEsc($pendingWa['nonce'] ?? '') ?>"
                            >

                            <button
                                type="submit"
                                class="sv-btn-whatsapp"
                            >

                                <i class="fa-brands fa-whatsapp"></i>

                                Kirim WhatsApp

                            </button>

                        </form>

                    </div>

                    <p class="sv-wa-note">

                        Pesan dikirim melalui Fonnte dan hasilnya
                        tercatat pada halaman Notifikasi WhatsApp.

                    </p>

                </div>

            </div>

        </div>

    <?php endif; ?>

    <!-- ==================================================
         JAVASCRIPT
    ================================================== -->

    <script>

        document.addEventListener(
            'DOMContentLoaded',
            function () {

                /* SIDEBAR */

                const sidebar =
                    document.getElementById('sidebar') ||
                    document.getElementById('appSidebar') ||
                    document.querySelector('.sidebar');

                const menuToggle =
                    document.getElementById('menuToggle');

                if (sidebar && menuToggle) {

                    menuToggle.addEventListener(
                        'click',
                        function () {
                            sidebar.classList.toggle('open');
                        }
                    );

                    document.addEventListener(
                        'click',
                        function (event) {

                            if (
                                window.innerWidth <= 850 &&
                                sidebar.classList.contains('open') &&
                                !sidebar.contains(event.target) &&
                                !menuToggle.contains(event.target)
                            ) {
                                sidebar.classList.remove('open');
                            }

                        }
                    );

                    window.addEventListener(
                        'resize',
                        function () {

                            if (window.innerWidth > 850) {
                                sidebar.classList.remove('open');
                            }

                        }
                    );
                }

                /* MODAL */

                const modalEdit =
                    document.getElementById('modalEdit');

                const modalHapus =
                    document.getElementById('modalHapus');

                const modalWhatsApp =
                    document.getElementById('modalWhatsApp');

                let tombolTerakhir = null;

                function bukaModal(modal, tombol = null) {

                    if (!modal) {
                        return;
                    }

                    tombolTerakhir = tombol;

                    modal.classList.add('show');

                    modal.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    document.body.style.overflow = 'hidden';
                }

                function tutupModal(modal) {

                    if (!modal) {
                        return;
                    }

                    /*
                     * Popup WhatsApp harus dipilih:
                     * Kirim WhatsApp atau Lewati.
                     */

                    if (modal.id === 'modalWhatsApp') {
                        return;
                    }

                    modal.classList.remove('show');

                    modal.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    document.body.style.overflow = '';

                    if (tombolTerakhir) {
                        tombolTerakhir.focus();
                    }
                }

                /* EDIT */

                document.querySelectorAll(
                    '.buka-edit'
                ).forEach(function (btn) {

                    btn.addEventListener(
                        'click',
                        function () {

                            const data = btn.dataset;

                            document.getElementById(
                                'edit_id'
                            ).value = data.id || '';

                            document.getElementById(
                                'edit_nomor'
                            ).value = data.nomor || '';

                            document.getElementById(
                                'edit_nama'
                            ).value = data.nama || '';

                            document.getElementById(
                                'edit_telepon'
                            ).value = data.telepon || '';

                            document.getElementById(
                                'edit_polisi'
                            ).value = data.polisi || '';

                            document.getElementById(
                                'edit_motor'
                            ).value = data.motor || '';

                            document.getElementById(
                                'edit_keluhan'
                            ).value = data.keluhan || '';

                            /* NORMALISASI STATUS */

                            let status = (
                                data.status || 'Antre'
                            ).toLowerCase();

                            if (
                                status === 'proses' ||
                                status === 'diproses'
                            ) {
                                status = 'pengerjaan';
                            }

                            if (status === 'diambil') {
                                status = 'selesai';
                            }

                            if (status === 'pengerjaan') {

                                status = 'Pengerjaan';

                            } else if (status === 'selesai') {

                                status = 'Selesai';

                            } else {

                                status = 'Antre';
                            }

                            document.getElementById(
                                'edit_status'
                            ).value = status;

                            /* FORMAT ESTIMASI */

                            let estimasi =
                                data.estimasi || '';

                            if (
                                estimasi &&
                                estimasi !== '-'
                            ) {

                                estimasi = estimasi
                                    .replace(' ', 'T')
                                    .slice(0, 16);

                            } else {

                                estimasi = '';
                            }

                            document.getElementById(
                                'edit_estimasi'
                            ).value = estimasi;

                            bukaModal(modalEdit, btn);

                            document.getElementById(
                                'edit_nama'
                            ).focus();

                        }
                    );

                });

                /* HAPUS */

                document.querySelectorAll(
                    '.buka-hapus'
                ).forEach(function (btn) {

                    btn.addEventListener(
                        'click',
                        function () {

                            document.getElementById(
                                'hapus_id'
                            ).value = btn.dataset.id || '';

                            document.getElementById(
                                'hapus_nomor'
                            ).textContent = btn.dataset.nomor || '';

                            document.getElementById(
                                'hapus_nama'
                            ).textContent = btn.dataset.nama || '';

                            bukaModal(modalHapus, btn);

                        }
                    );

                });

                /* TOMBOL TUTUP */

                document.querySelectorAll(
                    '[data-close-modal]'
                ).forEach(function (btn) {

                    btn.addEventListener(
                        'click',
                        function () {

                            const modal = btn.closest(
                                '.sv-modal-backdrop'
                            );

                            tutupModal(modal);

                        }
                    );

                });

                /* KLIK LUAR MODAL */

                [
                    modalEdit,
                    modalHapus
                ].forEach(function (modal) {

                    if (!modal) {
                        return;
                    }

                    modal.addEventListener(
                        'click',
                        function (event) {

                            if (event.target === modal) {
                                tutupModal(modal);
                            }

                        }
                    );

                });

                /* ESC */

                document.addEventListener(
                    'keydown',
                    function (event) {

                        if (event.key === 'Escape') {

                            const modalAktif =
                                document.querySelector(
                                    '.sv-modal-backdrop.show'
                                );

                            if (modalAktif) {
                                tutupModal(modalAktif);
                            }

                        }

                    }
                );

                /* ==========================================
                   POPUP WHATSAPP OTOMATIS
                ========================================== */

                if (modalWhatsApp) {

                    /*
                     * Popup muncul otomatis setelah halaman
                     * selesai dimuat.
                     */

                    setTimeout(function () {

                        bukaModal(modalWhatsApp);

                        const tombolKirim =
                            modalWhatsApp.querySelector(
                                '.sv-btn-whatsapp'
                            );

                        if (tombolKirim) {
                            tombolKirim.focus();
                        }

                    }, 350);
                }

                /* ==========================================
                   CEGAH KLIK KIRIM BERULANG
                ========================================== */

                document.querySelectorAll(
                    '.form-wa-action'
                ).forEach(function (form) {

                    form.addEventListener(
                        'submit',
                        function (event) {

                            if (form.dataset.submitted === '1') {

                                event.preventDefault();
                                return;
                            }

                            form.dataset.submitted = '1';

                            const tombol =
                                form.querySelector(
                                    'button[type="submit"]'
                                );

                            if (tombol) {

                                tombol.style.opacity = '.65';
                                tombol.style.cursor = 'wait';
                                tombol.style.pointerEvents = 'none';

                                if (
                                    tombol.classList.contains(
                                        'sv-btn-whatsapp'
                                    )
                                ) {

                                    tombol.innerHTML =
                                        '<i class="fa-solid fa-spinner fa-spin"></i> Mengirim...';
                                }
                            }

                        }
                    );

                });

            }
        );

    </script>

</body>
</html>