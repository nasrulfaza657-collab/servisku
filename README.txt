SERVISKU — MODUL LOGIN PELANGGAN

1. Backup database db_servis_montor.
2. Jalankan migrasi.sql di phpMyAdmin. Jika kolom/index sudah pernah dibuat, lewati perintah tersebut; periksa struktur dahulu. Pastikan tidak ada telepon duplikat sebelum UNIQUE.
3. Salin semua file PHP dalam paket ini ke C:\xampp\htdocs\sistem-servis-motor\ (sejajar login.php, daftar.php, dashboard.php).
4. Akses http://localhost/sistem-servis-motor/register_pelanggan.php untuk pelanggan daftar sendiri.
5. Akses http://localhost/sistem-servis-motor/login_pelanggan.php untuk login pelanggan.
6. Admin mendaftarkan pelanggan lewat menu pelanggan yang sudah ada, lalu buka admin_aktivasi_pelanggan.php untuk mengaktifkan akun yang belum punya password setelah verifikasi identitas.
7. PENTING: pada daftar.php, setelah memilih/menyimpan pelanggan, INSERT ke servis harus menyertakan id_pelanggan. Tanpa itu servis lama/baru tidak akan tampil di dashboard pelanggan. Jangan cocokkan riwayat hanya dari nama/nomor telepon untuk otorisasi.
   Contoh perubahan INSERT:
   INSERT INTO servis (id_pelanggan,nomor_servis,nama_pelanggan,telepon,nomor_polisi,jenis_motor,keluhan,status,estimasi_selesai,catatan_petugas) VALUES (?,?,?,?,?,?,?,?,?,?)
   Isi id_pelanggan dengan ID pelanggan yang dipilih/dibuat dalam transaksi.
8. Untuk servis lama, admin dapat menghubungkan id_pelanggan secara manual setelah verifikasi kepemilikan; jangan mengaitkan massal tanpa audit.
9. Sistem login admin login.php TIDAK diubah. Sesi pelanggan memakai pelanggan_id tersendiri.
10. Catatan: registrasi mandiri di paket ini belum memverifikasi kepemilikan WhatsApp (OTP). Untuk penggunaan publik/produksi, tambahkan verifikasi OTP dan pembatasan percobaan login, HTTPS, reset password yang aman. Hindari membagikan password awal lewat chat yang tidak aman.
