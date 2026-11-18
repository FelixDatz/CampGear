<?php

declare(strict_types=1);

/**
 * Membuat database lokal lalu menjalankan schema.sql dan seed.sql.
 *
 *   php database/setup.php                  buat database bila belum ada, isi tabel
 *   php database/setup.php --fresh          hapus database lalu buat ulang dari nol
 *   php database/setup.php --fresh --demo   seperti di atas + transaksi contoh (grafik, riwayat)
 *
 * PERINGATAN: schema.sql menghapus tabel yang ada. Jangan jalankan pada database
 * yang datanya ingin dipertahankan.
 *
 * Hanya untuk pengembangan lokal (CLI). Di hosting, impor file SQL lewat phpMyAdmin.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$name = (string) config('db.name');
if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    exit("Nama database tidak valid: {$name}\n");
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli(
    (string) config('db.host'),
    (string) config('db.user'),
    (string) config('db.pass'),
    '',
    (int) config('db.port', 3306),
);
$conn->set_charset('utf8mb4');

if (in_array('--fresh', $argv, true)) {
    $conn->query("DROP DATABASE IF EXISTS `{$name}`");
    echo "Database `{$name}` dihapus.\n";
}
$conn->query("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($name);

$files = ['schema.sql', 'seed.sql', 'seed_articles.sql'];
if (in_array('--demo', $argv, true)) {
    $files[] = 'demo_data.sql'; // transaksi contoh untuk grafik & demonstrasi
}
foreach ($files as $file) {
    $sql = file_get_contents(__DIR__ . '/' . $file);
    $conn->multi_query($sql);
    // Kosongkan semua hasil; error pada salah satu statement akan dilempar sebagai exception.
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "OK  {$file}\n";
}

$tables = $conn->query('SHOW TABLES')->num_rows;
echo "Selesai: {$tables} tabel di database `{$name}`.\n";
