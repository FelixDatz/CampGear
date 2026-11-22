<?php

declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Upload;
use App\Exceptions\BusinessRuleException;

echo "=== Memulai Validasi Tahap 1 ===\n";

// 1. Uji Whitelist Folder Privat
try {
    Upload::privateImage('proof', 'invalid_folder');
    echo "FAIL: Harusnya throw LogicException untuk folder tidak dikenal\n";
    exit(1);
} catch (\LogicException $e) {
    echo "PASS: Folder privat tidak dikenal ditolak dengan benar.\n";
}

// 2. Uji Keamanan Path & Path Traversal di Upload::privatePath()
$traversalAttacks = [
    '../storage/payments/abc.jpg',
    '../../etc/passwd',
    'payments/../payments/0123456789abcdef0123456789abcdef.jpg',
    'public/uploads/equipment/0123456789abcdef0123456789abcdef.jpg',
    'payments/0123456789abcdef0123456789abcdef.php',
    'payments/0123456789abcdef0123456789abcdef.sh',
    'payments/invalidname.jpg',
    'payments/0123456789abcdef0123456789abcdef.jpg.exe',
    '',
    null,
];

foreach ($traversalAttacks as $badPath) {
    $res = Upload::privatePath($badPath);
    if ($res !== null) {
        echo "FAIL: Path traversal '{$badPath}' lolos!\n";
        exit(1);
    }
}
echo "PASS: Seluruh serangan path traversal ditolak oleh privatePath().\n";

// 3. Uji deletePrivate() terhadap path traversal
foreach ($traversalAttacks as $badPath) {
    Upload::deletePrivate($badPath);
}
echo "PASS: deletePrivate() menolak path tidak aman tanpa error.\n";

// 4. Uji Pembuatan & Resolusi File Privat yang Sah
$dummyFilename = 'payments/' . bin2hex(random_bytes(16)) . '.jpg';
$fullPath = BASE_PATH . '/storage/' . $dummyFilename;

// Belum ada di disk: privatePath harus return null
if (Upload::privatePath($dummyFilename) !== null) {
    echo "FAIL: File belum ada tapi privatePath mengembalikan non-null\n";
    exit(1);
}

// Simpan file tiruan
file_put_contents($fullPath, 'fake-image-content');
if (Upload::privatePath($dummyFilename) !== $fullPath) {
    echo "FAIL: Path sah gagal di-resolve oleh privatePath()\n";
    exit(1);
}
echo "PASS: File privat yang sah berhasil di-resolve ke absolute path.\n";

// Hapus lewat deletePrivate()
Upload::deletePrivate($dummyFilename);
if (file_exists($fullPath)) {
    echo "FAIL: File tiruan gagal dihapus oleh deletePrivate()\n";
    exit(1);
}
echo "PASS: File privat berhasil dihapus secara aman oleh deletePrivate().\n";

// 5. Uji Validasi Konfigurasi
$windowHours = config('rental.payment_window_hours');
$rateLimit = config('rental.booking_rate_limit_per_hour');

if ($windowHours !== 3) {
    echo "FAIL: payment_window_hours diharapkan 3, dapat {$windowHours}\n";
    exit(1);
}
if ($rateLimit !== 60) {
    echo "FAIL: booking_rate_limit_per_hour diharapkan 60, dapat {$rateLimit}\n";
    exit(1);
}
echo "PASS: Konfigurasi payment_window_hours (3) dan booking_rate_limit_per_hour (60) sesuai.\n";

// 6. Uji Sintaks schema.sql (parsing struktur)
$schemaSql = file_get_contents(BASE_PATH . '/database/schema.sql');
if (!str_contains($schemaSql, 'CREATE TABLE rate_limits')) {
    echo "FAIL: schema.sql tidak memuat tabel rate_limits\n";
    exit(1);
}
if (!str_contains($schemaSql, 'proof_image VARCHAR(255)')) {
    echo "FAIL: schema.sql tidak memuat kolom proof_image\n";
    exit(1);
}
if (!str_contains($schemaSql, 'note        VARCHAR(255)')) {
    echo "FAIL: schema.sql tidak memuat kolom note di payments\n";
    exit(1);
}
echo "PASS: schema.sql memuat rate_limits, proof_image, dan note.\n";

echo "=== SELURUH VALIDASI TAHAP 1 BERHASIL (100% PASS) ===\n";
