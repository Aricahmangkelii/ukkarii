<?php
require 'config.php';
wajib_role(['admin', 'petugas'], 'admin_login.php');
$roleLabel = ($_SESSION['role'] === 'petugas') ? 'Petugas' : 'Admin';

$tab = in_array($_GET['tab'] ?? '', ['persetujuan','buku','transaksi','anggota','kategori','log']) ? $_GET['tab'] : 'persetujuan';

/* ============ HANDLE POST ACTIONS (Tambah/Ubah/Hapus) ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $adminId   = $_SESSION['admin_id'];
    $adminNama = $_SESSION['admin_nama'];

    if ($action === 'save_buku') {
        $id      = $_POST['id'] ?? '';
        $judul   = trim($_POST['judul'] ?? '');
        $penulis = trim($_POST['penulis'] ?? '');
        $katId   = $_POST['kategori_id'] !== '' ? (int)$_POST['kategori_id'] : null;
        $stok    = (int)($_POST['stok'] ?? 0);
        if ($judul === '' || $penulis === '') {
            $_SESSION['flash_error'] = 'Judul dan penulis wajib diisi.';
        } else {
            if ($id) {
                $stmt = $pdo->prepare('UPDATE buku SET judul=?, penulis=?, kategori_id=?, stok=? WHERE id=?');
                $stmt->execute([$judul, $penulis, $katId, $stok, $id]);
                catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Ubah Buku', "Mengubah buku #$id: $judul");
                $_SESSION['flash_success'] = 'Buku berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO buku (judul, penulis, kategori_id, stok) VALUES (?,?,?,?)');
                $stmt->execute([$judul, $penulis, $katId, $stok]);
                catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Tambah Buku', "Menambah buku: $judul");
                $_SESSION['flash_success'] = 'Buku baru berhasil ditambahkan.';
            }
        }
        header('Location: admin_dashboard.php?tab=buku'); exit;
    }

    if ($action === 'delete_buku') {
        $stmt = $pdo->prepare('SELECT judul FROM buku WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        $judul = $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM buku WHERE id = ?')->execute([$_POST['id']]);
        catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Hapus Buku', "Menghapus buku: $judul");
        $_SESSION['flash_success'] = 'Buku berhasil dihapus.';
        header('Location: admin_dashboard.php?tab=buku'); exit;
    }

    if ($action === 'save_kategori') {
        $id   = $_POST['id'] ?? '';
        $nama = trim($_POST['nama_kategori'] ?? '');
        if ($nama === '') {
            $_SESSION['flash_error'] = 'Nama kategori wajib diisi.';
        } else {
            try {
                if ($id) {
                    $pdo->prepare('UPDATE kategori SET nama_kategori=? WHERE id=?')->execute([$nama, $id]);
                    catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Ubah Kategori', "Mengubah kategori #$id: $nama");
                } else {
                    $pdo->prepare('INSERT INTO kategori (nama_kategori) VALUES (?)')->execute([$nama]);
                    catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Tambah Kategori', "Menambah kategori: $nama");
                }
                $_SESSION['flash_success'] = 'Kategori berhasil disimpan.';
            } catch (PDOException $e) {
                $_SESSION['flash_error'] = 'Nama kategori sudah ada / gagal disimpan.';
            }
        }
        header('Location: admin_dashboard.php?tab=kategori'); exit;
    }

    if ($action === 'delete_kategori') {
        $stmt = $pdo->prepare('SELECT nama_kategori FROM kategori WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        $nama = $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM kategori WHERE id = ?')->execute([$_POST['id']]);
        catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Hapus Kategori', "Menghapus kategori: $nama");
        $_SESSION['flash_success'] = 'Kategori berhasil dihapus. Buku terkait menjadi \"Tanpa Kategori\".';
        header('Location: admin_dashboard.php?tab=kategori'); exit;
    }

    if ($action === 'save_anggota') {
        $id       = $_POST['id'] ?? '';
        $nama     = trim($_POST['nama'] ?? '');
        $kelas    = trim($_POST['kelas'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($nama === '' || $username === '' || (!$id && $password === '')) {
            $_SESSION['flash_error'] = 'Nama, username, dan password wajib diisi.';
        } else {
            $cek = $pdo->prepare('SELECT id FROM anggota WHERE username = ? AND id <> ?');
            $cek->execute([$username, $id ?: 0]);
            if ($cek->fetch()) {
                $_SESSION['flash_error'] = 'Username sudah dipakai anggota lain.';
            } elseif ($id) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE anggota SET nama=?, kelas=?, username=?, password=? WHERE id=?');
                    $stmt->execute([$nama, $kelas, $username, password_hash($password, PASSWORD_DEFAULT), $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE anggota SET nama=?, kelas=?, username=? WHERE id=?');
                    $stmt->execute([$nama, $kelas, $username, $id]);
                }
                catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Ubah Anggota', "Mengubah anggota #$id: $nama");
                $_SESSION['flash_success'] = 'Data anggota berhasil diperbarui.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO anggota (nama, kelas, username, password) VALUES (?,?,?,?)');
                $stmt->execute([$nama, $kelas, $username, password_hash($password, PASSWORD_DEFAULT)]);
                catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Tambah Anggota', "Menambah anggota: $nama");
                $_SESSION['flash_success'] = 'Anggota baru berhasil ditambahkan.';
            }
        }
        header('Location: admin_dashboard.php?tab=anggota'); exit;
    }

    if ($action === 'delete_anggota') {
        $stmt = $pdo->prepare('SELECT nama FROM anggota WHERE id = ?');
        $stmt->execute([$_POST['id']]);
        $nama = $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM anggota WHERE id = ?')->execute([$_POST['id']]);
        catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Hapus Anggota', "Menghapus anggota: $nama");
        $_SESSION['flash_success'] = 'Anggota berhasil dihapus.';
        header('Location: admin_dashboard.php?tab=anggota'); exit;
    }

    /* ====== BARU: Aksi persetujuan peminjaman ====== */
    if ($action === 'setujui_pinjam') {
        $transId = (int)($_POST['id'] ?? 0);
        // Verifikasi stok sebelum menyetujui (bisa saja stok habis saat menunggu)
        $stmt = $pdo->prepare("SELECT t.id, t.buku_id, b.judul, b.stok, a.nama AS nama_anggota, t.durasi_pinjam
            FROM transaksi t JOIN buku b ON b.id = t.buku_id JOIN anggota a ON a.id = t.anggota_id
            WHERE t.id = ? AND t.status = 'Menunggu Persetujuan'");
        $stmt->execute([$transId]);
        $tr = $stmt->fetch();
        if (!$tr) {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan atau sudah diproses.';
        } elseif ($tr['stok'] <= 0) {
            // Stok habis saat menunggu -> tolak otomatis
            $pdo->prepare("UPDATE transaksi SET status = 'Ditolak' WHERE id = ?")->execute([$transId]);
            catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Tolak Pinjam (Stok Habis)', "Transaksi #$transId: {$tr['judul']} - stok habis saat akan disetujui");
            $_SESSION['flash_error'] = 'Stok buku sudah habis. Permohonan otomatis ditolak.';
        } else {
            $pdo->prepare("UPDATE transaksi SET status = 'Dipinjam' WHERE id = ? AND status = 'Menunggu Persetujuan'")->execute([$transId]);
            catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Setujui Pinjam', "Menyetujui transaksi #$transId: {$tr['judul']} oleh {$tr['nama_anggota']} ({$tr['durasi_pinjam']} hari)");
            $_SESSION['flash_success'] = 'Peminjaman buku \"' . $tr['judul'] . '\" disetujui untuk ' . $tr['nama_anggota'] . '.';
        }
        header('Location: admin_dashboard.php?tab=persetujuan'); exit;
    }

    if ($action === 'tolak_pinjam') {
        $transId = (int)($_POST['id'] ?? 0);
        $alasan  = trim($_POST['alasan'] ?? 'Tidak disetujui admin.');
        $stmt = $pdo->prepare("SELECT t.id, b.judul, a.nama AS nama_anggota FROM transaksi t JOIN buku b ON b.id = t.buku_id JOIN anggota a ON a.id = t.anggota_id WHERE t.id = ? AND t.status = 'Menunggu Persetujuan'");
        $stmt->execute([$transId]);
        $tr = $stmt->fetch();
        if ($tr) {
            $pdo->prepare("UPDATE transaksi SET status = 'Ditolak' WHERE id = ?")->execute([$transId]);
            catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Tolak Pinjam', "Menolak transaksi #$transId: {$tr['judul']} oleh {$tr['nama_anggota']}. Alasan: $alasan");
            $_SESSION['flash_success'] = 'Permohonan peminjaman ditolak.';
        } else {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan atau sudah diproses.';
        }
        header('Location: admin_dashboard.php?tab=persetujuan'); exit;
    }

    /* ====== BARU: Konfirmasi pengembalian oleh admin ====== */
    if ($action === 'konfirmasi_kembali') {
        $transId = (int)($_POST['id'] ?? 0);
        // Hitung denda otomatis berdasarkan batas_kembali
        $stmt = $pdo->prepare("SELECT t.*, b.judul FROM transaksi t JOIN buku b ON b.id = t.buku_id WHERE t.id = ? AND t.status = 'Dipinjam'");
        $stmt->execute([$transId]);
        $tr = $stmt->fetch();
        if ($tr) {
            $denda = 0;
            if ($tr['batas_kembali']) {
                $hariTerlambat = (strtotime(date('Y-m-d')) - strtotime($tr['batas_kembali'])) / 86400;
                if ($hariTerlambat > 0) {
                    $denda = (int)ceil($hariTerlambat) * DENDA_PER_HARI;
                }
            }
            $pdo->prepare("UPDATE transaksi SET status = 'Dikembalikan', tanggal_kembali = CURDATE(), denda = ? WHERE id = ? AND status = 'Dipinjam'")
                ->execute([$denda, $transId]);
            catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Konfirmasi Kembali', "Transaksi #$transId: {$tr['judul']}" . ($denda > 0 ? ", denda Rp $denda" : ', tepat waktu'));
            $_SESSION['flash_success'] = 'Pengembalian buku \"' . $tr['judul'] . '\" dikonfirmasi.' . ($denda > 0 ? ' Denda: <b>Rp ' . number_format($denda, 0, ',', '.') . '</b> (' . (int)ceil($hariTerlambat) . ' hari terlambat).' : ' Tepat waktu.');
        } else {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan atau sudah dikembalikan.';
        }
        header('Location: admin_dashboard.php?tab=transaksi'); exit;
    }

    // Legacy: tandai_kembali (masih didukung untuk kompatibilitas)
    if ($action === 'tandai_kembali') {
        $transId = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT t.*, b.judul FROM transaksi t JOIN buku b ON b.id = t.buku_id WHERE t.id = ? AND t.status = 'Dipinjam'");
        $stmt->execute([$transId]);
        $tr = $stmt->fetch();
        if ($tr) {
            $denda = 0;
            if ($tr['batas_kembali']) {
                $hariTerlambat = (strtotime(date('Y-m-d')) - strtotime($tr['batas_kembali'])) / 86400;
                if ($hariTerlambat > 0) { $denda = (int)ceil($hariTerlambat) * DENDA_PER_HARI; }
            }
            $pdo->prepare("UPDATE transaksi SET status = 'Dikembalikan', tanggal_kembali = CURDATE(), denda = ? WHERE id = ? AND status = 'Dipinjam'")
                ->execute([$denda, $transId]);
            catat_log($pdo, $_SESSION['role'], $adminId, $adminNama, 'Tandai Kembali', "Transaksi #$transId dikonfirmasi dikembalikan" . ($denda > 0 ? ", denda Rp $denda" : ''));
            $_SESSION['flash_success'] = 'Transaksi dikonfirmasi dikembalikan.' . ($denda > 0 ? ' Denda: Rp ' . number_format($denda, 0, ',', '.') : '');
        }
        header('Location: admin_dashboard.php?tab=transaksi'); exit;
    }
}

$q = trim($_GET['q'] ?? '');
$formMode = $_GET['form'] ?? null;
$editId   = $_GET['id'] ?? null;

/* ============ STATS ============ */
$totalBuku       = $pdo->query('SELECT COUNT(*) c FROM buku')->fetch()['c'];
$totalPinjam     = $pdo->query("SELECT COUNT(*) c FROM transaksi WHERE status='Dipinjam'")->fetch()['c'];
$totalAnggota    = $pdo->query('SELECT COUNT(*) c FROM anggota')->fetch()['c'];
$totalKategori   = $pdo->query('SELECT COUNT(*) c FROM kategori')->fetch()['c'];
$totalMenunggu   = $pdo->query("SELECT COUNT(*) c FROM transaksi WHERE status='Menunggu Persetujuan'")->fetch()['c'];
$totalTerlambat = $pdo->query("SELECT COUNT(*) c FROM transaksi WHERE status='Dipinjam' AND batas_kembali < CURDATE()")->fetch()['c'];

/* Data untuk grafik dashboard (Chart.js) */
$rekapKategori = $pdo->query('SELECT * FROM view_rekap_kategori ORDER BY jumlah_judul DESC')->fetchAll();

$semuaKategori = $pdo->query('SELECT * FROM kategori ORDER BY nama_kategori')->fetchAll();

/* Data notifikasi denda: semua peminjaman yang terlambat */
$terlambatList = [];
if ($totalTerlambat > 0) {
    $stmtTerlambat = $pdo->query("
        SELECT t.id, t.batas_kembali, t.tanggal_pinjam, t.durasi_pinjam,
               b.judul AS judul_buku, b.penulis,
               a.nama AS nama_anggota, a.kelas AS kelas_anggota,
               DATEDIFF(CURDATE(), t.batas_kembali) AS hari_terlambat,
               DATEDIFF(CURDATE(), t.batas_kembali) * " . DENDA_PER_HARI . " AS denda_terhitung
        FROM transaksi t
        JOIN buku b ON b.id = t.buku_id
        JOIN anggota a ON a.id = t.anggota_id
        WHERE t.status = 'Dipinjam' AND t.batas_kembali < CURDATE()
        ORDER BY t.batas_kembali ASC
    ");
    $terlambatList = $stmtTerlambat->fetchAll();
}
$totalDendaTerlambat = 0;
foreach ($terlambatList as $tl) {
    $totalDendaTerlambat += (int)$tl['denda_terhitung'];
}

/* ============ DATA PER TAB ============ */

if ($tab === 'persetujuan') {
    // Daftar permohonan yang menunggu persetujuan
    $stmt = $pdo->prepare("
        SELECT t.*, b.judul AS judul_buku, b.stok AS stok_buku, k.nama_kategori,
               a.nama AS nama_anggota, a.kelas AS kelas_anggota
        FROM transaksi t
        JOIN buku b ON b.id = t.buku_id
        JOIN anggota a ON a.id = t.anggota_id
        LEFT JOIN kategori k ON k.id = b.kategori_id
        WHERE t.status = 'Menunggu Persetujuan'
        ORDER BY t.id DESC
    ");
    $stmt->execute();
    $menungguList = $stmt->fetchAll();

    // Juga tampilkan yang sudah diproses (disetujui/ditolak) hari ini
    $stmt2 = $pdo->prepare("
        SELECT t.*, b.judul AS judul_buku, k.nama_kategori,
               a.nama AS nama_anggota, a.kelas AS kelas_anggota
        FROM transaksi t
        JOIN buku b ON b.id = t.buku_id
        JOIN anggota a ON a.id = t.anggota_id
        LEFT JOIN kategori k ON k.id = b.kategori_id
        WHERE t.status IN ('Dipinjam','Ditolak')
        ORDER BY
            CASE WHEN t.status = 'Ditolak' THEN 1 ELSE 0 END,
            t.id DESC
        LIMIT 50
    ");
    $stmt2->execute();
    $diprosesList = $stmt2->fetchAll();
}

if ($tab === 'buku') {
    $stmt = $pdo->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b LEFT JOIN kategori k ON k.id = b.kategori_id
        WHERE b.judul LIKE ? OR b.penulis LIKE ? OR k.nama_kategori LIKE ?
        ORDER BY b.judul
    ");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
    $bukuList = $stmt->fetchAll();

    $editBuku = null;
    if ($formMode === 'edit' && $editId) {
        $s = $pdo->prepare('SELECT * FROM buku WHERE id = ?');
        $s->execute([$editId]);
        $editBuku = $s->fetch();
    }
}

if ($tab === 'kategori') {
    $stmt = $pdo->prepare('SELECT k.*, (SELECT COUNT(*) FROM buku b WHERE b.kategori_id = k.id) AS jumlah_buku FROM kategori k WHERE k.nama_kategori LIKE ? ORDER BY k.nama_kategori');
    $stmt->execute(["%$q%"]);
    $kategoriList = $stmt->fetchAll();

    $editKategori = null;
    if ($formMode === 'edit' && $editId) {
        $s = $pdo->prepare('SELECT * FROM kategori WHERE id = ?');
        $s->execute([$editId]);
        $editKategori = $s->fetch();
    }
}

if ($tab === 'anggota') {
    $stmt = $pdo->prepare("SELECT * FROM anggota WHERE nama LIKE ? OR kelas LIKE ? OR username LIKE ? ORDER BY nama");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
    $anggotaList = $stmt->fetchAll();

    $editAnggota = null;
    if ($formMode === 'edit' && $editId) {
        $s = $pdo->prepare('SELECT * FROM anggota WHERE id = ?');
        $s->execute([$editId]);
        $editAnggota = $s->fetch();
    }
}

if ($tab === 'transaksi') {
    $stmt = $pdo->prepare("SELECT * FROM view_laporan_transaksi WHERE nama_anggota LIKE ? OR judul_buku LIKE ? ORDER BY id DESC");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    $transaksiList = $stmt->fetchAll();
}

if ($tab === 'log') {
    $stmt = $pdo->prepare("SELECT * FROM log_aktivitas WHERE nama_user LIKE ? OR aksi LIKE ? ORDER BY id DESC LIMIT 200");
    $like = "%$q%";
    $stmt->execute([$like, $like]);
    $logList = $stmt->fetchAll();
}

$flash = ambil_flash();
$pageTitle = 'Dashboard ' . $roleLabel;
$breadcrumbs = [$pageTitle => null];
require 'includes/head.php';

function qs($params) {
    return '?' . http_build_query($params);
}

/** Helper render badge status */
function render_status($status) {
    if ($status === 'Menunggu Persetujuan') return '<span class="badge badge-waiting">Menunggu Persetujuan</span>';
    if ($status === 'Ditolak') return '<span class="badge badge-rejected">Ditolak</span>';
    if ($status === 'Dipinjam') return '<span class="badge badge-out">Dipinjam</span>';
    if ($status === 'Dikembalikan') return '<span class="badge badge-in">Dikembalikan</span>';
    return h($status);
}
?>
<section>
  <div class="app-shell">
    <aside class="rail">
      <div class="brandmark"><span class="dot"></span><span>Pustaka.Lokal</span></div>
      <div class="rail-user">
        <div class="name"><?= h($_SESSION['admin_nama']) ?></div>
        <div class="role">Dashboard <?= h($roleLabel) ?></div>
      </div>
      <nav class="rail-nav">
        <?php if ($totalMenunggu > 0): ?>
        <a class="spine-btn <?= $tab==='persetujuan'?'active':'' ?>" href="admin_dashboard.php?tab=persetujuan" style="position:relative">
          <i class="fa-solid fa-clock"></i> Persetujuan
          <span class="nav-badge"><?= $totalMenunggu ?></span>
        </a>
        <?php else: ?>
        <a class="spine-btn <?= $tab==='persetujuan'?'active':'' ?>" href="admin_dashboard.php?tab=persetujuan"><i class="fa-solid fa-clock"></i> Persetujuan</a>
        <?php endif; ?>
        <a class="spine-btn <?= $tab==='buku'?'active':'' ?>" href="admin_dashboard.php?tab=buku"><i class="fa-solid fa-book"></i> Kelola Data Buku</a>
        <a class="spine-btn <?= $tab==='kategori'?'active':'' ?>" href="admin_dashboard.php?tab=kategori"><i class="fa-solid fa-tags"></i> Kelola Kategori</a>
        <a class="spine-btn <?= $tab==='transaksi'?'active':'' ?>" href="admin_dashboard.php?tab=transaksi" style="position:relative"><i class="fa-solid fa-right-left"></i> Transaksi<?php if ($totalTerlambat > 0): ?> <span class="nav-badge notif-pulse" style="background:#D97706"><?= $totalTerlambat ?></span><?php endif; ?></a>
        <a class="spine-btn <?= $tab==='anggota'?'active':'' ?>" href="admin_dashboard.php?tab=anggota"><i class="fa-solid fa-users"></i> Kelola Anggota</a>
        <a class="spine-btn <?= $tab==='log'?'active':'' ?>" href="admin_dashboard.php?tab=log"><i class="fa-solid fa-clock-rotate-left"></i> Log Aktivitas</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="spine-btn" href="admin_manage.php"><i class="fa-solid fa-user-shield"></i> Kelola Akun</a>
        <?php endif; ?>
        <a class="spine-btn" href="admin_laporan.php"><i class="fa-solid fa-print"></i> Laporan</a>
        <a class="spine-btn" href="help.php" target="_blank"><i class="fa-solid fa-circle-question"></i> Bantuan</a>
      </nav>
      <div class="rail-foot"><a class="btn btn-ghost" style="width:100%;color:#EFE9DA;border-color:rgba(255,255,255,.25);text-align:center" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a></div>
    </aside>

    <main class="main">
      <div class="main-head">
        <div>
          <div class="breadcrumb">Dashboard <?= h($roleLabel) ?> <span>/</span> <?= $tab==='persetujuan' ? 'Persetujuan' : ucfirst($tab) ?></div>
          <?php if ($tab==='persetujuan'): ?>
            <h2 class="serif">Persetujuan Peminjaman</h2><p>Kelola permohonan peminjaman buku dari anggota. Setujui atau tolak permintaan.</p>
          <?php elseif ($tab==='buku'): ?>
            <h2 class="serif">Kelola Data Buku</h2><p>Tambah, ubah, dan hapus koleksi buku perpustakaan.</p>
          <?php elseif ($tab==='kategori'): ?>
            <h2 class="serif">Kelola Kategori</h2><p>Kelompokkan koleksi buku berdasarkan kategori.</p>
          <?php elseif ($tab==='transaksi'): ?>
            <h2 class="serif">Transaksi Peminjaman</h2><p>Pantau seluruh peminjaman &amp; pengembalian buku. Konfirmasi pengembalian &amp; hitung denda keterlambatan.</p>
          <?php elseif ($tab==='log'): ?>
            <h2 class="serif">Log Aktivitas</h2><p>Riwayat aksi admin, petugas &amp; siswa di sistem.</p>
          <?php else: ?>
            <h2 class="serif">Kelola Anggota</h2><p>Kelola data anggota perpustakaan.</p>
          <?php endif; ?>
        </div>
        <div class="stat-grid" style="margin:0">
          <div class="card stat-card"><div class="n"><?= $totalBuku ?></div><div class="l">Judul Buku</div></div>
          <div class="card stat-card"><div class="n"><?= $totalPinjam ?></div><div class="l">Sedang Dipinjam</div></div>
          <?php if ($totalTerlambat > 0): ?>
          <div class="card stat-card stat-terlambat"><div class="n"><?= $totalTerlambat ?></div><div class="l">Terlambat (Ada Denda)</div></div>
          <?php endif; ?>
          <?php if ($totalMenunggu > 0): ?>
          <div class="card stat-card" style="border-color:var(--brass)"><div class="n" style="color:var(--brass)"><?= $totalMenunggu ?></div><div class="l">Menunggu Persetujuan</div></div>
          <?php endif; ?>
          <div class="card stat-card"><div class="n"><?= $totalAnggota ?></div><div class="l">Anggota</div></div>
          <div class="card stat-card"><div class="n"><?= $totalKategori ?></div><div class="l">Kategori</div></div>
        </div>
      </div>

      <?php if (!empty($terlambatList)): ?>
      <!-- NOTIFIKASI DENDA TERLAMBAT (ADMIN VIEW) -->
      <div class="notif-denda-banner admin-notif" id="notifDendaBanner">
        <div class="notif-denda-header open" onclick="toggleNotifDenda()">
          <div class="notif-icon"><i class="fa-solid fa-clock"></i></div>
          <div>
            <div class="notif-title"><?= count($terlambatList) ?> Peminjaman Terlambat Perlu Ditindaklanjuti</div>
            <div class="notif-subtitle">Total akumulasi denda saat ini: <strong>Rp <?= number_format($totalDendaTerlambat, 0, ',', '.') ?></strong></div>
          </div>
          <div class="notif-chevron"><i class="fa-solid fa-chevron-down"></i></div>
        </div>
        <div class="notif-denda-body open" id="notifDendaBody">
          <div class="notif-denda-list">
            <?php foreach ($terlambatList as $tl): ?>
            <div class="notif-denda-item">
              <div class="ndi-icon"><i class="fa-solid fa-user"></i></div>
              <div class="ndi-info">
                <div class="ndi-judul"><?= h($tl['nama_anggota']) ?> (<?= h($tl['kelas_anggota']) ?>) — <?= h($tl['judul_buku']) ?></div>
                <div class="ndi-detail">
                  Batas kembali: <strong><?= h($tl['batas_kembali']) ?></strong> &middot;
                  Terlambat <strong><?= (int)$tl['hari_terlambat'] ?> hari</strong>
                </div>
              </div>
              <div class="ndi-denda">
                <div class="ndi-amount">Rp <?= number_format((int)$tl['denda_terhitung'], 0, ',', '.') ?></div>
                <div class="ndi-label">Denda saat ini</div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="notif-denda-footer">
            <div class="ndf-total-label">Total akumulasi denda (<?= count($terlambatList) ?> peminjaman):</div>
            <div class="ndf-total-amount">Rp <?= number_format($totalDendaTerlambat, 0, ',', '.') ?></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($tab === 'persetujuan'): ?>
        <!-- ========== TAB PERSETUJUAN ========== -->
        <h3 class="serif" style="margin-bottom:8px"><i class="fa-solid fa-hourglass-half" style="color:var(--brass)"></i> Menunggu Persetujuan (<?= count($menungguList) ?>)</h3>
        <?php if (empty($menungguList)): ?>
          <div class="card" style="padding:32px;text-align:center;opacity:.6"><p>Tidak ada permohonan yang menunggu persetujuan.</p></div>
        <?php else: ?>
        <table>
          <thead><tr><th>Anggota</th><th>Kelas</th><th>Buku</th><th>Durasi</th><th>Batas Kembali</th><th>Stok</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($menungguList as $t): ?>
              <tr>
                <td><strong><?= h($t['nama_anggota']) ?></strong></td>
                <td><?= h($t['kelas_anggota']) ?></td>
                <td><?= h($t['judul_buku']) ?></td>
                <td><?= (int)$t['durasi_pinjam'] ?> hari</td>
                <td><?= h($t['batas_kembali']) ?></td>
                <td><?= $t['stok_buku'] <= 1 ? '<span class="badge badge-stok-low">' . (int)$t['stok_buku'] . '</span>' : (int)$t['stok_buku'] ?></td>
                <td class="row-actions">
                  <form method="post" action="admin_dashboard.php" style="display:inline">
                    <input type="hidden" name="action" value="setujui_pinjam">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm" <?= $t['stok_buku']<=0?'disabled title="Stok habis"':'' ?>>Setujui</button>
                  </form>
                  <button type="button" class="btn btn-danger btn-sm" onclick="tolakPinjam(<?= $t['id'] ?>, '<?= h(addslashes($t['judul_buku'])) ?>', '<?= h(addslashes($t['nama_anggota'])) ?>')">Tolak</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

        <h3 class="serif" style="margin:24px 0 8px"><i class="fa-solid fa-check-double" style="color:var(--ok)"></i> Sudah Diproses</h3>
        <?php if (empty($diprosesList)): ?>
          <div class="card" style="padding:24px;text-align:center;opacity:.6"><p>Belum ada permohonan yang diproses.</p></div>
        <?php else: ?>
        <table>
          <thead><tr><th>Anggota</th><th>Kelas</th><th>Buku</th><th>Durasi</th><th>Batas Kembali</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($diprosesList as $t): ?>
              <tr>
                <td><strong><?= h($t['nama_anggota']) ?></strong></td>
                <td><?= h($t['kelas_anggota']) ?></td>
                <td><?= h($t['judul_buku']) ?></td>
                <td><?= (int)$t['durasi_pinjam'] ?> hari</td>
                <td><?= h($t['batas_kembali'] ?? '—') ?></td>
                <td><?= render_status($t['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>

      <?php endif; ?>

      <?php if ($tab === 'buku'): ?>
        <div class="card chart-card">
          <h3 class="serif" style="margin-top:0">Koleksi Buku per Kategori</h3>
          <canvas id="chartKategori" height="90"></canvas>
        </div>

        <?php if ($formMode === 'add' || $editBuku): ?>
          <div class="card inline-form-card">
            <h3 class="serif"><?= $editBuku ? 'Ubah Buku' : 'Tambah Buku' ?></h3>
            <form method="post" action="admin_dashboard.php" novalidate>
              <input type="hidden" name="action" value="save_buku">
              <input type="hidden" name="id" value="<?= h($editBuku['id'] ?? '') ?>">
              <div class="field"><label>Judul</label><input name="judul" value="<?= h($editBuku['judul'] ?? '') ?>" required></div>
              <div class="field"><label>Penulis</label><input name="penulis" value="<?= h($editBuku['penulis'] ?? '') ?>" required></div>
              <div class="grid-2">
                <div class="field">
                  <label>Kategori</label>
                  <select name="kategori_id">
                    <option value="">— Tanpa kategori —</option>
                    <?php foreach ($semuaKategori as $k): ?>
                      <option value="<?= $k['id'] ?>" <?= (($editBuku['kategori_id'] ?? null) == $k['id']) ? 'selected' : '' ?>><?= h($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field"><label>Stok</label><input name="stok" type="number" min="0" value="<?= h($editBuku['stok'] ?? 1) ?>"></div>
              </div>
              <div class="form-actions">
                <a class="btn btn-ghost" href="admin_dashboard.php?tab=buku">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="toolbar">
            <form class="search-box" method="get" action="admin_dashboard.php">
              <input type="hidden" name="tab" value="buku">
              <input type="text" name="q" placeholder="Cari judul / penulis / kategori..." value="<?= h($q) ?>">
            </form>
            <a class="btn btn-primary btn-sm" href="admin_dashboard.php?tab=buku&form=add"><i class="fa-solid fa-plus"></i> Tambah Buku</a>
          </div>
          <table>
            <thead><tr><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if (empty($bukuList)): ?>
                <tr class="empty-row"><td colspan="5">Tidak ada buku yang cocok.</td></tr>
              <?php else: foreach ($bukuList as $b): ?>
                <tr>
                  <td><strong><?= h($b['judul']) ?></strong></td>
                  <td><?= h($b['penulis']) ?></td>
                  <td><?= $b['nama_kategori'] ? '<span class="badge badge-kategori">'.h($b['nama_kategori']).'</span>' : '<span class="muted">—</span>' ?></td>
                  <td><?= $b['stok']==0 ? '<span class="badge badge-stok-low">Habis</span>' : (int)$b['stok'] ?></td>
                  <td class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="admin_dashboard.php?tab=buku&form=edit&id=<?= $b['id'] ?>">Ubah</a>
                    <form method="post" action="admin_dashboard.php" class="js-confirm-delete" data-confirm-msg="Hapus buku &quot;<?= h($b['judul']) ?>&quot; dari koleksi?" style="display:inline">
                      <input type="hidden" name="action" value="delete_buku">
                      <input type="hidden" name="id" value="<?= $b['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($tab === 'kategori'): ?>
        <?php if ($formMode === 'add' || $editKategori): ?>
          <div class="card inline-form-card">
            <h3 class="serif"><?= $editKategori ? 'Ubah Kategori' : 'Tambah Kategori' ?></h3>
            <form method="post" action="admin_dashboard.php" novalidate>
              <input type="hidden" name="action" value="save_kategori">
              <input type="hidden" name="id" value="<?= h($editKategori['id'] ?? '') ?>">
              <div class="field"><label>Nama Kategori</label><input name="nama_kategori" value="<?= h($editKategori['nama_kategori'] ?? '') ?>" required></div>
              <div class="form-actions">
                <a class="btn btn-ghost" href="admin_dashboard.php?tab=kategori">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="toolbar">
            <form class="search-box" method="get" action="admin_dashboard.php">
              <input type="hidden" name="tab" value="kategori">
              <input type="text" name="q" placeholder="Cari kategori..." value="<?= h($q) ?>">
            </form>
            <a class="btn btn-primary btn-sm" href="admin_dashboard.php?tab=kategori&form=add"><i class="fa-solid fa-plus"></i> Tambah Kategori</a>
          </div>
          <table>
            <thead><tr><th>Nama Kategori</th><th>Jumlah Buku</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if (empty($kategoriList)): ?>
                <tr class="empty-row"><td colspan="3">Belum ada kategori.</td></tr>
              <?php else: foreach ($kategoriList as $k): ?>
                <tr>
                  <td><strong><?= h($k['nama_kategori']) ?></strong></td>
                  <td><?= (int)$k['jumlah_buku'] ?></td>
                  <td class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="admin_dashboard.php?tab=kategori&form=edit&id=<?= $k['id'] ?>">Ubah</a>
                    <form method="post" action="admin_dashboard.php" class="js-confirm-delete" data-confirm-msg="Hapus kategori &quot;<?= h($k['nama_kategori']) ?>&quot;? Buku terkait akan menjadi tanpa kategori." style="display:inline">
                      <input type="hidden" name="action" value="delete_kategori">
                      <input type="hidden" name="id" value="<?= $k['id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        <?php endif; ?>
      <?php endif; ?>

      <?php if ($tab === 'transaksi'): ?>
        <div class="toolbar">
          <form class="search-box" method="get" action="admin_dashboard.php">
            <input type="hidden" name="tab" value="transaksi">
            <input type="text" name="q" placeholder="Cari nama / judul buku..." value="<?= h($q) ?>">
          </form>
        </div>
        <table>
          <thead><tr><th>Anggota</th><th>Buku</th><th>Kategori</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Tgl Kembali</th><th>Durasi</th><th>Denda</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php if (empty($transaksiList)): ?>
              <tr class="empty-row"><td colspan="10">Belum ada transaksi.</td></tr>
            <?php else: foreach ($transaksiList as $t): ?>
              <?php
                $terlambat = false;
                if ($t['status'] === 'Dipinjam' && $t['batas_kembali']) {
                    $terlambat = strtotime(date('Y-m-d')) > strtotime($t['batas_kembali']);
                }
              ?>
              <tr<?= $terlambat ? ' style="background:rgba(224,64,25,.06)"' : '' ?>>
                <td><?= h($t['nama_anggota']) ?></td>
                <td><?= h($t['judul_buku']) ?></td>
                <td><?= h($t['kategori_buku'] ?? '—') ?></td>
                <td><?= h($t['tanggal_pinjam']) ?></td>
                <td><?= h($t['batas_kembali'] ?? '—') ?></td>
                <td><?= h($t['tanggal_kembali'] ?? '—') ?></td>
                <td><?= $t['durasi_pinjam'] ? (int)$t['durasi_pinjam'] . ' hari' : '—' ?></td>
                <td>
                  <?php if ((int)$t['denda'] > 0): ?>
                    <span class="badge badge-denda">Rp <?= number_format($t['denda'], 0, ',', '.') ?></span>
                  <?php elseif ($terlambat): ?>
                    <span class="badge badge-denda"><i class="fa-solid fa-triangle-exclamation"></i> Terlambat</span>
                  <?php else: ?>
                    <span class="muted">Rp 0</span>
                  <?php endif; ?>
                </td>
                <td><?= render_status($t['status']) ?></td>
                <td>
                  <?php if ($t['status']==='Dipinjam'): ?>
                  <form method="post" action="admin_dashboard.php" style="display:inline">
                    <input type="hidden" name="action" value="konfirmasi_kembali">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-brass btn-sm"><?= $terlambat ? 'Kembalikan (Ada Denda)' : 'Konfirmasi Kembali' ?></button>
                  </form>
                  <?php else: ?>—<?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($tab === 'log'): ?>
        <div class="toolbar">
          <form class="search-box" method="get" action="admin_dashboard.php">
            <input type="hidden" name="tab" value="log">
            <input type="text" name="q" placeholder="Cari nama / aksi..." value="<?= h($q) ?>">
          </form>
        </div>
        <table>
          <thead><tr><th>Waktu</th><th>Role</th><th>Nama</th><th>Aksi</th><th>Keterangan</th></tr></thead>
          <tbody>
            <?php if (empty($logList)): ?>
              <tr class="empty-row"><td colspan="5">Belum ada log aktivitas.</td></tr>
            <?php else: foreach ($logList as $l): ?>
              <tr>
                <td class="mono"><?= h($l['created_at']) ?></td>
                <td><span class="badge badge-kategori"><?= h(ucfirst($l['user_type'])) ?></span></td>
                <td><?= h($l['nama_user']) ?></td>
                <td><?= h($l['aksi']) ?></td>
                <td><?= h($l['keterangan']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if ($tab === 'anggota'): ?>
        <?php if ($formMode === 'add' || $editAnggota): ?>
          <div class="card inline-form-card">
            <h3 class="serif"><?= $editAnggota ? 'Ubah Anggota' : 'Tambah Anggota' ?></h3>
            <form method="post" action="admin_dashboard.php" novalidate>
              <input type="hidden" name="action" value="save_anggota">
              <input type="hidden" name="id" value="<?= h($editAnggota['id'] ?? '') ?>">
              <div class="field"><label>Nama</label><input name="nama" value="<?= h($editAnggota['nama'] ?? '') ?>" required></div>
              <div class="field"><label>Kelas</label><input name="kelas" value="<?= h($editAnggota['kelas'] ?? '') ?>"></div>
              <div class="grid-2">
                <div class="field"><label>Username</label><input name="username" value="<?= h($editAnggota['username'] ?? '') ?>" required></div>
                <div class="field"><label>Password<?= $editAnggota ? ' (kosongkan jika tidak diubah)' : '' ?></label><input name="password" type="password" <?= $editAnggota ? '' : 'required' ?>></div>
              </div>
              <div class="form-actions">
                <a class="btn btn-ghost" href="admin_dashboard.php?tab=anggota">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
              </div>
            </form>
          </div>
        <?php else: ?>
          <div class="toolbar">
            <form class="search-box" method="get" action="admin_dashboard.php">
              <input type="hidden" name="tab" value="anggota">
              <input type="text" name="q" placeholder="Cari nama / kelas / username..." value="<?= h($q) ?>">
            </form>
            <a class="btn btn-primary btn-sm" href="admin_dashboard.php?tab=anggota&form=add"><i class="fa-solid fa-plus"></i> Tambah Anggota</a>
          </div>
          <table>
            <thead><tr><th>Nama</th><th>Kelas</th><th>Username</th><th>Aksi</th></tr></thead>
            <tbody>
              <?php if (empty($anggotaList)): ?>
                <tr class="empty-row"><td colspan="4">Tidak ada anggota yang cocok.</td></tr>
              <?php else: foreach ($anggotaList as $a): ?>
                <tr>
                  <td><strong><?= h($a['nama']) ?></strong></td>
                  <td><?= h($a['kelas']) ?></td>
                  <td><?= h($a['username']) ?></td>
                  <td class="row-actions">
                    <a class="btn btn-ghost btn-sm" href="admin_dashboard.php?tab=anggota&form=edit&id=<?= $a['id'] ?>">Ubah</a>
                    <form method="post" action="admin_dashboard.php" class="js-confirm-delete" data-confirm-msg="Hapus anggota &quot;<?= h($a['nama']) ?>&quot;?" style="display:inline">
                      <input type="hidden" name="action" value="delete_anggota">
                      <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
<script>
window.__flash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;
window.__chartKategori = <?= json_encode($rekapKategori, JSON_UNESCAPED_UNICODE) ?>;

/* Toggle notifikasi denda banner */
function toggleNotifDenda() {
  var header = document.querySelector('#notifDendaBanner .notif-denda-header');
  var body = document.getElementById('notifDendaBody');
  if (!header || !body) return;
  header.classList.toggle('open');
  body.classList.toggle('open');
}

/* SweetAlert2: Form penolakan peminjaman dengan alasan */
function tolakPinjam(transId, judulBuku, namaAnggota) {
    if (!window.Swal) { if (confirm('Tolak permohonan peminjaman "' + judulBuku + '" oleh ' + namaAnggota + '?')) { submitTolak(transId, ''); } return; }
    Swal.fire({
        title: 'Tolak Peminjaman',
        html: 'Tolak permohonan <b>' + judulBuku + '</b> oleh <b>' + namaAnggota + '</b>?<br><br><input id="swal-alasan" class="swal2-input" placeholder="Alasan penolakan (opsional)">',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#B5482A',
        preConfirm: function() { return document.getElementById('swal-alasan').value; }
    }).then(function(result) {
        if (result.isConfirmed) { submitTolak(transId, result.value); }
    });
}
function submitTolak(transId, alasan) {
    var f = document.createElement('form');
    f.method = 'POST'; f.action = 'admin_dashboard.php?tab=persetujuan';
    var a1 = document.createElement('input'); a1.name = 'action'; a1.value = 'tolak_pinjam'; f.appendChild(a1);
    var a2 = document.createElement('input'); a2.name = 'id'; a2.value = transId; f.appendChild(a2);
    var a3 = document.createElement('input'); a3.name = 'alasan'; a3.value = alasan || 'Tidak disetujui admin.'; f.appendChild(a3);
    document.body.appendChild(f); f.submit();
}
</script>
<?php require 'includes/foot.php'; ?>
