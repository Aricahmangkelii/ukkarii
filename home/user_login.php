<?php
require 'config.php';

if (($_SESSION['role'] ?? null) === 'siswa') { header('Location: user_dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $anggota = verifikasi_login($pdo, 'anggota', $username, $password);
        if ($anggota) {
            $_SESSION['role']        = 'siswa';
            $_SESSION['siswa_id']    = $anggota['id'];
            $_SESSION['siswa_nama']  = $anggota['nama'];
            $_SESSION['siswa_kelas'] = $anggota['kelas'];
            catat_log($pdo, 'siswa', $anggota['id'], $anggota['nama'], 'Login', 'Login berhasil sebagai siswa');
            $_SESSION['flash_success'] = 'Login berhasil! Selamat datang, ' . $anggota['nama'] . '.';
            header('Location: user_dashboard.php');
            exit;
        } else {
            $error = 'Username atau password tidak ditemukan / salah.';
        }
    }
}

$pageTitle = 'Login Siswa';
require 'includes/head.php';
?>
<section class="center-screen">
  <div class="card form-card">
    <a class="back-link" href="user_check.php">← Kembali</a>
    <span class="eyebrow">Siswa</span>
    <h2 class="serif">Masuk sebagai Anggota</h2>
    <p class="sub">Masukkan username &amp; password keanggotaan Anda.</p>
    <?php if ($error): ?><div class="msg msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?></div><?php endif; ?>
    <form method="post" action="user_login.php" novalidate>
      <div class="field"><label>Username</label><input name="username" placeholder="username" required minlength="3" value="<?= h($_POST['username'] ?? '') ?>"></div>
      <div class="field"><label>Password</label><input name="password" type="password" placeholder="••••••••" required minlength="3"></div>
      <button type="submit" class="btn btn-primary" style="width:100%">Validasi &amp; Masuk</button>
    </form>
    <div class="hint-box">demo: username <b>budi</b> / password <b>budi123</b></div>
  </div>
</section>
<?php require 'includes/foot.php'; ?>
