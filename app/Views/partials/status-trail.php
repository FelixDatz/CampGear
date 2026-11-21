<?php
/**
 * Jejak status booking: Menunggu Pembayaran → Terkonfirmasi → Sedang Disewa →
 * Sudah Dikembalikan → Selesai. Kedaluwarsa dan Dibatalkan menghentikan jejak di
 * langkah terakhir yang sempat dicapai (dilihat dari waktu yang tercatat).
 *
 * @var array $booking
 * @var App\Enums\BookingStatus $status
 */
use App\Enums\BookingStatus;

$steps = BookingStatus::journey();
$times = [$booking['created_at'], $booking['confirmed_at'], $booking['picked_up_at'], $booking['returned_at'], $booking['completed_at']];
$stopped = in_array($status, [BookingStatus::Expired, BookingStatus::Cancelled], true);
$now = $stopped ? -1 : (int) array_search($status, $steps, true);
$reached = $now;
if ($stopped) {
    foreach ($times as $i => $time) {
        if ($time !== null) {
            $reached = $i;
        }
    }
}
?>
<ol class="status-trail<?= $stopped ? ' is-stopped' : '' ?>" style="--prog: <?= round(max(0, $reached) / (count($steps) - 1), 2) ?>" aria-label="Perjalanan status booking">
    <?php foreach ($steps as $i => $step):
        $class = ($i < $reached || ($stopped && $i === $reached)) ? 'is-done' : ($i === $now ? 'is-now' : '');
    ?>
        <li class="<?= $class ?>"<?= $i === $now ? ' aria-current="step"' : '' ?>><span class="st-dot" aria-hidden="true"></span><span class="st-label"><?= e($step->label()) ?></span></li>
    <?php endforeach ?>
</ol>
