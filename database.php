<?php
$host = 'localhost';
$dbname = 'spk_smart';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // error jadi exception
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // fetch langsung assoc
            PDO::ATTR_EMULATE_PREPARES => false, // security lebih aman
        ]
    );
} catch (PDOException $e) {
    // Jangan tampilkan error detail di production
    die("Koneksi database gagal!");
}
?>