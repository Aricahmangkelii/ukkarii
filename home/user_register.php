<?php
require 'config.php';

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama     = trim($_POST['nama'] ?? '');
    $kelas    = trim($_POST['kelas'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$nama || !$kelas || !$username || !$password) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter.';
    } elseif (strlen($password) < 5) {
        $error = 'Password minimal 5 karakter.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM anggota WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = 'Username sudah dipakai anggota lain.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO anggota (nama, kelas, username, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$nama, $kelas, $username, $hash]);
            catat_log($pdo, 'siswa', (int)$pdo->lastInsertId(), $nama, 'Daftar', 'Pendaftaran anggota baru');
            $success = 'Pendaftaran berhasil! Silakan login dengan akun baru Anda.';
        }
    }
}

$pageTitle = 'Daftar Anggota';
require 'includes/head.php';
?>
<section class="center-screen">
  <div class="card form-card">
    <a class="back-link" href="user_check.php">← Kembali</a>
    <span class="eyebrow">Daftar Anggota</span>
    <h2 class="serif">Formulir anggota baru</h2>
    <p class="sub">Lengkapi data untuk membuat akun keanggotaan perpustakaan.</p>
    <?php if ($error): ?><div class="msg msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="msg msg-ok"><i class="fa-solid fa-circle-check"></i> <?= h($success) ?></div>
      <a class="btn btn-primary" style="width:100%;text-align:center" href="user_login.php">Lanjut ke Login</a>
    <?php else: ?>
    <form method="post" action="user_register.php" novalidate>
      <div class="field"><label>Nama lengkap</label><input name="nama" placeholder="Nama siswa" value="<?= h($_POST['nama'] ?? '') ?>" required></div>
      <div class="field"><label>Kelas</label><input name="kelas" placeholder="mis. XII RPL 1" value="<?= h($_POST['kelas'] ?? '') ?>" required></div>
      <div class="field"><label>Buat username</label><input name="username" placeholder="username" minlength="3" value="<?= h($_POST['username'] ?? '') ?>" required></div>
      <div class="field"><label>Buat password</label><input name="password" type="password" placeholder="min. 5 karakter" minlength="5" required></div>
      <button type="submit" class="btn btn-primary" style="width:100%">Daftar &amp; Lanjut ke Login</button>
    </form>
    <?php endif; ?>
  </div>
</section>
<?php require 'includes/foot.php'; ?>
