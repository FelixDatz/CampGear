<?php

declare(strict_types=1);

/**
 * Generator database/demo_data.sql — transaksi contoh untuk demonstrasi & grafik.
 *
 *   php database/tools/generate-demo.php > database/demo_data.sql
 *
 * - Membaca alat & paket dari database yang dikonfigurasi (hasil seed.sql).
 * - Tanggal ditulis relatif terhadap CURDATE(), sehingga data selalu tampak terkini.
 * - Stok per hari dipatuhi agar data demo tidak pernah overbooking.
 * - Hasil deterministik (mt_srand) sehingga file bisa dibuat ulang dengan isi sama.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Database;

mt_srand(2026);

$equipment = [];
foreach (Database::select('SELECT id, name, stock_total, daily_rate FROM equipment WHERE is_active = 1 ORDER BY id') as $row) {
    $equipment[(int) $row['id']] = ['stock' => (int) $row['stock_total'], 'rate' => (float) $row['daily_rate'], 'name' => $row['name']];
}
$packages = [];
foreach (Database::select('SELECT id, discount_percent FROM packages WHERE is_active = 1') as $row) {
    $packages[(int) $row['id']] = [
        'discount' => (float) $row['discount_percent'],
        'items'    => Database::select('SELECT equipment_id, quantity FROM package_items WHERE package_id = ?', [(int) $row['id']]),
    ];
}

$staffId = (int) Database::value("SELECT id FROM users WHERE email = 'staff@demo.test'");
$adminId = (int) Database::value("SELECT id FROM users WHERE email = 'admin@demo.test'");
$userId = (int) Database::value("SELECT id FROM users WHERE email = 'user@demo.test'");

$names = ['Rina Pendaki', 'Bagus Santoso', 'Dewi Lestari', 'Andi Pratama', 'Sari Wulandari', 'Yoga Saputra', 'Maya Anggraini',
    'Fajar Nugroho', 'Lina Marlina', 'Dimas Haryanto', 'Putri Ayu', 'Rizky Ramadhan', 'Nina Kartika', 'Hendra Wijaya', 'Tari Oktaviani'];

// Pemakaian per hari (offset hari dari hari ini) per alat, untuk menjaga stok.
$usage = [];
$fits = static function (array $lines, int $start, int $end) use (&$usage, $equipment): bool {
    $need = [];
    foreach ($lines as $l) {
        $need[$l['equipment_id']] = ($need[$l['equipment_id']] ?? 0) + $l['quantity'];
    }
    for ($d = $start; $d <= $end; $d++) {
        foreach ($need as $id => $qty) {
            if (($usage[$d][$id] ?? 0) + $qty > $equipment[$id]['stock']) {
                return false;
            }
        }
    }
    return true;
};
$reserve = static function (array $lines, int $start, int $end) use (&$usage): void {
    for ($d = $start; $d <= $end; $d++) {
        foreach ($lines as $l) {
            $usage[$d][$l['equipment_id']] = ($usage[$d][$l['equipment_id']] ?? 0) + $l['quantity'];
        }
    }
};

$randomLines = static function () use ($equipment, $packages): array {
    if (mt_rand(1, 100) <= 25) {
        $packageId = array_rand($packages);
        $sets = 1;
        return array_map(static fn ($i) => [
            'equipment_id' => (int) $i['equipment_id'], 'quantity' => $sets * (int) $i['quantity'],
            'package_id' => $packageId, 'discount' => $packages[$packageId]['discount'],
        ], $packages[$packageId]['items']);
    }
    $lines = [];
    $ids = array_keys($equipment);
    shuffle($ids);
    foreach (array_slice($ids, 0, mt_rand(1, 3)) as $id) {
        $lines[] = ['equipment_id' => $id, 'quantity' => mt_rand(1, 2), 'package_id' => null, 'discount' => 0.0];
    }
    return $lines;
};

$day = static fn (int $offset, int $hour = 0): string =>
    'CURDATE() ' . ($offset < 0 ? '- INTERVAL ' . -$offset : '+ INTERVAL ' . $offset) . ' DAY'
    . ($hour !== 0 ? ' + INTERVAL ' . $hour . ' HOUR' : '');
$q = static fn (?string $v): string => $v === null ? 'NULL' : "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";

$bookingId = 1000;
$itemId = 5000;
$paymentId = 7000;
$cancelId = 3000;
$checklistId = 4000;
$handoverItemId = 6000;
$damageId = 8000;
$sql = [];
$audit = [];
$codeChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$usedCodes = [];

/**
 * @param string $scenario completed|cancelled|expired|picked_up|returned_damage|confirmed|pending|cancel_pending|cancel_rejected
 */
$addBooking = static function (int $start, int $length, string $scenario, ?bool $forUser = null) use (
    &$bookingId, &$itemId, &$paymentId, &$cancelId, &$checklistId, &$handoverItemId, &$damageId, &$sql, &$audit, &$usedCodes,
    $equipment, $names, $fits, $reserve, $randomLines, $day, $q, $staffId, $userId, $codeChars
): bool {
    $end = $start + $length - 1;
    $lines = null;
    for ($try = 0; $try < 20; $try++) {
        $candidate = $randomLines();
        if ($fits($candidate, $start, $end) || in_array($scenario, ['expired', 'cancelled'], true)) {
            $lines = $candidate;
            break;
        }
    }
    if ($lines === null) {
        return false;
    }
    if (!in_array($scenario, ['expired', 'cancelled'], true)) {
        $reserve($lines, $start, $end);
    }

    $bookingId++;
    do {
        $code = 'CGDEMO-';
        for ($i = 0; $i < 4; $i++) {
            $code .= $codeChars[mt_rand(0, strlen($codeChars) - 1)];
        }
    } while (isset($usedCodes[$code]));
    $usedCodes[$code] = true;

    $forUser ??= mt_rand(1, 100) <= 15;
    $name = $forUser ? 'Pelanggan Demo' : $names[array_rand($names)];
    $email = $forUser ? 'user@demo.test' : strtolower(explode(' ', $name)[0]) . mt_rand(10, 99) . '@contoh.test';
    $phone = $forUser ? '081200000003' : '08' . mt_rand(1111111111, 9999999999);
    $created = min($start - mt_rand(1, 10), 0);
    $createdHour = mt_rand(8, 20);

    $subtotal = 0.0;
    $discount = 0.0;
    $itemRows = [];
    foreach ($lines as $line) {
        $itemId++;
        $total = $line['quantity'] * $equipment[$line['equipment_id']]['rate'] * $length;
        $lineDiscount = round($total * $line['discount'] / 100);
        $subtotal += $total;
        $discount += $lineDiscount;
        $itemRows[] = $line + ['id' => $itemId, 'total' => $total, 'line_discount' => $lineDiscount];
        $sql[] = sprintf(
            'INSERT INTO booking_items (id, booking_id, equipment_id, package_id, quantity, daily_rate, line_total, discount_amount) VALUES (%d, %d, %d, %s, %d, %.2f, %.2f, %.2f);',
            $itemId, $bookingId, $line['equipment_id'], $line['package_id'] === null ? 'NULL' : (string) $line['package_id'],
            $line['quantity'], $equipment[$line['equipment_id']]['rate'], $total, $lineDiscount,
        );
    }
    $grand = $subtotal - $discount;

    $paid = !in_array($scenario, ['expired', 'pending'], true) && !($scenario === 'cancelled' && mt_rand(0, 1) === 0);
    $status = match ($scenario) {
        'completed' => 'completed', 'cancelled' => 'cancelled', 'expired' => 'expired', 'picked_up' => 'picked_up',
        'returned_damage' => 'returned', 'pending' => 'pending_payment',
        'confirmed', 'cancel_pending', 'cancel_rejected' => 'confirmed',
    };
    $pickedUp = in_array($status, ['picked_up', 'returned', 'completed'], true);
    $returned = in_array($status, ['returned', 'completed'], true);
    $deposit = $pickedUp ? (mt_rand(0, 1) ? 100000 : 200000) : null;
    $depositStatus = $status === 'completed' ? 'returned' : ($pickedUp ? 'held' : 'not_recorded');

    array_unshift($sql, sprintf(
        'INSERT INTO bookings (id, code, user_id, customer_name, customer_email, customer_phone, start_date, end_date, total_days, subtotal, discount_total, grand_total, status, deposit_amount, deposit_status, deposit_note, payment_due_at, confirmed_at, picked_up_at, returned_at, completed_at, cancelled_at, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %d, %.2f, %.2f, %.2f, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s);',
        $bookingId, $q($code), $forUser ? (string) $userId : 'NULL', $q($name), $q($email), $q($phone),
        $day($start), $day($end), $length, $subtotal, $discount, $grand, $q($status),
        $deposit === null ? 'NULL' : (string) $deposit, $q($depositStatus), $deposit === null ? 'NULL' : $q('Uang tunai'),
        $day($created, $createdHour + 24),
        $paid ? $day($created, $createdHour + 1) : 'NULL',
        $pickedUp ? $day($start, 9) : 'NULL',
        $returned ? $day($end, 16) : 'NULL',
        $status === 'completed' ? $day($end, 17) : 'NULL',
        $status === 'cancelled' ? $day(min($start - 1, 0), 10) : 'NULL',
        $day($created, $createdHour), $day($created, $createdHour),
    ));

    if ($paid) {
        $paymentId++;
        $sql[] = sprintf(
            "INSERT INTO payments (id, booking_id, purpose, method, amount, status, reference, paid_at, created_at) VALUES (%d, %d, 'rental', %s, %.2f, 'paid', %s, %s, %s);",
            $paymentId, $bookingId, $q(['transfer', 'ewallet', 'transfer'][mt_rand(0, 2)]), $grand, $q('SIM-DEMO' . $paymentId),
            $day($created, $createdHour + 1), $day($created, $createdHour + 1),
        );
    }

    $reasons = ['Rencana perjalanan ditunda karena cuaca buruk.', 'Ada anggota rombongan yang sakit.', 'Jadwal kerja berubah mendadak.', 'Jalur pendakian ditutup sementara oleh pengelola.'];
    if ($status === 'cancelled') {
        $cancelId++;
        $sql[] = sprintf(
            "INSERT INTO cancellation_requests (id, booking_id, reason, status, requested_by, reviewed_by, review_note, refund_amount, refund_note, refund_status, created_at, reviewed_at) VALUES (%d, %d, %s, 'approved', %s, %d, %s, %s, %s, %s, %s, %s);",
            $cancelId, $bookingId, $q($reasons[array_rand($reasons)]), $forUser ? (string) $userId : 'NULL', $staffId,
            $q('Pembatalan disetujui.'), $paid ? sprintf('%.2f', $grand) : 'NULL',
            $q($paid ? 'Refund dicatat sesuai kesepakatan dengan penyewa (simulasi).' : 'Belum ada pembayaran.'),
            $q($paid ? 'processed' : 'not_applicable'), $day(min($start - 2, 0), 9), $day(min($start - 1, 0), 10),
        );
        $audit[] = [$staffId, 'cancellation.approve', $bookingId, "Menyetujui pembatalan {$code}; booking dibatalkan dan stok dilepas", min($start - 1, 0)];
    }
    if ($scenario === 'cancel_pending' || $scenario === 'cancel_rejected') {
        $cancelId++;
        $rejected = $scenario === 'cancel_rejected';
        $sql[] = sprintf(
            'INSERT INTO cancellation_requests (id, booking_id, reason, status, requested_by, reviewed_by, review_note, created_at, reviewed_at) VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %s);',
            $cancelId, $bookingId, $q($reasons[array_rand($reasons)]), $q($rejected ? 'rejected' : 'pending'),
            $forUser ? (string) $userId : 'NULL', $rejected ? (string) $staffId : 'NULL',
            $rejected ? $q('Alat sudah disiapkan; silakan ubah jadwal melalui WhatsApp.') : 'NULL',
            $day(0, -3), $rejected ? $day(0, -1) : 'NULL',
        );
    }

    if ($pickedUp) {
        $checklistId++;
        $sql[] = sprintf("INSERT INTO handover_checklists (id, booking_id, type, handled_by, notes, created_at) VALUES (%d, %d, 'pickup', %d, NULL, %s);",
            $checklistId, $bookingId, $staffId, $day($start, 9));
        foreach ($itemRows as $row) {
            $handoverItemId++;
            $sql[] = sprintf('INSERT INTO handover_items (id, checklist_id, booking_item_id, qty_good, qty_damaged, qty_lost) VALUES (%d, %d, %d, %d, 0, 0);',
                $handoverItemId, $checklistId, $row['id'], $row['quantity']);
        }
        $audit[] = [$staffId, 'booking.pickup', $bookingId, "Menyerahkan alat booking {$code}", $start];
    }
    if ($returned) {
        $checklistId++;
        $sql[] = sprintf("INSERT INTO handover_checklists (id, booking_id, type, handled_by, notes, created_at) VALUES (%d, %d, 'return', %d, NULL, %s);",
            $checklistId, $bookingId, $staffId, $day($end, 16));
        foreach ($itemRows as $i => $row) {
            $handoverItemId++;
            $damaged = ($scenario === 'returned_damage' && $i === 0) ? 1 : 0;
            $sql[] = sprintf('INSERT INTO handover_items (id, checklist_id, booking_item_id, qty_good, qty_damaged, qty_lost, note) VALUES (%d, %d, %d, %d, %d, 0, %s);',
                $handoverItemId, $checklistId, $row['id'], $row['quantity'] - $damaged, $damaged, $damaged ? $q('Rusak saat dikembalikan') : 'NULL');
            if ($damaged) {
                $damageId++;
                $sql[] = sprintf("INSERT INTO damage_reports (id, booking_id, booking_item_id, type, quantity, description, charge_amount, status, reported_by, created_at) VALUES (%d, %d, %d, 'damaged', 1, %s, NULL, 'unsettled', %d, %s);",
                    $damageId, $bookingId, $row['id'], $q('Resleting pintu rusak dan satu frame retak'), $staffId, $day($end, 16));
            }
        }
        $audit[] = [$staffId, 'booking.return', $bookingId, "Menerima pengembalian alat booking {$code}", $end];
        if ($status === 'completed') {
            $audit[] = [$staffId, 'booking.complete', $bookingId, "Menyelesaikan booking {$code}", $end];
        }
    }
    return true;
};

// Riwayat ~6 bulan: kebanyakan selesai, sebagian dibatalkan/kedaluwarsa.
for ($start = -178; $start <= -4; $start += mt_rand(1, 2)) {
    for ($n = mt_rand(1, 2); $n > 0; $n--) {
        $length = mt_rand(2, 4);
        if ($start + $length - 1 > -3) {
            break 2;
        }
        $roll = mt_rand(1, 100);
        $scenario = $roll <= 80 ? 'completed' : ($roll <= 92 ? 'cancelled' : 'expired');
        $addBooking($start, $length, $scenario);
    }
}

// Kondisi terkini untuk demonstrasi setiap status.
$addBooking(-2, 4, 'picked_up');                 // sedang disewa, kembali 1 hari lagi
$addBooking(-4, 3, 'picked_up');                 // terlambat dikembalikan (jatuh tempo kemarin)
$addBooking(-6, 3, 'returned_damage', true);     // dikembalikan, ada kerusakan belum tuntas (milik user demo)
$addBooking(0, 2, 'confirmed');                  // siap diambil hari ini
$addBooking(3, 3, 'confirmed', true);            // akan datang (milik user demo)
$addBooking(5, 2, 'cancel_pending', true);       // pengajuan pembatalan menunggu (milik user demo)
$addBooking(8, 2, 'cancel_rejected');            // pembatalan ditolak
$addBooking(6, 2, 'pending');                    // menunggu pembayaran
$addBooking(10, 3, 'confirmed');

// Catatan aktivitas staff.
$auditId = 9000;
$auditSql = [];
foreach ($audit as [$uid, $action, $entityId, $description, $offset]) {
    $auditId++;
    $auditSql[] = sprintf(
        "INSERT INTO audit_logs (id, user_id, action, entity_type, entity_id, description, ip_address, created_at) VALUES (%d, %d, %s, 'booking', %d, %s, '127.0.0.1', %s);",
        $auditId, $uid, $q($action), $entityId, $q($description), $day($offset, mt_rand(9, 17)),
    );
}
$auditSql[] = sprintf("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, created_at) VALUES (%d, 'equipment.update', 'equipment', 1, 'Mengubah alat Tenda Dome 2 Orang', '127.0.0.1', %s);", $staffId, $day(-30, 10));
$auditSql[] = sprintf("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, created_at) VALUES (%d, 'user.create', 'user', %d, 'Membuat akun staff@demo.test sebagai Staff', '127.0.0.1', %s);", $adminId, $staffId, $day(-180, 9));

// Booking harus disisipkan sebelum tabel anak; array_unshift menaruh semua INSERT bookings di depan.
$bookingsSql = array_values(array_filter($sql, static fn ($s) => str_starts_with($s, 'INSERT INTO bookings')));
$otherSql = array_values(array_filter($sql, static fn ($s) => !str_starts_with($s, 'INSERT INTO bookings')));

echo "-- =====================================================================\n";
echo "-- CampGear — data transaksi DEMO (dibuat oleh database/tools/generate-demo.php)\n";
echo "-- Tanggal relatif terhadap CURDATE(); jalankan pada database BARU setelah\n";
echo "-- schema.sql, seed.sql, dan seed_articles.sql. Kode booking diawali CGDEMO-.\n";
echo "-- =====================================================================\n\n";
echo "SET NAMES utf8mb4;\n\n";
echo implode("\n", array_reverse($bookingsSql)), "\n\n";
echo implode("\n", $otherSql), "\n\n";
echo implode("\n", $auditSql), "\n";
