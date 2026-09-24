<?php
/**
 * admin_manage.php
 * CRUD akun Admin & Petugas — hanya bisa diakses oleh role admin.
 * Petugas TIDAK bisa mengelola akun admin/petugas lain.
 */
require 'config.php';
wajib_role('admin', 'admin_login.php');
$roleLabel = 'Admin';

$tab = in_array($_GET['tab'] ?? '', ['admin', 'petugas']) ? $_GET['tab'] : 'admin';

/* ============ HANDLE POST ACTIONS ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminId   = $_SESSION['admin_id'];
    $adminNama = $_SESSION['admin_nama'];

    /* ---- CRUD Admin ---- */
    if ($action === 'save_admin') {
        $id       = $_POST['id'] ?? '';
        $nama     = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nama === '' || $username === '' || (!$id && $password === '')) {
            $_SESSION['flash_error'] = 'Nama, username, dan password wajib diisi.';
        } else {
            $cek = $pdo->prepare('SELECT id FROM admin WHERE username = ? AND id <> ?');
            $cek->execute([$username, $id ?: 0]);
            if ($cek->fetch()) {
                $_SESSION['flash_error'] = 'Username sudah dipakai admin lain.';
            } elseif ($id) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE admin SET nama=?, username=?, password=? WHERE id=?');
                    $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE admin SET nama=?, username=? WHERE id=?');
                    $stmt->execute([$nama, $username, $id]);
                }
                catat_log($pdo, 'admin', $adminId, $adminNama, 'Ubah Admin', "Mengubah admin #$id: $nama");
                $_SESSION['flash_success'] = 'Data admin berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO admin (nama, username, password) VALUES (?,?,?)');
                $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT)]);
                catat_log($pdo, 'admin', $adminId, $adminNama, 'Tambah Admin', "Menambah admin: $nama");
                $_SESSION['flash_success'] = 'Admin baru berhasil ditambahkan.';
            }
        }
        header('Location: admin_manage.php?tab=admin'); exit;
    }

    if ($action === 'delete_admin') {
        // Cegah hapus akun sendiri
        if ((int)$_POST['id'] === $adminId) {
            $_SESSION['flash_error'] = 'Anda tidak bisa menghapus akun sendiri.';
        } else {
            $stmt = $pdo->prepare('SELECT nama FROM admin WHERE id = ?');
            $stmt->execute([$_POST['id']]);
            $nama = $stmt->fetchColumn();
            $pdo->prepare('DELETE FROM admin WHERE id = ?')->execute([$_POST['id']]);
            catat_log($pdo, 'admin', $adminId, $adminNama, 'Hapus Admin', "Menghapus admin: $nama");
            $_SESSION['flash_success'] = 'Admin berhasil dihapus.';
        }
        header('Location: admin_manage.php?tab=admin'); exit;
    }

    /* ---- CRUD Petugas ---- */
    if ($action === 'save_petugas') {
        $id       = $_POST['id'] ?? '';
        $nama     = trim($_POST['nama'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($nama === '' || $username === '' || (!$id && $password === '')) {
            $_SESSION['flash_error'] = 'Nama, username, dan password wajib diisi.';
        } else {
            $cek = $pdo->prepare('SELECT id FROM petugas WHERE username = ? AND id <> ?');
            $cek->execute([$username, $id ?: 0]);
            if ($cek->fetch()) {
                $_SESSION['flash_error'] = 'Username sudah dipakai petugas lain.';
            } elseif ($id) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE petugas SET nama=?, username=?, password=? WHERE id=?');
                    $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE petugas SET nama=?, username=? WHERE id=?');
                    $stmt->execute([$nama, $username, $id]);
                }
                catat_log($pdo, 'admin', $adminId, $adminNama, 'Ubah Petugas', "Mengubah petugas #$id: $nama");
                $_SESSION['flash_success'] = 'Data petugas berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO petugas (nama, username, password) VALUES (?,?,?)');
                $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT)]);
                catat_log($pdo, 'admin', $adminId, $adminNama, 'Tambah Petugas', "Menambah petugas: $nama");
                $_SESSION['flash_success'] = 'Petugas baru berhasil ditambahkan.';
            }
        }
        header('Location: admin_manage.php?tab=petugas'); exit;
    }

    if ($action === 'delete_petugas') {
        $stmt = $pdo->prepare('SELECT nama FROM petugas WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        $nama = $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM petugas WHERE id = ?')->execute([$_POST['id']]);
        catat_log($pdo, 'admin', $adminId, $adminNama, 'Hapus Petugas', "Menghapus petugas: $nama");
        $_SESSION['flash_success'] = 'Petugas berhasil dihapus.';
        header('Location: admin_manage.php?tab=petugas'); exit;
    }
}

/* ============ DATA PER TAB ============ */
$formMode = $_GET['form'] ?? null;
$editId   = $_GET['id'] ?? null;

if ($tab === 'admin') {
    $adminList = $pdo->query('SELECT * FROM admin ORDER BY nama')->fetchAll();
    $editRow = null;
    if ($formMode === 'edit' && $editId) {
        $s = $pdo->prepare('SELECT * FROM admin WHERE id = ?');
        $s->execute([$editId]);
        $editRow = $s->fetch();
    }
}

if ($tab === 'petugas') {
    $petugasList = $pdo->query('SELECT * FROM petugas ORDER BY nama')->fetchAll();
    $editRow = null;
    if ($formMode === 'edit' && $editId) {
        $s = $pdo->prepare('SELECT * FROM petugas WHERE id = ?');
        $s->execute([$editId]);
        $editRow = $s->fetch();
    }
}

$flash = ambil_flash();
$pageTitle = 'Kelola Akun';
require 'includes/head.php';
?>
<section>
  <div class="app-shell">
    <aside class="rail">
      <div class="brandmark"><span class="dot"></span><span>Pustaka.Lokal</span></div>
      <div class="rail-user">
        <div class="name"><?= h($_SESSION['admin_nama']) ?></div>
        <div class="role">Dashboard Admin</div>
      </div>
      <nav class="rail-nav">
        <a class="spine-btn" href="admin_dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard Utama</a>
        <a class="spine-btn active" href="admin_manage.php"><i class="fa-solid fa-user-shield"></i> Kelola Akun</a>
        <a class="spine-btn" href="admin_dashboard.php?tab=log"><i class="fa-solid fa-clock-rotate-left"></i> Log Aktivitas</a>
        <a class="spine-btn" href="admin_laporan.php" target="_blank"><i class="fa-solid fa-print"></i> Cetak Laporan</a>
      </nav>
      <div class="rail-foot"><a class="btn btn-ghost" style="width:100%;color:#EFE9DA;border-color:rgba(255,255,255,.25);text-align:center" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a></div>
    </aside>

    <main class="main">
      <div class="main-head">
        <div>
          <div class="breadcrumb">Dashboard Admin <span>/</span> Kelola Akun</div>
          <h2 class="serif">Kelola Akun Admin &amp; Petugas</h2>
          <p>Tambah, ubah, dan hapus akun admin serta petugas perpustakaan.</p>
        </div>
      </div>

      <!-- Tab Switcher -->
      <div class="toolbar" style="margin-bottom:20px">
        <a class="btn <?= $tab==='admin'?'btn-primary':'btn-ghost' ?>" href="admin_manage.php?tab=admin"><i class="fa-solid fa-user-shield"></i> Akun Admin</a>
        <a class="btn <?= $tab==='petugas'?'btn-primary':'btn-ghost' ?>" href="admin_manage.php?tab=petugas"><i class="fa-solid fa-user-tie"></i> Akun Petugas</a>
      </div>

      <?php if ($tab === 'admin'): ?>
        <?php if ($formMode === 'add' || $editRow): ?>
          <div class="card inline-form-card">
            <h3 class="serif"><?= $editRow ? 'Ubah Admin' : 'Tambah Admin' ?></h3>
            <form method="post" action="admin_manage.php" novalidate>
              <input type="hidden" name="action" value="save_admin">
              <input type="hidden" name="id" value="<?= h($editRow['id'] ?? '') ?>">
              <div class="field"><label>Nama Lengkap</label><input name="nama" value="<?= h($editRow['nama'] ?? '') ?>" required></div>
              <div class="grid-2">
                <div class="field"><label>Username</label><input name="username" value="<?= h($editRow['username'] ?? '') ?>" required></div>
                <div class="field"><label>Password<?= $editRow ? ' (kosongkan jika tidak diubah)' : '' ?></label><input name="password" type="password" <?= $editRow ? '' : 'required' ?>></div>
              </div>
              <div class="form-actions">
                <a class="btn btn-ghost" href="admin_manage.php?tab=admin">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="toolbar">
            <span style="color:var(--ink-soft);font-size:13px"><?= count($adminList) ?> akun admin terdaftar</span>
            <a class="btn btn-primary btn-sm" href="admin_manage.php?tab=admin&form=add"><i class="fa-solid fa-plus"></i> Tambah Admin</a>
          </div>
          <table>
            <thead><tr><th>Nama</th><th>Username</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if (empty($adminList)): ?>
                <tr class="empty-row"><td colspan="4">Belum ada akun admin.</td></tr>
              <?php else: foreach ($adminList as $a): ?>
                <tr>
                  <td><strong><?= h($a['nama']) ?></strong></td>
                  <td><span class="mono"><?= h($a['username']) ?></span></td>
                  <td class="mono"><?= h($a['created_at']) ?></td>
                  <td class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="admin_manage.php?tab=admin&form=edit&id=<?= $a['id'] ?>">Ubah</a>
                    <?php if ((int)$a['id'] !== $adminId): ?>
                    <form method="post" action="admin_manage.php" class="js-confirm-delete" data-confirm-msg="Hapus admin &quot;<?= h($a['nama']) ?>&quot;?" style="display:inline">
                      <input type="hidden" name="action" value="delete_admin">
                      <input type="hidden" name="id" value="<?= $a['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                    <?php else: ?>
                      <span class="muted" style="font-size:11px">akun sendiri</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($tab === 'petugas'): ?>
        <?php if ($formMode === 'add' || $editRow): ?>
          <div class="card inline-form-card">
            <h3 class="serif"><?= $editRow ? 'Ubah Petugas' : 'Tambah Petugas' ?></h3>
            <form method="post" action="admin_manage.php" novalidate>
              <input type="hidden" name="action" value="save_petugas">
              <input type="hidden" name="id" value="<?= h($editRow['id'] ?? '') ?>">
              <div class="field"><label>Nama Lengkap</label><input name="nama" value="<?= h($editRow['nama'] ?? '') ?>" required></div>
              <div class="grid-2">
                <div class="field"><label>Username</label><input name="username" value="<?= h($editRow['username'] ?? '') ?>" required></div>
                <div class="field"><label>Password<?= $editRow ? ' (kosongkan jika tidak diubah)' : '' ?></label><input name="password" type="password" <?= $editRow ? '' : 'required' ?>></div>
              </div>
              <div class="form-actions">
                <a class="btn btn-ghost" href="admin_manage.php?tab=petugas">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="toolbar">
            <span style="color:var(--ink-soft);font-size:13px"><?= count($petugasList) ?> akun petugas terdaftar</span>
            <a class="btn btn-primary btn-sm" href="admin_manage.php?tab=petugas&form=add"><i class="fa-solid fa-plus"></i> Tambah Petugas</a>
          </div>
          <table>
            <thead><tr><th>Nama</th><th>Username</th><th>Dibuat</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if (empty($petugasList)): ?>
                <tr class="empty-row"><td colspan="4">Belum ada akun petugas.</td></tr>
              <?php else: foreach ($petugasList as $p): ?>
                <tr>
                  <td><strong><?= h($p['nama']) ?></strong></td>
                  <td><span class="mono"><?= h($p['username']) ?></span></td>
                  <td class="mono"><?= h($p['created_at']) ?></td>
                  <td class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="admin_manage.php?tab=petugas&form=edit&id=<?= $p['id'] ?>">Ubah</a>
                    <form method="post" action="admin_manage.php" class="js-confirm-delete" data-confirm-msg="Hapus petugas &quot;<?= h($p['nama']) ?>&quot;?" style="display:inline">
                      <input type="hidden" name="action" value="delete_petugas">
                      <input type="hidden" name="id" value="<?= $p['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
</section>
<script>window.__flash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require 'includes/foot.php'; ?>
