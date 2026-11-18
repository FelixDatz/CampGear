<?php
/**
 * @var array $request
 * @var array $items
 * @var array $payments
 */
use App\Enums\BookingStatus;
use App\Models\CancellationRequest;

$bookingStatus = BookingStatus::from($request['booking_status']);
$paid = array_sum(array_map(
    static fn ($p) => $p['purpose'] === 'rental' && $p['status'] === 'paid' ? (float) $p['amount'] : 0.0,
    $payments,
));
$base = '/staff/pembatalan/' . $request['id'];
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/staff/pembatalan') ?>"><i class="bi bi-arrow-left"></i> Daftar pembatalan</a>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <h2 class="h6 mb-0">Alasan dari penyewa</h2>
                    <span class="badge <?= e(CancellationRequest::STATUS_BADGES[$request['status']]) ?>"><?= e(CancellationRequest::STATUS_LABELS[$request['status']]) ?></span>
                </div>
                <p class="mb-1"><?= nl2br(e($request['reason'])) ?></p>
                <p class="small text-muted mb-0">Diajukan <?= e(tanggal($request['created_at'], true, true)) ?><?= $request['requested_by'] ? ' oleh akun terdaftar' : ' melalui kode booking' ?></p>
            </div>
        </div>

        <?php if ($request['status'] === 'pending'): ?>
            <div class="card mb-3 border-success">
                <div class="card-body">
                    <h2 class="h6 text-success"><i class="bi bi-check2-circle me-1"></i> Setujui pembatalan</h2>
                    <p class="small text-muted">Booking akan berstatus <strong>Dibatalkan</strong> dan stok dilepas. Refund dan deposit hanya dicatat; nominal refund tidak dihitung otomatis.</p>
                    <form method="post" action="<?= url($base . '/setujui') ?>" data-confirm="Setujui pembatalan dan batalkan booking <?= e($request['code']) ?>?">
                        <?= csrf_field() ?>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small" for="refund_amount">Nominal refund (Rp, opsional)</label>
                                <input class="form-control<?= invalid('refund_amount') ?>" type="number" id="refund_amount" name="refund_amount"
                                       min="0" max="<?= (int) $paid ?>" step="1" value="<?= e(old('refund_amount')) ?>" <?= $paid <= 0 ? 'disabled placeholder="Belum ada pembayaran"' : '' ?>>
                                <?php if ($msg = error('refund_amount')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                                <div class="form-text">Sudah dibayar: <?= rupiah($paid) ?></div>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label small" for="refund_note">Catatan refund/deposit</label>
                                <textarea class="form-control<?= invalid('refund_note') ?>" id="refund_note" name="refund_note" rows="2" maxlength="500"><?= e(old('refund_note')) ?></textarea>
                                <?php if ($msg = error('refund_note')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label small" for="review_note">Catatan untuk penyewa (opsional)</label>
                                <input class="form-control" id="review_note" name="review_note" maxlength="500" value="<?= e(old('review_note')) ?>">
                            </div>
                        </div>
                        <button class="btn btn-success mt-3" type="submit">Setujui</button>
                    </form>
                </div>
            </div>

            <div class="card mb-3 border-danger">
                <div class="card-body">
                    <h2 class="h6 text-danger"><i class="bi bi-x-circle me-1"></i> Tolak pembatalan</h2>
                    <form method="post" action="<?= url($base . '/tolak') ?>" data-confirm="Tolak pengajuan pembatalan ini?">
                        <?= csrf_field() ?>
                        <label class="form-label small" for="reject_note">Alasan penolakan (dilihat penyewa)</label>
                        <textarea class="form-control<?= invalid('reject_note') ?>" id="reject_note" name="reject_note" rows="2" minlength="5" maxlength="500" required><?= e(old('reject_note')) ?></textarea>
                        <?php if ($msg = error('reject_note')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        <button class="btn btn-outline-danger mt-3" type="submit">Tolak</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Hasil peninjauan</h2>
                    <dl class="spec-list small mb-0">
                        <div><dt>Ditinjau oleh</dt><dd><?= e($request['reviewer_name'] ?? '-') ?></dd></div>
                        <div><dt>Waktu</dt><dd><?= e(tanggal($request['reviewed_at'], false, true)) ?></dd></div>
                        <div><dt>Catatan</dt><dd><?= e($request['review_note'] ?: '-') ?></dd></div>
                        <?php if ($request['status'] === 'approved'): ?>
                            <div><dt>Status refund</dt><dd><?= e(CancellationRequest::REFUND_LABELS[$request['refund_status']]) ?></dd></div>
                            <div><dt>Nominal refund</dt><dd><?= $request['refund_amount'] !== null ? rupiah($request['refund_amount']) : '-' ?></dd></div>
                            <div><dt>Catatan refund/deposit</dt><dd><?= e($request['refund_note'] ?: '-') ?></dd></div>
                        <?php endif ?>
                    </dl>
                    <?php if ($request['status'] === 'approved' && $request['refund_status'] === 'pending'): ?>
                        <form method="post" action="<?= url($base . '/refund') ?>" class="mt-3" data-confirm="Tandai refund sudah diproses?">
                            <?= csrf_field() ?>
                            <label class="form-label small" for="refund_note_done">Catatan proses refund (opsional)</label>
                            <input class="form-control mb-2" id="refund_note_done" name="refund_note" maxlength="500" placeholder="mis. ditransfer ke rekening penyewa (simulasi)">
                            <button class="btn btn-sm btn-primary" type="submit">Tandai refund diproses</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <h2 class="h6">Booking <a class="font-monospace" href="<?= url('/staff/booking/' . $request['code']) ?>"><?= e($request['code']) ?></a></h2>
                    <span class="badge <?= e($bookingStatus->badgeClass()) ?>"><?= e($bookingStatus->label()) ?></span>
                </div>
                <dl class="spec-list small">
                    <div><dt>Penyewa</dt><dd><?= e($request['customer_name']) ?></dd></div>
                    <div><dt>Kontak</dt><dd class="text-break"><?= e($request['customer_phone']) ?><br><?= e($request['customer_email']) ?></dd></div>
                    <div><dt>Tanggal sewa</dt><dd><?= e(tanggal($request['start_date'])) ?> – <?= e(tanggal($request['end_date'])) ?></dd></div>
                    <div><dt>Total sewa</dt><dd><?= rupiah($request['grand_total']) ?></dd></div>
                    <div><dt>Sudah dibayar</dt><dd><?= rupiah($paid) ?></dd></div>
                    <div><dt>Deposit</dt><dd><?= e(App\Models\Booking::DEPOSIT_LABELS[$request['deposit_status']]) ?><?= $request['deposit_amount'] !== null ? ' · ' . rupiah($request['deposit_amount']) : '' ?></dd></div>
                </dl>
                <h3 class="h6 small text-uppercase text-muted">Alat</h3>
                <ul class="small mb-0">
                    <?php foreach ($items as $item): ?>
                        <li><?= e($item['equipment_name']) ?> ×<?= (int) $item['quantity'] ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        </div>
    </div>
</div>
