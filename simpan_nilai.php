<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

/* =========================
   AMBIL DATA FORM
========================= */
$id_siswa = $_POST['id_siswa'];
$nilai = $_POST['nilai'];

/* =========================
   VALIDASI
========================= */
if (!$id_siswa || empty($nilai)) {
    die("Data tidak lengkap!");
}

try {

    /* =========================
       TRANSACTION (AMAN)
    ========================= */
    $pdo->beginTransaction();

    /* =========================
       HAPUS NILAI LAMA (BIAR TIDAK DOUBLE)
    ========================= */
    $stmtDelete = $pdo->prepare("DELETE FROM nilai WHERE id_siswa = ?");
    $stmtDelete->execute([$id_siswa]);

    /* =========================
       INSERT NILAI BARU
    ========================= */
    $stmtInsert = $pdo->prepare("
        INSERT INTO nilai (id_siswa, kode_kriteria, nilai)
        VALUES (?, ?, ?)
    ");

    foreach ($nilai as $kode => $n) {

        // validasi angka
        if ($n < 0 || $n > 100) {
            throw new Exception("Nilai harus 0 - 100");
        }

        $stmtInsert->execute([
            $id_siswa,
            $kode,
            $n
        ]);
    }

    $pdo->commit();

    /* =========================
       REDIRECT
    ========================= */
    header("Location: penilaian.php?success=1");
    exit;

} catch (Exception $e) {

    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}