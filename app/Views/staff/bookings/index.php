<?php
/**
 * @var array $filters
 * @var array $bookings
 * @var App\Core\Paginator $pager
 */
use App\Enums\BookingStatus;
use App\Models\Booking;
?>
<?php App\Core\View::partial('status-tabs', ['tabs' => Booking::STAFF_VIEWS, 'current' => $filters['view'], 'param' => 'view', 'path' => '/staff/booking']) ?>

<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get" action="<?= url('/staff/booking') ?>" role="search">
            <?php if ($filters['view'] !== ''): ?><input type="hidden" name="view" value="<?= e($filters['view']) ?>"><?php endif ?>
            <div class="col-md-6">
                <label class="visually-hidden" for="q">Cari</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="Kode, nama, email, atau nomor HP" maxlength="100">
            </div>
            <div class="col-8 col-md-4">
                <label class="visually-hidden" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (BookingStatus::cases() as $case): ?>
                        <option value="<?= e($case->value) ?>"<?= selected($filters['status'], $case->value) ?>><?= e($case->label()) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-4 col-md-2 d-grid">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Cari</button>
            </div>
        </form>

        <?php if ($bookings === []): ?>
            <div class="empty-state py-4">
                <i class="bi bi-journal-x"></i>
                <p class="text-muted mb-0">Tidak ada booking yang cocok dengan filter.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted"><th>Kode</th><th>Penyewa</th><th>Tanggal sewa</th><th class="text-end">Unit</th><th class="text-end">Total</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bookings as $booking): $status = BookingStatus::from($booking['status']); ?>
                        <tr>
                            <td><a class="font-monospace fw-semibold" href="<?= url('/staff/booking/' . $booking['code']) ?>"><?= e($booking['code']) ?></a>
                                <div class="small text-muted"><?= e(tanggal($booking['created_at'], false, true)) ?></div></td>
                            <td><?= e($booking['customer_name']) ?><div class="small text-muted"><?= e($booking['customer_phone']) ?></div></td>
                            <td class="text-nowrap"><?= e(tanggal($booking['start_date'])) ?> – <?= e(tanggal($booking['end_date'])) ?>
                                <div class="small text-muted"><?= (int) $booking['total_days'] ?> hari</div></td>
                            <td class="text-end"><?= (int) $booking['unit_count'] ?></td>
                            <td class="text-end text-nowrap"><?= rupiah($booking['grand_total']) ?></td>
                            <td>
                                <span class="badge <?= e($status->badgeClass()) ?>"><?= e($status->label()) ?></span>
                                <?php if ((int) $booking['pending_cancel'] > 0): ?><span class="badge text-bg-warning" title="Ada pengajuan pembatalan">Batal?</span><?php endif ?>
                                <?php if ((int) $booking['unsettled_damage'] > 0): ?><span class="badge text-bg-danger" title="Ada penggantian belum selesai"><i class="bi bi-shield-exclamation"></i></span><?php endif ?>
                                <?php if ($booking['status'] === 'picked_up' && $booking['end_date'] < date('Y-m-d')): ?><span class="badge text-bg-danger">Terlambat</span><?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
