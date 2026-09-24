<?php
require 'config.php';
$pageTitle = 'Cek Keanggotaan';
require 'includes/head.php';
?>
<section class="center-screen">
  <div class="card form-card">
    <a class="back-link" href="index.php">← Kembali</a>
    <span class="eyebrow">Siswa</span>
    <h2 class="serif">Apakah Anda sudah anggota?</h2>
    <p class="sub">Anggota terdaftar dapat langsung login. Belum terdaftar? Daftar dulu sebagai anggota baru.</p>
    <div class="form-actions">
      <a class="btn btn-primary" href="user_login.php">Ya, sudah anggota</a>
      <a class="btn btn-brass" href="user_register.php">Belum, daftar</a>
    </div>
  </div>
</section>
<?php require 'includes/foot.php'; ?>
