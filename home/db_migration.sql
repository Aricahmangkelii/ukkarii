-- =========================================================
-- MIGRASI untuk database yang sudah dibuat dari database.sql
-- versi LAMA (sebelum revisi ini). Jalankan skrip ini SEKALI
-- di phpMyAdmin > tab SQL jika Anda TIDAK ingin drop & re-import
-- database dari awal.
--
-- Jika database masih kosong / baru, LEWATI file ini dan cukup
-- import database.sql saja.
-- =========================================================

USE perpustakaan_sekolah_digital;

-- 1) Tabel kategori baru
CREATE TABLE IF NOT EXISTS kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2) Pindahkan nilai teks bebas buku.kategori -> tabel kategori
INSERT IGNORE INTO kategori (nama_kategori)
SELECT DISTINCT kategori FROM buku WHERE kategori IS NOT NULL AND kategori <> '';

-- 3) Tambah kolom kategori_id di buku, isi dari mapping nama, lalu hapus kolom lama
ALTER TABLE buku ADD COLUMN kategori_id INT DEFAULT NULL AFTER penulis;
UPDATE buku b JOIN kategori k ON k.nama_kategori = b.kategori SET b.kategori_id = k.id;
ALTER TABLE buku ADD CONSTRAINT fk_buku_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL;
ALTER TABLE buku DROP COLUMN kategori;

-- 4) Tabel log_aktivitas baru
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','petugas','siswa') NOT NULL,
    user_id INT NOT NULL,
    nama_user VARCHAR(100) NOT NULL,
    aksi VARCHAR(100) NOT NULL,
    keterangan VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4b) BARU · Tabel petugas + kolom log_aktivitas.user_type diperluas
--     (jika tabel log_aktivitas sudah ada dari migrasi sebelumnya,
--     ALTER berikut menambahkan 'petugas' ke daftar ENUM yang diizinkan)
CREATE TABLE IF NOT EXISTS petugas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE log_aktivitas MODIFY COLUMN user_type ENUM('admin','petugas','siswa') NOT NULL;
INSERT IGNORE INTO petugas (nama, username, password) VALUES
('Petugas Perpustakaan', 'petugas', 'petugas123');

-- 5) View laporan (drop dulu jika sudah ada agar bisa dibuat ulang)
DROP VIEW IF EXISTS view_laporan_transaksi;
CREATE VIEW view_laporan_transaksi AS
SELECT
    t.id, a.nama AS nama_anggota, a.kelas AS kelas_anggota,
    b.judul AS judul_buku, k.nama_kategori AS kategori_buku,
    t.tanggal_pinjam, t.tanggal_kembali, t.status
FROM transaksi t
JOIN anggota a ON a.id = t.anggota_id
JOIN buku b ON b.id = t.buku_id
LEFT JOIN kategori k ON k.id = b.kategori_id;

DROP VIEW IF EXISTS view_rekap_kategori;
CREATE VIEW view_rekap_kategori AS
SELECT COALESCE(k.nama_kategori,'Tanpa Kategori') AS nama_kategori,
       COUNT(b.id) AS jumlah_judul, SUM(b.stok) AS total_stok
FROM buku b LEFT JOIN kategori k ON k.id = b.kategori_id
GROUP BY k.id;

-- =========================================================
-- MIGRASI v2: Sistem persetujuan peminjaman, durasi pinjam, & denda
-- Jalankan ini SETELAH migrasi v1 di atas.
-- =========================================================

-- 7) Hapus trigger lama jika ada (nama berbeda dari versi sebelumnya)
DROP TRIGGER IF EXISTS trg_kurangi_stok_pinjam;
DROP TRIGGER IF EXISTS trg_tambah_stok_kembali;

-- 8) Ubah ENUM status transaksi: tambah 'Menunggu Persetujuan' & 'Ditolak'
ALTER TABLE transaksi MODIFY COLUMN status ENUM('Menunggu Persetujuan','Dipinjam','Ditolak','Dikembalikan') NOT NULL DEFAULT 'Menunggu Persetujuan';

-- 9) Tambah kolom durasi_pinjam, batas_kembali, dan denda
ALTER TABLE transaksi ADD COLUMN durasi_pinjam INT NOT NULL DEFAULT 7 COMMENT 'Durasi peminjaman dalam hari' AFTER tanggal_pinjam;
ALTER TABLE transaksi ADD COLUMN batas_kembali DATE DEFAULT NULL AFTER durasi_pinjam;
ALTER TABLE transaksi ADD COLUMN denda INT NOT NULL DEFAULT 0 COMMENT 'Total denda keterlambatan (Rp)' AFTER batas_kembali;

-- 10) Update data lama yang statusnya 'Dipinjam': set durasi & batas_kembali
UPDATE transaksi SET durasi_pinjam = 7, batas_kembali = DATE_ADD(tanggal_pinjam, INTERVAL 7 DAY) WHERE status IN ('Dipinjam','Dikembalikan') AND durasi_pinjam = 7 AND batas_kembali IS NULL;

-- 11) Buat trigger baru: kelola stok saat status berubah
DELIMITER $$
CREATE TRIGGER trg_kelola_stok_transaksi
AFTER UPDATE ON transaksi
FOR EACH ROW
BEGIN
    IF OLD.status = 'Menunggu Persetujuan' AND NEW.status = 'Dipinjam' THEN
        UPDATE buku SET stok = stok - 1 WHERE id = NEW.buku_id;
    END IF;
    IF OLD.status = 'Dipinjam' AND NEW.status = 'Dikembalikan' THEN
        UPDATE buku SET stok = stok + 1 WHERE id = NEW.buku_id;
    END IF;
END$$
DELIMITER ;

-- 12) Perbarui view laporan dengan kolom baru
DROP VIEW IF EXISTS view_laporan_transaksi;
CREATE VIEW view_laporan_transaksi AS
SELECT
    t.id, a.nama AS nama_anggota, a.kelas AS kelas_anggota,
    b.judul AS judul_buku, k.nama_kategori AS kategori_buku,
    t.tanggal_pinjam, t.batas_kembali, t.tanggal_kembali,
    t.durasi_pinjam, t.denda, t.status
FROM transaksi t
JOIN anggota a ON a.id = t.anggota_id
JOIN buku b ON b.id = t.buku_id
LEFT JOIN kategori k ON k.id = b.kategori_id;

-- 13) Kembalikan stok buku untuk transaksi lama yang sudah 'Dipinjam'
--     (karena trigger lama sudah mengurangi stok saat INSERT,
--      dan trigger baru hanya mengurangi saat status berubah ke Dipinjam)
--     Langkah ini memastikan stok konsisten setelah migrasi.
--     Jika Anda mengimpor database.sql dari awal, bagian ini aman dijalankan
--     karena tidak ada data yang cocok dengan kondisi.
-- Catatan: Perbaikan stok harus dilakukan manual jika ada ketidaksesuaian.

-- 6) Password: TIDAK perlu diubah manual. config.php (fungsi verifikasi_login)
--    otomatis meng-hash password lama dalam bentuk teks biasa begitu akun
--    tersebut login pertama kali setelah migrasi ini.

-- =========================================================
-- MIGRASI v3: Tabel notifikasi_denda (audit trail denda terlambat)
-- Jalankan ini SETELAH migrasi v1 & v2 di atas.
-- =========================================================

-- 14) Tabel notifikasi_denda: mencatat kapan notifikasi denda dibuat/dibaca
CREATE TABLE IF NOT EXISTS notifikasi_denda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaksi_id INT NOT NULL,
    anggota_id INT NOT NULL,
    hari_terlambat INT NOT NULL,
    denda_terhitung INT NOT NULL COMMENT 'Estimasi denda saat notifikasi dibuat (Rp)',
    dibaca TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_transaksi FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_anggota FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE
);

-- 15) View: daftar peminjaman terlambat beserta detail denda (dipakai notifikasi)
DROP VIEW IF EXISTS view_peminjaman_terlambat;
CREATE VIEW view_peminjaman_terlambat AS
SELECT
    t.id AS transaksi_id,
    t.anggota_id,
    a.nama AS nama_anggota,
    a.kelas AS kelas_anggota,
    b.judul AS judul_buku,
    b.penulis,
    t.tanggal_pinjam,
    t.batas_kembali,
    t.durasi_pinjam,
    DATEDIFF(CURDATE(), t.batas_kembali) AS hari_terlambat,
    DATEDIFF(CURDATE(), t.batas_kembali) * 1000 AS denda_terhitung
FROM transaksi t
JOIN anggota a ON a.id = t.anggota_id
JOIN buku b ON b.id = t.buku_id
WHERE t.status = 'Dipinjam' AND t.batas_kembali < CURDATE();

-- =========================================================
-- MIGRASI v4: Kondisi buku & Tabel testimoni (komentar + rating)
-- Jalankan ini SETELAH migrasi v1, v2 & v3 di atas.
-- =========================================================

-- 16) Tambah kolom kondisi ke tabel buku
ALTER TABLE buku ADD COLUMN kondisi ENUM('Baik','Cukup','Buruk','Rusak') NOT NULL DEFAULT 'Baik' COMMENT 'Kondisi fisik buku' AFTER stok;

-- 17) Tabel testimoni untuk komentar & rating pengguna
CREATE TABLE IF NOT EXISTS testimoni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    peran VARCHAR(80) DEFAULT NULL COMMENT 'Peran/jabatan pengguna',
    rating TINYINT NOT NULL DEFAULT 5 COMMENT 'Rating 1-5 bintang',
    komentar TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 18) Data testimoni awal (hanya insert jika belum ada)
INSERT IGNORE INTO testimoni (nama, peran, rating, komentar) VALUES
('Sri Rahayu', 'Admin Perpustakaan', 5, 'Sejak menggunakan Pustaka.Lokal, pencatatan peminjaman buku jadi jauh lebih rapi dan cepat. Fitur log aktivitas sangat membantu saya melacak siapa saja yang meminjam buku.'),
('Budi Prasetyo', 'Kepala Sekolah', 5, 'Laporan grafik di dashboard memberikan gambaran yang jelas tentang kondisi perpustakaan kami. Sangat berguna untuk evaluasi dan pengambilan keputusan.'),
('Anisa Nurwati', 'Petugas Perpustakaan', 4, 'Tampilannya intuitif dan mudah dipelajari. Saya baru dua minggu bertugas tapi sudah bisa mengoperasikan semua fitur tanpa bantuan teknis.'),
('Dina Kusuma', 'Siswa Kelas XII', 5, 'Dulu harus ngantri lama buat pinjam buku, sekarang tinggal login dan pilih buku yang tersedia. Praktis banget dan gak perlu repot lagi!'),
('Rizky Hidayat', 'Siswa Kelas X', 4, 'Aplikasinya ringan dan bisa diakses dari HP. Saya sering cek ketersediaan buku sebelum ke perpustakaan. Cocok buat siswa yang suka baca!'),
('Mbak Sari', 'Guru Bahasa Indonesia', 5, 'Sebagai guru yang sering merekomendasikan bacaan tambahan, saya bisa dengan mudah mengecek apakah buku yang saya sarankan tersedia di perpustakaan.');

-- =========================================================
-- MIGRASI v5: Tabel ulasan_buku & view_rating_buku (dipakai
-- halaman detail buku / buku_detail.php). Jalankan ini
-- SETELAH migrasi v1, v2, v3 & v4 di atas.
-- =========================================================

-- 19) Tabel ulasan_buku untuk ulasan & rating per buku
CREATE TABLE IF NOT EXISTS ulasan_buku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buku_id INT NOT NULL,
    anggota_id INT DEFAULT NULL COMMENT 'Diisi jika pengulas login sebagai siswa',
    nama VARCHAR(100) NOT NULL,
    rating TINYINT NOT NULL COMMENT 'Rating 1-5 bintang',
    komentar TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ulasan_buku FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE,
    CONSTRAINT fk_ulasan_anggota FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE SET NULL
);

-- 20) View: rata-rata rating & jumlah ulasan per buku
DROP VIEW IF EXISTS view_rating_buku;
CREATE VIEW view_rating_buku AS
SELECT buku_id, COUNT(*) AS jumlah_ulasan, ROUND(AVG(rating), 1) AS rata_rating
FROM ulasan_buku
GROUP BY buku_id;
