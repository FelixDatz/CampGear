<?php
/**
 * Tiket ekspedisi untuk booking asli (halaman penyewa dan panel staff).
 * Menyiapkan isi tiket dari data booking lalu memakai partial "ticket".
 *
 * @var array $booking
 * @var array $items
 * @var App\Enums\BookingStatus $status
 * @var string|null $tag  elemen kode booking: 'h1' (halaman penyewa) atau 'p' (panel staff)
 */
use App\Enums\BookingStatus;

// Isi tiket: nama paket, ditambah jumlah unit alat satuan.
$packageNames = array_values(array_unique(array_filter(array_column($items, 'package_name'))));
$looseUnits = 0;
foreach ($items as $item) {
    $looseUnits += $item['package_name'] ? 0 : (int) $item['quantity'];
}
$isi = implode(' + ', $packageNames);
if ($looseUnits > 0) {
    $isi .= ($isi !== '' ? ' + ' : '') . $looseUnits . ' alat';
}

$inPanel = ($tag ?? 'h1') !== 'h1';
[$stub, $stubIcon, $stubText] = match ($status) {
    // Di panel staff QR tidak perlu ditampilkan; staf yang memindainya.
    BookingStatus::Confirmed      => $inPanel ? ['icon', 'bi-box-arrow-up', 'Siap diambil'] : ['qr', null, null],
    BookingStatus::PendingPayment => ['locked', null, null],
    BookingStatus::PickedUp       => ['icon', 'bi-backpack', 'Alat sedang dibawa'],
    BookingStatus::Returned       => ['icon', 'bi-arrow-return-left', 'Alat sudah kembali'],
    BookingStatus::Completed      => ['icon', 'bi-check2-circle', 'Perjalanan selesai'],
    default                       => ['icon', 'bi-slash-circle', 'Tiket tidak berlaku'],
};

App\Core\View::partial('ticket', ['t' => [
    'code'     => $booking['code'],
    'tag'      => $tag ?? 'h1',
    'start'    => tanggal_singkat($booking['start_date']),
    'startSub' => hari($booking['start_date']) . ' ' . date('Y', strtotime($booking['start_date'])),
    'end'      => tanggal_singkat($booking['end_date']),
    'endSub'   => hari($booking['end_date']) . ' · paling lambat',
    'days'     => (int) $booking['total_days'] . ' hari',
    'isi'      => $isi,
    'total'    => rupiah($booking['grand_total']),
    'stamp'    => $status->stamp(),
    'tone'     => $status->tone(),
    'stub'     => $stub,
    'stubIcon' => $stubIcon,
    'stubText' => $stubText,
]]);
