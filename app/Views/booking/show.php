<?php
/**
 * @var array $booking
 * @var App\Enums\BookingStatus $status
 * @var array $items
 * @var array $payments
 * @var array $cancellations
 * @var string|null $cancelBlocker
 * @var array $damages
 * @var array $handovers
 * @var array $business
 */
use App\Enums\BookingStatus;

$methodLabels = ['transfer' => 'Transfer bank', 'ewallet' => 'E-wallet', 'cash' => 'Tunai'];
$depositLabels = ['not_recorded' => 'Belum dicatat', 'held' => 'Ditahan', 'returned' => 'Dikembalikan'];
$timeline = [
    ['Booking dibuat', $booking['created_at']],
    ['Pembayaran diterima', $booking['confirmed_at']],
    ['Alat diambil', $booking['picked_up_at']],
    ['Alat dikembalikan', $booking['returned_at']],
    ['Selesai', $booking['completed_at']],
];
if ($booking['cancelled_at']) {
    $timeline[] = ['Dibatalkan', $booking['cancelled_at']];
}
?>
<section class="page-header d-print-none">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item"><a href="<?= url('/cek-booking') ?>">Cek Booking</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= e($booking['code']) ?></li>
            </ol>
        </nav>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <h1 class="h2 mb-0">Booking <span class="font-monospace"><?= e($booking['code']) ?></span></h1>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-copy="<?= e($booking['code']) ?>" aria-label="Salin kode booking">
                <i class="bi bi-clipboard"></i> Salin
            </button>
            <span class="badge fs-6 <?= e($status->badgeClass()) ?>"><?= e($status->label()) ?></span>
        </div>
        <p class="text-muted mb-0 mt-1">Dibuat <?= e(tanggal($booking['created_at'], true, true)) ?></p>
    </div>
</section>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">

            <?php if ($status === BookingStatus::PendingPayment): ?>
                <div class="card border-warning mb-3 d-print-none">
                    <div class="card-body">
                        <h2 class="h5"><i class="bi bi-credit-card text-warning me-1"></i> Selesaikan pembayaran</h2>
                        <p class="mb-3">Bayar <strong><?= rupiah($booking['grand_total']) ?></strong> sebelum
                            <strong><?= e(tanggal($booking['payment_due_at'], true, true)) ?> WIB</strong>.
                            Lewat dari itu booking otomatis kedaluwarsa dan stok dilepas.</p>
                        <form method="post" action="<?= url('/booking/' . $booking['code'] . '/bayar') ?>">
                            <?= csrf_field() ?>
                            <div class="row g-2 mb-3">
                                <?php foreach (['transfer' => ['bi-bank', 'Transfer bank'], 'ewallet' => ['bi-wallet2', 'E-wallet']] as $value => [$icon, $label]): ?>
                                    <div class="col-sm-6">
                                        <input class="btn-check" type="radio" name="method" id="method-<?= $value ?>" value="<?= $value ?>" <?= $value === 'transfer' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary w-100 py-3" for="method-<?= $value ?>">
                                            <i class="bi <?= $icon ?> d-block fs-4 mb-1"></i><?= $label ?> (simulasi)
                                        </label>
                                    </div>
                                <?php endforeach ?>
                            </div>
                            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-lock me-1"></i> Bayar Sekarang</button>
                            <p class="small text-muted mt-2 mb-0">Ini simulasi pembayaran (sandbox). Tidak ada uang sungguhan yang ditransfer.</p>
                        </form>
                    </div>
                </div>
            <?php elseif ($status === BookingStatus::Expired): ?>
                <div class="alert alert-secondary d-flex gap-2">
                    <i class="bi bi-hourglass-bottom flex-shrink-0 mt-1"></i>
                    <div>Booking ini kedaluwarsa karena tidak dibayar sampai <?= e(tanggal($booking['payment_due_at'], true, true)) ?>. Stok sudah dilepas.
                        <a href="<?= url('/sewa') ?>">Buat booking baru</a>.</div>
                </div>
            <?php elseif ($status === BookingStatus::Cancelled): ?>
                <div class="alert alert-danger d-flex gap-2">
                    <i class="bi bi-x-octagon flex-shrink-0 mt-1"></i>
                    <div>Booking ini telah dibatalkan pada <?= e(tanggal($booking['cancelled_at'], true, true)) ?>.</div>
                </div>
            <?php elseif ($status === BookingStatus::PickedUp): ?>
                <div class="alert alert-info d-flex gap-2">
                    <i class="bi bi-backpack flex-shrink-0 mt-1"></i>
                    <div>Alat sedang kamu sewa. Kembalikan paling lambat <strong><?= e(tanggal($booking['end_date'], true)) ?></strong>.</div>
                </div>
            <?php elseif ($status === BookingStatus::Returned): ?>
                <?php $unsettled = count(array_filter($damages, static fn ($d) => $d['status'] === 'unsettled')); ?>
                <div class="alert <?= $unsettled > 0 ? 'alert-warning' : 'alert-secondary' ?> d-flex gap-2">
                    <i class="bi bi-arrow-return-left flex-shrink-0 mt-1"></i>
                    <div>Alat sudah dikembalikan pada <?= e(tanggal($booking['returned_at'], true, true)) ?>.
                        <?php if ($unsettled > 0): ?>
                            Ada <strong><?= $unsettled ?> laporan kerusakan/kehilangan</strong> yang wajib diselesaikan sebelum booking dinyatakan selesai.
                        <?php else: ?>
                            Booking akan dinyatakan selesai oleh staf.
                        <?php endif ?>
                    </div>
                </div>
            <?php elseif ($status === BookingStatus::Completed): ?>
                <div class="alert alert-success d-flex gap-2">
                    <i class="bi bi-check2-circle flex-shrink-0 mt-1"></i>
                    <div>Booking selesai. Terima kasih telah menyewa di <?= e(config('app.name')) ?>!</div>
                </div>
            <?php endif ?>

            <?php if ($status === BookingStatus::Confirmed): ?>
                <!-- Kartu pengambilan: yang tercetak saat tombol cetak ditekan -->
                <div class="card pickup-card mb-3" id="pickup-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div>
                                <div class="small text-uppercase fw-semibold text-muted"><?= e(config('app.name')) ?> · Kartu Pengambilan Alat</div>
                                <div class="display-6 font-monospace fw-bold my-1"><?= e($booking['code']) ?></div>
                                <span class="badge text-bg-primary"><i class="bi bi-patch-check"></i> Terkonfirmasi</span>
                            </div>
                            <div class="qr-box" data-qr="<?= e($booking['code']) ?>" role="img" aria-label="QR kode booking <?= e($booking['code']) ?>">
                                <noscript><span class="small">QR memerlukan JavaScript</span></noscript>
                            </div>
                        </div>

                        <dl class="row small mt-3 mb-0">
                            <dt class="col-sm-4 text-muted fw-normal">Nama penyewa</dt><dd class="col-sm-8"><?= e($booking['customer_name']) ?></dd>
                            <dt class="col-sm-4 text-muted fw-normal">Nomor HP</dt><dd class="col-sm-8"><?= e($booking['customer_phone']) ?></dd>
                            <dt class="col-sm-4 text-muted fw-normal">Ambil alat</dt><dd class="col-sm-8 fw-semibold"><?= e(tanggal($booking['start_date'], true)) ?></dd>
                            <dt class="col-sm-4 text-muted fw-normal">Kembalikan</dt><dd class="col-sm-8 fw-semibold"><?= e(tanggal($booking['end_date'], true)) ?></dd>
                            <dt class="col-sm-4 text-muted fw-normal">Lokasi</dt><dd class="col-sm-8"><?= e($business['address']) ?> (<?= e($business['hours']) ?>)</dd>
                        </dl>

                        <h3 class="h6 mt-3">Checklist serah terima</h3>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle checklist-table mb-2">
                                <thead class="table-light">
                                    <tr><th>Alat</th><th class="text-center">Jumlah</th><th class="text-center">Kondisi baik</th><th>Catatan</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= e($item['equipment_name']) ?><?php if ($item['package_name']): ?><div class="small text-muted"><?= e($item['package_name']) ?></div><?php endif ?></td>
                                        <td class="text-center"><?= (int) $item['quantity'] ?></td>
                                        <td class="text-center"><span class="check-box" aria-hidden="true"></span></td>
                                        <td class="checklist-note"></td>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mb-3">Tunjukkan kartu ini (layar atau cetak) kepada staf. Kondisi setiap alat diperiksa bersama dan dicatat staf di sistem.</p>
                        <div class="row text-center small signature-row">
                            <div class="col">Petugas<div class="signature-line"></div></div>
                            <div class="col">Penyewa<div class="signature-line"></div></div>
                        </div>
                        <button class="btn btn-outline-primary mt-3 d-print-none" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i> Cetak kartu</button>
                    </div>
                </div>
            <?php endif ?>

            <div class="card mb-3 d-print-none">
                <div class="card-body">
                    <h2 class="h5">Rincian alat</h2>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr class="small text-muted"><th>Alat</th><th class="text-end">Jumlah</th><th class="text-end">Tarif/hari</th><th class="text-end">Subtotal</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <a class="text-reset" href="<?= url('/alat/' . $item['equipment_slug']) ?>"><?= e($item['equipment_name']) ?></a>
                                        <?php if ($item['package_name']): ?><div class="small text-muted"><i class="bi bi-box2-heart"></i> <?= e($item['package_name']) ?></div><?php endif ?>
                                    </td>
                                    <td class="text-end"><?= (int) $item['quantity'] ?></td>
                                    <td class="text-end"><?= rupiah($item['daily_rate']) ?></td>
                                    <td class="text-end"><?= rupiah($item['line_total']) ?>
                                        <?php if ((float) $item['discount_amount'] > 0): ?><div class="small text-success">−<?= rupiah($item['discount_amount']) ?></div><?php endif ?>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($damages !== []): ?>
                <div class="card mb-3 d-print-none border-warning">
                    <div class="card-body">
                        <h2 class="h5"><i class="bi bi-shield-exclamation text-warning me-1"></i> Kerusakan &amp; kehilangan</h2>
                        <p class="small text-muted">Penyewa wajib melakukan penggantian. Bentuk dan nominal penggantian dicatat oleh staf.</p>
                        <?php App\Core\View::partial('damage-table', ['damages' => $damages, 'showCode' => false]) ?>
                    </div>
                </div>
            <?php endif ?>

            <?php if ($handovers !== []): ?>
                <div class="card mb-3 d-print-none">
                    <div class="card-body">
                        <h2 class="h5">Catatan serah terima</h2>
                        <?php App\Core\View::partial('handover-list', ['handovers' => $handovers]) ?>
                    </div>
                </div>
            <?php endif ?>

            <?php if ($cancellations !== [] || in_array($status, [BookingStatus::PendingPayment, BookingStatus::Confirmed], true)): ?>
                <div class="card mb-3 d-print-none" id="pembatalan">
                    <div class="card-body">
                        <h2 class="h5">Pembatalan</h2>
                        <?php foreach ($cancellations as $request): ?>
                            <div class="cancel-entry">
                                <div class="d-flex flex-wrap justify-content-between gap-2">
                                    <span class="small text-muted">Diajukan <?= e(tanggal($request['created_at'], false, true)) ?></span>
                                    <span class="badge <?= e(App\Models\CancellationRequest::STATUS_BADGES[$request['status']]) ?>"><?= e(App\Models\CancellationRequest::STATUS_LABELS[$request['status']]) ?></span>
                                </div>
                                <p class="mb-1 mt-1"><?= nl2br(e($request['reason'])) ?></p>
                                <?php if ($request['status'] !== 'pending'): ?>
                                    <div class="small bg-light rounded p-2">
                                        <div><strong>Tanggapan staf</strong> · <?= e(tanggal($request['reviewed_at'], false, true)) ?></div>
                                        <?php if ($request['review_note']): ?><div><?= nl2br(e($request['review_note'])) ?></div><?php endif ?>
                                        <?php if ($request['status'] === 'approved'): ?>
                                            <div class="mt-1">Refund: <?= e(App\Models\CancellationRequest::REFUND_LABELS[$request['refund_status']]) ?>
                                                <?php if ($request['refund_amount'] !== null): ?>· <?= rupiah($request['refund_amount']) ?><?php endif ?></div>
                                            <?php if ($request['refund_note']): ?><div class="text-muted"><?= nl2br(e($request['refund_note'])) ?></div><?php endif ?>
                                        <?php endif ?>
                                    </div>
                                <?php endif ?>
                            </div>
                        <?php endforeach ?>

                        <?php if ($cancelBlocker === null): ?>
                            <form method="post" action="<?= url('/booking/' . $booking['code'] . '/batal') ?>" class="mt-2"
                                  data-confirm="Kirim pengajuan pembatalan booking ini?">
                                <?= csrf_field() ?>
                                <label class="form-label" for="reason">Alasan pembatalan</label>
                                <textarea class="form-control<?= invalid('reason') ?>" id="reason" name="reason" rows="3"
                                          minlength="10" maxlength="1000" required placeholder="Jelaskan alasan pembatalan (minimal 10 karakter)"><?= e(old('reason')) ?></textarea>
                                <?php if ($msg = error('reason')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                                <div class="form-text mb-2">Pengajuan ditinjau staf (menunggu → disetujui/ditolak). Jika disetujui, booking dibatalkan dan catatan refund/deposit dicatat staf.</div>
                                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-circle me-1"></i> Ajukan Pembatalan</button>
                            </form>
                        <?php elseif (array_filter($cancellations, static fn ($c) => $c['status'] === 'pending') === []): ?>
                            <p class="small text-muted mb-0 mt-2"><i class="bi bi-info-circle"></i> <?= e($cancelBlocker) ?></p>
                        <?php endif ?>
                    </div>
                </div>
            <?php endif ?>
        </div>

        <div class="col-lg-4 d-print-none">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5">Ringkasan</h2>
                    <dl class="summary-totals mb-0">
                        <div><dt>Tanggal</dt><dd><?= e(tanggal($booking['start_date'])) ?> – <?= e(tanggal($booking['end_date'])) ?></dd></div>
                        <div><dt>Durasi</dt><dd><?= (int) $booking['total_days'] ?> hari</dd></div>
                        <div><dt>Subtotal</dt><dd><?= rupiah($booking['subtotal']) ?></dd></div>
                        <?php if ((float) $booking['discount_total'] > 0): ?>
                            <div class="text-success"><dt>Diskon paket</dt><dd>−<?= rupiah($booking['discount_total']) ?></dd></div>
                        <?php endif ?>
                        <div class="fs-5 fw-bold"><dt>Total</dt><dd><?= rupiah($booking['grand_total']) ?></dd></div>
                    </dl>
                    <?php foreach ($payments as $payment): ?>
                        <div class="small border-top pt-2 mt-2">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <?= e($methodLabels[$payment['method']] ?? $payment['method']) ?> · <?= rupiah($payment['amount']) ?>
                            <div class="text-muted">Ref. <?= e($payment['reference']) ?> · <?= e(tanggal($payment['paid_at'], false, true)) ?></div>
                        </div>
                    <?php endforeach ?>
                    <?php if ($booking['deposit_status'] !== 'not_recorded'): ?>
                        <div class="small border-top pt-2 mt-2">
                            <span class="text-muted">Deposit:</span> <?= e($depositLabels[$booking['deposit_status']] ?? '') ?>
                            <?php if ($booking['deposit_amount'] !== null): ?>· <?= rupiah($booking['deposit_amount']) ?><?php endif ?>
                            <?php if ($booking['deposit_note']): ?><div class="text-muted"><?= e($booking['deposit_note']) ?></div><?php endif ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h5">Penyewa</h2>
                    <dl class="spec-list small mb-0">
                        <div><dt>Nama</dt><dd><?= e($booking['customer_name']) ?></dd></div>
                        <div><dt>Email</dt><dd class="text-break"><?= e($booking['customer_email']) ?></dd></div>
                        <div><dt>Nomor HP</dt><dd><?= e($booking['customer_phone']) ?></dd></div>
                    </dl>
                    <?php if ($booking['notes']): ?>
                        <p class="small text-muted mt-2 mb-0"><i class="bi bi-chat-left-text"></i> <?= e($booking['notes']) ?></p>
                    <?php endif ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Riwayat status</h2>
                    <ol class="timeline small mb-0">
                        <?php foreach ($timeline as [$label, $time]): if ($time === null) continue; ?>
                            <li><div class="fw-semibold"><?= e($label) ?></div><div class="text-muted"><?= e(tanggal($time, false, true)) ?></div></li>
                        <?php endforeach ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
