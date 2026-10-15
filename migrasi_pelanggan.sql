-- Migrasi modul Pelanggan (opsional: tabel juga dibuat otomatis saat halaman dibuka)
-- Jalankan di phpMyAdmin pada database `db_servis_montor`.

CREATE TABLE IF NOT EXISTS `pelanggan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `telepon` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pelanggan_telepon` (`telepon`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Impor pelanggan dari data servis yang sudah ada (dikelompokkan per nomor telepon)
INSERT IGNORE INTO `pelanggan` (`nama`, `telepon`)
SELECT MAX(`nama_pelanggan`),
       REPLACE(REPLACE(REPLACE(REPLACE(`telepon`,' ',''),'-',''),'+62','0'),'.','')
FROM `servis`
GROUP BY REPLACE(REPLACE(REPLACE(REPLACE(`telepon`,' ',''),'-',''),'+62','0'),'.','');
