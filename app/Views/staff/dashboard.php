<?php
/**
 * Dashboard staff: sapaan + ringkasan hari ini, angka utama, agenda serah terima,
 * grafik, dan transaksi terbaru.
 *
 * @var array $stats
 * @var array $pickups
 * @var array $returns
 * @var array $latest
 */
use App\Enums\BookingStatus;

$today = date('Y-m-d');
$user = auth_user();
$hour = (int) date('G');
$greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 18 ? 'Selamat sore' : 'Selamat malam'));
$late = count(array_filter($returns, static fn ($r) => $r['end_date'] < $today));
$summary = [];
$summary[] = count($pickups) . ' booking siap diambil';
$summary[] = count($returns) . ' jatuh tempo kembali' . ($late > 0 ? " ({$late} terlambat)" : '');
if ((int) $stats['pending_cancellations'] > 0) {
    $summary[] = (int) $stats['pending_cancellations'] . ' pembatalan menunggu';
}
$cards = [
    ['Ambil hari ini', (string) $stats['pickups_today'], 'bi-box-arrow-up', 'accent', '/staff/booking?view=pickup'],
    ['Sedang disewa', (string) $stats['active_rentals'], 'bi-backpack', 'ok', '/staff/booking?view=active'],
    ['Pembatalan menunggu', (string) $stats['pending_cancellations'], 'bi-hourglass-split', (int) $stats['pending_cancellations'] > 0 ? 'warn' : '', '/staff/pembatalan'],
    ['Penggantian belum tuntas', (string) $stats['unsettled_damages'], 'bi-shield-exclamation', (int) $stats['unsettled_damages'] > 0 ? 'bad' : '', '/staff/kerusakan'],
    ['Pendapatan bulan ini', rupiah($stats['revenue_month']), 'bi-cash-stack', '', '/staff/booking?status=completed'],
];
?>
<div class="dash">
    <section class="dash-hello">
        <div>
            <p class="eyebrow">Hari ini</p>
            <h2><?= e($greeting) ?>, <?= e(explode(' ', $user['name'])[0]) ?>.</h2>
            <p><?= e(implode(' · ', $summary)) ?>.</p>
        </div>
        <div class="dash-actions">
            <a class="btn btn-outline-secondary" href="<?= url('/staff/booking') ?>"><i class="bi bi-search me-1" aria-hidden="true"></i> Cari booking</a>
            <a class="btn btn-cta" href="<?= url('/sewa') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Booking untuk pelanggan</a>
        </div>
    </section>

    <div class="kpi-grid">
        <?php foreach ($cards as [$label, $value, $icon, $tone, $link]): ?>
            <a class="kpi" href="<?= url($link) ?>"<?= $tone !== '' ? ' data-tone="' . $tone . '"' : '' ?>>
                <span class="kpi-label"><i class="bi <?= $icon ?>" aria-hidden="true"></i><?= e($label) ?></span>
                <span class="kpi-value<?= str_starts_with($value, 'Rp') ? ' is-money' : '' ?>"><?= e($value) ?></span>
                <i class="bi bi-arrow-up-right kpi-go" aria-hidden="true"></i>
            </a>
        <?php endforeach ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="agenda-head">
                        <h2 class="h6 mb-0"><i class="bi bi-box-arrow-up text-primary me-1" aria-hidden="true"></i> Siap diambil hari ini</h2>
                        <span class="count"><?= count($pickups) ?> booking</span>
                    </div>
                    <?php if ($pickups === []): ?>
                        <p class="agenda-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i> Tidak ada pengambilan terjadwal.</p>
                    <?php else: ?>
                        <ul class="agenda">
                            <?php foreach ($pickups as $row): ?>
                                <li>
                                    <span><a class="code" href="<?= url('/staff/booking/' . $row['code']) ?>"><?= e($row['code']) ?></a>
                                        <span class="who"><?= e($row['customer_name']) ?> · <?= e($row['customer_phone']) ?></span></span>
                                    <span class="small text-nowrap"><?= e(tanggal_singkat($row['start_date'])) ?> → <?= e(tanggal_singkat($row['end_date'])) ?></span>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="agenda-head">
                        <h2 class="h6 mb-0"><i class="bi bi-arrow-return-left text-primary me-1" aria-hidden="true"></i> Jatuh tempo pengembalian</h2>
                        <span class="count"><?= count($returns) ?> booking</span>
                    </div>
                    <?php if ($returns === []): ?>
                        <p class="agenda-empty"><i class="bi bi-check2-circle" aria-hidden="true"></i> Tidak ada alat yang jatuh tempo hari ini.</p>
                    <?php else: ?>
                        <ul class="agenda">
                            <?php foreach ($returns as $row): ?>
                                <li>
                                    <span><a class="code" href="<?= url('/staff/booking/' . $row['code']) ?>"><?= e($row['code']) ?></a>
                                        <span class="who"><?= e($row['customer_name']) ?> · <?= e($row['customer_phone']) ?></span></span>
                                    <?php if ($row['end_date'] < $today): ?>
                                        <span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Terlambat sejak <?= e(tanggal($row['end_date'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning">Hari ini</span>
                                    <?php endif ?>
                                </li>
                            <?php endforeach ?>
                        </ul>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>

    <div class="page-actions">
        <div>
            <p class="eyebrow">Grafik</p>
            <h2 class="h5 mb-0">Penyewaan, pembatalan, dan pemakaian stok</h2>
        </div>
    </div>
    <div class="mb-4">
        <?php App\Core\View::partial('stats-panel', ['showRevenue' => true]) ?>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="agenda-head mb-3">
                <h2 class="h6 mb-0">Transaksi Terbaru</h2>
                <a class="small fw-semibold" href="<?= url('/staff/booking') ?>">Semua booking <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <?php if ($latest === []): ?>
                <div class="empty-state py-4">
                    <i class="bi bi-receipt"></i>
                    <p class="text-muted mb-0">Belum ada transaksi. Booking baru akan muncul di sini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr><th>Kode</th><th>Penyewa</th><th>Tanggal sewa</th><th class="text-end">Unit</th><th class="text-end">Total</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($latest as $booking): $status = BookingStatus::from($booking['status']); ?>
                            <tr>
                                <td><a class="font-monospace fw-semibold" href="<?= url('/staff/booking/' . $booking['code']) ?>"><?= e($booking['code']) ?></a></td>
                                <td><?= e($booking['customer_name']) ?><div class="small text-muted"><?= e($booking['customer_phone']) ?></div></td>
                                <td class="text-nowrap"><?= e(tanggal_singkat($booking['start_date'])) ?> → <?= e(tanggal_singkat($booking['end_date'])) ?></td>
                                <td class="text-end"><?= (int) $booking['unit_count'] ?></td>
                                <td class="text-end text-nowrap"><?= rupiah($booking['grand_total']) ?></td>
                                <td><span class="badge <?= e($status->badgeClass()) ?>"><?= e($status->label()) ?></span></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
