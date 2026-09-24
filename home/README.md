# Pustaka.Lokal — Perpustakaan Sekolah Digital (versi PHP + MySQL)

Aplikasi web peminjaman buku sekolah, dibuat mengikuti *flowmap* pada soal
**UKK Rekayasa Perangkat Lunak Paket 4 (2025/2026)** dan mengacu pada
**Daftar Target Produk Pra UKK 2026**. Versi ini menggunakan **PHP (PDO)**
sebagai server-side script yang terhubung ke **MySQL/MariaDB**.

> Revisi terbaru menambahkan: CRUD akun admin/petugas, halaman bantuan,
> dokumentasi lengkap (wireframe, use case, activity diagram, flowchart, ERD,
> manual pengguna, struktur folder, dokumentasi modul), kategori relasional,
> hashing password otomatis, log aktivitas (audit trail), role-based access,
> dashboard grafik (Chart.js), notifikasi toast & konfirmasi (SweetAlert2),
> ikon (Font Awesome), serta halaman cetak laporan.

## 1. Struktur File
```
php-projectneww/
├── config.php            -> Koneksi database (PDO) + session + helper (hash/log/role)
├── database.sql          -> Skema database lengkap (tabel, view, trigger, SP, function, data awal)
├── db_migration.sql      -> Skrip migrasi untuk DB lama (opsional, jika tidak import ulang)
├── index.php             -> Halaman gerbang (pilih login admin/petugas/siswa)
├── admin_login.php       -> Form & proses login admin
├── petugas_login.php     -> Form & proses login petugas (akses sama dengan admin)
├── user_check.php        -> "Apakah sudah anggota?"
├── user_register.php     -> Form daftar anggota baru (password di-hash)
├── user_login.php        -> Form & proses login siswa
├── admin_dashboard.php   -> Dashboard admin/petugas: CRUD Buku/Kategori/Anggota, Transaksi, Log
├── admin_manage.php      -> Kelola akun admin & petugas (CRUD, khusus admin saja)
├── admin_laporan.php     -> Halaman cetak laporan (buku & transaksi)
├── user_dashboard.php    -> Dashboard siswa: Peminjaman & Pengembalian Buku
├── help.php              -> Halaman bantuan / FAQ / panduan peran
├── logout.php            -> Hapus session & kembali ke gerbang
├── css/
│   └── style.css         -> Gaya tampilan bersama (tema rak buku)
├── includes/
│   ├── head.php          -> <head> bersama + Font Awesome CDN
│   └── foot.php          -> SweetAlert2 + Chart.js + toast/konfirmasi/grafik
├── assets/
│   └── .gitkeep          -> Folder aset (gambar, icon, video)
├── docs/
│   ├── wireframe.md      -> Dokumentasi wireframe seluruh halaman
│   ├── usecase.md        -> Dokumentasi Use Case Diagram
│   ├── activity_diagram.md -> Dokumentasi Activity Diagram
│   ├── flowchart.md      -> Dokumentasi Flowchart / Userflow
│   ├── erd.md            -> Dokumentasi ERD (Entity Relationship Diagram)
│   ├── manual_pengguna.md  -> Manual pengguna lengkap
│   ├── struktur_folder.md  -> Dokumentasi struktur folder
│   └── dokumentasi_modul.md -> Penjelasan modul & fungsi/method
└── README.md             -> Dokumentasi utama proyek (file ini)
```

## 2. Cara Menjalankan (XAMPP / Laragon / server PHP lokal)
1. Salin folder `php-projectneww` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. Buka **phpMyAdmin**, buat database baru, lalu **impor** file `database.sql`
   (otomatis membuat database `perpustakaan_sekolah_digital` beserta tabel,
   view, trigger, stored procedure, function, dan data awal).
   - Jika database lama sudah pernah dibuat dari versi sebelumnya dan Anda
     tidak ingin drop semua data, jalankan `db_migration.sql` saja.
3. Buka `config.php`, sesuaikan `$user` dan `$pass` bila kredensial MySQL
   Anda berbeda dari default XAMPP (`root` / kosong).
4. Jalankan Apache & MySQL, lalu akses:
   ```
   http://localhost/php-projectneww/
   ```
5. Aplikasi memakai beberapa library via CDN (Font Awesome, SweetAlert2,
   Chart.js), jadi **komputer perlu koneksi internet minimal saat pertama
   kali membuka halaman** meski database berjalan lokal/offline. Jika ujian
   benar-benar tanpa internet, unduh file CDN tersebut lalu ganti tautan di
   `includes/head.php` / `includes/foot.php` menjadi path lokal.

Bisa juga dijalankan dengan PHP built-in server (tanpa Apache):
```
php -S localhost:8000
```

## 3. Akun Demo (dari data awal `database.sql`)
| Peran   | Username | Password    |
|---------|----------|-------------|
| Admin   | admin    | admin123    |
| Petugas | petugas  | petugas123  |
| Siswa   | budi     | budi123     |
| Siswa   | siti     | siti123     |

> Password di `database.sql` disimpan sebagai teks biasa agar mudah dibaca
> sebagai data contoh. **Begitu akun ini login pertama kali**, `config.php`
> (fungsi `verifikasi_login`) otomatis meng-hash ulang password tersebut
> dengan `password_hash()` dan menyimpannya kembali — tidak perlu migrasi
> manual, dan sejak saat itu login memakai `password_verify()` sepenuhnya.

## 4. Alur Aplikasi (sesuai *flowmap* soal)
- **Admin**: `admin_login.php` → validasi ke tabel `admin` (hash + log) →
  `admin_dashboard.php` (tab Buku / Kategori / Transaksi / Anggota / Log,
  masing-masing CRUD langsung ke database lewat query PDO) →
  `admin_manage.php` untuk kelola akun admin/petugas → `admin_laporan.php`
  untuk cetak laporan.
- **Petugas**: `petugas_login.php` → validasi ke tabel `petugas` (hash + log)
  → **`admin_dashboard.php` yang sama** dengan admin (akses & menu identik,
  kecuali menu Kelola Akun yang hanya muncul untuk admin).
  Petugas adalah akun login terpisah untuk staf perpustakaan harian; satu-
  satunya beda dari admin adalah tabel akun sendiri dan label "Petugas" yang
  tampil di sidebar & log aktivitas, supaya audit trail tetap membedakan
  siapa yang login sebagai apa. `wajib_role(['admin','petugas'], ...)` di
  `config.php` yang mengizinkan kedua role masuk ke dashboard yang sama.
- **Siswa**: `user_check.php` → jika belum anggota ke `user_register.php`
  (INSERT ke tabel `anggota`, password di-hash), jika sudah ke
  `user_login.php` → validasi ke tabel `anggota` → `user_dashboard.php`
  (tab Peminjaman Buku / Pengembalian Buku).
- Peminjaman & pengembalian buku menulis/meng-update baris di tabel
  `transaksi`. Pengurangan/penambahan **stok buku otomatis** dilakukan oleh
  trigger `trg_kurangi_stok_pinjam` dan `trg_tambah_stok_kembali` di
  `database.sql` — bukan dihitung manual di PHP.
- Setiap aksi penting (login, CRUD, pinjam, kembalikan) dicatat ke tabel
  `log_aktivitas` lewat fungsi `catat_log()` di `config.php`, dan bisa
  dilihat admin di tab **Log Aktivitas**.
- **Halaman Bantuan** (`help.php`) dapat diakses dari sidebar dashboard maupun
  langsung oleh pengunjung tanpa login.

## 5. Keamanan & Validasi
- **Prepared statement** (PDO) di semua query → proteksi SQL Injection.
- **`htmlspecialchars()`** (fungsi `h()`) di semua output → proteksi XSS.
- **Password hashing** (`password_hash` / `password_verify`), dengan
  migrasi otomatis dari data lama teks biasa (lihat bagian 3).
- **Role-based access**: `wajib_role()` di `config.php` mengunci
  `admin_dashboard.php`/`admin_laporan.php` khusus role `admin` **atau**
  `petugas` (menerima array role), `admin_manage.php` khusus role `admin`,
  dan `user_dashboard.php` khusus role `siswa`, dengan pesan "akses ditolak"
  bila login sebagai role lain.
- **Validasi server-side**: kolom wajib, panjang minimum username/password,
  duplikasi username, cek stok sebelum simpan transaksi (pesan spesifik
  "stok buku sedang habis"), pencegahan hapus akun sendiri.
- **Validasi client-side**: atribut HTML5 `required`/`minlength` pada form.
- **Konfirmasi hapus**: SweetAlert2 menggantikan `confirm()` bawaan browser.

## 6. ERD (ringkas)
```
admin (1)      petugas (1)     anggota (1)──<transaksi>──(1) buku (1)──<kategori
  id              id              id              id              id          id
  nama            nama            nama            anggota_id(FK)  judul       nama_kategori
  username        username        kelas           buku_id(FK)     penulis
  password        password        username        tanggal_pinjam  kategori_id(FK)
                                  password        tanggal_kembali stok
                                                   status

log_aktivitas: id, user_type, user_id, nama_user, aksi, keterangan, created_at
  (tidak berelasi FK — mencatat lintas admin, petugas & anggota)
```

## 7. Objek Database (lihat `database.sql`)
| Objek | Nama | Fungsi |
|---|---|---|
| Trigger | `trg_kurangi_stok_pinjam` | Kurangi stok saat transaksi baru berstatus Dipinjam |
| Trigger | `trg_tambah_stok_kembali` | Tambah stok saat status berubah jadi Dikembalikan |
| Stored Procedure | `sp_pinjam_buku` | Validasi stok lalu catat transaksi (alternatif dari SQL langsung) |
| Stored Procedure | `sp_kembalikan_buku` | Tandai transaksi selesai dalam satu transaction COMMIT |
| Function | `fn_jumlah_pinjam` | Hitung jumlah buku yang sedang dipinjam seorang anggota |
| View | `view_laporan_transaksi` | JOIN transaksi+anggota+buku+kategori, dipakai tab Transaksi & laporan cetak |
| View | `view_rekap_kategori` | Rekap jumlah judul & stok per kategori, dipakai grafik dashboard |

## 8. Debugging (contoh temuan & perbaikan selama pengembangan)
| Jenis Error   | Contoh Kasus                                              | Perbaikan |
|---------------|-------------------------------------------------------------|----------|
| Syntax Error  | Lupa `exit;` setelah `header('Location: ...')`              | Menambahkan `exit;` di setiap redirect agar skrip berhenti |
| Logic Error   | Stok berkurang dua kali (dihitung manual + trigger)          | Menghapus perhitungan manual, mengandalkan trigger database sepenuhnya |
| Runtime Error | Notice "Undefined array key" saat anggota/buku sudah dihapus | Menggunakan `LEFT JOIN` + fallback teks di tampilan |
| DB Related    | Duplikasi username saat pendaftaran/ubah anggota              | Validasi `SELECT` username sebelum `INSERT`/`UPDATE` |
| DB Related    | Query pencarian rentan SQL Injection                         | Semua query memakai *prepared statement* (`PDO::prepare`) |
| Security      | Password tersimpan teks biasa                                | `password_hash()` saat daftar/ubah + migrasi otomatis saat login lama |
| Access Control| Siswa bisa membuka URL dashboard admin langsung               | `wajib_role()` mengecek `$_SESSION['role']` di awal setiap halaman terproteksi |
| Access Control| Petugas bisa mengakses halaman kelola akun admin              | `wajib_role('admin', ...)` pada `admin_manage.php` membatasi khusus admin |

## 9. Status terhadap Checklist UKK (ringkas)
| Area | Status | Catatan |
|---|:---:|---|
| Landing Page (index.php) | ✔ | Halaman gerbang dengan 3 kartu pilihan role |
| Login admin/petugas/siswa | ✔ | 3 halaman login terpisah + hashing + log |
| Register (daftar anggota) | ✔ | Validasi duplikasi username, password di-hash |
| Logout & session | ✔ | session_destroy + redirect |
| Role-based access control | ✔ | `wajib_role()` dengan dukungan multi-role |
| CRUD Buku | ✔ | Tambah/Ubah/Hapus + pencarian + grafik kategori |
| CRUD Kategori | ✔ | Tambah/Ubah/Hapus + jumlah buku per kategori |
| CRUD Anggota | ✔ | Tambah/Ubah/Hapus + pencarian |
| CRUD Admin/Petugas | ✔ | `admin_manage.php` — khusus admin, cegah hapus akun sendiri |
| Peminjaman buku (siswa) | ✔ | Cek stok → INSERT → trigger kurangi stok |
| Pengembalian buku (siswa) | ✔ | UPDATE milik sendiri → trigger tambah stok |
| Tandai pengembalian (admin) | ✔ | UPDATE status + tanggal_kembali |
| Pencarian | ✔ | GET + LIKE + prepared statement |
| Dashboard statistik & grafik | ✔ | 4 kartu stat + grafik Chart.js |
| Log aktivitas (audit trail) | ✔ | Tabel log_aktivitas + tab admin |
| Laporan & cetak (print) | ✔ | `admin_laporan.php` (print-friendly) |
| Halaman bantuan (Help) | ✔ | `help.php` — FAQ accordion + panduan peran + info teknis |
| Toast / notifikasi | ✔ | SweetAlert2 (sukses/error) |
| Konfirmasi hapus | ✔ | SweetAlert2 (pengganti confirm) |
| Ikon (Font Awesome) | ✔ | Ikon di seluruh navigasi, tombol, dan tabel |
| Validasi client & server | ✔ | HTML5 required/minlength + validasi PHP |
| Prepared statement, XSS | ✔ | Konsisten di seluruh file |
| Header, sidebar, footer | ✔ | Sidebar navigasi + brandmark + rail-user |
| Trigger | ✔ | 2 trigger (kurangi & tambah stok) |
| Stored Procedure | ✔ | 2 SP (pinjam & kembalikan) |
| Function | ✔ | 1 function (hitung pinjam) |
| View | ✔ | 2 view (laporan transaksi & rekap kategori) |
| COMMIT / ROLLBACK | ✔ | Di dalam stored procedure |
| Dokumentasi Wireframe | ✔ | `docs/wireframe.md` |
| Dokumentasi Use Case | ✔ | `docs/usecase.md` |
| Dokumentasi Activity Diagram | ✔ | `docs/activity_diagram.md` |
| Dokumentasi Flowchart | ✔ | `docs/flowchart.md` |
| Dokumentasi ERD | ✔ | `docs/erd.md` |
| Manual Pengguna | ✔ | `docs/manual_pengguna.md` |
| Struktur Folder | ✔ | `docs/struktur_folder.md` |
| Dokumentasi Modul | ✔ | `docs/dokumentasi_modul.md` |
| Error Handling (Notifikasi) | ✔ | Flash message + toast, TRUE/FALSE |
| Penentuan tipe data | ✔ | INT, VARCHAR, ENUM, DATE, TIMESTAMP |
| Percabangan | ✔ | if/else di seluruh logika PHP |
| Perulangan | ✔ | foreach pada tampilan tabel |
| Array | ✔ | Data tabel di-fetch sebagai array |
| Function & Procedure (PHP) | ✔ | h(), verifikasi_login(), catat_log(), wajib_role(), ambil_flash() |
| File Access | ✔ | require/require_once untuk include |
| CRUD Database | ✔ | INSERT, UPDATE, DELETE, SELECT, JOIN |
| CREATE DATABASE & TABLE | ✔ | Di `database.sql` |
| ERD (Entity Relationship Diagram) | ✔ | Tabel relasional + FK + diagram |
