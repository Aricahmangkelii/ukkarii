<?php
require 'config.php';

if (($_SESSION['role'] ?? null) === 'admin') { header('Location: admin_dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $admin = verifikasi_login($pdo, 'admin', $username, $password);
        if ($admin) {
            $_SESSION['role']       = 'admin';
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_nama'] = $admin['nama'];
            catat_log($pdo, 'admin', $admin['id'], $admin['nama'], 'Login', 'Login berhasil sebagai admin');
            $_SESSION['flash_success'] = 'Login berhasil! Selamat datang, ' . $admin['nama'] . '.';
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Username atau password admin salah. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Login Admin';
require 'includes/head.php';
?>
<section class="center-screen">
  <div class="card form-card">
    <a class="back-link" href="index.php">← Kembali</a>
    <span class="eyebrow">Admin</span>
    <h2 class="serif">Masuk sebagai Admin</h2>
    <p class="sub">Masukkan username &amp; password admin untuk mengelola perpustakaan.</p>
    <?php if ($error): ?><div class="msg msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= h($error) ?></div><?php endif; ?>
    <form method="post" action="admin_login.php" class="needs-validation" novalidate>
      <div class="field"><label>Username</label><input name="username" placeholder="admin" required minlength="3" value="<?= h($_POST['username'] ?? '') ?>"></div>
      <div class="field"><label>Password</label><input name="password" type="password" placeholder="••••••••" required minlength="3"></div>
      <button type="submit" class="btn btn-primary" style="width:100%">Validasi &amp; Masuk</button>
    </form>
    <div class="hint-box">demo: username <b>admin</b> / password <b>admin123</b></div>
  </div>
</section>
<?php require 'includes/foot.php'; ?>
