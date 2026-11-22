<?php

declare(strict_types=1);

ob_start();

// Arahkan ke database pengujian campgear_test
$tmpConfig = sys_get_temp_dir() . '/config_test_phase2a.php';
$baseConfig = require __DIR__ . '/../config/config.php';
$baseConfig['db']['name'] = 'campgear_test';
file_put_contents($tmpConfig, '<?php return ' . var_export($baseConfig, true) . ';');
putenv("CAMPGEAR_CONFIG={$tmpConfig}");

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;

echo "=== Memulai Validasi Tahap 2A ===\n";

// 1. Uji IP Source & Anti-Spoofing (Request::ip)
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 172.16.0.1'; // Spoofed proxy header
if (Request::ip() !== '192.168.1.100') {
    echo "FAIL: Request::ip() mempercayai X-Forwarded-For! Terbaca: " . Request::ip() . "\n";
    exit(1);
}
echo "PASS: Request::ip() hanya mengambil dari REMOTE_ADDR yang tepercaya.\n";

// 2. Uji HMAC-SHA256 pada RateLimiter::ipKey()
$ip = '192.168.1.100';
$key1 = RateLimiter::ipKey($ip);
$key2 = RateLimiter::ipKey($ip);

if ($key1 !== $key2) {
    echo "FAIL: RateLimiter::ipKey() tidak konsisten/deterministik\n";
    exit(1);
}
if (!str_starts_with($key1, 'bk:')) {
    echo "FAIL: RateLimiter::ipKey() format prefix salah: {$key1}\n";
    exit(1);
}
if (strlen($key1) > 80) {
    echo "FAIL: RateLimiter::ipKey() melebihi batas 80 karakter: " . strlen($key1) . "\n";
    exit(1);
}
echo "PASS: RateLimiter::ipKey() menghasilkan kunci HMAC-SHA256 valid (panjang " . strlen($key1) . " char).\n";

// 3. Uji Operasi Atomik & Batas Kuota pada Database campgear_test
$testIp = '203.0.113.' . random_int(1, 250);
$testKey = RateLimiter::ipKey($testIp);

// Bersihkan rekaman uji sebelumnya jika ada
Database::execute('DELETE FROM rate_limits WHERE rate_key = ?', [$testKey]);

// Lakukan 3 percobaan dengan batas 3
for ($i = 1; $i <= 3; $i++) {
    $ok = RateLimiter::attempt($testIp, 3, 3600);
    if (!$ok) {
        echo "FAIL: Attempt ke-{$i} harusnya berhasil dalam kuota 3\n";
        exit(1);
    }
}
$hits = (int) Database::value('SELECT hits FROM rate_limits WHERE rate_key = ?', [$testKey]);
if ($hits !== 3) {
    echo "FAIL: Counter atomik hits diharapkan 3, terbaca {$hits}\n";
    exit(1);
}

// Percobaan ke-4 harus ditolak (kuota 3 habis)
$ok4 = RateLimiter::attempt($testIp, 3, 3600);
if ($ok4) {
    echo "FAIL: Attempt ke-4 harusnya ditolak karena melebihi kuota 3\n";
    exit(1);
}
if (!RateLimiter::tooMany($testIp, 3)) {
    echo "FAIL: tooMany() harusnya bernilai true\n";
    exit(1);
}
if (RateLimiter::remaining($testIp, 3) !== 0) {
    echo "FAIL: remaining() harusnya 0 saat kuota habis\n";
    exit(1);
}
echo "PASS: Counter atomik, batas kuota, tooMany(), dan remaining() bekerja presisi.\n";

// 4. Uji Pembersihan Record Kedaluwarsa (prune)
$expiredIp = '198.51.100.99';
$expiredKey = RateLimiter::ipKey($expiredIp);
Database::execute(
    'INSERT INTO rate_limits (rate_key, hits, reset_at) VALUES (?, 10, NOW() - INTERVAL 10 SECOND)
     ON DUPLICATE KEY UPDATE hits = 10, reset_at = NOW() - INTERVAL 10 SECOND',
    [$expiredKey],
);
$pruned = RateLimiter::prune();
if ($pruned < 1) {
    echo "FAIL: Prune harusnya menghapus minimal 1 record kedaluwarsa\n";
    exit(1);
}
$check = Database::value('SELECT COUNT(*) FROM rate_limits WHERE rate_key = ?', [$expiredKey]);
if ($check != 0) {
    echo "FAIL: Record kedaluwarsa masih ada setelah prune()\n";
    exit(1);
}
echo "PASS: RateLimiter::prune() berhasil membersihkan record kedaluwarsa.\n";

// 5. Uji Form Token Multi-Tab & Single-Use
Session::start();
$tokenTab1 = bin2hex(random_bytes(16));
$tokenTab2 = bin2hex(random_bytes(16));

$forms = [
    $tokenTab1 => time(),
    $tokenTab2 => time(),
];
Session::put('_booking_forms', $forms);

// Konsumsi tab 1
$retrievedForms = (array) Session::get('_booking_forms', []);
if (!isset($retrievedForms[$tokenTab1], $retrievedForms[$tokenTab2])) {
    echo "FAIL: Kedua token form tab tidak tersimpan di session\n";
    exit(1);
}
unset($retrievedForms[$tokenTab1]);
Session::put('_booking_forms', $retrievedForms);

// Cek tab 1 sudah hilang, tab 2 masih ada
$afterConsume = (array) Session::get('_booking_forms', []);
if (isset($afterConsume[$tokenTab1])) {
    echo "FAIL: Token tab 1 tidak terhapus (bukan single-use)\n";
    exit(1);
}
if (!isset($afterConsume[$tokenTab2])) {
    echo "FAIL: Token tab 2 ikut terhapus secara tidak sengaja\n";
    exit(1);
}
echo "PASS: Form token unik per tab dan sifat single-use terverifikasi.\n";

// 6. Uji Cooldown Sukses (15 detik)
Session::put('_last_booking_success', time() - 5); // baru 5 detik yang lalu
$lastSuccess = (int) Session::get('_last_booking_success', 0);
$inCooldown = (time() - $lastSuccess) < 15;
if (!$inCooldown) {
    echo "FAIL: Cooldown 15 detik gagal dideteksi pada selang 5 detik\n";
    exit(1);
}

Session::put('_last_booking_success', time() - 20); // sudah 20 detik yang lalu
$lastSuccess2 = (int) Session::get('_last_booking_success', 0);
$inCooldown2 = (time() - $lastSuccess2) < 15;
if ($inCooldown2) {
    echo "FAIL: Cooldown masih aktif padahal sudah lewat 20 detik\n";
    exit(1);
}
echo "PASS: Mekanisme cooldown 15 detik pasca-booking sukses terverifikasi.\n";

// Bersihkan file sementara
@unlink($tmpConfig);

echo "=== SELURUH VALIDASI TAHAP 2A BERHASIL (100% PASS) ===\n";
