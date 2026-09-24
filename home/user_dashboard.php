<?php
require 'config.php';
wajib_role('siswa', 'user_login.php');
$siswaId = $_SESSION['siswa_id'];

// Jaga-jaga: pastikan akun ini masih ada di tabel anggota
$cekAnggota = $pdo->prepare('SELECT id FROM anggota WHERE id = ?');
$cekAnggota->execute([$siswaId]);
if (!$cekAnggota->fetch()) {
    $_SESSION = [];
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'Sesi Anda tidak valid lagi. Silakan login ulang.';
    header('Location: user_login.php');
    exit;
}

$tab = in_array($_GET['tab'] ?? '', ['pinjam','kembali']) ? $_GET['tab'] : 'pinjam';

/* ============ HANDLE POST ACTIONS ============ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'ajukan_pinjam') {
        $bukuId  = (int)($_POST['buku_id'] ?? 0);
        $durasi  = (int)($_POST['durasi_pinjam'] ?? DURASI_DEFAULT);

        // Validasi durasi
        if ($durasi < 1) $durasi = 1;
        if ($durasi > DURASI_MAKS) $durasi = DURASI_MAKS;

        // Validasi buku & stok
        $stmt = $pdo->prepare('SELECT judul, stok FROM buku WHERE id = ?');
        $stmt->execute([$bukuId]);
        $buku = $stmt->fetch();

        if (!$buku) {
            $_SESSION['flash_error'] = 'Buku tidak ditemukan.';
        } elseif ($buku['stok'] <= 0) {
            $_SESSION['flash_error'] = 'Stok buku "' . $buku['judul'] . '" sedang habis.';
        } else {
            // Cek apakah siswa sudah ada pengajuan untuk buku yang sama yang masih Menunggu
            $cek = $pdo->prepare("SELECT id FROM transaksi WHERE anggota_id = ? AND buku_id = ? AND status = 'Menunggu Persetujuan'");
            $cek->execute([$siswaId, $bukuId]);
            if ($cek->fetch()) {
                $_SESSION['flash_error'] = 'Anda sudah mengajukan peminjaman buku ini. Silakan tunggu persetujuan admin.';
            } else {
                $batasKembali = date('Y-m-d', strtotime("+$durasi days"));
                $pdo->prepare("INSERT INTO transaksi (anggota_id, buku_id, tanggal_pinjam, durasi_pinjam, batas_kembali, status) VALUES (?, ?, CURDATE(), ?, ?, 'Menunggu Persetujuan')")
                    ->execute([$siswaId, $bukuId, $durasi, $batasKembali]);
                catat_log($pdo, 'siswa', $siswaId, $_SESSION['siswa_nama'], 'Ajukan Pinjam', "Mengajukan pinjam: " . $buku['judul'] . " ($durasi hari)");
                $_SESSION['flash_success'] = 'Pengajuan peminjaman buku "' . $buku['judul'] . '" berhasil dikirim. Menunggu persetujuan admin.';
            }
        }
        header('Location: user_dashboard.php?tab=pinjam'); exit;
    }

    if ($action === 'kembalikan_buku') {
        $transaksiId = (int)($_POST['id'] ?? 0);
        // Siswa mengajukan pengembalian: ubah status jadi Menunggu Pengembalian
        // (admin yang akan mengkonfirmasi & menghitung denda)
        // Untuk simplisitas, langsung ubah ke Dikembalikan dan hitung denda otomatis
        $stmt = $pdo->prepare("SELECT t.*, b.judul FROM transaksi t JOIN buku b ON b.id = t.buku_id WHERE t.id = ? AND t.anggota_id = ? AND t.status = 'Dipinjam'");
        $stmt->execute([$transaksiId, $siswaId]);
        $transaksi = $stmt->fetch();

        if ($transaksi) {
            // Hitung denda
            $denda = 0;
            if ($transaksi['batas_kembali']) {
                $hariTerlambat = (strtotime(date('Y-m-d')) - strtotime($transaksi['batas_kembali'])) / 86400;
                if ($hariTerlambat > 0) {
                    $denda = (int)ceil($hariTerlambat) * DENDA_PER_HARI;
                }
            }
            $pdo->prepare("UPDATE transaksi SET status='Dikembalikan', tanggal_kembali = CURDATE(), denda = ? WHERE id = ? AND anggota_id = ? AND status='Dipinjam'")
                ->execute([$denda, $transaksiId, $siswaId]);
            catat_log($pdo, 'siswa', $siswaId, $_SESSION['siswa_nama'], 'Kembalikan Buku', "Transaksi #$transaksiId dikembalikan" . ($denda > 0 ? ", denda: Rp $denda" : ''));
            $_SESSION['flash_success'] = 'Buku berhasil dikembalikan.' . ($denda > 0 ? " Denda keterlambatan: <b>Rp " . number_format($denda, 0, ',', '.') . '</b>' : '');
        } else {
            $_SESSION['flash_error'] = 'Transaksi tidak ditemukan atau bukan milik Anda.';
        }
        header('Location: user_dashboard.php?tab=kembali'); exit;
    }
}

$q = trim($_GET['q'] ?? '');

// Peta sampul buku (cover) berdasarkan ID buku — sama seperti di katalog beranda
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
function coverBuku($id, $coverFiles) {
    $idx = isset($coverFiles[$id]) ? $id : ((($id - 1) % 8) + 1);
    return $coverFiles[$idx] ?? 'assets/images/covers/cover-1.png';
}

if ($tab === 'pinjam') {
    $stmt = $pdo->prepare("
        SELECT b.*, k.nama_kategori
        FROM buku b LEFT JOIN kategori k ON k.id = b.kategori_id
        WHERE b.judul LIKE ? OR b.penulis LIKE ? OR k.nama_kategori LIKE ?
        ORDER BY b.judul
    ");
    $like = "%$q%";
    $stmt->execute([$like, $like, $like]);
    $bukuList = $stmt->fetchAll();
}

if ($tab === 'kembali') {
    $stmt = $pdo->prepare("
        SELECT t.*, b.judul AS judul_buku
        FROM transaksi t
        LEFT JOIN buku b ON b.id = t.buku_id
        WHERE t.anggota_id = ? AND b.judul LIKE ?
        ORDER BY t.id DESC
    ");
    $stmt->execute([$siswaId, "%$q%"]);
    $riwayat = $stmt->fetchAll();
}

$flash = ambil_flash();
$pageTitle = 'Dashboard Siswa';

/* ============ DATA NOTIFIKASI DENDA ============ */
$stmtTerlambat = $pdo->prepare("
    SELECT t.id, t.batas_kembali, t.tanggal_pinjam, t.durasi_pinjam,
           b.judul AS judul_buku, b.penulis,
           DATEDIFF(CURDATE(), t.batas_kembali) AS hari_terlambat,
           DATEDIFF(CURDATE(), t.batas_kembali) * ? AS denda_terhitung
    FROM transaksi t
    JOIN buku b ON b.id = t.buku_id
    WHERE t.anggota_id = ? AND t.status = 'Dipinjam' AND t.batas_kembali < CURDATE()
    ORDER BY t.batas_kembali ASC
");
$stmtTerlambat->execute([DENDA_PER_HARI, $siswaId]);
$terlambatList = $stmtTerlambat->fetchAll();
$totalDendaTerlambat = 0;
foreach ($terlambatList as $tl) {
    $totalDendaTerlambat += (int)$tl['denda_terhitung'];
}

require 'includes/head.php';
?>
<section>
  <div class="app-shell">
    <aside class="rail">
      <div class="brandmark"><span class="dot"></span><span>Pustaka.Lokal</span></div>
      <div class="rail-user">
        <div class="name"><?= h($_SESSION['siswa_nama']) ?></div>
        <div class="role"><?= h($_SESSION['siswa_kelas']) ?></div>
      </div>
      <nav class="rail-nav">
        <a class="spine-btn <?= $tab==='pinjam'?'active':'' ?>" href="user_dashboard.php?tab=pinjam"><i class="fa-solid fa-book-open"></i> Peminjaman Buku</a>
        <a class="spine-btn <?= $tab==='kembali'?'active':'' ?>" href="user_dashboard.php?tab=kembali"><i class="fa-solid fa-rotate-left"></i> Pengembalian Buku</a>
        <a class="spine-btn" href="help.php" target="_blank"><i class="fa-solid fa-circle-question"></i> Bantuan</a>
      </nav>
      <div class="rail-foot"><a class="btn btn-ghost" style="width:100%;color:#EFE9DA;border-color:rgba(255,255,255,.25);text-align:center" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a></div>
    </aside>

    <main class="main">

      <?php if (!empty($terlambatList)): ?>
      <!-- NOTIFIKASI DENDA TERLAMBAT -->
      <div class="notif-denda-banner" id="notifDendaBanner">
        <div class="notif-denda-header" onclick="toggleNotifDenda()">
          <div class="notif-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
          <div>
            <div class="notif-title"><?= count($terlambatList) ?> Peminjaman Melewati Batas Waktu!</div>
            <div class="notif-subtitle">Anda memiliki buku yang belum dikembalikan melebihi tempo. Denda terus bertambah setiap hari.</div>
          </div>
          <div class="notif-chevron notif-pulse"><i class="fa-solid fa-chevron-down"></i></div>
        </div>
        <div class="notif-denda-body open" id="notifDendaBody">
          <div class="notif-denda-list">
            <?php foreach ($terlambatList as $tl): ?>
            <div class="notif-denda-item">
              <div class="ndi-icon"><i class="fa-solid fa-book"></i></div>
              <div class="ndi-info">
                <div class="ndi-judul"><?= h($tl['judul_buku']) ?></div>
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
            <div class="ndf-total-label">Total denda yang harus dibayar jika dikembalikan hari ini:</div>
            <div class="ndf-total-amount">Rp <?= number_format($totalDendaTerlambat, 0, ',', '.') ?> <small>(<?= count($terlambatList) ?> buku)</small></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="main-head">
        <div>
          <div class="breadcrumb">Dashboard Siswa <span>/</span> <?= $tab==='pinjam' ? 'Peminjaman' : 'Pengembalian' ?></div>
          <?php if ($tab==='pinjam'): ?>
            <h2 class="serif">Peminjaman Buku</h2><p>Cari buku yang tersedia, tentukan durasi pinjam, lalu ajukan peminjaman. Admin akan mempersetujui permintaan Anda.</p>
          <?php else: ?>
            <h2 class="serif">Pengembalian Buku</h2><p>Kembalikan buku yang sedang Anda pinjam. Denda otomatis dihitung jika terlambat.</p>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($tab === 'pinjam'): ?>
        <div class="toolbar">
          <form class="search-box" method="get" action="user_dashboard.php">
            <input type="hidden" name="tab" value="pinjam">
            <input type="text" name="q" placeholder="Cari judul / penulis / kategori..." value="<?= h($q) ?>">
          </form>
        </div>
        <table>
          <thead><tr><th>Sampul</th><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php if (empty($bukuList)): ?>
              <tr class="empty-row"><td colspan="6">Tidak ada buku yang cocok.</td></tr>
            <?php else: foreach ($bukuList as $b): ?>
              <tr>
                <td>
                  <div class="table-book-cover">
                    <img src="<?= coverBuku((int)$b['id'], $coverFiles) ?>" alt="Sampul: <?= h($b['judul']) ?>" loading="lazy">
                  </div>
                </td>
                <td><strong><?= h($b['judul']) ?></strong></td>
                <td><?= h($b['penulis']) ?></td>
                <td><?= $b['nama_kategori'] ? '<span class="badge badge-kategori">'.h($b['nama_kategori']).'</span>' : '<span class="muted">—</span>' ?></td>
                <td><?= $b['stok']==0 ? '<span class="badge badge-stok-low">Habis</span>' : (int)$b['stok'] ?></td>
                <td>
                  <?php if ($b['stok'] > 0): ?>
                  <button type="button" class="btn btn-primary btn-sm btn-ajukan"
                    data-buku-id="<?= $b['id'] ?>"
                    data-judul="<?= h($b['judul']) ?>"
                    data-penulis="<?= h($b['penulis']) ?>"
                    data-kategori="<?= h($b['nama_kategori'] ?? 'Tanpa Kategori') ?>"
                    data-stok="<?= (int)$b['stok'] ?>"
                  >Ajukan</button>
                  <?php else: ?>
                  <button type="button" class="btn btn-primary btn-sm" disabled>Stok Habis</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div class="hint-box" style="margin-top:16px">Tarif denda: <b>Rp <?= number_format(DENDA_PER_HARI, 0, ',', '.') ?>/hari</b> keterlambatan pengembalian.</div>
      <?php else: ?>
        <div class="toolbar">
          <form class="search-box" method="get" action="user_dashboard.php">
            <input type="hidden" name="tab" value="kembali">
            <input type="text" name="q" placeholder="Cari judul buku..." value="<?= h($q) ?>">
          </form>
        </div>
        <table>
          <thead><tr><th>Buku</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Status</th><th>Denda</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php if (empty($riwayat)): ?>
              <tr class="empty-row"><td colspan="6">Belum ada riwayat peminjaman.</td></tr>
            <?php else: foreach ($riwayat as $t): ?>
              <?php
                $statusBadge = '';
                if ($t['status'] === 'Menunggu Persetujuan') {
                    $statusBadge = '<span class="badge badge-waiting">Menunggu Persetujuan</span>';
                } elseif ($t['status'] === 'Ditolak') {
                    $statusBadge = '<span class="badge badge-rejected">Ditolak</span>';
                } elseif ($t['status'] === 'Dipinjam') {
                    $statusBadge = '<span class="badge badge-out">Dipinjam</span>';
                } elseif ($t['status'] === 'Dikembalikan') {
                    $statusBadge = '<span class="badge badge-in">Dikembalikan</span>';
                }

                // Cek apakah terlambat
                $terlambat = false;
                if ($t['status'] === 'Dipinjam' && $t['batas_kembali']) {
                    $terlambat = strtotime(date('Y-m-d')) > strtotime($t['batas_kembali']);
                }
              ?>
              <tr<?= $terlambat ? ' style="background:rgba(224,64,25,.06)"' : '' ?>>
                <td><strong><?= h($t['judul_buku'] ?? '(buku dihapus)') ?></strong></td>
                <td><?= h($t['tanggal_pinjam']) ?></td>
                <td><?= $t['batas_kembali'] ? h($t['batas_kembali']) : '<span class="muted">—</span>' ?></td>
                <td><?= $statusBadge ?></td>
                <td>
                  <?php if ((int)$t['denda'] > 0): ?>
                    <span class="badge badge-denda">Rp <?= number_format($t['denda'], 0, ',', '.') ?></span>
                  <?php elseif ($terlambat): ?>
                    <span class="badge badge-denda"><i class="fa-solid fa-triangle-exclamation"></i> Terlambat!</span>
                  <?php else: ?>
                    <span class="muted">Rp 0</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($t['status'] === 'Dipinjam'): ?>
                  <form method="post" action="user_dashboard.php" style="display:inline">
                    <input type="hidden" name="action" value="kembalikan_buku">
                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                    <button type="submit" class="btn btn-brass btn-sm"><?= $terlambat ? 'Kembalikan (Terlambat)' : 'Kembalikan' ?></button>
                  </form>
                  <?php else: ?>—<?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </main>
  </div>
</section>
<script>window.__flash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
function toggleNotifDenda() {
  var header = document.querySelector('.notif-denda-header');
  var body = document.getElementById('notifDendaBody');
  if (!header || !body) return;
  header.classList.toggle('open');
  body.classList.toggle('open');
}

/* Pop-up notifikasi denda saat pertama kali buka halaman */
var __terlambatCount = <?= count($terlambatList) ?>;
var __totalDenda = <?= $totalDendaTerlambat ?>;
if (__terlambatCount > 0 && window.Swal) {
  Swal.fire({
    icon: 'warning',
    title: 'Peminjaman Terlambat!',
    html: 'Anda memiliki <b>' + __terlambatCount + ' buku</b> yang melewati batas waktu pengembalian.<br><br>' +
          'Total denda saat ini: <b style="color:#B5482A;font-size:18px">Rp ' + __totalDenda.toLocaleString('id-ID') + '</b><br>' +
          '<small style="color:#888">Denda bertambah Rp <?= DENDA_PER_HARI ?>/hari per buku.</small>',
    confirmButtonText: '<i class="fa-solid fa-check" style="margin-right:6px"></i>Saya Mengerti',
    confirmButtonColor: '#B5482A',
    customClass: { popup: 'notif-denda-swal-popup' }
  });
}
(function () {
  var DURASI_DEFAULT = <?= DURASI_DEFAULT ?>;
  var DURASI_MAKS = <?= DURASI_MAKS ?>;
  var DENDA_PER_HARI = <?= DENDA_PER_HARI ?>;
  var today = new Date();
  var todayStr = today.toISOString().split('T')[0];

  function formatTanggal(dateStr) {
    var d = new Date(dateStr + 'T00:00:00');
    var bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
  }

  function hitungBatasKembali(durasi) {
    var d = new Date(todayStr + 'T00:00:00');
    d.setDate(d.getDate() + parseInt(durasi, 10));
    return d.toISOString().split('T')[0];
  }

  document.querySelectorAll('.btn-ajukan').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();

      var bukuId  = btn.getAttribute('data-buku-id');
      var judul   = btn.getAttribute('data-judul');
      var penulis = btn.getAttribute('data-penulis');
      var kategori = btn.getAttribute('data-kategori');
      var stok    = btn.getAttribute('data-stok');

      // Nonaktifkan tombol saat modal terbuka (cegah double-click)
      btn.disabled = true;

      Swal.fire({
        title: 'Detail Peminjaman Buku',
        html:
          '<div style="text-align:left;font-size:14px;line-height:1.7;color:#333">' +
            '<div style="background:#f8f6f0;border-radius:10px;padding:16px 20px;margin-bottom:16px;border:1px solid #e8e2d6">' +
              '<div style="font-weight:700;font-size:16px;margin-bottom:10px;color:#1a1a1a"><i class="fa-solid fa-book" style="color:#1F3D2E;margin-right:8px"></i>' + judul + '</div>' +
              '<div style="display:grid;grid-template-columns:120px 1fr;gap:4px 12px">' +
                '<span style="color:#888">Penulis</span><span style="font-weight:500">' + penulis + '</span>' +
                '<span style="color:#888">Kategori</span><span style="font-weight:500">' + kategori + '</span>' +
                '<span style="color:#888">Stok tersedia</span><span style="font-weight:600;color:' + (parseInt(stok) <= 1 ? '#c0392b' : '#1F3D2E') + '">' + stok + ' eksemplar</span>' +
              '</div>' +
            '</div>' +
            '<div style="margin-bottom:16px">' +
              '<label style="display:block;font-weight:600;margin-bottom:6px;color:#1a1a1a">Durasi Pinjam</label>' +
              '<div style="display:flex;align-items:center;gap:10px">' +
                '<input id="swal-durasi" type="number" min="1" max="' + DURASI_MAKS + '" value="' + DURASI_DEFAULT + '" style="width:80px;padding:8px 10px;border:1px solid #ccc;border-radius:8px;font-size:14px;text-align:center">' +
                '<span style="color:#666">hari (maks. ' + DURASI_MAKS + ')</span>' +
              '</div>' +
            '</div>' +
            '<div id="swal-info-tanggal" style="background:#eef6ee;border:1px solid #c3dfc3;border-radius:8px;padding:12px 16px;font-size:13px;color:#1F3D2E">' +
              '<div style="margin-bottom:4px"><i class="fa-solid fa-calendar-check" style="margin-right:6px"></i><strong>Tanggal Pinjam:</strong> ' + formatTanggal(todayStr) + '</div>' +
              '<div><i class="fa-solid fa-calendar-xmark" style="margin-right:6px"></i><strong>Batas Kembali:</strong> <span id="swal-batas">' + formatTanggal(hitungBatasKembali(DURASI_DEFAULT)) + '</span></div>' +
            '</div>' +
            '<div style="margin-top:12px;font-size:12px;color:#888;text-align:center"><i class="fa-solid fa-circle-info" style="margin-right:4px"></i>Denda keterlambatan: Rp ' + DENDA_PER_HARI.toLocaleString('id-ID') + '/hari</div>' +
          '</div>',
        showCancelButton: true,
        showConfirmButton: true,
        confirmButtonText: '<i class="fa-solid fa-paper-plane" style="margin-right:6px"></i>Konfirmasi Ajukan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#1F3D2E',
        cancelButtonColor: '#888',
        width: 480,
        didOpen: function () {
          var durasiInput = document.getElementById('swal-durasi');
          var batasSpan = document.getElementById('swal-batas');
          durasiInput.addEventListener('input', function () {
            var v = parseInt(this.value, 10);
            if (isNaN(v) || v < 1) v = 1;
            if (v > DURASI_MAKS) v = DURASI_MAKS;
            batasSpan.textContent = formatTanggal(hitungBatasKembali(v));
          });
        },
        preConfirm: function () {
          var durasi = parseInt(document.getElementById('swal-durasi').value, 10);
          if (isNaN(durasi) || durasi < 1) {
            Swal.showValidationMessage('Durasi peminjaman minimal 1 hari.');
            return false;
          }
          if (durasi > DURASI_MAKS) {
            Swal.showValidationMessage('Durasi peminjaman maksimal ' + DURASI_MAKS + ' hari.');
            return false;
          }
          return { durasi: durasi };
        }
      }).then(function (result) {
        if (result.isConfirmed) {
          // Putar suara berhasil saat klik konfirmasi
          if (typeof playSuccessChime === 'function') playSuccessChime();
          // Buat form tersembunyi dan kirim
          var form = document.createElement('form');
          form.method = 'POST';
          form.action = 'user_dashboard.php';

          var addField = function(name, value) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
          };

          addField('action', 'ajukan_pinjam');
          addField('buku_id', bukuId);
          addField('durasi_pinjam', result.value.durasi);

          document.body.appendChild(form);
          form.submit();
        } else {
          // User membatalkan — aktifkan kembali tombol
          btn.disabled = false;
        }
      }).catch(function () {
        // Modal ditutup lewat tombol X atau klik luar
        btn.disabled = false;
      });
    });
  });

  // Suara saat klik tombol Kembalikan
  document.querySelectorAll('.btn-brass[type="submit"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (typeof playReturnChime === 'function') playReturnChime();
    });
  });
})();
</script>
<?php require 'includes/foot.php'; ?>