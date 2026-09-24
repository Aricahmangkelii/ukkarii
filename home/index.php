<?php
require 'config.php';
// Jika sudah login, arahkan langsung ke dashboard masing-masing
if (in_array($_SESSION['role'] ?? null, ['admin', 'petugas'], true)) { header('Location: admin_dashboard.php'); exit; }
if (($_SESSION['role'] ?? null) === 'siswa') { header('Location: user_dashboard.php'); exit; }

$pageTitle = 'Beranda';

/* ============ DATA KATALOG BUKU (dengan fallback demo) ============ */
$katalogBuku = [];
$katalogError = false;
$filterKatalog = in_array($_GET['filter'] ?? '', ['tersedia','dipinjam','sering']) ? $_GET['filter'] : 'semua';

// Peta sampul buku (cover) berdasarkan ID buku
$coverFiles = [
  1 => 'assets/images/covers/cover-1.png',
  2 => 'assets/images/covers/cover-2.png',
  3 => 'assets/images/covers/cover-3.png',
  4 => 'assets/images/covers/cover-4.png',
  5 => 'assets/images/covers/cover-5.png',
  6 => 'assets/images/covers/cover-6.png',
  7 => 'assets/images/covers/cover-7.png',
  8 => 'assets/images/covers/cover-8.png',
];

try {
    // Query: setiap buku + kategori + jumlah sedang dipinjam + total pernah dipinjam
    $stmt = $pdo->query('
        SELECT 
            b.id,
            b.judul,
            b.penulis,
            b.stok,
            b.kondisi,
            k.nama_kategori,
            COALESCE(pinj_dipinjam.jml, 0) AS sedang_dipinjam,
            COALESCE(pinj_total.jml, 0)   AS total_dipinjam
        FROM buku b
        LEFT JOIN kategori k ON k.id = b.kategori_id
        LEFT JOIN (SELECT buku_id, COUNT(*) AS jml FROM transaksi WHERE status = \'Dipinjam\' GROUP BY buku_id) pinj_dipinjam ON pinj_dipinjam.buku_id = b.id
        LEFT JOIN (SELECT buku_id, COUNT(*) AS jml FROM transaksi GROUP BY buku_id) pinj_total ON pinj_total.buku_id = b.id
        ORDER BY b.judul
    ');
    $katalogBuku = $stmt->fetchAll();
} catch (Exception $e) {
    $katalogError = true;
    // Data demo jika database belum tersedia
    $katalogBuku = [
        ['id'=>1,'judul'=>'Algoritma & Pemrograman Dasar','penulis'=>'Rinaldi Munir','stok'=>4,'kondisi'=>'Baik','nama_kategori'=>'Informatika','sedang_dipinjam'=>0,'total_dipinjam'=>5],
        ['id'=>2,'judul'=>'Basis Data Relasional','penulis'=>'Abdul Kadir','stok'=>2,'kondisi'=>'Cukup','nama_kategori'=>'Informatika','sedang_dipinjam'=>1,'total_dipinjam'=>8],
        ['id'=>3,'judul'=>'Laskar Pelangi','penulis'=>'Andrea Hirata','stok'=>0,'kondisi'=>'Baik','nama_kategori'=>'Fiksi','sedang_dipinjam'=>1,'total_dipinjam'=>12],
        ['id'=>4,'judul'=>'Sejarah Nusantara','penulis'=>'Sartono Kartodirdjo','stok'=>3,'kondisi'=>'Buruk','nama_kategori'=>'Sejarah','sedang_dipinjam'=>0,'total_dipinjam'=>3],
        ['id'=>5,'judul'=>'Fisika Dasar Universitas','penulis'=>'Halliday & Resnick','stok'=>2,'kondisi'=>'Baik','nama_kategori'=>'Sains','sedang_dipinjam'=>0,'total_dipinjam'=>1],
        ['id'=>6,'judul'=>'Bahasa Indonesia yang Benar','penulis'=>'Hasan Alwi','stok'=>1,'kondisi'=>'Cukup','nama_kategori'=>'Bahasa','sedang_dipinjam'=>1,'total_dipinjam'=>7],
        ['id'=>7,'judul'=>'Matematika Diskrit','penulis'=>'Rinaldi Munir','stok'=>3,'kondisi'=>'Rusak','nama_kategori'=>'Matematika','sedang_dipinjam'=>0,'total_dipinjam'=>2],
        ['id'=>8,'judul'=>'Teknik Digital','penulis'=>'Thomas Floyd','stok'=>0,'kondisi'=>'Cukup','nama_kategori'=>'Teknologi','sedang_dipinjam'=>2,'total_dipinjam'=>15],
    ];
}

// Filter berdasarkan status
if ($filterKatalog === 'tersedia') {
    $katalogBuku = array_filter($katalogBuku, function($b){ return $b['stok'] > 0; });
} elseif ($filterKatalog === 'dipinjam') {
    $katalogBuku = array_filter($katalogBuku, function($b){ return (int)$b['sedang_dipinjam'] > 0; });
} elseif ($filterKatalog === 'sering') {
    $katalogBuku = array_filter($katalogBuku, function($b){ return (int)$b['total_dipinjam'] >= 5; });
}


/* ============ DATA TESTIMONI (dengan fallback demo) ============ */
$testimoniList = [];
$testimoniError = false;
try {
    $stmtTesti = $pdo->query('
        SELECT id, nama, peran, rating, komentar, created_at
        FROM testimoni
        ORDER BY created_at DESC
    ');
    $testimoniList = $stmtTesti->fetchAll();
} catch (Exception $e) {
    $testimoniError = true;
    $testimoniList = [
        ['id'=>1,'nama'=>'Sri Rahayu','peran'=>'Admin Perpustakaan','rating'=>5,'komentar'=>'Sejak menggunakan Pustaka.Lokal, pencatatan peminjaman buku jadi jauh lebih rapi dan cepat.','created_at'=>'2025-12-01 10:00:00'],
        ['id'=>2,'nama'=>'Budi Prasetyo','peran'=>'Kepala Sekolah','rating'=>5,'komentar'=>'Laporan grafik di dashboard memberikan gambaran yang jelas tentang kondisi perpustakaan kami.','created_at'=>'2025-11-28 14:30:00'],
        ['id'=>3,'nama'=>'Anisa Nurwati','peran'=>'Petugas Perpustakaan','rating'=>4,'komentar'=>'Tampilannya intuitif dan mudah dipelajari. Saya baru dua minggu bertugas tapi sudah bisa mengoperasikan semua fitur.','created_at'=>'2025-11-25 09:15:00'],
        ['id'=>4,'nama'=>'Dina Kusuma','peran'=>'Siswa Kelas XII','rating'=>5,'komentar'=>'Dulu harus ngantri lama buat pinjam buku, sekarang tinggal login dan pilih buku yang tersedia. Praktis banget!','created_at'=>'2025-11-20 16:45:00'],
        ['id'=>5,'nama'=>'Rizky Hidayat','peran'=>'Siswa Kelas X','rating'=>4,'komentar'=>'Aplikasinya ringan dan bisa diakses dari HP. Saya sering cek ketersediaan buku sebelum ke perpustakaan.','created_at'=>'2025-11-18 08:00:00'],
        ['id'=>6,'nama'=>'Mbak Sari','peran'=>'Guru Bahasa Indonesia','rating'=>5,'komentar'=>'Sebagai guru yang sering merekomendasikan bacaan tambahan, saya bisa dengan mudah mengecek ketersediaan buku.','created_at'=>'2025-11-15 11:20:00'],
    ];
}

// Hitung rating summary
$totalRating = 0;
$countRating = count($testimoniList);
$ratingDist = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
foreach ($testimoniList as $t) {
    $r = (int)$t['rating'];
    if ($r >= 1 && $r <= 5) {
        $totalRating += $r;
        $ratingDist[$r]++;
    }
}
$avgRating = $countRating > 0 ? round($totalRating / $countRating, 1) : 0;

// Handle POST submit testimoni
$testiSubmitted = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kirim_testimoni') {
    $testiNama = trim($_POST['testi_nama'] ?? '');
    $testiPeran = trim($_POST['testi_peran'] ?? '');
    $testiRating = (int)($_POST['testi_rating'] ?? 5);
    $testiKomentar = trim($_POST['testi_komentar'] ?? '');

    if ($testiNama !== '' && $testiKomentar !== '' && $testiRating >= 1 && $testiRating <= 5) {
        try {
            $stmtIns = $pdo->prepare('INSERT INTO testimoni (nama, peran, rating, komentar) VALUES (?, ?, ?, ?)');
            $stmtIns->execute([$testiNama, $testiPeran ?: null, $testiRating, $testiKomentar]);
            $testiSubmitted = true;
            // Refresh data
            $stmtTesti = $pdo->query('SELECT id, nama, peran, rating, komentar, created_at FROM testimoni ORDER BY created_at DESC');
            $testimoniList = $stmtTesti->fetchAll();
            $totalRating = 0;
            $countRating = count($testimoniList);
            $ratingDist = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
            foreach ($testimoniList as $t) {
                $r = (int)$t['rating'];
                if ($r >= 1 && $r <= 5) { $totalRating += $r; $ratingDist[$r]++; }
            }
            $avgRating = $countRating > 0 ? round($totalRating / $countRating, 1) : 0;
        } catch (Exception $e) {
            $testiSubmitted = false;
        }
    }
}

require 'includes/head.php';
require 'includes/header.php';
?>

<!-- ==================== HERO SECTION ==================== -->
<section class="lp-hero">
  <div class="lp-hero-overlay"></div>
  <img src="assets/images/hero-library.png" alt="Perpustakaan Sekolah" class="lp-hero-bg">
  <div class="lp-hero-content">
    <span class="eyebrow">Perpustakaan Sekolah Digital</span>
    <h1 class="serif lp-hero-title">Rak Digital untuk<br>Peminjaman Buku Sekolah</h1>
    <p class="lp-hero-desc">Kelola peminjaman dan pengembalian buku dengan mudah, cepat, dan terorganisir. Pustaka.Lokal membantu perpustakaan sekolah bertransformasi ke era digital.</p>
    <div class="lp-hero-buttons">
      <a href="user_check.php" class="btn btn-primary">Mulai Pinjam Buku <i class="fas fa-arrow-right" style="margin-left:8px;"></i></a>
      <a href="#fitur" class="btn btn-ghost" style="color:#fff;border-color:rgba(255,255,255,.4);">Lihat Fitur <i class="fas fa-chevron-down" style="margin-left:8px;"></i></a>
    </div>
  </div>
</section>

<!-- ==================== STATISTIK SECTION ==================== -->
<section class="lp-stats">
  <div class="lp-container">
    <div class="lp-stats-grid">
      <div class="lp-stat-item">
        <i class="fas fa-book-open"></i>
        <span class="lp-stat-num">3</span>
        <span class="lp-stat-label">Jalur Login</span>
      </div>
      <div class="lp-stat-item">
        <i class="fas fa-layer-group"></i>
        <span class="lp-stat-num">5+</span>
        <span class="lp-stat-label">Modul CRUD</span>
      </div>
      <div class="lp-stat-item">
        <i class="fas fa-shield-halved"></i>
        <span class="lp-stat-num">100%</span>
        <span class="lp-stat-label">Aman &amp; Validasi</span>
      </div>
      <div class="lp-stat-item">
        <i class="fas fa-wifi"></i>
        <span class="lp-stat-num">Offline</span>
        <span class="lp-stat-label">Server Lokal</span>
      </div>
    </div>
  </div>
</section>

<!-- ==================== FITUR SECTION ==================== -->
<section class="lp-section" id="fitur">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Fitur Unggulan</span>
      <h2 class="serif">Semua yang Perpustakaan Butuhkan</h2>
      <p class="lp-section-sub">Sistem lengkap untuk mengelola perpustakaan sekolah, dari manajemen buku hingga pelacakan peminjaman.</p>
    </div>
    <div class="lp-features-grid">
      <div class="lp-feature-card">
        <div class="lp-feature-icon" style="background:var(--forest);"><i class="fas fa-book"></i></div>
        <h3 class="serif">Manajemen Buku</h3>
        <p>Tambah, ubah, dan hapus data buku beserta kategorinya. Pantau stok buku secara real-time dengan grafik visual interaktif.</p>
      </div>
      <div class="lp-feature-card">
        <div class="lp-feature-icon" style="background:var(--navy);"><i class="fas fa-exchange-alt"></i></div>
        <h3 class="serif">Peminjaman &amp; Pengembalian</h3>
        <p>Sistem peminjaman otomatis dengan pengurangan stok via trigger database, serta pengembalian yang mudah bagi siswa.</p>
      </div>
      <div class="lp-feature-card">
        <div class="lp-feature-icon" style="background:var(--brass);"><i class="fas fa-users"></i></div>
        <h3 class="serif">Kelola Keanggotaan</h3>
        <p>Pendaftaran anggota baru, validasi duplikasi username, dan password yang tersimpan aman dengan hashing otomatis.</p>
      </div>
      <div class="lp-feature-card">
        <div class="lp-feature-icon" style="background:var(--ok);"><i class="fas fa-chart-bar"></i></div>
        <h3 class="serif">Dashboard &amp; Grafik</h3>
        <p>Halaman dashboard dengan statistik ringkasan, grafik Chart.js untuk visualisasi koleksi buku per kategori.</p>
      </div>
      <div class="lp-feature-card">
        <div class="lp-feature-icon" style="background:var(--alert);"><i class="fas fa-file-print"></i></div>
        <h3 class="serif">Laporan &amp; Cetak</h3>
        <p>Halaman laporan siap cetak untuk data buku dan transaksi peminjaman, tampilan print-friendly yang rapi.</p>
      </div>
      <div class="lp-feature-card">
        <div class="lp-feature-icon" style="background:var(--forest-light);"><i class="fas fa-history"></i></div>
        <h3 class="serif">Log Aktivitas</h3>
        <p>Audit trail lengkap yang mencatat setiap aksi penting: login, CRUD, pinjam, dan kembalikan buku.</p>
      </div>
    </div>
  </div>
</section>

<!-- ==================== GALERI / SHOWCASE SECTION ==================== -->
<section class="lp-section lp-section-alt" id="galeri">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Galeri</span>
      <h2 class="serif">Lingkungan Perpustakaan Modern</h2>
      <p class="lp-section-sub">Ruang baca yang nyaman dan ter digitalisasi untuk mendukung kegiatan belajar mengajar di sekolah.</p>
    </div>
    <div class="lp-gallery-grid">
      <div class="lp-gallery-card lp-gallery-wide">
        <img src="assets/images/hero-library.png" alt="Interior Perpustakaan Modern">
        <div class="lp-gallery-overlay">
          <h3 class="serif">Interior Perpustakaan</h3>
          <p>Ruang baca modern dengan rak buku kayu dan pencahayaan alami</p>
        </div>
      </div>
      <div class="lp-gallery-card">
        <img src="assets/images/students-reading.png" alt="Siswa Membaca Buku">
        <div class="lp-gallery-overlay">
          <h3 class="serif">Siswa Berkumpul</h3>
          <p>Kegiatan membaca bersama di lingkungan yang nyaman</p>
        </div>
      </div>
      <div class="lp-gallery-card">
        <img src="assets/images/digital-library.png" alt="Sistem Digital">
        <div class="lp-gallery-overlay">
          <h3 class="serif">Sistem Digital</h3>
          <p>Manajemen perpustakaan berbasis teknologi modern</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== KATALOG BUKU SECTION ==================== -->
<section class="lp-section" id="katalog">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Katalog Buku</span>
      <h2 class="serif">Jelajahi Koleksi Perpustakaan</h2>
      <p class="lp-section-sub">Lihat seluruh buku yang tersedia, cek status ketersediaan, dan simpan buku favorit Anda untuk nanti.</p>
    </div>

    <?php if($katalogError): ?>
    <div class="lp-katalog-notice">
      <i class="fas fa-database"></i>
      <span>Database belum terhubung. Menampilkan data demo. Import <code>database.sql</code> untuk melihat data asli.</span>
    </div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div class="lp-katalog-filters">
      <a href="?filter=semua" class="lp-katalog-filter-btn <?= $filterKatalog==='semua'?'active':'' ?>">
        <i class="fas fa-layer-group"></i> Semua
      </a>
      <a href="?filter=tersedia" class="lp-katalog-filter-btn <?= $filterKatalog==='tersedia'?'active':'' ?>">
        <i class="fas fa-check-circle"></i> Tersedia
      </a>
      <a href="?filter=dipinjam" class="lp-katalog-filter-btn <?= $filterKatalog==='dipinjam'?'active':'' ?>">
        <i class="fas fa-clock"></i> Sedang Dipinjam
      </a>
      <a href="?filter=sering" class="lp-katalog-filter-btn <?= $filterKatalog==='sering'?'active':'' ?>">
        <i class="fas fa-fire"></i> Sering Dipinjam
      </a>
    </div>

    <!-- Simpan counter -->
    <div class="lp-katalog-saved-bar" id="savedBar" style="display:none;">
      <i class="fas fa-bookmark"></i>
      <span id="savedCountText">0 buku disimpan</span>
      <button class="btn btn-ghost btn-sm" id="clearSavedBtn" style="margin-left:auto;color:var(--alert);border-color:var(--alert);"><i class="fas fa-trash-alt"></i> Hapus Semua</button>
    </div>

    <!-- Grid buku -->
    <div class="lp-katalog-grid">
      <?php if(empty($katalogBuku)): ?>
        <div class="lp-katalog-empty">
          <i class="fas fa-search"></i>
          <h4>Tidak Ada Buku</h4>
          <p>Tidak ditemukan buku dengan filter yang dipilih.</p>
        </div>
      <?php else: foreach($katalogBuku as $b): ?>
        <?php
          $stok = (int)$b['stok'];
          $sedangPinjam = (int)$b['sedang_dipinjam'];
          $totalPinjam = (int)$b['total_dipinjam'];

          // Status utama
          if($stok <= 0 && $sedangPinjam > 0) {
            $statusLabel = 'Habis Dipinjam';
            $statusClass = 'habis';
            $statusIcon = 'fa-times-circle';
          } elseif($sedangPinjam > 0) {
            $statusLabel = 'Sedang Dipinjam';
            $statusClass = 'dipinjam';
            $statusIcon = 'fa-clock';
          } else {
            $statusLabel = 'Tersedia';
            $statusClass = 'tersedia';
            $statusIcon = 'fa-check-circle';
          }

          // Kondisi fisik buku (dari kolom database)
          $kondisiVal = $b['kondisi'] ?? 'Baik';
          $kondisiMap = [
            'Baik'   => ['label'=>'Baik',   'cls'=>'kondisi-baik',   'icon'=>'fa-circle-check'],
            'Cukup'  => ['label'=>'Cukup',  'cls'=>'kondisi-cukup',  'icon'=>'fa-circle-minus'],
            'Buruk'  => ['label'=>'Buruk',  'cls'=>'kondisi-buruk',  'icon'=>'fa-triangle-exclamation'],
            'Rusak'  => ['label'=>'Rusak',  'cls'=>'kondisi-rusak',  'icon'=>'fa-circle-xmark'],
          ];
          $kd = $kondisiMap[$kondisiVal] ?? $kondisiMap['Baik'];
        ?>
        <div class="lp-buku-card" data-buku-id="<?= (int)$b['id'] ?>" role="link" tabindex="0" aria-label="Lihat detail buku <?= h($b['judul']) ?>">
          <!-- Sampul buku -->
          <div class="lp-buku-cover">
            <?php
              $coverIdx = isset($coverFiles[(int)$b['id']]) ? (int)$b['id'] : ((($b['id'] - 1) % 8) + 1);
              $coverPath = $coverFiles[$coverIdx] ?? 'assets/images/covers/cover-1.png';
            ?>
            <img src="<?= $coverPath ?>" alt="Sampul: <?= h($b['judul']) ?>" loading="lazy">
          </div>
          <!-- Kondisi buku badge -->
          <div class="lp-buku-kondisi <?= $kd['cls'] ?>">
            <i class="fas <?= $kd['icon'] ?>"></i> Kondisi: <?= h($kd['label']) ?>
          </div>
          <!-- Simpan button -->
          <button class="lp-buku-save-btn" data-buku-id="<?= (int)$b['id'] ?>" data-judul="<?= h($b['judul']) ?>" title="Simpan buku">
            <i class="far fa-bookmark"></i>
          </button>
          <!-- Info buku -->
          <div class="lp-buku-info">
            <div class="lp-buku-category">
              <i class="fas fa-tag"></i> <?= h($b['nama_kategori'] ?? 'Tanpa Kategori') ?>
            </div>
            <h3 class="serif lp-buku-judul"><?= h($b['judul']) ?></h3>
            <p class="lp-buku-penulis"><i class="fas fa-pen-nib"></i> <?= h($b['penulis']) ?></p>
            <!-- Stats bar -->
            <div class="lp-buku-stats">
              <div class="lp-buku-stat">
                <i class="fas fa-box" style="color:var(--forest);"></i>
                <span>Stok: <strong><?= $stok ?></strong></span>
              </div>
              <div class="lp-buku-stat">
                <i class="fas fa-arrow-right-from-bracket" style="color:var(--navy);"></i>
                <span>Dipinjam: <strong><?= $sedangPinjam ?></strong></span>
              </div>
              <div class="lp-buku-stat">
                <i class="fas fa-repeat" style="color:var(--brass);"></i>
                <span>Total: <strong><?= $totalPinjam ?>x</strong></span>
              </div>
            </div>
            <!-- Status badge -->
            <div class="lp-buku-status <?= $statusClass ?>">
              <i class="fas <?= $statusIcon ?>"></i> <?= $statusLabel ?>
            </div>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</section>

<!-- ==================== BUKU TERSIMPAN SECTION ==================== -->
<section class="lp-section lp-section-alt" id="buku-tersimpan" style="display:none;">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Favorit</span>
      <h2 class="serif">Buku Tersimpan</h2>
      <p class="lp-section-sub">Daftar buku yang telah Anda simpan untuk dibaca nanti.</p>
    </div>
    <div class="lp-saved-grid" id="savedGrid"></div>
    <div class="lp-katalog-empty" id="savedEmpty" style="display:none;">
      <i class="fas fa-bookmark"></i>
      <h4>Belum Ada Buku Tersimpan</h4>
      <p>Klik ikon bookmark pada katalog untuk menyimpan buku favorit Anda.</p>
    </div>
  </div>
</section>

<!-- ==================== GRAFIK KOLEKSI SECTION ==================== -->
<section class="lp-section" id="grafik">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Visualisasi Data</span>
      <h2 class="serif">Koleksi Buku per Kategori</h2>
      <p class="lp-section-sub">Grafik visual interaktif yang menampilkan distribusi jumlah judul dan total stok buku di setiap kategori perpustakaan.</p>
    </div>
    <div class="lp-chart-wrap">
      <div class="card lp-chart-card">
        <div class="lp-chart-header">
          <h3 class="serif"><i class="fas fa-chart-bar" style="color:var(--brass);margin-right:8px;"></i>Rekap Koleksi Buku</h3>
          <span class="muted">Data ditampilkan dari database perpustakaan sekolah</span>
        </div>
        <canvas id="chartKategoriLanding" height="100"></canvas>
      </div>
      <div class="lp-chart-info">
        <div class="lp-chart-stat-box">
          <i class="fas fa-book" style="color:var(--forest);"></i>
          <div><span class="lp-chart-stat-num" id="totalJudulDemo">0</span><span class="lp-chart-stat-label">Total Judul Buku</span></div>
        </div>
        <div class="lp-chart-stat-box">
          <i class="fas fa-layer-group" style="color:var(--brass);"></i>
          <div><span class="lp-chart-stat-num" id="totalStokDemo">0</span><span class="lp-chart-stat-label">Total Stok Tersedia</span></div>
        </div>
        <div class="lp-chart-stat-box">
          <i class="fas fa-tags" style="color:var(--navy);"></i>
          <div><span class="lp-chart-stat-num" id="totalKategoriDemo">0</span><span class="lp-chart-stat-label">Kategori Aktif</span></div>
        </div>
        <div class="lp-chart-note">
          <i class="fas fa-info-circle"></i>
          <p>Grafik ini menampilkan data demo. Setelah login sebagai admin, Anda akan melihat grafik dengan data real-time dari database perpustakaan.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== VIDEO SECTION ==================== -->
<section class="lp-section lp-section-alt" id="video">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Video Panduan</span>
      <h2 class="serif">Cara Menggunakan Pustaka.Lokal</h2>
      <p class="lp-section-sub">Tonton video panduan untuk memahami alur peminjaman dan pengembalian buku di sistem perpustakaan digital.</p>
    </div>
    <div class="lp-video-wrap">
      <div class="lp-video-container">
        <div class="video-embed">
          <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Video Panduan Pustaka.Lokal" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
        </div>
      </div>
      <div class="lp-video-steps">
        <div class="lp-video-step">
          <div class="lp-video-step-num">1</div>
          <div>
            <h4>Daftar Akun</h4>
            <p>Siswa mendaftar melalui halaman pendaftaran anggota baru. Username dan password akan tersimpan aman dengan hashing otomatis.</p>
          </div>
        </div>
        <div class="lp-video-step">
          <div class="lp-video-step-num">2</div>
          <div>
            <h4>Login ke Sistem</h4>
            <p>Masuk menggunakan username dan password yang telah didaftarkan. Sistem akan mengarahkan ke dashboard sesuai peran Anda.</p>
          </div>
        </div>
        <div class="lp-video-step">
          <div class="lp-video-step-num">3</div>
          <div>
            <h4>Pinjam Buku</h4>
            <p>Cari buku yang tersedia, lalu klik tombol Pinjam. Stok buku akan otomatis berkurang melalui trigger database.</p>
          </div>
        </div>
        <div class="lp-video-step">
          <div class="lp-video-step-num">4</div>
          <div>
            <h4>Kembalikan Buku</h4>
            <p>Klik tombol Kembalikan pada buku yang sedang dipinjam. Stok buku akan otomatis kembali bertambah.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ==================== TESTIMONI SECTION ==================== -->
<section class="lp-section lp-section-alt" id="testimoni">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Testimoni</span>
      <h2 class="serif">Apa Kata Mereka?</h2>
      <p class="lp-section-sub">Pendapat pengguna yang sudah merasakan kemudahan Pustaka.Lokal dalam mengelola perpustakaan sekolah.</p>
    </div>
    <!-- Rating ringkasan -->
    <div class="lp-testi-summary">
      <div class="lp-testi-overall">
        <span class="lp-testi-big-num"><?= number_format($avgRating, 1, ',', '.') ?></span>
        <div class="lp-testi-stars">
          <?php
    $sisa = $avgRating - floor($avgRating);
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($avgRating)) {
            echo '<i class="fas fa-star"></i>';
        } elseif ($i === ceil($avgRating) && $sisa >= 0.3) {
            echo '<i class="fas fa-star-half-alt"></i>';
        } else {
            echo '<i class="far fa-star"></i>';
        }
    }
?>
        </div>
        <span class="lp-testi-count">Dari <?= $countRating ?> ulasan pengguna</span>
      </div>
      <div class="lp-testi-bars">
        
        <?php for ($i = 5; $i >= 1; $i--):
          $pct = $countRating > 0 ? round(($ratingDist[$i] / $countRating) * 100) : 0;
        ?>
        <div class="lp-testi-bar-row"><span><?= $i ?></span><div class="lp-testi-bar-track"><div class="lp-testi-bar-fill" style="width:<?= $pct ?>%;"></div></div><span><?= $pct ?>%</span></div>
        <?php endfor; ?>
      </div>
    </div>
    <!-- Form kirim testimoni -->
    <div class="lp-testi-form-wrap">
      <?php if ($testiSubmitted): ?>
      <div class="lp-alert lp-alert-success" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#e6f4ea;color:#1e7e34;border:1px solid #b7dfc0;">
        <i class="fas fa-check-circle" style="margin-right:8px;"></i>Terima kasih! Testimoni Anda berhasil dikirim.
      </div>
      <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kirim_testimoni'): ?>
      <div class="lp-alert lp-alert-error" style="margin-bottom:16px;padding:12px 16px;border-radius:8px;background:#fdecea;color:#b3261e;border:1px solid #f5c2bd;">
        <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>Gagal mengirim testimoni. Pastikan nama, rating, dan komentar terisi dengan benar.
      </div>
      <?php endif; ?>
      <button class="btn btn-primary" id="toggleTestiFormBtn" type="button" style="width:100%;margin-bottom:16px;">
        <i class="fas fa-pen" style="margin-right:8px;"></i>Tulis Testimoni Anda
      </button>
      <form id="testiForm" class="lp-testi-form" method="post" action="#testimoni" style="display:none;">
        <input type="hidden" name="action" value="kirim_testimoni">
        <div class="lp-testi-form-grid">
          <div class="lp-testi-form-field">
            <label for="testi_nama">Nama <span class="lp-testi-required">*</span></label>
            <input type="text" id="testi_nama" name="testi_nama" placeholder="Nama lengkap Anda" required maxlength="100">
          </div>
          <div class="lp-testi-form-field">
            <label for="testi_peran">Peran / Jabatan</label>
            <input type="text" id="testi_peran" name="testi_peran" placeholder="Contoh: Siswa Kelas XI" maxlength="80">
          </div>
        </div>
        <div class="lp-testi-form-field" style="margin-top:12px;">
          <label>Rating <span class="lp-testi-required">*</span></label>
          <div class="lp-testi-star-input" id="starInput">
            <?php for ($si = 1; $si <= 5; $si++): ?>
            <i class="far fa-star" data-value="<?= $si ?>"></i>
            <?php endfor; ?>
            <input type="hidden" name="testi_rating" id="testiRating" value="5">
            <span class="lp-testi-rating-text" id="ratingText">5 dari 5</span>
          </div>
        </div>
        <div class="lp-testi-form-field" style="margin-top:12px;">
          <label for="testi_komentar">Komentar <span class="lp-testi-required">*</span></label>
          <textarea id="testi_komentar" name="testi_komentar" rows="3" placeholder="Tulis pengalaman Anda menggunakan Pustaka.Lokal..." required maxlength="500"></textarea>
        </div>
        <div class="lp-testi-form-actions">
          <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane" style="margin-right:6px;"></i>Kirim Testimoni</button>
          <button type="button" class="btn btn-ghost" id="cancelTestiBtn">Batal</button>
        </div>
      </form>
    </div>
    <!-- Kartu testimoni -->
    <div class="lp-testi-grid">
      
        <?php
        $colorIdx = 0;
        $testiColors = ['var(--forest)', 'var(--navy)', 'var(--brass)', 'var(--ok)', 'var(--alert)', 'var(--forest-light)'];
        foreach ($testimoniList as $ti):
          $avatarColor = $testiColors[$colorIdx % count($testiColors)];
          $colorIdx++;
          $inisial = strtoupper(mb_substr($ti['nama'], 0, 1)) . strtoupper(mb_substr(trim($ti['nama'] ?? ''), -1, 1));
          $ratingVal = (int)$ti['rating'];
        ?>
        <div class="lp-testi-card">
          <div class="lp-testi-card-top">
            <div class="lp-testi-avatar" style="background:<?= $avatarColor ?>;">"><?= $inisial ?></div>
            <div class="lp-testi-meta">
              <h4><?= h($ti['nama']) ?></h4>
              <span><?= h($ti['peran'] ?? 'Pengguna') ?></span>
            </div>
            <div class="lp-testi-stars-sm">
              <?php for ($si = 1; $si <= 5; $si++): ?>
                <?php if ($si <= $ratingVal): ?>
                  <i class="fas fa-star"></i>
                <?php else: ?>
                  <i class="far fa-star"></i>
                <?php endif; ?>
              <?php endfor; ?>
            </div>
          </div>
          <p><?= h($ti['komentar']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ==================== LOGIN GATE SECTION ==================== -->
<section class="lp-section lp-gate-section">
  <div class="lp-container">
    <div class="lp-section-header">
      <span class="eyebrow">Masuk ke Sistem</span>
      <h2 class="serif">Pilih Jalur Masuk Anda</h2>
      <p class="lp-section-sub">Pustaka.Lokal memiliki tiga jalur akses sesuai peran di perpustakaan sekolah.</p>
    </div>
    <div class="shelf lp-shelf-center">
      <a class="spine-card admin" href="admin_login.php">
        <span class="tag">Staf Perpustakaan</span>
        <h3 class="serif">Login sebagai Admin</h3>
        <p>Kelola data buku, transaksi, dan keanggotaan.</p>
      </a>
      <a class="spine-card petugas" href="petugas_login.php">
        <span class="tag">Staf Perpustakaan</span>
        <h3 class="serif">Login sebagai Petugas</h3>
        <p>Bantu kelola buku, transaksi, dan keanggotaan harian.</p>
      </a>
      <a class="spine-card user" href="user_check.php">
        <span class="tag">Warga Sekolah</span>
        <h3 class="serif">Login sebagai Siswa</h3>
        <p>Pinjam dan kembalikan buku perpustakaan.</p>
      </a>
    </div>
    <div class="shelf-ledge lp-shelf-ledge-center"></div>
  </div>
</section>

<!-- ==================== FOOTER ==================== -->
<footer class="lp-footer">
  <div class="lp-container">
    <div class="lp-footer-grid">
      <div class="lp-footer-brand">
        <div class="brandmark" style="margin-bottom:12px;"><span class="dot"></span><span>Pustaka.Lokal</span></div>
        <p style="color:var(--ink-soft);font-size:13px;max-width:280px;">Aplikasi perpustakaan sekolah digital berbasis PHP dan MySQL. Dibuat untuk UKK RPL 2025/2026.</p>
      </div>
      <div class="lp-footer-links">
        <h4>Navigasi</h4>
        <a href="index.php">Beranda</a>
        <a href="#fitur">Fitur</a>
        <a href="#galeri">Galeri</a>
        <a href="help.php">Bantuan</a>
      </div>
      <div class="lp-footer-links">
        <h4>Akses Cepat</h4>
        <a href="admin_login.php">Login Admin</a>
        <a href="petugas_login.php">Login Petugas</a>
        <a href="user_check.php">Login Siswa</a>
      </div>
    </div>
    <div class="lp-footer-bottom">
      <p>&copy; 2026 Pustaka.Lokal &mdash; Perpustakaan Sekolah Digital. All rights reserved by Ari  mei sihran SMK N 1 Sanden.</p>
  </div>
</footer>

<!-- Burger menu toggle -->
<script>
(function(){
  var btn = document.getElementById('burgerBtn');
  var nav = document.getElementById('mobileNav');
  if(btn && nav){
    btn.addEventListener('click', function(){
      nav.classList.toggle('open');
      btn.classList.toggle('open');
    });
  }
  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(function(a){
    a.addEventListener('click', function(e){
      var id = this.getAttribute('href');
      if(id.length > 1){
        var el = document.querySelector(id);
        if(el){ e.preventDefault(); el.scrollIntoView({behavior:'smooth'}); if(nav.classList.contains('open')){ nav.classList.remove('open'); btn.classList.remove('open'); } }
      }
    });
  });

  // ===== SIMPAN BUKU (localStorage bookmark) =====
  var STORAGE_KEY = 'pustaka_lokal_saved';
  var savedBar = document.getElementById('savedBar');
  var savedCountText = document.getElementById('savedCountText');
  var clearSavedBtn = document.getElementById('clearSavedBtn');
  var savedSection = document.getElementById('buku-tersimpan');
  var savedGrid = document.getElementById('savedGrid');
  var savedEmpty = document.getElementById('savedEmpty');

  function getSaved() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch(e) { return []; }
  }
  function setSaved(arr) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(arr));
    updateSavedUI();
  }
  function isSaved(id) { return getSaved().some(function(b){ return b.id === id; }); }

  function updateSavedUI() {
    var saved = getSaved();
    // Update counter bar
    if(saved.length > 0) {
      savedBar.style.display = 'flex';
      savedCountText.textContent = saved.length + ' buku disimpan';
    } else {
      savedBar.style.display = 'none';
    }
    // Update all bookmark icons
    document.querySelectorAll('.lp-buku-save-btn').forEach(function(btn){
      var id = parseInt(btn.getAttribute('data-buku-id'));
      var icon = btn.querySelector('i');
      if(isSaved(id)) {
        icon.className = 'fas fa-bookmark';
        btn.style.color = 'var(--brass)';
      } else {
        icon.className = 'far fa-bookmark';
        btn.style.color = '';
      }
    });
    // Update saved section
    if(saved.length > 0) {
      savedSection.style.display = '';
      savedGrid.style.display = '';
      savedEmpty.style.display = 'none';
      savedGrid.innerHTML = '';
      saved.forEach(function(b) {
        var card = document.createElement('div');
        card.className = 'lp-saved-item';
        card.innerHTML = '<i class="fas fa-bookmark" style="color:var(--brass);font-size:18px;flex-shrink:0;"></i>' +
          '<div style="flex:1;min-width:0;"><strong>' + b.judul + '</strong>' +
          '<span class="muted" style="font-size:12px;">' + (b.penulis || '') + '</span></div>' +
          '<button class="btn btn-ghost btn-sm lp-saved-remove" data-id="' + b.id + '"><i class="fas fa-times"></i></button>';
        savedGrid.appendChild(card);
      });
      // Bind remove buttons
      savedGrid.querySelectorAll('.lp-saved-remove').forEach(function(btn){
        btn.addEventListener('click', function(){
          var rid = parseInt(this.getAttribute('data-id'));
          setSaved(getSaved().filter(function(b){ return b.id !== rid; }));
        });
      });
    } else {
      if(savedSection.style.display !== 'none') {
        savedGrid.style.display = 'none';
        savedEmpty.style.display = '';
      }
    }
  }

  // Bind save buttons
  document.querySelectorAll('.lp-buku-save-btn').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.stopPropagation(); // Jangan sampai memicu klik kartu (buka detail)
      var id = parseInt(this.getAttribute('data-buku-id'));
      var judul = this.getAttribute('data-judul');
      if(isSaved(id)) {
        setSaved(getSaved().filter(function(b){ return b.id !== id; }));
      } else {
        var arr = getSaved();
        arr.push({ id: id, judul: judul });
        setSaved(arr);
      }
    });
  });

  // Klik kartu buku -> buka halaman detail buku
  document.querySelectorAll('.lp-buku-card').forEach(function(card){
    var bukuId = card.getAttribute('data-buku-id');
    function goToDetail(){ window.location.href = 'buku_detail.php?id=' + bukuId; }
    card.addEventListener('click', function(e){
      if(e.target.closest('.lp-buku-save-btn')) return; // Klik tombol simpan tidak membuka detail
      goToDetail();
    });
    card.addEventListener('keydown', function(e){
      if(e.key === 'Enter' || e.key === ' '){
        e.preventDefault();
        goToDetail();
      }
    });
  });

  // Clear all
  if(clearSavedBtn) {
    clearSavedBtn.addEventListener('click', function(){
      localStorage.removeItem(STORAGE_KEY);
      updateSavedUI();
    });
  }

  // Init
  updateSavedUI();
})();
</script>

<script>
// ===== TESTIMONI: Star rating & form toggle =====
(function(){
  // Toggle form
  var toggleBtn = document.getElementById('toggleTestiFormBtn');
  var testiForm = document.getElementById('testiForm');
  var cancelBtn = document.getElementById('cancelTestiBtn');
  if(toggleBtn && testiForm){
    toggleBtn.addEventListener('click', function(){
      var isHidden = testiForm.style.display === 'none';
      testiForm.style.display = isHidden ? 'block' : 'none';
      toggleBtn.innerHTML = isHidden
        ? '<i class="fas fa-times" style="margin-right:8px;"></i>Tutup Form'
        : '<i class="fas fa-pen" style="margin-right:8px;"></i>Tulis Testimoni Anda';
      if(isHidden) testiForm.scrollIntoView({behavior:'smooth', block:'center'});
    });
  }
  if(cancelBtn && testiForm){
    cancelBtn.addEventListener('click', function(){
      testiForm.style.display = 'none';
      if(toggleBtn) toggleBtn.innerHTML = '<i class="fas fa-pen" style="margin-right:8px;"></i>Tulis Testimoni Anda';
    });
  }

  // Star rating input
  var starContainer = document.getElementById('starInput');
  var ratingInput = document.getElementById('testiRating');
  var ratingText = document.getElementById('ratingText');
  var starLabels = ['', 'Sangat Buruk', 'Buruk', 'Cukup', 'Bagus', 'Sangat Bagus'];
  if(starContainer && ratingInput){
    var stars = starContainer.querySelectorAll('i[data-value]');
    var currentRating = 5;
    function highlightStars(val, isPreview){
      stars.forEach(function(s){
        var v = parseInt(s.getAttribute('data-value'));
        if(v <= val){
          s.className = 'fas fa-star';
          s.style.color = isPreview ? '#f59f00' : 'var(--brass)';
        } else {
          s.className = 'far fa-star';
          s.style.color = '';
        }
      });
    }
    highlightStars(5, false);
    stars.forEach(function(s){
      s.addEventListener('mouseenter', function(){
        highlightStars(parseInt(this.getAttribute('data-value')), true);
      });
      s.addEventListener('click', function(){
        currentRating = parseInt(this.getAttribute('data-value'));
        ratingInput.value = currentRating;
        if(ratingText) ratingText.textContent = currentRating + ' dari 5 — ' + starLabels[currentRating];
        highlightStars(currentRating, false);
      });
      s.style.cursor = 'pointer';
      s.style.fontSize = '24px';
      s.style.transition = 'color .15s, transform .15s';
      s.style.marginRight = '4px';
    });
    starContainer.addEventListener('mouseleave', function(){
      highlightStars(currentRating, false);
    });
  }
})();
</script>

<script>
// ===== Grafik Chart.js demo untuk landing page =====
(function(){
  // Data demo kategori buku (mengikuti format view_rekap_kategori dari database)
  var demoData = [
    {nama_kategori: 'Fiksi',       jumlah_judul: 8, total_stok: 24},
    {nama_kategori: 'Sains',       jumlah_judul: 6, total_stok: 18},
    {nama_kategori: 'Sejarah',     jumlah_judul: 5, total_stok: 15},
    {nama_kategori: 'Matematika',  jumlah_judul: 4, total_stok: 12},
    {nama_kategori: 'Bahasa',      jumlah_judul: 7, total_stok: 21},
    {nama_kategori: 'Teknologi',   jumlah_judul: 3, total_stok: 9}
  ];

  // Hitung total untuk stat boxes
  var totalJudul = demoData.reduce(function(s,d){ return s + d.jumlah_judul; }, 0);
  var totalStok  = demoData.reduce(function(s,d){ return s + d.total_stok; }, 0);
  var totalKat   = demoData.length;

  // Animasi angka
  function animateNum(el, target){
    var current = 0;
    var step = Math.ceil(target / 30);
    var timer = setInterval(function(){
      current += step;
      if(current >= target){ current = target; clearInterval(timer); }
      el.textContent = current;
    }, 30);
  }
  var elJudul = document.getElementById('totalJudulDemo');
  var elStok  = document.getElementById('totalStokDemo');
  var elKat   = document.getElementById('totalKategoriDemo');
  if(elJudul) animateNum(elJudul, totalJudul);
  if(elStok)  animateNum(elStok, totalStok);
  if(elKat)   animateNum(elKat, totalKat);

  // Render chart
  var chartEl = document.getElementById('chartKategoriLanding');
  if(chartEl && window.Chart){
    new Chart(chartEl, {
      type: 'bar',
      data: {
        labels: demoData.map(function(d){ return d.nama_kategori; }),
        datasets: [
          { label: 'Jumlah Judul', data: demoData.map(function(d){ return d.jumlah_judul; }), backgroundColor: '#1F3D2E', borderRadius: 6 },
          { label: 'Total Stok',   data: demoData.map(function(d){ return d.total_stok; }),   backgroundColor: '#B08D57', borderRadius: 6 }
        ]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'top', labels: { font: { family: 'Inter', size: 12 }, usePointStyle: true, pointStyle: 'rectRounded', padding: 16 } }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, font: { family: 'JetBrains Mono', size: 11 } } },
          x: { ticks: { font: { family: 'Inter', size: 12 } } }
        }
      }
    });
  }
})();
</script>

<?php require 'includes/foot.php'; ?>
