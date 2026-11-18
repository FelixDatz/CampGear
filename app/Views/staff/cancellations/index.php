<?php
/**
 * @var string $status
 * @var array $requests
 * @var App\Core\Paginator $pager
 */
use App\Enums\BookingStatus;
use App\Models\CancellationRequest;

$tabs = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'all' => 'Semua'];
?>
<?php App\Core\View::partial('status-tabs', ['tabs' => $tabs, 'current' => $status, 'path' => '/staff/pembatalan']) ?>

<div class="card">
    <div class="card-body">
        <?php if ($requests === []): ?>
            <div class="empty-state py-4">
                <i class="bi bi-inbox"></i>
                <p class="text-muted mb-0">Tidak ada pengajuan pembatalan<?= $status === 'pending' ? ' yang menunggu peninjauan' : '' ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="small text-muted"><th>#</th><th>Booking</th><th>Tanggal sewa</th><th>Alasan</th><th>Diajukan</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($requests as $request): $bookingStatus = BookingStatus::from($request['booking_status']); ?>
                        <tr>
                            <td class="text-muted"><?= (int) $request['id'] ?></td>
                            <td><a class="font-monospace fw-semibold" href="<?= url('/staff/booking/' . $request['code']) ?>"><?= e($request['code']) ?></a>
                                <div class="small text-muted"><?= e($request['customer_name']) ?> · <span class="badge <?= e($bookingStatus->badgeClass()) ?>"><?= e($bookingStatus->label()) ?></span></div></td>
                            <td class="text-nowrap small"><?= e(tanggal($request['start_date'])) ?> – <?= e(tanggal($request['end_date'])) ?></td>
                            <td class="small" style="min-width: 14rem"><?= e(excerpt($request['reason'], 90)) ?></td>
                            <td class="small text-nowrap"><?= e(tanggal($request['created_at'], false, true)) ?></td>
                            <td><span class="badge <?= e(CancellationRequest::STATUS_BADGES[$request['status']]) ?>"><?= e(CancellationRequest::STATUS_LABELS[$request['status']]) ?></span>
                                <?php if ($request['status'] === 'approved' && $request['refund_status'] === 'pending'): ?>
                                    <div><span class="badge text-bg-info">Refund perlu diproses</span></div>
                                <?php endif ?></td>
                            <td class="text-end"><a class="btn btn-sm <?= $request['status'] === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= url('/staff/pembatalan/' . $request['id']) ?>"><?= $request['status'] === 'pending' ? 'Tinjau' : 'Detail' ?></a></td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
