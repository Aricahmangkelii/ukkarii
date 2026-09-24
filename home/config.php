<?php
/**
 * config.php
 * Koneksi database (PDO) + session + helper bersama untuk seluruh aplikasi.
 * Sesuaikan $host, $db, $user, $pass dengan server MySQL/MariaDB Anda.
 * Import file database.sql terlebih dahulu sebelum menjalankan aplikasi.
 */

session_start();

/** Tarif denda keterlambatan pengembalian buku (Rp per hari) */
define('DENDA_PER_HARI', 1000);
/** Durasi default peminjaman (hari) */
define('DURASI_DEFAULT', 7);
/** Maksimum durasi peminjaman (hari) */
define('DURASI_MAKS', 30);

$host    = 'localhost';
$db      = 'perpustakaan_sekolah_digital';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die(
        '<div style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:20px;' .
        'border:1px solid #eab;background:#fbeae3;border-radius:10px;color:#7a2e17;">' .
        '<b>Koneksi database gagal.</b><br>' . htmlspecialchars($e->getMessage()) .
        '<br><br>Pastikan database <code>perpustakaan_sekolah_digital</code> sudah dibuat ' .
        'dengan mengimpor <code>database.sql</code>, dan kredensial di <code>config.php</code> sudah benar.' .
        '</div>'
    );
}

/** Helper singkat untuk escape output ke HTML (proteksi XSS) */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verifikasi login dengan dukungan migrasi password otomatis.
 *
 * - Jika password di database sudah di-hash (password_hash), dicek dengan
 *   password_verify() seperti biasa.
 * - Jika password di database MASIH teks biasa (data lama / data contoh di
 *   database.sql) dan cocok persis dengan input, login tetap diizinkan DAN
 *   password tersebut langsung di-hash ulang serta disimpan kembali ke
 *   database. Dengan begitu akun demo & data lama tetap bisa login tanpa
 *   perlu migrasi manual, tapi setelah login pertama password sudah aman.
 *
 * @return array|false Baris user jika berhasil, false jika gagal.
 */
function verifikasi_login(PDO $pdo, string $tabel, string $username, string $passwordInput) {
    $stmt = $pdo->prepare("SELECT * FROM `$tabel` WHERE username = ?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    $hashInfo  = password_get_info($row['password']);
    $sudahHash = $hashInfo['algo'] !== null && $hashInfo['algo'] !== 0;

    if ($sudahHash) {
        if (!password_verify($passwordInput, $row['password'])) {
            return false;
        }
    } else {
        // Password lama masih teks biasa -> bandingkan langsung, lalu upgrade ke hash.
        if (!hash_equals($row['password'], $passwordInput)) {
            return false;
        }
        $newHash = password_hash($passwordInput, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE `$tabel` SET password = ? WHERE id = ?");
        $upd->execute([$newHash, $row['id']]);
    }

    return $row;
}

/**
 * Mencatat aktivitas penting ke tabel log_aktivitas (audit trail).
 * Dipanggil setelah aksi login, CRUD, peminjaman, pengembalian, dsb.
 */
function catat_log(PDO $pdo, string $userType, int $userId, string $namaUser, string $aksi, ?string $keterangan = null) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO log_aktivitas (user_type, user_id, nama_user, aksi, keterangan) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userType, $userId, $namaUser, $aksi, $keterangan]);
    } catch (PDOException $e) {
        // Jangan hentikan aplikasi hanya karena logging gagal.
    }
}

/**
 * Hentikan eksekusi & redirect jika role sesi tidak sesuai (role-based access).
 * Menyimpan pesan "akses ditolak" jika user sudah login dengan role lain.
 *
 * @param string|array $roleDibutuhkan Satu role ('admin') atau beberapa role
 *                                     sekaligus (['admin','petugas']) yang
 *                                     boleh mengakses halaman ini.
 */
function wajib_role($roleDibutuhkan, string $redirectKe) {
    $rolesDiizinkan = is_array($roleDibutuhkan) ? $roleDibutuhkan : [$roleDibutuhkan];
    if (!in_array($_SESSION['role'] ?? null, $rolesDiizinkan, true)) {
        if (isset($_SESSION['role'])) {
            $_SESSION['flash_error'] = 'Akses ditolak: halaman ini khusus untuk role ' . implode('/', $rolesDiizinkan) . '.';
        }
        header('Location: ' . $redirectKe);
        exit;
    }
}

/** Ambil & hapus pesan flash (sukses/gagal) untuk ditampilkan sebagai toast sekali tampil. */
function ambil_flash(): array {
    $flash = [
        'success' => $_SESSION['flash_success'] ?? null,
        'error'   => $_SESSION['flash_error'] ?? null,
    ];
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
    return $flash;
}
