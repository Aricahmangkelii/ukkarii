<?php
require 'config.php';
$pageTitle = 'Detail Buku';

$bukuId = (int)($_GET['id'] ?? 0);
if ($bukuId <= 0) { header('Location: index.php'); exit; }

// Peta sampul buku
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

// Data buku
$stmt = $pdo->prepare('
    SELECT b.*, k.nama_kategori,
        COALESCE(r.jumlah_ulasan, 0) AS jumlah_ulasan,
        COALESCE(r.rata_rating, 0) AS rata_rating,
        COALESCE(pt.jml, 0) AS total_dipinjam
    FROM buku b
    LEFT JOIN kategori k ON k.id = b.kategori_id
    LEFT JOIN view_rating_buku r ON r.buku_id = b.id
    LEFT JOIN (SELECT buku_id, COUNT(*) AS jml FROM transaksi GROUP BY buku_id) pt ON pt.buku_id = b.id
    WHERE b.id = ?
');
$stmt->execute([$bukuId]);
$buku = $stmt->fetch();
if (!$buku) { header('Location: index.php'); exit; }

// Kondisi mapping
$kondisiMap = [
    'Baik'  => ['class' => 'kondisi-baik',  'icon' => 'fa-circle-check'],
    'Cukup' => ['class' => 'kondisi-cukup', 'icon' => 'fa-circle-minus'],
    'Buruk' => ['class' => 'kondisi-buruk', 'icon' => 'fa-triangle-exclamation'],
    'Rusak' => ['class' => 'kondisi-rusak', 'icon' => 'fa-circle-xmark'],
];
$kd = $kondisiMap[$buku['kondisi']] ?? $kondisiMap['Baik'];
$coverIdx = isset($coverFiles[$bukuId]) ? $bukuId : ((($bukuId - 1) % 8) + 1);
$coverPath = $coverFiles[$coverIdx] ?? 'assets/images/covers/cover-1.png';

// Render bintang
function renderStars($rating, $class = '') {
    $html = '<span class="star-rating ' . $class . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $html .= '<i class="fas fa-star"></i>';
        } elseif ($i - 0.5 <= $rating) {
            $html .= '<i class="fas fa-star-half-alt"></i>';
        } else {
            $html .= '<i class="far fa-star"></i>';
        }
    }
    $html .= '</span>';
    return $html;
}

// Ulasan
$stmtUlasan = $pdo->prepare('
    SELECT u.*, a.kelas
    FROM ulasan_buku u
    LEFT JOIN anggota a ON a.id = u.anggota_id
    WHERE u.buku_id = ?
    ORDER BY u.created_at DESC
');
$stmtUlasan->execute([$bukuId]);
$ulasanList = $stmtUlasan->fetchAll();

$avatarColors = ['var(--forest)','var(--navy)','var(--brass)','var(--ok)','var(--alert)','var(--forest-light)'];

// Info user login
$loggedIn = (isset($_SESSION['role']) && $_SESSION['role'] === 'siswa');
$userNama = $loggedIn ? $_SESSION['siswa_nama'] : '';
$userKelas = $loggedIn ? ($_SESSION['siswa_kelas'] ?? '') : '';

require 'includes/head.php';
require 'includes/header.php';
?>

<div class="buku-detail-wrap">
  <!-- Tombol kembali -->
  <a href="index.php#katalog" style="display:inline-flex;align-items:center;gap:6px;color:var(--ink-soft);font-size:13px;margin-bottom:20px;text-decoration:none;">
    <i class="fas fa-arrow-left"></i> Kembali ke Katalog
  </a>

  <!-- ===== DETAIL BUKU ===== -->
  <div class="buku-detail-top">
    <div class="buku-detail-cover">
      <img src="<?= $coverPath ?>" alt="Sampul: <?= h($buku['judul']) ?>">
    </div>
    <div class="buku-detail-info">
      <div style="margin-bottom:6px;"><span class="badge badge-kategori"><?= h($buku['nama_kategori'] ?? 'Tanpa Kategori') ?></span></div>
      <h1 class="serif"><?= h($buku['judul']) ?></h1>
      <p class="bd-penulis"><i class="fas fa-pen-nib" style="margin-right:6px;"></i><?= h($buku['penulis']) ?></p>

      <!-- Rating & Kondisi row -->
      <div class="buku-detail-row">
        <div class="buku-detail-rating">
          <span class="rating-num"><?= number_format((float)$buku['rata_rating'], 1, '.', '') ?></span>
          <div>
            <?= renderStars((float)$buku['rata_rating']) ?>
            <div class="rating-count"><?= (int)$buku['jumlah_ulasan'] ?> ulasan</div>
          </div>
        </div>
        <span class="badge-kondisi <?= $kd['class'] ?>"><i class="fas <?= $kd['icon'] ?>"></i> Kondisi: <?= h($buku['kondisi']) ?></span>
      </div>

      <!-- Meta grid -->
      <div class="buku-detail-meta">
        <div class="bd-meta-item">
          <div class="bd-meta-icon"><i class="fas fa-box"></i></div>
          <div><div class="bd-meta-label">Stok</div><div class="bd-meta-value"><?= (int)$buku['stok'] ?> eksemplar</div></div>
        </div>
        <div class="bd-meta-item">
          <div class="bd-meta-icon"><i class="fas fa-repeat"></i></div>
          <div><div class="bd-meta-label">Total Dipinjam</div><div class="bd-meta-value"><?= (int)$buku['total_dipinjam'] ?? 0 ?>x</div></div>
        </div>
      </div>

      <div class="buku-detail-actions">
        <?php if ($loggedIn): ?>
          <a href="user_dashboard.php?tab=pinjam" class="btn btn-primary"><i class="fas fa-hand-holding" style="margin-right:6px;"></i>Pinjam Buku Ini</a>
        <?php else: ?>
          <a href="user_check.php" class="btn btn-primary"><i class="fas fa-sign-in-alt" style="margin-right:6px;"></i>Login untuk Pinjam</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ===== SECTION: ULASAN ===== -->
  <div class="ulasan-section">
    <h3 class="serif"><i class="fas fa-comments" style="color:var(--brass);margin-right:8px;"></i>Ulasan Pembaca (<?= count($ulasanList) ?>)</h3>

    <?php if ($loggedIn || true): ?>
    <!-- Form ulasan -->
    <div class="ulasan-form" id="ulasanFormWrap">
      <h4><i class="fas fa-pen" style="margin-right:6px;color:var(--forest);"></i>Tulis Ulasan Anda</h4>
      <?php if (!$loggedIn): ?>
      <div class="field" style="margin-bottom:10px;">
        <label>Nama</label>
        <input type="text" id="ulasanNama" placeholder="Masukkan nama Anda">
      </div>
      <?php endif; ?>
      <div style="margin-bottom:10px;">
        <label style="display:block;font-size:12.5px;font-weight:600;color:var(--ink-soft);margin-bottom:6px;">Rating</label>
        <div class="star-select" id="starSelect">
          <?php for ($i = 5; $i >= 1; $i--): ?>
          <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>"><label for="star<?= $i ?>"><i class="fas fa-star"></i></label>
          <?php endfor; ?>
        </div>
      </div>
      <div style="margin-bottom:0;">
        <label style="display:block;font-size:12.5px;font-weight:600;color:var(--ink-soft);margin-bottom:6px;">Komentar</label>
        <textarea id="ulasanKomentar" placeholder="Tulis pendapat Anda tentang buku ini..."></textarea>
      </div>
      <div class="ulasan-form-actions">
        <span id="ulasanError" style="color:var(--alert);font-size:12px;"></span>
        <button type="button" class="btn btn-primary btn-sm" id="submitUlasan" data-buku-id="<?= $bukuId ?>"><i class="fas fa-paper-plane" style="margin-right:6px;"></i>Kirim Ulasan</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Daftar ulasan -->
    <div id="ulasanList">
      <?php if (empty($ulasanList)): ?>
        <p style="text-align:center;color:var(--ink-soft);padding:20px 0;font-size:13px;">Belum ada ulasan untuk buku ini. Jadilah yang pertama!</p>
      <?php else: foreach ($ulasanList as $idx => $u): ?>
      <div class="ulasan-item">
        <div class="ulasan-avatar" style="background:<?= $avatarColors[$idx % count($avatarColors)] ?>;">
          <?= strtoupper(mb_substr(preg_replace('/\s+/', '', $u['nama']), 0, 2)) ?>
        </div>
        <div class="ulasan-body">
          <div class="ulasan-header">
            <span class="ulasan-nama"><?= h($u['nama']) ?></span>
            <?php if ($u['kelas']): ?><span style="color:var(--ink-soft);font-size:11px;">&middot; <?= h($u['kelas']) ?></span><?php endif; ?>
            <?= renderStars((int)$u['rating'], 'star-rating-sm') ?>
            <span class="ulasan-date"><?= h($u['created_at']) ?></span>
          </div>
          <p class="ulasan-text"><?= nl2br(h($u['komentar'])) ?></p>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<script>
(function(){
  var submitBtn = document.getElementById('submitUlasan');
  if (!submitBtn) return;
   var bukuId = submitBtn.getAttribute('data-buku-id');
  var isLoggedIn = <?= $loggedIn ? 'true' : 'false' ?>;
  var userNama = <?= json_encode($userNama, JSON_UNESCAPED_UNICODE) ?>;
  var selectedRating = 0;

  // Star select
  document.querySelectorAll('#starSelect input').forEach(function(inp){
    inp.addEventListener('change', function(){ selectedRating = parseInt(this.value); });
  });

  submitBtn.addEventListener('click', function(){
    var errorEl = document.getElementById('ulasanError');
    var komentar = document.getElementById('ulasanKomentar').value.trim();
    var nama = isLoggedIn ? userNama : (document.getElementById('ulasanNama') ? document.getElementById('ulasanNama').value.trim() : '');

    errorEl.textContent = '';
    if (selectedRating < 1) { errorEl.textContent = 'Pilih rating bintang terlebih dahulu.'; return; }
    if (!komentar) { errorEl.textContent = 'Komentar wajib diisi.'; return; }
    if (!isLoggedIn && !nama) { errorEl.textContent = 'Nama wajib diisi.'; return; }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Mengirim...';

    var fd = new FormData();
    fd.append('action', 'submit_ulasan');
    fd.append('buku_id', bukuId);
    fd.append('rating', selectedRating);
    fd.append('komentar', komentar);
    if (!isLoggedIn) fd.append('nama', nama);

    fetch('api_ulasan.php', {method:'POST', body:fd})
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (res.success) {
        var d = res.data;
        var colors = ['var(--forest)','var(--navy)','var(--brass)','var(--ok)','var(--alert)','var(--forest-light)'];
        var initials = d.nama.replace(/\s+/g,'').substring(0,2).toUpperCase();
        var starsHtml = '';
        for (var i=1;i<=5;i++) starsHtml += i<=d.rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';

        var html = '<div class="ulasan-item" style="animation:notifSlideIn .4s ease;">' +
          '<div class="ulasan-avatar" style="background:' + colors[Math.floor(Math.random()*colors.length)] + ';">' + initials + '</div>' +
          '<div class="ulasan-body"><div class="ulasan-header">' +
          '<span class="ulasan-nama">' + d.nama + '</span>' +
          '<span class="star-rating star-rating-sm">' + starsHtml + '</span>' +
          '<span class="ulasan-date">Baru saja</span></div>' +
          '<p class="ulasan-text">' + d.komentar.replace(/\n/g,'<br>') + '</p></div></div>';

        var list = document.getElementById('ulasanList');
        var emptyMsg = list.querySelector('p');
        if (emptyMsg) emptyMsg.remove();
        list.insertAdjacentHTML('afterbegin', html);

        // Reset form
        document.getElementById('ulasanKomentar').value = '';
        if (document.getElementById('ulasanNama')) document.getElementById('ulasanNama').value = '';
        document.querySelectorAll('#starSelect input').forEach(function(i){ i.checked = false; });
        selectedRating = 0;

        if (window.Swal) Swal.fire({icon:'success',title:'Berhasil!',text:res.message,timer:2000,showConfirmButton:false,position:'top-end'});
      } else {
        errorEl.textContent = res.message;
      }
    })
    .catch(function(){ errorEl.textContent = 'Terjadi kesalahan jaringan.'; })
    .finally(function(){
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right:6px;"></i>Kirim Ulasan';
    });
  });
})();
</script>

<?php require 'includes/foot.php'; ?>
