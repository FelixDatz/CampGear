<?php
putenv("CAMPGEAR_CONFIG=" . sys_get_temp_dir() . '/config_test_phase2b.php');
require __DIR__ . '/../app/bootstrap.php';
use App\Core\Database;
$rows = Database::select(
    'SELECT p.id, p.status FROM payments p
     JOIN bookings b ON b.id = p.booking_id
     WHERE b.code = ?',
    ['CG000001-TST3']
);
echo "Payments for TST3:\n";
foreach ($rows as $r) {
    echo "  id={$r['id']} status={$r['status']}\n";
}
$b = Database::first('SELECT status FROM bookings WHERE code = ?', ['CG000001-TST3']);
echo "Booking TST3 status: " . $b['status'] . "\n";
