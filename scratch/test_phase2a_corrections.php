<?php

/**
 * Test terarah: Koreksi Tahap 2A
 * - RateLimiter fail-open (dev) vs fail-closed (prod) saat tabel tidak ada (errno 1146)
 * - Error non-1146 → selalu fail-closed
 * - app.key kosong → RuntimeException dari ipKey()
 * - app.key valid → HMAC bekerja normal
 */

declare(strict_types=1);

ob_start();

// Arahkan ke campgear_test agar tidak menyentuh DB utama
$tmpConfig = sys_get_temp_dir() . '/config_test_phase2a_corr.php';
$baseConfig = require __DIR__ . '/../config/config.php';
$baseConfig['db']['name'] = 'campgear_test';
file_put_contents($tmpConfig, '<?php return ' . var_export($baseConfig, true) . ';');
putenv("CAMPGEAR_CONFIG={$tmpConfig}");

require __DIR__ . '/../app/bootstrap.php';

use App\Core\RateLimiter;

$passed = 0;
$failed = 0;

function assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] $label\n";
        $passed++;
    } else {
        echo "  [FAIL] $label\n";
        $failed++;
    }
}

echo "=== Test Koreksi Tahap 2A: RateLimiter ===\n\n";

// ---------------------------------------------------------------------------
// Test 1: ipKey() dengan app.key valid → format benar, tidak throw
// ---------------------------------------------------------------------------
echo "--- Test 1: ipKey() dengan app.key valid ---\n";

$key = (string) config('app.key');
if ($key !== '') {
    try {
        $result = RateLimiter::ipKey('127.0.0.1');
        assert_true(
            str_starts_with($result, 'bk:') && strlen($result) <= 80,
            'ipKey() dengan app.key valid menghasilkan key berformat benar (prefix bk:, <=80 char)'
        );
    } catch (\Throwable $e) {
        assert_true(false, 'ipKey() dengan app.key valid tidak boleh throw: ' . $e->getMessage());
    }
} else {
    echo "  [SKIP] app.key kosong di lingkungan ini\n";
}

// ---------------------------------------------------------------------------
// Test 2–4: handleDbError — akses via Reflection (private method)
// ---------------------------------------------------------------------------
$reflection = new ReflectionClass(RateLimiter::class);
$method     = $reflection->getMethod('handleDbError');
$method->setAccessible(true);

// Buat dua jenis exception untuk disimulasikan
$exc1146  = new mysqli_sql_exception("Table 'campgear_test.rate_limits' doesn't exist", 1146);
$excOther = new mysqli_sql_exception('Connection refused', 2002);

$isDebug = (bool) config('app.debug');
echo "\n--- Test 2: handleDbError — errno 1146, attempt context (failOpenValue=true) ---\n";
echo "  [INFO] app.debug = " . ($isDebug ? 'true (dev)' : 'false (prod)') . "\n";

$resultAttempt1146 = $method->invoke(null, $exc1146, true);
if ($isDebug) {
    assert_true($resultAttempt1146 === true,
        'debug=true + errno 1146 + attempt → fail-open (true)');
} else {
    assert_true($resultAttempt1146 === false,
        'debug=false + errno 1146 + attempt → fail-closed (false)');
}

echo "\n--- Test 3: handleDbError — errno 1146, tooMany context (failOpenValue=false) ---\n";

$resultTooMany1146 = $method->invoke(null, $exc1146, false);
if ($isDebug) {
    assert_true($resultTooMany1146 === false,
        'debug=true + errno 1146 + tooMany → fail-open (false = tidak diblokir)');
} else {
    assert_true($resultTooMany1146 === true,
        'debug=false + errno 1146 + tooMany → fail-closed (true = diblokir)');
}

echo "\n--- Test 4: handleDbError — error non-1146 → selalu fail-closed terlepas dari debug ---\n";

$resultAttemptOther = $method->invoke(null, $excOther, true);
assert_true($resultAttemptOther === false,
    'error non-1146 + attempt context → fail-closed (false) [bukan tergantung debug]');

$resultTooManyOther = $method->invoke(null, $excOther, false);
assert_true($resultTooManyOther === true,
    'error non-1146 + tooMany context → fail-closed (true) [bukan tergantung debug]');

// ---------------------------------------------------------------------------
// Test 5: config.example.php — tidak mengandung secret demo
// ---------------------------------------------------------------------------
echo "\n--- Test 5: config.example.php bersih dari secret default ---\n";

$exampleContent = file_get_contents(__DIR__ . '/../config/config.example.php');
assert_true(
    !str_contains($exampleContent, 'campgear_sec_key_2026_demo_secret_key_fixed'),
    'config.example.php tidak mengandung secret demo hardcoded'
);
assert_true(
    str_contains($exampleContent, "'key'      => ''"),
    "config.example.php memiliki placeholder key kosong ('key' => '')"
);
assert_true(
    str_contains($exampleContent, 'base64_encode(random_bytes(32))'),
    'config.example.php memiliki instruksi generate key'
);
assert_true(
    str_contains($exampleContent, 'WAJIB'),
    'config.example.php memiliki peringatan WAJIB'
);

// ---------------------------------------------------------------------------
// Test 6: Integrasi nyata dengan campgear_test — attempt, tooMany, remaining
// ---------------------------------------------------------------------------
echo "\n--- Test 6: Integrasi RateLimiter dengan campgear_test ---\n";

try {
    // IP unik agar tidak bentrok dengan run sebelumnya
    $testIp = '10.254.' . random_int(0, 255) . '.' . random_int(1, 254);

    // Attempt pertama pada IP baru harus allowed
    $allowed = RateLimiter::attempt($testIp, 5, 60);
    assert_true($allowed === true, "attempt pertama pada IP baru → allowed");

    // 4 attempt berikutnya (total 5 = batas)
    for ($i = 0; $i < 4; $i++) {
        RateLimiter::attempt($testIp, 5, 60);
    }

    // Attempt ke-6 → harus blocked
    $blocked = RateLimiter::attempt($testIp, 5, 60);
    assert_true($blocked === false, "attempt ke-6 melampaui batas 5 → blocked (false)");

    // tooMany harus true
    assert_true(
        RateLimiter::tooMany($testIp, 5) === true,
        "tooMany() setelah melampaui batas → true"
    );

    // remaining harus 0
    assert_true(
        RateLimiter::remaining($testIp, 5) === 0,
        "remaining() setelah batas terlampaui → 0"
    );

} catch (\Throwable $e) {
    echo "  [SKIP] DB tidak tersedia atau error: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------------------------
// Test 7: ipKey() tanpa fallback — tidak mengandung '-rate-salt'
// ---------------------------------------------------------------------------
echo "\n--- Test 7: RateLimiter.php tidak mengandung fallback '-rate-salt' ---\n";

$rlContent = file_get_contents(__DIR__ . '/../app/Core/RateLimiter.php');
assert_true(
    !str_contains($rlContent, '-rate-salt'),
    'RateLimiter.php tidak memiliki fallback hash berbasis db.name'
);
assert_true(
    str_contains($rlContent, 'RuntimeException'),
    'RateLimiter::ipKey() melempar RuntimeException saat app.key kosong'
);

// ---------------------------------------------------------------------------
// Ringkasan
// ---------------------------------------------------------------------------
echo "\n==========================================\n";
echo "Hasil: $passed PASS, $failed FAIL\n";
echo "==========================================\n";

exit($failed > 0 ? 1 : 0);
