<?php
/**
 * api_ulasan.php
 * Endpoint AJAX untuk submit ulasan buku dan testimoni.
 * Mengembalikan JSON.
 */
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Aksi tidak dikenali.'];

if ($action === 'submit_ulasan') {
    $bukuId  = (int)($_POST['buku_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $komentar = trim($_POST['komentar'] ?? '');
    $nama    = '';
    $anggotaId = null;

    if ($rating < 1 || $rating > 5) {
        $response = ['success' => false, 'message' => 'Rating harus antara 1-5.'];
        echo json_encode($response); exit;
    }
    if ($komentar === '') {
        $response = ['success' => false, 'message' => 'Komentar wajib diisi.'];
        echo json_encode($response); exit;
    }

    // Cek apakah user login sebagai siswa
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'siswa') {
        $anggotaId = $_SESSION['siswa_id'];
        $nama = $_SESSION['siswa_nama'];
    } else {
        $nama = trim($_POST['nama'] ?? '');
        if ($nama === '') {
            $response = ['success' => false, 'message' => 'Nama wajib diisi.'];
            echo json_encode($response); exit;
        }
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO ulasan_buku (buku_id, anggota_id, nama, rating, komentar) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$bukuId, $anggotaId, $nama, $rating, $komentar]);
        $newId = $pdo->lastInsertId();
        catat_log($pdo, $anggotaId ? 'siswa' : 'tamu', $anggotaId ?: 0, $nama, 'Ulasan Buku', "Buku #$bukuId, rating $rating");
        $response = [
            'success' => true,
            'message' => 'Ulasan berhasil ditambahkan!',
            'data' => [
                'id' => (int)$newId,
                'nama' => $nama,
                'rating' => $rating,
                'komentar' => $komentar,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ];
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Gagal menyimpan ulasan: ' . $e->getMessage()];
    }
}

if ($action === 'submit_testimoni') {
    $nama    = trim($_POST['nama'] ?? '');
    $peran   = trim($_POST['peran'] ?? '');
    $rating  = (int)($_POST['rating'] ?? 0);
    $komentar = trim($_POST['komentar'] ?? '');

    if ($nama === '' || $komentar === '' || $rating < 1 || $rating > 5) {
        $response = ['success' => false, 'message' => 'Semua field wajib diisi. Rating harus 1-5.'];
        echo json_encode($response); exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO testimoni (nama, peran, rating, komentar) VALUES (?, ?, ?, ?)');
        $stmt->execute([$nama, $peran, $rating, $komentar]);
        $newId = $pdo->lastInsertId();
        $avatarColors = ['var(--forest)','var(--navy)','var(--brass)','var(--ok)','var(--alert)','var(--forest-light)'];
        $initials = strtoupper(mb_substr(preg_replace('/\\s+/', '', $nama), 0, 2));
        $response = [
            'success' => true,
            'message' => 'Testimoni berhasil ditambahkan!',
            'data' => [
                'id' => (int)$newId,
                'nama' => $nama,
                'peran' => $peran,
                'rating' => $rating,
                'komentar' => $komentar,
                'initials' => $initials,
                'color' => $avatarColors[(int)$newId % count($avatarColors)],
                'created_at' => date('Y-m-d H:i:s'),
            ]
        ];
    } catch (PDOException $e) {
        $response = ['success' => false, 'message' => 'Gagal menyimpan testimoni: ' . $e->getMessage()];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
