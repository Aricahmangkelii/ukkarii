<?php
require 'config.php';
wajib_role(['admin', 'petugas'], 'admin_login.php');

$roleLabel = ($_SESSION['role'] === 'petugas') ? 'Petugas' : 'Admin';

$NAMA_BULAN = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// ───── Cetak mode (print-friendly, no app-shell) ─────
if (isset($_GET['cetak']) && $_GET['cetak'] === '1') {
    $jenis     = in_array($_GET['jenis'] ?? '', ['transaksi','buku']) ? $_GET['jenis'] : 'transaksi';
    $tahun     = (int)($_GET['tahun'] ?? date('Y')) ?: date('Y');
    $bulan     = (int)($_GET['bulan'] ?? 0);
    $bulanNama = $bulan > 0 ? ($NAMA_BULAN[$bulan] ?? '') : 'Semua Bulan';

    if ($jenis === 'buku') {
        $rows = $pdo->query("
            SELECT b.judul, b.penulis, COALESCE(k.nama_kategori,'—') AS kategori, b.stok
            FROM buku b LEFT JOIN kategori k ON k.id = b.kategori_id
            ORDER BY b.judul
        ")->fetchAll();
        $judulLaporan = 'Laporan Data Buku';
    } else {
        if ($bulan > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM view_laporan_transaksi
                WHERE YEAR(tanggal_pinjam) = ? AND MONTH(tanggal_pinjam) = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$tahun, $bulan]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM view_laporan_transaksi
                WHERE YEAR(tanggal_pinjam) = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$tahun]);
        }
        $rows = $stmt->fetchAll();
        $judulLaporan = 'Laporan Transaksi Peminjaman & Pengembalian';
    }

    $dicetakOleh = $_SESSION['admin_nama'];
    $tanggalCetak = date('d-m-Y H:i');
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
    <meta charset="UTF-8">
    <title><?= $judulLaporan ?> — Pustaka.Lokal</title>
    <style>
      body{font-family:Arial,Helvetica,sans-serif;color:#24211D;margin:32px;}
      h1{font-size:18px;margin-bottom:0;}
      .sub{color:#5B564C;font-size:12px;margin-top:4px;margin-bottom:20px;}
      table{width:100%;border-collapse:collapse;font-size:12.5px;}
      th,td{border:1px solid #ccc;padding:6px 8px;text-align:left;}
      th{background:#F6F1E4;}
      .no-print{margin-bottom:16px;}
      .no-print button,.no-print a{padding:8px 16px;font-size:13px;cursor:pointer;}
      @media print { .no-print{display:none;} body{margin:12px;} }
    </style>
    </head>
    <body>
      <div class="no-print">
        <button onclick="window.print()">🖨️ Cetak / Simpan sebagai PDF</button>
        <a href="admin_laporan.php" style="margin-left:10px;">← Kembali ke Filter Laporan</a>
      </div>
      <h1>Perpustakaan Sekolah Digital — Pustaka.Lokal</h1>
      <div class="sub">
        <?= $judulLaporan ?> · Periode: <?= $bulanNama ?> <?= $tahun ?>
        · Dicetak oleh <?= h($dicetakOleh) ?> pada <?= $tanggalCetak ?>
      </div>
      <?php if ($jenis === 'buku'): ?>
        <table>
          <thead><tr><th>#</th><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= h($r['judul']) ?></td>
                <td><?= h($r['penulis']) ?></td>
                <td><?= h($r['kategori']) ?></td>
                <td><?= (int)$r['stok'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <table>
          <thead><tr><th>#</th><th>Anggota</th><th>Kelas</th><th>Buku</th><th>Kategori</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Tgl Kembali</th><th>Durasi</th><th>Denda</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= h($r['nama_anggota']) ?></td>
                <td><?= h($r['kelas_anggota']) ?></td>
                <td><?= h($r['judul_buku']) ?></td>
                <td><?= h($r['kategori_buku'] ?? '—') ?></td>
                <td><?= h($r['tanggal_pinjam']) ?></td>
                <td><?= h($r['batas_kembali'] ?? '—') ?></td>
                <td><?= h($r['tanggal_kembali'] ?? '—') ?></td>
                <td><?= $r['durasi_pinjam'] ? (int)$r['durasi_pinjam'] . ' hari' : '—' ?></td>
                <td><?= (int)$r['denda'] > 0 ? 'Rp ' . number_format($r['denda'], 0, ',', '.') : 'Rp 0' ?></td>
                <td><?= h($r['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

// ───── CSV Export ─────
if (isset($_GET['csv']) && $_GET['csv'] === '1') {
    $jenis = in_array($_GET['jenis'] ?? '', ['transaksi','buku']) ? $_GET['jenis'] : 'transaksi';
    $tahun = (int)($_GET['tahun'] ?? date('Y')) ?: date('Y');
    $bulan = (int)($_GET['bulan'] ?? 0);

    if ($jenis === 'buku') {
        $rows = $pdo->query("
            SELECT b.judul, b.penulis, COALESCE(k.nama_kategori,'') AS kategori, b.stok
            FROM buku b LEFT JOIN kategori k ON k.id = b.kategori_id
            ORDER BY b.judul
        ")->fetchAll();
        $filename = 'laporan_buku_' . $tahun . '.csv';
        $header = ['No', 'Judul', 'Penulis', 'Kategori', 'Stok'];
    } else {
        if ($bulan > 0) {
            $stmt = $pdo->prepare("SELECT * FROM view_laporan_transaksi WHERE YEAR(tanggal_pinjam) = ? AND MONTH(tanggal_pinjam) = ? ORDER BY id DESC");
            $stmt->execute([$tahun, $bulan]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM view_laporan_transaksi WHERE YEAR(tanggal_pinjam) = ? ORDER BY id DESC");
            $stmt->execute([$tahun]);
        }
        $rows = $stmt->fetchAll();
        $filename = 'laporan_transaksi_' . $tahun . ($bulan > 0 ? '_' . sprintf('%02d', $bulan) : '') . '.csv';
        $header = ['No', 'Anggota', 'Kelas', 'Buku', 'Kategori', 'Tgl Pinjam', 'Batas Kembali', 'Tgl Kembali', 'Durasi', 'Denda', 'Status'];
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $fp = fopen('php://output', 'w');
    fputs($fp, "\xEF\xBB\xBF"); // BOM for Excel UTF-8
    fputcsv($fp, $header, ';');
    foreach ($rows as $i => $r) {
        if ($jenis === 'buku') {
            fputcsv($fp, [$i + 1, $r['judul'], $r['penulis'], $r['kategori'], (int)$r['stok']], ';');
        } else {
            fputcsv($fp, [
                $i + 1,
                $r['nama_anggota'],
                $r['kelas_anggota'],
                $r['judul_buku'],
                $r['kategori_buku'] ?? '',
                $r['tanggal_pinjam'],
                $r['batas_kembali'] ?? '',
                $r['tanggal_kembali'] ?? '',
                $r['durasi_pinjam'] ? (int)$r['durasi_pinjam'] . ' hari' : '',
                (int)$r['denda'] > 0 ? 'Rp ' . number_format($r['denda'], 0, ',', '.') : 'Rp 0',
                $r['status']
            ], ';');
        }
    }
    fclose($fp);
    exit;
}

// ───── Normal page (app-shell + filter) ─────
$showResults  = false;
$jenis       = 'transaksi';
$tahun       = (int)date('Y');
$bulan       = 0;
$rows        = [];
$judulLaporan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lihat_laporan') {
    $jenis  = in_array($_POST['jenis'] ?? '', ['transaksi','buku']) ? $_POST['jenis'] : 'transaksi';
    $tahun  = (int)($_POST['tahun'] ?? 0) ?: (int)date('Y');
    $bulan  = (int)($_POST['bulan'] ?? 0);
    $showResults = true;

    if ($jenis === 'buku') {
        $rows = $pdo->query("
            SELECT b.judul, b.penulis, COALESCE(k.nama_kategori,'—') AS kategori, b.stok
            FROM buku b LEFT JOIN kategori k ON k.id = b.kategori_id
            ORDER BY b.judul
        ")->fetchAll();
        $judulLaporan = 'Laporan Data Buku';
    } else {
        if ($bulan > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM view_laporan_transaksi
                WHERE YEAR(tanggal_pinjam) = ? AND MONTH(tanggal_pinjam) = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$tahun, $bulan]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM view_laporan_transaksi
                WHERE YEAR(tanggal_pinjam) = ?
                ORDER BY id DESC
            ");
            $stmt->execute([$tahun]);
        }
        $rows = $stmt->fetchAll();
        $judulLaporan = 'Laporan Transaksi Peminjaman & Pengembalian';
    }
}

$flash = ambil_flash();
$pageTitle = 'Laporan';
require 'includes/head.php';
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
        <a class="spine-btn" href="admin_dashboard.php?tab=persetujuan"><i class="fa-solid fa-clock"></i> Persetujuan</a>
        <a class="spine-btn" href="admin_dashboard.php?tab=buku"><i class="fa-solid fa-book"></i> Kelola Data Buku</a>
        <a class="spine-btn" href="admin_dashboard.php?tab=kategori"><i class="fa-solid fa-tags"></i> Kelola Kategori</a>
        <a class="spine-btn" href="admin_dashboard.php?tab=transaksi"><i class="fa-solid fa-right-left"></i> Transaksi</a>
        <a class="spine-btn" href="admin_dashboard.php?tab=anggota"><i class="fa-solid fa-users"></i> Kelola Anggota</a>
        <a class="spine-btn" href="admin_dashboard.php?tab=log"><i class="fa-solid fa-clock-rotate-left"></i> Log Aktivitas</a>
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a class="spine-btn" href="admin_manage.php"><i class="fa-solid fa-user-shield"></i> Kelola Akun</a>
        <?php endif; ?>
        <a class="spine-btn active" href="admin_laporan.php"><i class="fa-solid fa-print"></i> Cetak Laporan</a>
        <a class="spine-btn" href="help.php" target="_blank"><i class="fa-solid fa-circle-question"></i> Bantuan</a>
      </nav>
      <div class="rail-foot"><a class="btn btn-ghost" style="width:100%;color:#EFE9DA;border-color:rgba(255,255,255,.25);text-align:center" href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a></div>
    </aside>

    <main class="main">
      <div class="main-head">
        <div>
          <div class="breadcrumb">Dashboard <?= h($roleLabel) ?> <span>/</span> Laporan</div>
          <h2 class="serif">Laporan</h2>
          <p>Generate laporan perpustakaan per periode.</p>
        </div>
      </div>

      <!-- ===== FILTER CARD ===== -->
      <div class="card" style="max-width:560px;padding:28px 32px">
        <form id="formFilter" method="post" action="admin_laporan.php">
          <input type="hidden" name="action" value="lihat_laporan">

          <div style="margin-bottom:20px">
            <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px">
              Jenis Laporan <span style="color:#c0392b">*</span>
            </label>
            <select name="jenis" id="selJenis" required style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:inherit;font-size:14px;appearance:auto">
              <option value="transaksi" <?= $jenis==='transaksi'?'selected':'' ?>>Peminjaman Buku</option>
              <option value="buku" <?= $jenis==='buku'?'selected':'' ?>>Data Buku</option>
            </select>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
            <div>
              <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px">Tahun</label>
              <select name="tahun" style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:inherit;font-size:14px;appearance:auto">
                <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= $tahun===$y?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div>
              <label style="display:block;font-weight:600;margin-bottom:6px;font-size:14px">Bulan</label>
              <select name="bulan" style="width:100%;padding:10px 14px;border-radius:8px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:inherit;font-size:14px;appearance:auto">
                <option value="0" <?= $bulan===0?'selected':'' ?>>Semua Bulan</option>
                <?php foreach ($NAMA_BULAN as $m => $nm): ?>
                <option value="<?= $m ?>" <?= $bulan===$m?'selected':'' ?>><?= $nm ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" class="btn btn-primary" id="btnLihat">
              <i class="fa-solid fa-eye" style="margin-right:6px"></i>Lihat Laporan
            </button>
            <button type="button" class="btn btn-ghost" id="btnCetak" <?= !$showResults?'disabled style="opacity:.4;cursor:not-allowed"':'' ?>>
              <i class="fa-solid fa-print" style="margin-right:6px"></i>Cetak
            </button>
            <button type="button" class="btn btn-ghost" id="btnCsv" <?= !$showResults?'disabled style="opacity:.4;cursor:not-allowed"':'' ?>>
              <i class="fa-solid fa-download" style="margin-right:6px"></i>Ekspor CSV
            </button>
          </div>
        </form>
      </div>

      <!-- ===== RESULTS TABLE ===== -->
      <?php if ($showResults): ?>
      <div style="margin-top:24px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <div>
            <strong style="font-size:15px"><?= h($judulLaporan) ?></strong>
            <span class="muted" style="margin-left:8px;font-size:13px">
              — <?= $bulan > 0 ? h($NAMA_BULAN[$bulan]) . ' ' : '' ?><?= $tahun ?>
              · <?= count($rows) ?> data
            </span>
          </div>
        </div>

        <?php if (empty($rows)): ?>
          <div class="card" style="padding:32px;text-align:center">
            <i class="fa-solid fa-inbox" style="font-size:28px;opacity:.3;margin-bottom:8px;display:block"></i>
            <span class="muted">Tidak ada data untuk periode ini.</span>
          </div>
        <?php elseif ($jenis === 'buku'): ?>
          <div class="card" style="padding:0;overflow-x:auto">
            <table>
              <thead><tr><th>#</th><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok</th></tr></thead>
              <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><strong><?= h($r['judul']) ?></strong></td>
                  <td><?= h($r['penulis']) ?></td>
                  <td><?= $r['kategori'] !== '—' ? '<span class="badge badge-kategori">'.h($r['kategori']).'</span>' : '<span class="muted">—</span>' ?></td>
                  <td><?= (int)$r['stok'] ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="card" style="padding:0;overflow-x:auto">
            <table>
              <thead><tr><th>#</th><th>Anggota</th><th>Kelas</th><th>Buku</th><th>Kategori</th><th>Tgl Pinjam</th><th>Batas Kembali</th><th>Tgl Kembali</th><th>Durasi</th><th>Denda</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <?php
                  $statusBadge = '';
                  if ($r['status'] === 'Menunggu Persetujuan') {
                      $statusBadge = '<span class="badge badge-waiting">Menunggu</span>';
                  } elseif ($r['status'] === 'Ditolak') {
                      $statusBadge = '<span class="badge badge-rejected">Ditolak</span>';
                  } elseif ($r['status'] === 'Dipinjam') {
                      $statusBadge = '<span class="badge badge-out">Dipinjam</span>';
                  } elseif ($r['status'] === 'Dikembalikan') {
                      $statusBadge = '<span class="badge badge-in">Dikembalikan</span>';
                  }
                ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><strong><?= h($r['nama_anggota']) ?></strong></td>
                  <td><?= h($r['kelas_anggota']) ?></td>
                  <td><?= h($r['judul_buku']) ?></td>
                  <td><?= $r['kategori_buku'] ? '<span class="badge badge-kategori">'.h($r['kategori_buku']).'</span>' : '<span class="muted">—</span>' ?></td>
                  <td><?= h($r['tanggal_pinjam']) ?></td>
                  <td><?= h($r['batas_kembali'] ?? '—') ?></td>
                  <td><?= h($r['tanggal_kembali'] ?? '—') ?></td>
                  <td><?= $r['durasi_pinjam'] ? (int)$r['durasi_pinjam'].' hari' : '—' ?></td>
                  <td><?= (int)$r['denda'] > 0 ? '<span class="badge badge-denda">Rp '.number_format($r['denda'],0,',','.').'</span>' : '<span class="muted">Rp 0</span>' ?></td>
                  <td><?= $statusBadge ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </main>
  </div>
</section>
<script>window.__flash = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script>
(function () {
  var btnCetak = document.getElementById('btnCetak');
  var btnCsv   = document.getElementById('btnCsv');
  var form     = document.getElementById('formFilter');

  function getFilterParams() {
    var fd = new FormData(form);
    return {
      jenis: fd.get('jenis'),
      tahun: fd.get('tahun'),
      bulan: fd.get('bulan')
    };
  }

  function buildUrl(base, extra) {
    var p = getFilterParams();
    var qs = 'jenis=' + encodeURIComponent(p.jenis) + '&tahun=' + encodeURIComponent(p.tahun) + '&bulan=' + encodeURIComponent(p.bulan);
    if (extra) qs += '&' + extra;
    return base + '?' + qs;
  }

  btnCetak.addEventListener('click', function () {
    window.open(buildUrl('admin_laporan.php', 'cetak=1'), '_blank');
  });

  btnCsv.addEventListener('click', function () {
    window.location.href = buildUrl('admin_laporan.php', 'csv=1');
  });
})();
</script>
<?php require 'includes/foot.php'; ?>