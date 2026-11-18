<?php
/**
 * @var array $stats
 * @var array $pickups
 * @var array $returns
 * @var array $latest
 */
$today = date('Y-m-d');
?>
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['Ambil hari ini', (string) $stats['pickups_today'], 'bi-box-arrow-up', 'primary', '/staff/booking?view=pickup'],
        ['Sedang disewa', (string) $stats['active_rentals'], 'bi-backpack', 'success', '/staff/booking?view=active'],
        ['Pembatalan menunggu', (string) $stats['pending_cancellations'], 'bi-hourglass-split', 'warning', '/staff/pembatalan'],
        ['Penggantian belum tuntas', (string) $stats['unsettled_damages'], 'bi-shield-exclamation', 'danger', '/staff/kerusakan'],
        ['Pendapatan bulan ini', rupiah($stats['revenue_month']), 'bi-cash-stack', 'info', '/staff/booking?status=completed'],
    ];
    foreach ($cards as [$label, $value, $icon, $tone, $link]): ?>
        <div class="col-6 col-md-4 col-xl">
            <a class="card stat-card h-100 text-reset text-decoration-none" href="<?= url($link) ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="stat-icon text-bg-<?= $tone ?>"><i class="bi <?= $icon ?>"></i></span>
                    <div class="min-w-0">
                        <div class="small text-muted"><?= e($label) ?></div>
                        <div class="fs-5 fw-bold text-truncate"><?= e($value) ?></div>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-box-arrow-up text-primary me-1"></i> Siap diambil hari ini</h2>
                <?php if ($pickups === []): ?>
                    <p class="small text-muted mb-0">Tidak ada pengambilan terjadwal.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($pickups as $row): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                <span><a class="font-monospace fw-semibold" href="<?= url('/staff/booking/' . $row['code']) ?>"><?= e($row['code']) ?></a>
                                    <span class="small text-muted d-block"><?= e($row['customer_name']) ?> · <?= e($row['customer_phone']) ?></span></span>
                                <span class="small text-nowrap"><?= e(tanggal($row['start_date'])) ?> – <?= e(tanggal($row['end_date'])) ?></span>
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
                <h2 class="h6"><i class="bi bi-arrow-return-left text-primary me-1"></i> Jatuh tempo pengembalian</h2>
                <?php if ($returns === []): ?>
                    <p class="small text-muted mb-0">Tidak ada alat yang jatuh tempo hari ini.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($returns as $row): ?>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                                <span><a class="font-monospace fw-semibold" href="<?= url('/staff/booking/' . $row['code']) ?>"><?= e($row['code']) ?></a>
                                    <span class="small text-muted d-block"><?= e($row['customer_name']) ?> · <?= e($row['customer_phone']) ?></span></span>
                                <?php if ($row['end_date'] < $today): ?>
                                    <span class="badge text-bg-danger"><i class="bi bi-exclamation-triangle"></i> Terlambat sejak <?= e(tanggal($row['end_date'])) ?></span>
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

<h2 class="h5 mb-3">Grafik</h2>
<div class="mb-4">
    <?php App\Core\View::partial('stats-panel', ['showRevenue' => true]) ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">Transaksi Terbaru</h2>
            <a class="small" href="<?= url('/staff/booking') ?>">Semua booking <i class="bi bi-arrow-right"></i></a>
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
                        <tr class="small text-muted">
                            <th>Kode</th><th>Penyewa</th><th>Tanggal sewa</th><th class="text-end">Unit</th><th class="text-end">Total</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($latest as $booking): $status = App\Enums\BookingStatus::from($booking['status']); ?>
                        <tr>
                            <td><a class="font-monospace fw-semibold" href="<?= url('/staff/booking/' . $booking['code']) ?>"><?= e($booking['code']) ?></a></td>
                            <td><?= e($booking['customer_name']) ?><div class="small text-muted"><?= e($booking['customer_phone']) ?></div></td>
                            <td class="text-nowrap"><?= e(tanggal($booking['start_date'])) ?> – <?= e(tanggal($booking['end_date'])) ?></td>
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
