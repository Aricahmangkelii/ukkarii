<?php
/**
 * help.php
 * Halaman bantuan / panduan penggunaan aplikasi Pustaka.Lokal.
 * Bisa diakses tanpa login (publik) maupun dari dashboard.
 */
require 'config.php';
$pageTitle = 'Bantuan';
require 'includes/head.php';
?>
<section class="help-section">
  <div class="help-container">
    <a class="back-link" href="<?= isset($_SESSION['role']) ? (($_SESSION['role'] === 'siswa') ? 'user_dashboard.php' : 'admin_dashboard.php') : 'index.php' ?>">← Kembali
    </a>
    <h1 class="serif">Pusat Bantuan</h1>
    <p class="lede">Panduan lengkap penggunaan aplikasi <strong>Pustaka.Lokal</strong> — Perpustakaan Sekolah Digital.</p>

    <!-- FAQ Accordion -->
    <div class="faq-group">
      <h2 class="serif" style="margin-bottom:16px"><i class="fa-solid fa-circle-question" style="color:var(--brass)"></i> Pertanyaan Umum (FAQ)</h2>

      <details class="faq-item">
        <summary>Bagaimana cara login sebagai Siswa?</summary>
        <div class="faq-body">
          <ol>
            <li>Buka halaman utama (<code>index.php</code>), lalu klik kartu <strong>“Login sebagai Siswa”</strong>.</li>
            <li>Jika Anda sudah terdaftar sebagai anggota, klik <strong>“Ya, sudah anggota”</strong> lalu masukkan username dan password.</li>
            <li>Jika belum terdaftar, klik <strong>“Belum, daftar”</strong>, isi formulir pendaftaran (nama, kelas, username, password), lalu login dengan akun baru Anda.</li>
          </ol>
        </div>
      </details>

      <details class="faq-item">
        <summary>Bagaimana cara meminjam buku?</summary>
        <div class="faq-body">
          <ol>
            <li>Login sebagai Siswa, Anda akan diarahkan ke <strong>Dashboard Siswa</strong>.</li>
            <li>Di tab <strong>“Peminjaman Buku”</strong>, cari buku yang tersedia menggunakan kolom pencarian.</li>
            <li>Klik tombol <strong>“Pinjam”</strong> pada baris buku yang diinginkan. Stok buku akan otomatis berkurang.</li>
            <li>Jika stok buku <span class="badge badge-stok-low">Habis</span>, tombol Pinjam akan dinonaktifkan.</li>
          </ol>
        </div>
      </details>

      <details class="faq-item">
        <summary>Bagaimana cara mengembalikan buku?</summary>
        <div class="faq-body">
          <ol>
            <li>Di Dashboard Siswa, pindah ke tab <strong>“Pengembalian Buku”</strong>.</li>
            <li>Cari buku yang sedang Anda pinjam (status <span class="badge badge-out">Dipinjam</span>).</li>
            <li>Klik tombol <strong>“Kembalikan”</strong>. Status akan berubah menjadi <span class="badge badge-in">Dikembalikan</span> dan stok buku otomatis bertambah.</li>
          </ol>
        </div>
      </details>

      <details class="faq-item">
        <summary>Bagaimana cara menambah buku baru (Admin)?</summary>
        <div class="faq-body">
          <ol>
            <li>Login sebagai Admin, buka <strong>Dashboard Admin</strong>.</li>
            <li>Pilih tab <strong>“Kelola Data Buku”</strong>, lalu klik tombol <strong>“Tambah Buku”</strong>.</li>
            <li>Isi formulir: judul, penulis, pilih kategori (opsional), dan jumlah stok.</li>
            <li>Klik <strong>“Simpan”</strong>. Buku baru akan muncul di daftar.</li>
          </ol>
        </div>
      </details>

      <details class="faq-item">
        <summary>Bagaimana cara mencetak laporan?</summary>
        <div class="faq-body">
          <ol>
            <li>Login sebagai Admin/Petugas, di sidebar klik <strong>“Cetak Laporan”</strong>.</li>
            <li>Pilih jenis laporan: <strong>Laporan Transaksi</strong> atau <strong>Laporan Data Buku</strong>.</li>
            <li>Klik tombol <strong>“Cetak / Simpan sebagai PDF”</strong> atau gunakan Ctrl+P di browser.</li>
          </ol>
        </div>
      </details>

      <details class="faq-item">
        <summary>Apa perbedaan Admin dan Petugas?</summary>
        <div class="faq-body">
          <p><strong>Admin</strong> memiliki akses penuh: CRUD buku, kategori, anggota, transaksi, log aktivitas, kelola akun admin/petugas, dan cetak laporan.</p>
          <p><strong>Petugas</strong> memiliki akses yang sama dengan admin untuk mengelola buku, kategori, anggota, transaksi, dan log, tetapi <em>tidak</em> bisa mengelola akun admin/petugas. Petugas adalah staf perpustakaan harian.</p>
        </div>
      </details>

      <details class="faq-item">
        <summary>Apakah password saya aman?</summary>
        <div class="faq-body">
          <p>Ya. Semua password disimpan dalam bentuk <strong>hash</strong> menggunakan fungsi <code>password_hash()</code> PHP (bcrypt). Password asli tidak disimpan di database. Saat login, sistem menggunakan <code>password_verify()</code> untuk memverifikasi. Data contoh di <code>database.sql</code> menggunakan teks biasa, tetapi akan otomatis di-hash saat pertama kali login.</p>
        </div>
      </details>
    </div>

    <!-- Panduan Singkat Per Role -->
    <div class="help-roles">
      <h2 class="serif" style="margin-bottom:16px"><i class="fa-solid fa-users-gear" style="color:var(--brass)"></i> Panduan Peran</h2>
      <div class="help-grid">
        <div class="card help-card">
          <h3><i class="fa-solid fa-user-shield"></i> Admin</h3>
          <ul>
            <li>Kelola data buku (CRUD)</li>
            <li>Kelola kategori buku (CRUD)</li>
            <li>Kelola data anggota (CRUD)</li>
            <li>Kelola akun admin &amp; petugas (CRUD)</li>
            <li>Pantau transaksi peminjaman</li>
            <li>Tandai pengembalian buku</li>
            <li>Lihat log aktivitas (audit trail)</li>
            <li>Lihat grafik koleksi per kategori</li>
            <li>Cetak laporan (buku &amp; transaksi)</li>
          </ul>
        </div>
        <div class="card help-card">
          <h3><i class="fa-solid fa-user-tie"></i> Petugas</h3>
          <ul>
            <li>Kelola data buku (CRUD)</li>
            <li>Kelola kategori buku (CRUD)</li>
            <li>Kelola data anggota (CRUD)</li>
            <li>Pantau transaksi peminjaman</li>
            <li>Tandai pengembalian buku</li>
            <li>Lihat log aktivitas (audit trail)</li>
            <li>Lihat grafik koleksi per kategori</li>
            <li>Cetak laporan (buku &amp; transaksi)</li>
          </ul>
        </div>
        <div class="card help-card">
          <h3><i class="fa-solid fa-user-graduate"></i> Siswa</h3>
          <ul>
            <li>Daftar sebagai anggota baru</li>
            <li>Login dengan akun anggota</li>
            <li>Cari &amp; pinjam buku</li>
            <li>Kembalikan buku yang dipinjam</li>
            <li>Lihat riwayat peminjaman</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Info Teknis -->
    <div class="card help-tech">
      <h2 class="serif" style="margin-top:0"><i class="fa-solid fa-gear" style="color:var(--brass)"></i> Informasi Teknis</h2>
      <table class="tech-table">
        <tr><td class="label">Versi</td><td>1.0 (Revisi UKK 2025/2026)</td></tr>
        <tr><td class="label">Teknologi</td><td>PHP 8+ (PDO), MySQL/MariaDB</td></tr>
        <tr><td class="label">Keamanan</td><td>Prepared Statement, password_hash, XSS protection, role-based access</td></tr>
        <tr><td class="label">Objek DB</td><td>2 Trigger, 2 Stored Procedure, 1 Function, 2 View</td></tr>
        <tr><td class="label">Library CDN</td><td>Font Awesome 6, SweetAlert2, Chart.js 4</td></tr>
        <tr><td class="label">Font</td><td>Source Serif 4, Inter, JetBrains Mono</td></tr>
      </table>
    </div>
  </div>
</section>
<style>
.help-section{min-height:100vh;padding:32px 16px;}
.help-container{max-width:800px;margin:0 auto;}
.help-container h1{font-size:32px;margin:0 0 8px;}
.help-container .lede{color:var(--ink-soft);font-size:14.5px;margin:0 0 32px;max-width:600px;}
.faq-group{margin-bottom:36px;}
.faq-item{background:var(--paper);border:1px solid var(--line);border-radius:10px;margin-bottom:10px;overflow:hidden;}
.faq-item summary{padding:14px 18px;font-weight:600;font-size:14.5px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;}
.faq-item summary::-webkit-details-marker{display:none;}
.faq-item summary::before{content:'\f054';font-family:'Font Awesome 6 Free';font-weight:900;font-size:10px;color:var(--brass);transition:transform .2s;}
.faq-item[open] summary::before{transform:rotate(90deg);}
.faq-body{padding:0 18px 16px;font-size:13.5px;line-height:1.7;color:var(--ink-soft);}
.faq-body ol{padding-left:20px;}
.faq-body ol li{margin-bottom:6px;}
.faq-body code{background:var(--bg);padding:2px 6px;border-radius:4px;font-family:'JetBrains Mono',monospace;font-size:12px;}
.help-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
@media(max-width:700px){.help-grid{grid-template-columns:1fr;}}
.help-card{padding:20px;}
.help-card h3{margin:0 0 12px;font-size:16px;display:flex;align-items:center;gap:8px;}
.help-card h3 i{color:var(--forest);}
.help-card ul{padding-left:18px;margin:0;}
.help-card ul li{font-size:13px;color:var(--ink-soft);margin-bottom:5px;}
.help-tech{padding:20px 24px;margin-bottom:24px;}
.tech-table{width:100%;border-collapse:collapse;}
.tech-table td{padding:8px 0;font-size:13px;border-bottom:1px solid var(--line);}
.tech-table .label{font-weight:600;width:160px;color:var(--ink);}
</style>
<?php require 'includes/foot.php'; ?>
