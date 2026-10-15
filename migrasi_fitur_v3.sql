-- Jalankan SEKALI setelah mengimpor db_servis_montor.sql
ALTER TABLE users ADD COLUMN foto_profil VARCHAR(255) DEFAULT NULL;
-- Konfigurasi token Fonnte disimpan pada environment variable FONNTE_TOKEN, bukan database.
