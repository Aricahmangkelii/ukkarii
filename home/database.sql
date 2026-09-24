-- =========================================================
--  DATABASE: perpustakaan_sekolah_digital
--  Aplikasi Peminjaman Buku - Perpustakaan Sekolah Digital
--  Revisi UKK: menambahkan kategori (tabel relasional),
--  log_aktivitas, view laporan, tanpa mengubah struktur
--  yang sudah berjalan (transaksi, admin, anggota, buku).
-- =========================================================

CREATE DATABASE IF NOT EXISTS perpustakaan_sekolah_digital;
USE perpustakaan_sekolah_digital;

-- ---------------------------------------------------------
-- Tabel admin (staf perpustakaan / "users" berperan admin)
-- ---------------------------------------------------------
CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- BARU · Tabel petugas (staf perpustakaan harian — akses sama
-- persis dengan admin, hanya beda label & tabel akun login)
-- ---------------------------------------------------------
CREATE TABLE petugas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Tabel anggota (siswa / warga sekolah)
-- ---------------------------------------------------------
CREATE TABLE anggota (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    kelas VARCHAR(30) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- BARU · Tabel kategori (dulu kolom teks bebas di buku.kategori,
-- sekarang tabel master relasional agar konsisten & mudah dilaporkan)
-- ---------------------------------------------------------
CREATE TABLE kategori (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- Tabel buku (koleksi perpustakaan) — kategori kini FK
-- ---------------------------------------------------------
CREATE TABLE buku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(150) NOT NULL,
    penulis VARCHAR(100) NOT NULL,
    kategori_id INT DEFAULT NULL,
    stok INT NOT NULL DEFAULT 0,
    kondisi ENUM('Baik','Cukup','Buruk','Rusak') NOT NULL DEFAULT 'Baik' COMMENT 'Kondisi fisik buku',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_buku_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- Tabel transaksi (peminjaman & pengembalian buku dalam satu
-- baris — status membedakan "Dipinjam" / "Dikembalikan". Ini
-- setara dengan pasangan tabel peminjaman/pengembalian pada
-- checklist UKK, namun dinormalisasi jadi satu tabel + trigger
-- agar tidak ada duplikasi data pengembalian yang harus disinkronkan.)
-- ---------------------------------------------------------
CREATE TABLE transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    anggota_id INT NOT NULL,
    buku_id INT NOT NULL,
    tanggal_pinjam DATE NOT NULL,
    batas_kembali DATE DEFAULT NULL,
    tanggal_kembali DATE DEFAULT NULL,
    durasi_pinjam INT NOT NULL DEFAULT 7 COMMENT 'Durasi peminjaman dalam hari',
    denda INT NOT NULL DEFAULT 0 COMMENT 'Total denda keterlambatan (Rp)',
    status ENUM('Menunggu Persetujuan','Dipinjam','Ditolak','Dikembalikan') NOT NULL DEFAULT 'Menunggu Persetujuan',
    CONSTRAINT fk_transaksi_anggota FOREIGN KEY (anggota_id) REFERENCES anggota(id) ON DELETE CASCADE,
    CONSTRAINT fk_transaksi_buku FOREIGN KEY (buku_id) REFERENCES buku(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- BARU · Tabel log_aktivitas (audit trail: siapa melakukan
-- apa dan kapan — dipakai admin & siswa)
-- ---------------------------------------------------------
CREATE TABLE log_aktivitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('admin','petugas','siswa') NOT NULL,
    user_id INT NOT NULL,
    nama_user VARCHAR(100) NOT NULL,
    aksi VARCHAR(100) NOT NULL,
    keterangan VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================================
-- Trigger: kelola stok berdasarkan perubahan status transaksi.
--   Menunggu Persetujuan -> Dipinjam   : kurangi stok (disetujui admin)
--   Dipinjam             -> Dikembalikan : tambah stok (buku dikembalikan)
--   Menunggu Persetujuan -> Ditolak      : tidak ada perubahan stok
-- =========================================================
DELIMITER $$
CREATE TRIGGER trg_kelola_stok_transaksi
AFTER UPDATE ON transaksi
FOR EACH ROW
BEGIN
    -- Saat admin menyetujui peminjaman: kurangi stok
    IF OLD.status = 'Menunggu Persetujuan' AND NEW.status = 'Dipinjam' THEN
        UPDATE buku SET stok = stok - 1 WHERE id = NEW.buku_id;
    END IF;
    -- Saat buku dikembalikan: kembalikan stok
    IF OLD.status = 'Dipinjam' AND NEW.status = 'Dikembalikan' THEN
        UPDATE buku SET stok = stok + 1 WHERE id = NEW.buku_id;
    END IF;
END$$
DELIMITER ;

-- =========================================================
-- Stored Procedure: ajukan peminjaman buku (status Menunggu Persetujuan)
-- =========================================================
DELIMITER $$
CREATE PROCEDURE sp_ajukan_pinjam(IN p_anggota_id INT, IN p_buku_id INT, IN p_durasi INT)
BEGIN
    DECLARE v_stok INT;
    SELECT stok INTO v_stok FROM buku WHERE id = p_buku_id;

    IF v_stok > 0 THEN
        START TRANSACTION;
        INSERT INTO transaksi (anggota_id, buku_id, tanggal_pinjam, durasi_pinjam, batas_kembali, status)
        VALUES (p_anggota_id, p_buku_id, CURDATE(), p_durasi, DATE_ADD(CURDATE(), INTERVAL p_durasi DAY), 'Menunggu Persetujuan');
        COMMIT;
    ELSE
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stok buku habis';
    END IF;
END$$
DELIMITER ;

-- =========================================================
-- Stored Procedure: kembalikan buku (dengan perhitungan denda)
-- =========================================================
DELIMITER $$
CREATE PROCEDURE sp_kembalikan_buku(IN p_transaksi_id INT)
BEGIN
    DECLARE v_batas DATE;
    DECLARE v_hari_terlambat INT;
    DECLARE v_denda INT;

    SELECT batas_kembali INTO v_batas FROM transaksi WHERE id = p_transaksi_id;

    SET v_denda = 0;
    IF v_batas IS NOT NULL AND CURDATE() > v_batas THEN
        SET v_hari_terlambat = DATEDIFF(CURDATE(), v_batas);
        SET v_denda = v_hari_terlambat * 1000;
    END IF;

    START TRANSACTION;
    UPDATE transaksi
       SET status = 'Dikembalikan', tanggal_kembali = CURDATE(), denda = v_denda
     WHERE id = p_transaksi_id AND status = 'Dipinjam';
    COMMIT;
END$$
DELIMITER ;

-- =========================================================
-- Function: hitung jumlah buku yang sedang dipinjam anggota
-- =========================================================
DELIMITER $$
CREATE FUNCTION fn_jumlah_pinjam(p_anggota_id INT) RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    SELECT COUNT(*) INTO total FROM transaksi
     WHERE anggota_id = p_anggota_id AND status IN ('Dipinjam','Menunggu Persetujuan');
    RETURN total;
END$$
DELIMITER ;

-- =========================================================
-- BARU · View: laporan transaksi lengkap (dipakai halaman
-- cetak/laporan admin — JOIN 4 tabel sekaligus)
-- =========================================================
CREATE VIEW view_laporan_transaksi AS
SELECT
    t.id,
    a.nama            AS nama_anggota,
    a.kelas           AS kelas_anggota,
    b.judul           AS judul_buku,
    k.nama_kategori   AS kategori_buku,
    t.tanggal_pinjam,
    t.batas_kembali,
    t.tanggal_kembali,
    t.durasi_pinjam,
    t.denda,
    t.status
FROM transaksi t
JOIN anggota a ON a.id = t.anggota_id
JOIN buku b ON b.id = t.buku_id
LEFT JOIN kategori k ON k.id = b.kategori_id;

-- =========================================================
-- BARU · View: rekap buku per kategori (dipakai grafik dashboard)
-- =========================================================
CREATE VIEW view_rekap_kategori AS
SELECT
    COALESCE(k.nama_kategori, 'Tanpa Kategori') AS nama_kategori,
    COUNT(b.id)   AS jumlah_judul,
    SUM(b.stok)   AS total_stok
FROM buku b
LEFT JOIN kategori k ON k.id = b.kategori_id
GROUP BY k.id;

-- =========================================================
-- BARU · View: daftar peminjaman terlambat beserta detail denda
-- =========================================================
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
-- Data awal (sample data untuk pengujian / demo)
-- Password disimpan dalam bentuk teks biasa di sini agar mudah
-- dibaca sebagai data contoh; config.php + halaman login akan
-- MENG-HASH otomatis (password_hash) begitu akun ini pertama kali
-- dipakai login (lihat fungsi verifikasi_login() di config.php).
-- =========================================================
INSERT INTO admin (nama, username, password) VALUES
('Admin Perpustakaan', 'admin', 'admin123');

INSERT INTO petugas (nama, username, password) VALUES
('Petugas Perpustakaan', 'petugas', 'petugas123');

INSERT INTO anggota (nama, kelas, username, password) VALUES
('Budi Santoso', 'XII RPL 1', 'budi', 'budi123'),
('Siti Aminah', 'XII RPL 2', 'siti', 'siti123');

INSERT INTO kategori (nama_kategori) VALUES
('Informatika'), ('Fiksi'), ('Sejarah');

INSERT INTO buku (judul, penulis, kategori_id, stok, kondisi) VALUES
('Algoritma & Pemrograman Dasar', 'Rinaldi Munir', 1, 4, 'Baik'),
('Basis Data Relasional', 'Abdul Kadir', 1, 2, 'Cukup'),
('Laskar Pelangi', 'Andrea Hirata', 2, 1, 'Baik'),
('Sejarah Nusantara', 'Sartono Kartodirdjo', 3, 3, 'Buruk');

INSERT INTO transaksi (anggota_id, buku_id, tanggal_pinjam, durasi_pinjam, batas_kembali, status) VALUES
(1, 3, CURDATE(), 7, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Dipinjam');

-- =========================================================
-- BARU · Tabel notifikasi_denda (audit trail notifikasi denda)
-- =========================================================
CREATE TABLE notifikasi_denda (
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


-- =========================================================
-- BARU · Tabel testimoni (komentar & rating pengguna)
-- =========================================================
CREATE TABLE testimoni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    peran VARCHAR(80) DEFAULT NULL COMMENT 'Peran/jabatan pengguna',
    rating TINYINT NOT NULL DEFAULT 5 COMMENT 'Rating 1-5 bintang',
    komentar TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO testimoni (nama, peran, rating, komentar) VALUES
('Sri Rahayu', 'Admin Perpustakaan', 5, 'Sejak menggunakan Pustaka.Lokal, pencatatan peminjaman buku jadi jauh lebih rapi dan cepat. Fitur log aktivitas sangat membantu saya melacak siapa saja yang meminjam buku.'),
('Budi Prasetyo', 'Kepala Sekolah', 5, 'Laporan grafik di dashboard memberikan gambaran yang jelas tentang kondisi perpustakaan kami. Sangat berguna untuk evaluasi dan pengambilan keputusan.'),
('Anisa Nurwati', 'Petugas Perpustakaan', 4, 'Tampilannya intuitif dan mudah dipelajari. Saya baru dua minggu bertugas tapi sudah bisa mengoperasikan semua fitur tanpa bantuan teknis.'),
('Dina Kusuma', 'Siswa Kelas XII', 5, 'Dulu harus ngantri lama buat pinjam buku, sekarang tinggal login dan pilih buku yang tersedia. Praktis banget dan gak perlu repot lagi!'),
('Rizky Hidayat', 'Siswa Kelas X', 4, 'Aplikasinya ringan dan bisa diakses dari HP. Saya sering cek ketersediaan buku sebelum ke perpustakaan. Cocok buat siswa yang suka baca!'),
('Mbak Sari', 'Guru Bahasa Indonesia', 5, 'Sebagai guru yang sering merekomendasikan bacaan tambahan, saya bisa dengan mudah mengecek apakah buku yang saya sarankan tersedia di perpustakaan.');

-- =========================================================
-- BARU · Tabel ulasan_buku (ulasan & rating per buku, dipakai
-- di halaman detail buku / buku_detail.php)
-- =========================================================
CREATE TABLE ulasan_buku (
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

-- View: rata-rata rating & jumlah ulasan per buku (dipakai buku_detail.php)
CREATE VIEW view_rating_buku AS
SELECT buku_id, COUNT(*) AS jumlah_ulasan, ROUND(AVG(rating), 1) AS rata_rating
FROM ulasan_buku
GROUP BY buku_id;

-- =========================================================
-- Contoh query yang sering dipakai aplikasi (JOIN, SELECT)
-- =========================================================
-- Daftar transaksi lengkap dengan nama anggota, judul buku & kategori
-- SELECT * FROM view_laporan_transaksi ORDER BY id DESC;
--
-- Rekap jumlah judul & stok per kategori (untuk grafik dashboard)
-- SELECT * FROM view_rekap_kategori;
--
-- Daftar peminjaman yang terlambat beserta estimasi denda
-- SELECT * FROM view_peminjaman_terlambat;
