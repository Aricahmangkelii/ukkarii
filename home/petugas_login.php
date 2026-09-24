<?php
require 'config.php';

if (in_array($_SESSION['role'] ?? null, ['admin', 'petugas'], true)) { header('Location: admin_dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $petugas = verifikasi_login($pdo, 'petugas', $username, $password);
        if ($petugas) {
            $_SESSION['role']       = 'petugas';
            $_SESSION['admin_id']   = $petugas['id'];
            $_SESSION['admin_nama'] = $petugas['nama'];
            catat_log($pdo, 'petugas', $petugas['id'], $petugas['nama'], 'Login', 'Login berhasil sebagai petugas');
            $_SESSION['flash_success'] = 'Login berhasil! Selamat datang, ' . $petugas['nama'] . '.';
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Username atau password petugas salah. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Login Petugas';
require 'includes/head.php';
?>
<section class="center-screen">
  <div class="card form-card">
    <a class="back-link" href="index.php">← Kembali</a>
    <span class="eyebrow">Petugas</span>
    <h2 class="serif">Masuk sebagai Petugas</h2>
    <p class="sub">Masukkan username &amp; password petugas untuk mengelola perpustakaan.</p>
    <?php if ($error): ?><div class="msg msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?></div><?php endif; ?>
    <form method="post" action="petugas_login.php" class="needs-validation" novalidate>
      <div class="field"><label>Username</label><input name="username" placeholder="petugas" required minlength="3" value="<?= h($_POST['username'] ?? '') ?>"></div>
      <div class="field"><label>Password</label><input name="password" type="password" placeholder="••••••••" required minlength="3"></div>
      <button type="submit" class="btn btn-primary" style="width:100%">Validasi &amp; Masuk</button>
    </form>
    <div class="hint-box">demo: username <b>petugas</b> / password <b>petugas123</b></div>
  </div>
</section>
<?php require 'includes/foot.php'; ?>
