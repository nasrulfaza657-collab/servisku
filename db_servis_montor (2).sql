-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 15, 2026 at 08:54 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_servis_montor`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifikasi_wa`
--

CREATE TABLE `notifikasi_wa` (
  `id` int(11) NOT NULL,
  `id_servis` int(11) NOT NULL,
  `telepon_tujuan` varchar(20) NOT NULL,
  `jenis_notifikasi` enum('Status Servis','Estimasi Berubah','Motor Siap Diambil') NOT NULL,
  `pesan` text NOT NULL,
  `status_pengiriman` enum('menunggu','terkirim','gagal') NOT NULL DEFAULT 'menunggu',
  `referensi_api` varchar(100) DEFAULT NULL,
  `respons_api` text DEFAULT NULL,
  `jumlah_percobaan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `dikirim_pada` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifikasi_wa`
--

INSERT INTO `notifikasi_wa` (`id`, `id_servis`, `telepon_tujuan`, `jenis_notifikasi`, `pesan`, `status_pengiriman`, `referensi_api`, `respons_api`, `jumlah_percobaan`, `dikirim_pada`, `created_at`, `updated_at`) VALUES
(1, 1, '6281234567890', 'Motor Siap Diambil', 'jadiii', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:00:13', '2026-10-14 06:00:13'),
(2, 1, '6281234567890', 'Motor Siap Diambil', 'jadiii', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:00:52', '2026-10-14 06:00:52'),
(3, 2, '6285746863781', 'Motor Siap Diambil', 'dadi cokkk', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:01:05', '2026-10-14 06:01:05'),
(4, 2, '6285746863781', 'Motor Siap Diambil', 'dadi cokkk', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:01:43', '2026-10-14 06:01:43'),
(5, 2, '6285746863781', 'Motor Siap Diambil', 'dadi cokkk', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:01:45', '2026-10-14 06:01:45'),
(6, 2, '6285746863781', 'Motor Siap Diambil', 'dadi cokkk', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:01:48', '2026-10-14 06:01:48'),
(7, 2, '6285746863781', 'Motor Siap Diambil', 'dadi cokkk', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:01:52', '2026-10-14 06:01:52'),
(8, 2, '6285746863781', 'Motor Siap Diambil', 'ws dadi bos', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185730423],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":1000,\"remaining\":999,\"used\":1}},\"requestid\":1043903213,\"status\":true,\"target\":[\"6285746863781\"]}', 1, '2026-10-14 08:05:34', '2026-10-14 06:05:33', '2026-10-14 06:05:34'),
(9, 2, '6285746863781', 'Motor Siap Diambil', 'dadi cokkk', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185730869],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":999,\"remaining\":998,\"used\":1}},\"requestid\":1043906878,\"status\":true,\"target\":[\"6285746863781\"]}', 1, '2026-10-14 08:07:44', '2026-10-14 06:07:34', '2026-10-14 06:07:44'),
(10, 4, '6283848310282', 'Motor Siap Diambil', 'Halo Pelanggan ServisKu,\r\n\r\nKendaraan Anda telah selesai diservis dan siap untuk diambil.\r\n\r\nNomor Servis: SRV-261014-B3985A\r\n\r\nSilakan datang ke bengkel untuk mengambil kendaraan Anda.\r\n\r\nTerima kasih telah menggunakan layanan ServisKu.', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:53:36', '2026-10-14 06:53:36'),
(11, 4, '6283848310282', 'Motor Siap Diambil', 'Halo Pelanggan ServisKu,\r\n\r\nKendaraan Anda telah selesai diservis dan siap untuk diambil.\r\n\r\nNomor Servis: SRV-261014-B3985A\r\n\r\nSilakan datang ke bengkel untuk mengambil kendaraan Anda.\r\n\r\nTerima kasih telah menggunakan layanan ServisKu.', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 06:56:54', '2026-10-14 06:56:54'),
(12, 2, '6285746863781', 'Status Servis', 'Halo Pelanggan ServisKu,\r\n\r\nKami ingin menginformasikan perkembangan servis kendaraan Anda.\r\n\r\nNomor Servis: SRV-261014-B3985A\r\n\r\nSilakan hubungi bengkel untuk informasi status terbaru.\r\n\r\nTerima kasih telah mempercayakan kendaraan Anda kepada ServisKu.', 'menunggu', NULL, NULL, 0, NULL, '2026-10-14 10:36:22', '2026-10-14 10:36:22'),
(13, 2, '6285746863781', 'Motor Siap Diambil', 'Halo Pelanggan ServisKu,\r\n\r\nKendaraan Anda telah selesai diservis dan siap untuk diambil.\r\n\r\nNomor Servis: SRV-261014-18C8F4\r\n\r\nSilakan datang ke bengkel untuk mengambil kendaraan Anda.\r\n\r\nTerima kasih telah menggunakan layanan ServisKu.', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185799529],\"process\":\"pending\",\"quota\":{\"6288976546026\":{\"details\":\"deduced from total quota\",\"quota\":1000,\"remaining\":999,\"used\":1}},\"requestid\":1044361319,\"status\":true,\"target\":[\"6285746863781\"]}', 1, '2026-10-14 12:47:56', '2026-10-14 10:47:55', '2026-10-14 10:47:56'),
(14, 2, '6285746863781', 'Motor Siap Diambil', 'Halo Pelanggan ServisKu,\r\n\r\nKendaraan Anda telah selesai diservis dan siap untuk diambil.\r\n\r\nNomor Servis: SRV-261014-18C8F4\r\n\r\nSilakan datang ke bengkel untuk mengambil kendaraan Anda.\r\n\r\nTerima kasih telah menggunakan layanan ServisKu.', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185799856],\"process\":\"pending\",\"quota\":{\"6288976546026\":{\"details\":\"deduced from total quota\",\"quota\":999,\"remaining\":998,\"used\":1}},\"requestid\":1044365308,\"status\":true,\"target\":[\"6285746863781\"]}', 1, '2026-10-14 12:49:58', '2026-10-14 10:49:58', '2026-10-14 10:49:58'),
(15, 2, '6285746863781', 'Motor Siap Diambil', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *faza*! 👋\r\n\r\n🎉 *KABAR BAIK!*\r\n\r\nServis motor Anda telah *SELESAI*.\r\n\r\n📋 *Nomor Servis:* SRV-261014-18C8F4\r\n🏍️ *Motor:* honda\r\n🔢 *Nomor Polisi:* L 7129 GI\r\n\r\nMotor Anda sudah siap diambil di bengkel kami.\r\n\r\nTerima kasih telah menggunakan layanan *ServisKu*. 🙏', 'gagal', NULL, 'Token FONNTE_TOKEN belum dikonfigurasi.', 1, NULL, '2026-10-14 13:00:37', '2026-10-14 13:00:38'),
(16, 2, '6285746863781', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *faza*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261014-18C8F4\r\n🏍️ *Motor:* honda\r\n📅 *Estimasi Selesai:* -\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Token Fonnte belum dikonfigurasi.', 1, NULL, '2026-10-14 13:14:34', '2026-10-14 13:14:34'),
(17, 2, '6285746863781', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *faza*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261014-18C8F4\r\n🏍️ *Motor:* honda\r\n📅 *Estimasi Selesai:* -\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Token Fonnte belum dikonfigurasi.', 1, NULL, '2026-10-14 13:15:03', '2026-10-14 13:15:03'),
(18, 2, '6285746863781', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *faza*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261014-18C8F4\r\n🏍️ *Motor:* honda\r\n📅 *Estimasi Selesai:* -\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Token Fonnte belum dikonfigurasi.', 1, NULL, '2026-10-14 13:15:39', '2026-10-14 13:15:39'),
(19, 2, '6285746863781', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *faza*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261014-18C8F4\r\n🏍️ *Motor:* honda\r\n📅 *Estimasi Selesai:* -\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Token Fonnte belum dikonfigurasi.', 1, NULL, '2026-10-14 13:15:51', '2026-10-14 13:15:51'),
(20, 2, '6285746863781', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *faza*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261014-18C8F4\r\n🏍️ *Motor:* honda\r\n📅 *Estimasi Selesai:* -\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185822895],\"process\":\"pending\",\"quota\":{\"6288976546026\":{\"details\":\"deduced from total quota\",\"quota\":998,\"remaining\":997,\"used\":1}},\"requestid\":1044526292,\"status\":true,\"target\":[\"6285746863781\"]}', 1, '2026-10-14 15:16:47', '2026-10-14 13:16:47', '2026-10-14 13:16:47'),
(22, 4, '6283848310282', 'Motor Siap Diambil', '🏍️ *SERVISKU — BENGKEL MOTOR*\n\nHalo, Bapak/Ibu *rasya*! 👋\n\n🎉 *KABAR BAIK!*\n\nServis motor Anda telah *SELESAI*.\n\n📋 *Nomor Servis:* SRV-261014-B3985A\n🏍️ *Motor:* Vario 150\n🔢 *Nomor Polisi:* M 89120 HA\n✅ *Status:* SELESAI\n\nMotor Anda sudah siap diambil di bengkel kami.\n\nTerima kasih telah mempercayakan motor Anda kepada *ServisKu*. 🙏', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185910331],\"process\":\"pending\",\"quota\":{\"6288976546026\":{\"details\":\"deduced from total quota\",\"quota\":996,\"remaining\":995,\"used\":1}},\"requestid\":1045127279,\"status\":true,\"target\":[\"6283848310282\"]}', 1, '2026-10-15 03:35:41', '2026-10-15 01:35:40', '2026-10-15 01:35:41'),
(23, 8, '6288976546026', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *assa*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261015-ADAAA23A\r\n🏍️ *Motor:* BEAT 150\r\n📅 *Estimasi Selesai:* 2026-10-17 11:41:00\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Kesalahan cURL: Could not resolve host: api.fonnte.com', 1, NULL, '2026-10-15 03:01:14', '2026-10-15 03:01:14'),
(24, 8, '6288976546026', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *assa*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261015-ADAAA23A\r\n🏍️ *Motor:* BEAT 150\r\n📅 *Estimasi Selesai:* 2026-10-17 11:41:00\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Token Fonnte belum dikonfigurasi.', 1, NULL, '2026-10-15 03:02:27', '2026-10-15 03:02:27'),
(25, 8, '6288976546026', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *assa*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261015-ADAAA23A\r\n🏍️ *Motor:* BEAT 150\r\n📅 *Estimasi Selesai:* 2026-10-17 11:41:00\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'gagal', NULL, 'Kesalahan cURL: Could not resolve host: api.fonnte.com', 1, NULL, '2026-10-15 03:02:51', '2026-10-15 03:02:51'),
(26, 8, '6288976546026', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *assa*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261015-ADAAA23A\r\n🏍️ *Motor:* BEAT 150\r\n📅 *Estimasi Selesai:* 2026-10-17 11:41:00\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185937599],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":998,\"remaining\":997,\"used\":1}},\"requestid\":1045329242,\"status\":true,\"target\":[\"6288976546026\"]}', 1, '2026-10-15 05:03:54', '2026-10-15 03:03:54', '2026-10-15 03:03:54'),
(27, 8, '6288976546026', 'Motor Siap Diambil', '━━━━━━━━━━━━━━━━━━━━\r\n🏍️ *SERVISKU MOTOR SERVICE*\r\n✨ Solusi Terbaik Perawatan Motor Anda\r\n━━━━━━━━━━━━━━━━━━━━\r\n\r\nHalo, Bapak/Ibu *assa*! 👋😊\r\n\r\n🎉 *KABAR GEMBIRA UNTUK ANDA!*\r\n\r\nKami dengan senang hati menginformasikan bahwa proses servis kendaraan Anda telah:\r\n\r\n✅ *SELESAI DENGAN SUKSES!*\r\n\r\n🔧 *DETAIL KENDARAAN*\r\n━━━━━━━━━━━━━━━━━━━━\r\n📋 *No. Servis* : SRV-261015-ADAAA23A\r\n🏍️ *Jenis Motor* : BEAT 150\r\n🔢 *No. Polisi* : KP 0912 AP\r\n📌 *Status* : ✅ SELESAI\r\n━━━━━━━━━━━━━━━━━━━━\r\n\r\n📍 *MOTOR SIAP DIAMBIL!*\r\n\r\nKendaraan Anda kini sudah siap untuk dijemput di bengkel kami. 🛵💨\r\n\r\n💡 *Informasi Penting:*\r\nMohon membawa bukti servis saat melakukan pengambilan kendaraan.\r\n\r\n💙 *TERIMA KASIH!*\r\nTerima kasih telah mempercayakan perawatan motor Anda kepada *ServisKu*.\r\n\r\nKami selalu siap memberikan pelayanan terbaik agar perjalanan Anda tetap aman dan nyaman. ✨\r\n\r\n━━━━━━━━━━━━━━━━━━━━\r\n🏍️ *SERVISKU — BENGKEL MOTOR*\r\n💙 Motor Sehat, Perjalanan Hebat!\r\n━━━━━━━━━━━━━━━━━━━━\r\n\r\n📩 _Pesan ini dikirim otomatis oleh sistem ServisKu._', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185939035],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":997,\"remaining\":996,\"used\":1}},\"requestid\":1045348904,\"status\":true,\"target\":[\"6288976546026\"]}', 1, '2026-10-15 05:09:17', '2026-10-15 03:09:17', '2026-10-15 03:09:17'),
(28, 8, '6288976546026', 'Estimasi Berubah', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *assa*! 👋\r\n\r\nKami ingin menginformasikan perubahan estimasi servis.\r\n\r\n📋 *Nomor Servis:* SRV-261015-ADAAA23A\r\n🏍️ *Motor:* BEAT 150\r\n📅 *Estimasi Selesai:* 2026-10-17 11:41:00\r\n\r\nMohon maaf atas perubahan jadwal.\r\nTerima kasih atas pengertiannya.\r\n\r\n*ServisKu — Bengkel Motor*', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185939651],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":996,\"remaining\":995,\"used\":1}},\"requestid\":1045353057,\"status\":true,\"target\":[\"6288976546026\"]}', 1, '2026-10-15 05:11:35', '2026-10-15 03:11:35', '2026-10-15 03:11:35'),
(29, 8, '6288976546026', 'Status Servis', 'hallo kak', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185945176],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":995,\"remaining\":994,\"used\":1}},\"requestid\":1045393869,\"status\":true,\"target\":[\"6288976546026\"]}', 1, '2026-10-15 05:29:24', '2026-10-15 03:29:24', '2026-10-15 03:29:24'),
(30, 4, '6283848310282', 'Status Servis', '🏍️ *SERVISKU — BENGKEL MOTOR*\r\n\r\nHalo, Bapak/Ibu *rasya*! 👋\r\n\r\nKami ingin menginformasikan perkembangan servis motor Anda.\r\n\r\n📋 *Nomor Servis:* SRV-261014-B3985A\r\n🏍️ *Motor:* Vario 150\r\n🔢 *Nomor Polisi:* M 89120 HA\r\n🔧 *Status Saat Ini:* Selesai\r\n\r\nTerima kasih telah mempercayakan motor Anda kepada *ServisKu*.', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185945488],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":994,\"remaining\":993,\"used\":1}},\"requestid\":1045396064,\"status\":true,\"target\":[\"6283848310282\"]}', 1, '2026-10-15 05:30:22', '2026-10-15 03:30:22', '2026-10-15 03:30:22'),
(31, 9, '6285177863330', 'Motor Siap Diambil', '🏍️ *SERVISKU — BENGKEL MOTOR*\n\nHalo, Bapak/Ibu *reva alvay*! 👋\n\n🎉 *KABAR BAIK!*\n\nServis motor Anda telah *SELESAI*.\n\n📋 *Nomor Servis:* SRV-261015-35D89377\n🏍️ *Motor:* Honda Zx\n🔢 *Nomor Polisi:* M 123 QW\n✅ *Status:* SELESAI\n\nMotor Anda sudah siap diambil di bengkel kami.\n\nTerima kasih telah mempercayakan motor Anda kepada *ServisKu*. 🙏', 'terkirim', NULL, '{\"detail\":\"success! message in queue\",\"id\":[185949107],\"process\":\"pending\",\"quota\":{\"085177863330\":{\"details\":\"deduced from total quota\",\"quota\":993,\"remaining\":992,\"used\":1}},\"requestid\":1045432148,\"status\":true,\"target\":[\"6285177863330\"]}', 1, '2026-10-15 05:46:30', '2026-10-15 03:46:29', '2026-10-15 03:46:30');

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `password` varchar(255) DEFAULT NULL,
  `foto_profil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pelanggan`
--

INSERT INTO `pelanggan` (`id`, `nama`, `telepon`, `email`, `alamat`, `catatan`, `created_at`, `updated_at`, `password`, `foto_profil`) VALUES
(1, 'Budi Santoso', '081234567890', NULL, NULL, NULL, '2026-10-14 04:00:00', '2026-10-14 04:00:00', NULL, NULL),
(2, 'faza', '085746863781', NULL, NULL, NULL, '2026-10-14 04:31:06', '2026-10-14 15:19:05', '$2y$10$vxiBeYfHnQ47eWj57kKL8u2lfTmf5uYCNjwG/L3pCcPIyuvOaxVwi', NULL),
(3, 'rasya', '083848310282', 'rasya@gmail.com', NULL, 'po', '2026-10-14 06:49:03', '2026-10-14 06:49:03', NULL, NULL),
(4, 'assa', '088976546026', NULL, 'jenangan', NULL, '2026-10-14 13:25:51', '2026-10-15 02:30:04', '$2y$10$1IX66Pd4qEBuH2vFgJkCUevI9aJZFvNyqjMMaEtkoyz/jbiskzW36', 'pelanggan_4_8135c444b0674e9a.jpg'),
(6, 'nina', '082179129217', NULL, 'ponorogo', NULL, '2026-10-14 15:12:38', '2026-10-14 15:12:38', NULL, NULL),
(7, 'telo', '08653421876', NULL, 'ponorogo', NULL, '2026-10-14 15:14:20', '2026-10-15 01:38:11', '$2y$10$JmVQfvbDzPCEhgIRkVMeMuReZBr/p.Ut60YqZ/1leaC42Y2sRPWEi', NULL),
(9, 'reva', '6285177863330', NULL, NULL, NULL, '2026-10-15 03:45:03', '2026-10-15 03:48:05', '$2y$10$NR4Uq3Z3qOOfxvNJ381jXOSrpnk9JPCprE7i83rHMBUIyzg.VpcBu', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `selector` char(24) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `selector`, `token_hash`, `expires_at`, `created_at`) VALUES
(3, 1, 'fd10881733e542b1703bf8a4', 'ab28ee8f46a226c028671ed199bfba585128ab87d02159999dbe1dd4fdb6ed6a', '2026-11-14 04:43:25', '2026-10-15 03:43:25');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_servis`
--

CREATE TABLE `riwayat_servis` (
  `id` int(11) NOT NULL,
  `id_servis` int(11) NOT NULL,
  `status_sebelumnya` enum('Antre','Pengerjaan','Selesai') DEFAULT NULL,
  `status_baru` enum('Antre','Pengerjaan','Selesai') DEFAULT NULL,
  `estimasi_sebelumnya` datetime DEFAULT NULL,
  `estimasi_baru` datetime DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `dibuat_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `riwayat_servis`
--

INSERT INTO `riwayat_servis` (`id`, `id_servis`, `status_sebelumnya`, `status_baru`, `estimasi_sebelumnya`, `estimasi_baru`, `keterangan`, `dibuat_oleh`, `created_at`) VALUES
(1, 4, NULL, 'Antre', NULL, NULL, 'Servis baru didaftarkan', 1, '2026-10-14 06:51:53'),
(2, 2, 'Antre', 'Selesai', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-14 12:54:12'),
(3, 4, 'Pengerjaan', 'Antre', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-14 12:57:27'),
(4, 2, 'Selesai', 'Pengerjaan', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-14 13:23:52'),
(5, 2, 'Pengerjaan', 'Selesai', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-14 13:24:08'),
(9, 7, NULL, 'Antre', NULL, NULL, 'Servis baru didaftarkan melalui halaman Servis Baru.', 1, '2026-10-14 15:16:44'),
(10, 7, 'Antre', 'Pengerjaan', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-14 15:39:03'),
(11, 4, 'Antre', 'Selesai', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-15 01:35:36'),
(12, 8, NULL, 'Antre', NULL, NULL, 'Servis baru didaftarkan melalui halaman Servis Baru.', 1, '2026-10-15 01:42:13'),
(13, 8, 'Antre', 'Pengerjaan', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-15 03:13:56'),
(14, 9, NULL, 'Antre', NULL, NULL, 'Servis baru didaftarkan melalui halaman Servis Baru.', 1, '2026-10-15 03:45:03'),
(15, 9, 'Antre', 'Selesai', NULL, NULL, 'Status servis diperbarui melalui halaman Data Servis.', 1, '2026-10-15 03:46:24');

-- --------------------------------------------------------

--
-- Table structure for table `servis`
--

CREATE TABLE `servis` (
  `id` int(11) NOT NULL,
  `nomor_servis` varchar(30) NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `nomor_polisi` varchar(20) NOT NULL,
  `jenis_motor` varchar(100) NOT NULL,
  `keluhan` text NOT NULL,
  `status` enum('Antre','Pengerjaan','Selesai') NOT NULL DEFAULT 'Antre',
  `estimasi_selesai` datetime DEFAULT NULL,
  `selesai_pada` datetime DEFAULT NULL,
  `catatan_petugas` text DEFAULT NULL,
  `alasan_keterlambatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_pelanggan` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `servis`
--

INSERT INTO `servis` (`id`, `nomor_servis`, `nama_pelanggan`, `telepon`, `nomor_polisi`, `jenis_motor`, `keluhan`, `status`, `estimasi_selesai`, `selesai_pada`, `catatan_petugas`, `alasan_keterlambatan`, `created_at`, `updated_at`, `id_pelanggan`) VALUES
(1, 'SRV-261013-ABC123', 'Budi', '081234567890', 'L 1234 ABC', 'Honda Beat', 'Mesin sulit dinyalakan', 'Antre', NULL, NULL, 'Menunggu pemeriksaan mekanik', NULL, '2026-10-13 04:17:06', '2026-10-14 14:22:16', 1),
(2, 'SRV-261014-18C8F4', 'faza', '085746863781', 'L 7129 GI', 'honda', 'ganti oli', 'Selesai', NULL, '2026-10-14 15:24:08', NULL, NULL, '2026-10-14 04:28:38', '2026-10-14 14:22:16', 2),
(4, 'SRV-261014-B3985A', 'rasya', '6283848310282', 'M 89120 HA', 'Vario 150', 'rusak ,oli bocor', 'Selesai', '2026-10-17 13:51:00', '2026-10-15 03:35:36', 'yg baik', NULL, '2026-10-14 06:51:53', '2026-10-15 01:35:36', 3),
(7, 'SRV-261014-B53B3659', 'yuni', '628653421876', 'MP 8323 AP', 'MX 128', 'mesin ngk nyaman', 'Pengerjaan', '2026-10-17 10:16:00', NULL, '', NULL, '2026-10-14 15:16:44', '2026-10-14 15:39:03', 7),
(8, 'SRV-261015-ADAAA23A', 'assa', '6288976546026', 'KP 0912 AP', 'BEAT 150', 'VELG PECAH', 'Pengerjaan', '2026-10-17 11:41:00', NULL, '', NULL, '2026-10-15 01:42:13', '2026-10-15 03:13:56', 4),
(9, 'SRV-261015-35D89377', 'reva alvay', '6285177863330', 'M 123 QW', 'Honda Zx', 'oli bocor', 'Selesai', '2026-10-17 13:44:00', '2026-10-15 05:46:24', '', NULL, '2026-10-15 03:45:03', '2026-10-15 03:46:24', 9);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','petugas') DEFAULT 'petugas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `foto_profil` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `created_at`, `foto_profil`) VALUES
(1, 'Administrator', 'admin', '$2y$10$yFR/y5Y822Jf1gj2v4ylheO4MY41D6ustlmvDzuvm7egK./caUdhO', 'admin', '2026-10-13 04:17:06', 'uploads/profil/6c1bd4bbc99b8b2863358e31bcd0899b.webp');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifikasi_wa`
--
ALTER TABLE `notifikasi_wa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifikasi_status_created` (`status_pengiriman`,`created_at`),
  ADD KEY `idx_notifikasi_id_servis` (`id_servis`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pelanggan_telepon` (`telepon`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_remember_selector` (`selector`),
  ADD KEY `idx_remember_user` (`user_id`);

--
-- Indexes for table `riwayat_servis`
--
ALTER TABLE `riwayat_servis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_riwayat_id_servis` (`id_servis`),
  ADD KEY `idx_riwayat_dibuat_oleh` (`dibuat_oleh`);

--
-- Indexes for table `servis`
--
ALTER TABLE `servis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor_servis` (`nomor_servis`),
  ADD KEY `idx_servis_id_pelanggan` (`id_pelanggan`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifikasi_wa`
--
ALTER TABLE `notifikasi_wa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `riwayat_servis`
--
ALTER TABLE `riwayat_servis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `servis`
--
ALTER TABLE `servis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifikasi_wa`
--
ALTER TABLE `notifikasi_wa`
  ADD CONSTRAINT `fk_notifikasi_wa_servis` FOREIGN KEY (`id_servis`) REFERENCES `servis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `riwayat_servis`
--
ALTER TABLE `riwayat_servis`
  ADD CONSTRAINT `fk_riwayat_servis_servis` FOREIGN KEY (`id_servis`) REFERENCES `servis` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_riwayat_servis_user` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `servis`
--
ALTER TABLE `servis`
  ADD CONSTRAINT `fk_servis_pelanggan` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
