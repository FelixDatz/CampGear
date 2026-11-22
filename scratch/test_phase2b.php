<?php

/**
 * Test terarah Tahap 2B: Payment Model Layer
 *
 * Skenario yang diuji:
 * 1.  submitProof() pada booking valid → payment pending terbuat
 * 2.  submitProof() duplikat (sudah ada pending) → BusinessRuleException
 * 3.  submitProof() setelah deadline → BusinessRuleException
 * 4.  submitProof() pada booking bukan pending_payment → BusinessRuleException
 * 5.  approve() payment pending → payment=paid, booking=confirmed
 * 6.  approve() payment yang sudah paid → BusinessRuleException (double process)
 * 7.  reject() dengan note → payment=failed, booking tetap pending_payment
 * 8.  reject() tanpa note → BusinessRuleException
 * 9.  reject() payment sudah failed → BusinessRuleException
 * 10. upload ulang setelah reject (sebelum deadline) → payment pending baru terbuat
 * 11. expireOverdue() tidak mengexpire booking yang punya payment pending
 * 12. expireOverdue() mengexpire booking tanpa payment pending yang sudah lewat deadline
 * 13. expireOverdue() mengexpire booking dengan payment failed yang sudah lewat deadline
 * 14. Availability::forRange() menahan stok untuk booking past-deadline tapi ada payment pending
 * 15. Availability::forRange() melepas stok untuk booking past-deadline tanpa payment pending
 * 16. rentalHistory() mengembalikan semua baris termasuk yang failed
 * 17. pendingRental() mengembalikan payment pending aktif, null jika tidak ada
 */

declare(strict_types=1);

ob_start();

// Bootstrap ke campgear_test
$tmpConfig = sys_get_temp_dir() . '/config_test_phase2b.php';
$baseConfig = require __DIR__ . '/../config/config.php';
$baseConfig['db']['name'] = 'campgear_test';
$baseConfig['app']['debug'] = true;
file_put_contents($tmpConfig, '<?php return ' . var_export($baseConfig, true) . ';');
putenv("CAMPGEAR_CONFIG={$tmpConfig}");

require __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;
use App\Exceptions\BusinessRuleException;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Payment;

$pass = 0;
$fail = 0;

function ok(bool $cond, string $label): void
{
    global $pass, $fail;
    if ($cond) {
        echo "  [PASS] $label\n";
        $pass++;
    } else {
        echo "  [FAIL] $label\n";
        $fail++;
    }
}

function throws(callable $fn, string $expectMsg, string $label): void
{
    global $pass, $fail;
    try {
        $fn();
        echo "  [FAIL] $label (tidak melempar exception)\n";
        $fail++;
    } catch (BusinessRuleException $e) {
        if ($expectMsg === '' || str_contains($e->getMessage(), $expectMsg)) {
            echo "  [PASS] $label\n";
            $pass++;
        } else {
            echo "  [FAIL] $label — pesan: {$e->getMessage()}\n";
            $fail++;
        }
    }
}

echo "=== Test Tahap 2B: Payment Model Layer ===\n\n";

// ---------------------------------------------------------------------------
// Setup: buat booking untuk test
// (langsung via SQL karena test ini fokus pada model Payment, bukan controller)
// ---------------------------------------------------------------------------
Database::execute("DELETE FROM bookings WHERE code LIKE 'CG000001-TST%'");
Booking::expireOverdue(); // bersihkan sisa jika ada

// Booking A: masih dalam deadline (payment_due_at = sekarang + 1 jam)
$bookingIdA = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test A', 'a@test.test', '081200000001',
             DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY),
             3, 300000, 0, 300000, 'pending_payment', DATE_ADD(NOW(), INTERVAL 1 HOUR))",
    ['CG000001-TST1'],
);

// Booking B: sudah lewat deadline, tidak ada payment
$bookingIdB = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test B', 'b@test.test', '081200000002',
             DATE_ADD(CURDATE(), INTERVAL 8 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY),
             3, 200000, 0, 200000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 10 MINUTE))",
    ['CG000001-TST2'],
);

// Booking C: sudah lewat deadline, akan disiapkan punya payment pending
$bookingIdC = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test C', 'c@test.test', '081200000003',
             DATE_ADD(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 14 DAY),
             3, 150000, 0, 150000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 5 MINUTE))",
    ['CG000001-TST3'],
);

// Booking D: sudah lewat deadline, payment failed (bukti sudah ditolak)
$bookingIdD = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test D', 'd@test.test', '081200000004',
             DATE_ADD(CURDATE(), INTERVAL 15 DAY), DATE_ADD(CURDATE(), INTERVAL 17 DAY),
             3, 120000, 0, 120000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 2 MINUTE))",
    ['CG000001-TST4'],
);
// Tambahkan payment failed untuk booking D
Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image, note)
     VALUES (?, 'rental', 'transfer', 120000, 'failed', 'payments/abc123.jpg', 'Bukti tidak terbaca')",
    [$bookingIdD],
);

// Fake staff ID (pakai user seed)
$staffId = (int) Database::value("SELECT id FROM users WHERE role='staff' LIMIT 1") ?: 1;

// ---------------------------------------------------------------------------
// Test 1–4: submitProof()
// ---------------------------------------------------------------------------
echo "--- submitProof() ---\n";

$proofPath = 'payments/' . bin2hex(random_bytes(16)) . '.jpg';

$paymentIdA = Payment::submitProof($bookingIdA, 'transfer', $proofPath);
ok($paymentIdA > 0, 'Test 1: submitProof() pada booking valid → payment ID terbuat');

// Verifikasi DB
$row = Database::first('SELECT * FROM payments WHERE id = ?', [$paymentIdA]);
ok($row !== null && $row['status'] === 'pending' && $row['purpose'] === 'rental', 'Test 1b: status pending, purpose=rental');

// Test 2: duplikat
throws(
    fn() => Payment::submitProof($bookingIdA, 'transfer', $proofPath),
    'menunggu verifikasi',
    'Test 2: submitProof() duplikat saat sudah ada pending → exception'
);

// Test 3: setelah deadline (booking B)
throws(
    fn() => Payment::submitProof($bookingIdB, 'transfer', $proofPath),
    'Batas waktu',
    'Test 3: submitProof() setelah deadline → exception'
);

// Test 4: booking bukan pending_payment (simulate confirmed)
Database::execute("UPDATE bookings SET status = 'confirmed' WHERE id = ?", [$bookingIdB]);
throws(
    fn() => Payment::submitProof($bookingIdB, 'ewallet', $proofPath),
    'tidak sedang menunggu',
    'Test 4: submitProof() pada booking non-pending_payment → exception'
);
Database::execute("UPDATE bookings SET status = 'pending_payment' WHERE id = ?", [$bookingIdB]);

// ---------------------------------------------------------------------------
// Test 5–9: approve() dan reject()
// ---------------------------------------------------------------------------
echo "\n--- approve() ---\n";

// Test 5: approve normal (sebelum deadline)
Payment::approve($paymentIdA, $staffId);
$pAfterApprove = Database::first('SELECT * FROM payments WHERE id = ?', [$paymentIdA]);
$bAfterApprove = Database::first('SELECT * FROM bookings WHERE id = ?', [$bookingIdA]);
ok($pAfterApprove['status'] === 'paid', 'Test 5a: approve() → payment status=paid');
ok($pAfterApprove['handled_by'] == $staffId, 'Test 5b: approve() → handled_by tercatat');
ok($pAfterApprove['paid_at'] !== null, 'Test 5c: approve() → paid_at tercatat');
ok($bAfterApprove['status'] === 'confirmed', 'Test 5d: approve() → booking status=confirmed');
ok($bAfterApprove['confirmed_at'] !== null, 'Test 5e: approve() → confirmed_at tercatat');

// Test 6: approve ulang payment yang sudah paid (idempotency/double process guard)
throws(
    fn() => Payment::approve($paymentIdA, $staffId),
    'sudah diproses',
    'Test 6: approve() ulang payment yang sudah paid → exception (tidak memicu status ganda)'
);

// Test 6b: approve SETELAH deadline tetap berhasil jika bukti disubmit tepat waktu
$bookingIdAfterDeadline = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test Overdue Approve', 'overdue@test.test', '081200000099',
             DATE_ADD(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 12 DAY),
             3, 250000, 0, 250000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 1 HOUR))",
    ['CG000001-TST99'],
);
$paymentIdAfterDeadline = Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image)
     VALUES (?, 'rental', 'transfer', 250000, 'pending', 'payments/ontime_proof.jpg')",
    [$bookingIdAfterDeadline],
);
Payment::approve($paymentIdAfterDeadline, $staffId);
$pAfterDl = Database::first('SELECT * FROM payments WHERE id = ?', [$paymentIdAfterDeadline]);
$bAfterDl = Database::first('SELECT * FROM bookings WHERE id = ?', [$bookingIdAfterDeadline]);
ok($pAfterDl['status'] === 'paid', 'Test 6c: approve() setelah deadline lewat tetap berhasil untuk bukti tepat waktu');
ok($bAfterDl['status'] === 'confirmed', 'Test 6d: booking status menjadi confirmed meski diverifikasi setelah deadline');

echo "\n--- reject() ---\n";

// Siapkan booking sebelum deadline untuk reject test (rejection SEBELUM deadline)
$bookingIdBeforeDl = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test Reject Before DL', 'rej_before@test.test', '081200000088',
             DATE_ADD(CURDATE(), INTERVAL 18 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY),
             3, 175000, 0, 175000, 'pending_payment', DATE_ADD(NOW(), INTERVAL 2 HOUR))",
    ['CG000001-TST88'],
);
$paymentIdBeforeDl = Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image)
     VALUES (?, 'rental', 'transfer', 175000, 'pending', 'payments/rej_before.jpg')",
    [$bookingIdBeforeDl],
);

// Test 7: reject SEBELUM deadline → payment=failed, booking TETAP pending_payment
Payment::reject($paymentIdBeforeDl, $staffId, 'Bukti transfer buram');
$pRejBef = Database::first('SELECT * FROM payments WHERE id = ?', [$paymentIdBeforeDl]);
$bRejBef = Database::first('SELECT * FROM bookings WHERE id = ?', [$bookingIdBeforeDl]);
ok($pRejBef['status'] === 'failed', 'Test 7a: reject() sebelum deadline → payment status=failed');
ok($pRejBef['note'] === 'Bukti transfer buram', 'Test 7b: reject() sebelum deadline → note tersimpan');
ok($pRejBef['handled_by'] == $staffId, 'Test 7c: reject() sebelum deadline → handled_by tercatat');
ok($bRejBef['status'] === 'pending_payment', 'Test 7d: reject() sebelum deadline → booking TETAP pending_payment');

// Test 7e: reject SETELAH deadline → payment=failed, booking LANGSUNG expired
$proofPath2 = 'payments/' . bin2hex(random_bytes(16)) . '.jpg';
$paymentIdC = Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image)
     VALUES (?, 'rental', 'transfer', 150000, 'pending', ?)",
    [$bookingIdC, $proofPath2],
);
Payment::reject($paymentIdC, $staffId, 'Bukti palsu / tidak valid');
$pAfterRejectC = Database::first('SELECT * FROM payments WHERE id = ?', [$paymentIdC]);
$bAfterRejectC = Database::first('SELECT * FROM bookings WHERE id = ?', [$bookingIdC]);
ok($pAfterRejectC['status'] === 'failed', 'Test 7f: reject() setelah deadline → payment status=failed');
ok($bAfterRejectC['status'] === 'expired', 'Test 7g: reject() setelah deadline → booking LANGSUNG expired dalam transaksi yang sama');

// Test 8: reject tanpa note
throws(
    fn() => Payment::reject($paymentIdC, $staffId, ''),
    'wajib diisi',
    'Test 8: reject() tanpa note → exception'
);

// Test 9: reject payment sudah failed (double reject)
throws(
    fn() => Payment::reject($paymentIdC, $staffId, 'coba lagi'),
    'sudah diproses',
    'Test 9: reject() payment yang sudah failed → exception (double process dicegah)'
);

// ---------------------------------------------------------------------------
// Test 10: upload ulang setelah reject (sebelum deadline)
// ---------------------------------------------------------------------------
echo "\n--- Upload ulang setelah reject ---\n";

// Siapkan booking E: dalam deadline, punya payment failed
$bookingIdE = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test E', 'e@test.test', '081200000005',
             DATE_ADD(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 22 DAY),
             3, 90000, 0, 90000, 'pending_payment', DATE_ADD(NOW(), INTERVAL 2 HOUR))",
    ['CG000001-TST5'],
);
$rejectedPaymentId = Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image, note)
     VALUES (?, 'rental', 'transfer', 90000, 'failed', 'payments/oldproof.jpg', 'Tidak terbaca')",
    [$bookingIdE],
);

$newProofPath = 'payments/' . bin2hex(random_bytes(16)) . '.jpg';
$newPaymentId = Payment::submitProof($bookingIdE, 'ewallet', $newProofPath);
ok($newPaymentId > 0, 'Test 10a: upload ulang setelah reject → payment baru terbuat');

// Pastikan payment lama (failed) masih ada
$histCount = (int) Database::value(
    'SELECT COUNT(*) FROM payments WHERE booking_id = ?',
    [$bookingIdE],
);
ok($histCount === 2, 'Test 10b: upload ulang → riwayat payment lama (failed) tidak dihapus, total 2 baris');

// ---------------------------------------------------------------------------
// Test 11–13: expireOverdue()
// ---------------------------------------------------------------------------
echo "\n--- expireOverdue() ---\n";

// Booking H: past deadline, punya payment PENDING → TIDAK boleh di-expire
$bookingIdH = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test H', 'h@test.test', '081200000008',
             DATE_ADD(CURDATE(), INTERVAL 30 DAY), DATE_ADD(CURDATE(), INTERVAL 32 DAY),
             3, 80000, 0, 80000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 3 MINUTE))",
    ['CG000001-TST8'],
);
Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image)
     VALUES (?, 'rental', 'transfer', 80000, 'pending', 'payments/testH.jpg')",
    [$bookingIdH],
);

$expired = Booking::expireOverdue();

$statusB = Database::value('SELECT status FROM bookings WHERE id = ?', [$bookingIdB]);
$statusD = Database::value('SELECT status FROM bookings WHERE id = ?', [$bookingIdD]);
$statusH = Database::value('SELECT status FROM bookings WHERE id = ?', [$bookingIdH]);

ok($statusB === 'expired', 'Test 12: expireOverdue() → booking past-deadline tanpa payment → expired');
ok($statusH === 'pending_payment', 'Test 11: expireOverdue() → booking past-deadline dengan payment pending → TETAP pending_payment');
ok($statusD === 'expired', 'Test 13: expireOverdue() → booking past-deadline dengan payment failed → expired');

// ---------------------------------------------------------------------------
// Test 14–15: Availability::forRange() — stock retention
// ---------------------------------------------------------------------------
echo "\n--- Availability::forRange() stock retention ---\n";

// Siapkan booking F: past deadline, punya payment pending, ada booking_items
$bookingIdF = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test F', 'f@test.test', '081200000006',
             DATE_ADD(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 27 DAY),
             3, 60000, 0, 60000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 1 MINUTE))",
    ['CG000001-TST6'],
);
// Ambil equipment_id pertama yang aktif
$eqId = (int) Database::value('SELECT id FROM equipment WHERE is_active = 1 ORDER BY id LIMIT 1');
$eqStock = (int) Database::value('SELECT stock_total FROM equipment WHERE id = ?', [$eqId]);
Database::insert(
    'INSERT INTO booking_items (booking_id, equipment_id, quantity, daily_rate, line_total, discount_amount)
     VALUES (?, ?, 2, 20000, 60000, 0)',
    [$bookingIdF, $eqId],
);
// Tambah payment pending → stok harus tetap ditahan
Database::insert(
    "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image)
     VALUES (?, 'rental', 'transfer', 60000, 'pending', 'payments/testF.jpg')",
    [$bookingIdF],
);

$startF = date('Y-m-d', strtotime('+25 days'));
$endF   = date('Y-m-d', strtotime('+27 days'));
$availWithPending = Availability::forRange($startF, $endF, [$eqId]);
$availableWithPending = $availWithPending[$eqId]['available'] ?? -1;
ok($availableWithPending === $eqStock - 2, "Test 14: Availability menahan stok (past deadline + payment pending) → tersedia {$availableWithPending}");

// Siapkan booking G: past deadline, TANPA payment, sama tanggal
$bookingIdG = Database::insert(
    "INSERT INTO bookings
     (code, customer_name, customer_email, customer_phone,
      start_date, end_date, total_days, subtotal, discount_total, grand_total,
      status, payment_due_at)
     VALUES (?, 'Test G', 'g@test.test', '081200000007',
             DATE_ADD(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 27 DAY),
             3, 60000, 0, 60000, 'pending_payment', DATE_SUB(NOW(), INTERVAL 1 MINUTE))",
    ['CG000001-TST7'],
);
Database::insert(
    'INSERT INTO booking_items (booking_id, equipment_id, quantity, daily_rate, line_total, discount_amount)
     VALUES (?, ?, 2, 20000, 60000, 0)',
    [$bookingIdG, $eqId],
);
// Tanpa payment → stok harus dilepas (booking G dianggap expired secara lazy)

$availWithExpired = Availability::forRange($startF, $endF, [$eqId]);
$availableWithExpired = $availWithExpired[$eqId]['available'] ?? -1;
// Booking F: stok ditahan (2 unit), Booking G: stok dilepas → tersedia = stock - 2
ok($availableWithExpired === $eqStock - 2, "Test 15: Availability melepas stok (past deadline tanpa payment) → tersedia {$availableWithExpired}");

// ---------------------------------------------------------------------------
// Test 16–17: rentalHistory() dan pendingRental()
// ---------------------------------------------------------------------------
echo "\n--- rentalHistory() dan pendingRental() ---\n";

$history = Payment::rentalHistory($bookingIdE);
ok(count($history) === 2, 'Test 16: rentalHistory() mengembalikan semua baris termasuk failed');
ok($history[0]['status'] === 'failed', 'Test 16b: baris pertama adalah yang lama (failed)');
ok($history[1]['status'] === 'pending', 'Test 16c: baris kedua adalah yang baru (pending)');

$pending = Payment::pendingRental($bookingIdA);
ok($pending === null, 'Test 17a: pendingRental() → null untuk booking sudah confirmed (approved)');

$pendingE = Payment::pendingRental($bookingIdE);
ok($pendingE !== null && $pendingE['status'] === 'pending', 'Test 17b: pendingRental() → payment pending aktif dikembalikan');

// ---------------------------------------------------------------------------
// Ringkasan
// ---------------------------------------------------------------------------
echo "\n==========================================\n";
echo "Hasil: $pass PASS, $fail FAIL\n";
echo "==========================================\n";

exit($fail > 0 ? 1 : 0);
