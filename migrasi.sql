-- Jalankan SEKALI pada database db_servis_montor melalui phpMyAdmin.
-- Sebaiknya backup database terlebih dahulu.
ALTER TABLE pelanggan ADD COLUMN password VARCHAR(255) NULL;
ALTER TABLE servis ADD COLUMN id_pelanggan INT NULL;
CREATE INDEX idx_servis_id_pelanggan ON servis(id_pelanggan);
-- Sesuaikan tipe kolom jika pelanggan.id bukan INT.
ALTER TABLE servis ADD CONSTRAINT fk_servis_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id) ON DELETE SET NULL;
-- Menghindari dua akun dengan nomor WA yang sama: rapikan duplikat dahulu sebelum menjalankan.
ALTER TABLE pelanggan ADD UNIQUE KEY uq_pelanggan_telepon (telepon);
