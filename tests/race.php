<?php

declare(strict_types=1);

/**
 * Pekerja uji race condition (dipanggil oleh tests/race.sh, bukan untuk web).
 *   php tests/race.php <nama_pekerja> <jumlah_putaran> <waktu_mulai_unix>
 *
 * Dua pekerja memesan 2 unit Tenda Family 6 Orang (stok 3) pada tanggal yang sama
 * secara bersamaan. Karena 2 + 2 > 3, tepat satu pekerja boleh berhasil per tanggal.
 * Konfigurasi database diambil dari CAMPGEAR_CONFIG (database uji).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Exceptions\BusinessRuleException;
use App\Models\Booking;

[, $worker, $rounds, $startAt] = $argv;
$equipmentId = 3;
$quantity = 2;

while (microtime(true) < (float) $startAt) {
    usleep(200);
}

$won = 0;
for ($i = 0; $i < (int) $rounds; $i++) {
    // Sinkronkan kedua pekerja di setiap putaran agar benar-benar bersamaan.
    $tick = (float) $startAt + 1 + $i * 0.25;
    while (microtime(true) < $tick) {
        usleep(100);
    }
    $date = date('Y-m-d', strtotime('+' . (2 + $i) . ' days'));
    try {
        Booking::create(
            ['name' => 'Race ' . $worker, 'email' => 'race@test.local', 'phone' => '081100000000', 'notes' => null],
            $date,
            $date,
            [['equipment_id' => $equipmentId, 'quantity' => $quantity, 'package_id' => null, 'discount_percent' => 0]],
        );
        $won++;
    } catch (BusinessRuleException) {
        // Stok habis: perilaku yang benar untuk pekerja yang kalah.
    }
}
echo "{$worker}={$won}\n";
