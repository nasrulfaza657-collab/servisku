-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 13, 2026 at 07:55 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `servis`
--

INSERT INTO `servis` (`id`, `nomor_servis`, `nama_pelanggan`, `telepon`, `nomor_polisi`, `jenis_motor`, `keluhan`, `status`, `estimasi_selesai`, `selesai_pada`, `catatan_petugas`, `alasan_keterlambatan`, `created_at`, `updated_at`) VALUES
(1, 'SRV-261013-ABC123', 'Budi Santoso', '081234567890', 'L 1234 ABC', 'Honda Beat', 'Mesin sulit dinyalakan', 'Antre', NULL, NULL, 'Menunggu pemeriksaan mekanik', NULL, '2026-10-13 04:17:06', '2026-10-13 04:17:06');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2*uHEG4M6', 'admin', '2026-10-13 04:17:06');

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
  ADD UNIQUE KEY `nomor_servis` (`nomor_servis`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `riwayat_servis`
--
ALTER TABLE `riwayat_servis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `servis`
--
ALTER TABLE `servis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
