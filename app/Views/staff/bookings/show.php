<?php
/**
 * @var array $booking
 * @var App\Enums\BookingStatus $status
 * @var array $items
 * @var array $payments
 * @var array $cancellations
 * @var array $damages
 * @var array $handovers
 * @var bool  $canPickup
 */
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CancellationRequest;

$code = $booking['code'];
$base = '/staff/booking/' . $code;
$unsettled = count(array_filter($damages, static fn ($d) => $d['status'] === 'unsettled'));
$pendingCancel = array_values(array_filter($cancellations, static fn ($c) => $c['status'] === 'pending'));
$methodLabels = ['transfer' => 'Transfer', 'ewallet' => 'E-wallet', 'cash' => 'Tunai'];
$isLate = $booking['status'] === 'picked_up' && $booking['end_date'] < date('Y-m-d');

// Langkah berikutnya bagi staf, sesuai status booking.
[$nextTitle, $nextText, $nextTone] = match ($status) {
    BookingStatus::PendingPayment => ['Tunggu pembayaran', 'Batas bayar ' . tanggal($booking['payment_due_at'], true, true) . '. Bila penyewa membayar tunai di lokasi, catat di bawah.', 'warn'],
    BookingStatus::Confirmed      => $canPickup
        ? ['Serah terima pengambilan', 'Periksa setiap alat bersama penyewa, isi checklist, catat deposit bila ada, lalu serahkan alat.', 'accent']
        : ['Belum bisa diambil', date('Y-m-d') < $booking['start_date'] ? 'Pengambilan dapat dicatat mulai ' . tanggal($booking['start_date'], true) . '.' : 'Periode sewa sudah lewat tanpa pengambilan alat.', 'muted'],
    BookingStatus::PickedUp       => ['Terima pengembalian', 'Jatuh tempo ' . tanggal($booking['end_date'], true) . ($isLate ? '. Alat terlambat dikembalikan.' : '. Isi checklist pengembalian saat alat diterima.'), $isLate ? 'bad' : 'accent'],
    BookingStatus::Returned       => $unsettled > 0
        ? ['Selesaikan penggantian', $unsettled . ' laporan kerusakan/kehilangan belum tuntas. Booking baru bisa diselesaikan setelah semuanya tuntas.', 'bad']
        : ['Selesaikan booking', 'Perbarui status deposit bila perlu, lalu tandai booking selesai.', 'accent'],
    BookingStatus::Completed      => ['Tidak ada tindakan', 'Booking selesai pada ' . tanggal($booking['completed_at'], true, true) . '.', 'ok'],
    BookingStatus::Cancelled      => ['Booking dibatalkan', 'Dibatalkan pada ' . tanggal($booking['cancelled_at'], true, true) . '. Stok sudah dilepas.', 'bad'],
    BookingStatus::Expired        => ['Booking kedaluwarsa', 'Tidak dibayar sampai ' . tanggal($booking['payment_due_at'], true, true) . '. Stok sudah dilepas.', 'muted'],
};
?>
<div class="page-actions">
    <a class="btn btn-sm btn-outline-secondary" href="<?= url('/staff/booking') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar booking</a>
    <a class="btn btn-sm btn-outline-primary" href="<?= url('/booking/' . $code) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Halaman penyewa</a>
</div>

<section class="staff-ticket" aria-label="Tiket booking">
    <?php App\Core\View::partial('booking-ticket', ['booking' => $booking, 'items' => $items, 'status' => $status, 'tag' => 'p']) ?>
    <div class="status">
        <?php App\Core\View::partial('status-trail', ['booking' => $booking, 'status' => $status]) ?>
        <div class="next-step" data-tone="<?= e($nextTone) ?>">
            <span class="eyebrow">Langkah berikutnya</span>
            <b><?= e($nextTitle) ?></b>
            <span><?= e($nextText) ?></span>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge <?= e($status->badgeClass()) ?>"><?= e($status->label()) ?></span>
            <?php if ($isLate): ?><span class="badge text-bg-danger">Terlambat dikembalikan</span><?php endif ?>
            <span class="badge text-bg-light border"><?= $booking['user_id'] ? 'Akun terdaftar' : 'Tamu' ?></span>
        </div>
    </div>
</section>

<?php if ($pendingCancel !== []): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>Penyewa mengajukan pembatalan yang menunggu peninjauan.</span>
        <a class="btn btn-sm btn-warning ms-auto" href="<?= url('/staff/pembatalan/' . $pendingCancel[0]['id']) ?>">Tinjau pengajuan</a>
    </div>
<?php endif ?>

<div class="row g-3">
    <div class="col-xl-8">

        <?php /* ---- Aksi sesuai status ---- */ ?>
        <?php if ($status === BookingStatus::PendingPayment): ?>
            <div class="card mb-3 border-warning">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-cash-coin me-1"></i> Menunggu pembayaran</h2>
                    <p class="small mb-3">Batas bayar <?= e(tanggal($booking['payment_due_at'], true, true)) ?>. Bila penyewa membayar tunai di lokasi, catat di sini.</p>
                    <form method="post" action="<?= url($base . '/bayar') ?>" data-confirm="Catat pembayaran tunai <?= e(rupiah($booking['grand_total'])) ?>?">
                        <?= csrf_field() ?>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i> Terima tunai <?= rupiah($booking['grand_total']) ?></button>
                    </form>
                </div>
            </div>

        <?php elseif ($status === BookingStatus::Confirmed): ?>
            <div class="card mb-3 border-primary">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-box-arrow-up me-1"></i> Serah terima: pengambilan alat</h2>
                    <?php if (!$canPickup): ?>
                        <p class="small text-muted mb-0">
                            <?= date('Y-m-d') < $booking['start_date']
                                ? 'Pengambilan dapat dicatat mulai ' . e(tanggal($booking['start_date'], true)) . '.'
                                : 'Periode sewa sudah lewat tanpa pengambilan alat.' ?>
                        </p>
                    <?php else: ?>
                        <form method="post" action="<?= url($base . '/ambil') ?>" class="handover-form" data-confirm="Simpan checklist pengambilan dan serahkan alat?">
                            <?= csrf_field() ?>
                            <p class="small text-muted">Periksa setiap alat bersama penyewa. Jumlah <em>baik + cacat</em> harus sama dengan jumlah disewa. Catat cacat yang sudah ada sebelum alat diserahkan.</p>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle handover-table">
                                    <thead><tr class="small text-muted"><th>Alat</th><th class="text-center">Disewa</th><th>Baik</th><th>Cacat</th><th>Catatan</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($items as $item): $id = (int) $item['id']; $qty = (int) $item['quantity']; ?>
                                        <tr data-handover-row data-qty="<?= $qty ?>">
                                            <td><?= e($item['equipment_name']) ?><?php if ($item['package_name']): ?><div class="small text-muted"><?= e($item['package_name']) ?></div><?php endif ?></td>
                                            <td class="text-center fw-semibold"><?= $qty ?></td>
                                            <td><input class="form-control form-control-sm qty-input" type="number" name="items[<?= $id ?>][good]" value="<?= $qty ?>" min="0" max="<?= $qty ?>" data-role="good" aria-label="Jumlah baik <?= e($item['equipment_name']) ?>"></td>
                                            <td><input class="form-control form-control-sm qty-input" type="number" name="items[<?= $id ?>][damaged]" value="0" min="0" max="<?= $qty ?>" data-role="damaged" aria-label="Jumlah cacat <?= e($item['equipment_name']) ?>"></td>
                                            <td><input class="form-control form-control-sm" name="items[<?= $id ?>][note]" maxlength="255" aria-label="Catatan <?= e($item['equipment_name']) ?>"></td>
                                        </tr>
                                    <?php endforeach ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small" for="deposit_amount">Deposit diterima (Rp, opsional)</label>
                                    <input class="form-control" type="number" id="deposit_amount" name="deposit_amount" min="0" step="1" inputmode="numeric">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small" for="deposit_note">Catatan deposit (mis. KTP ditahan / uang tunai)</label>
                                    <input class="form-control" id="deposit_note" name="deposit_note" maxlength="255">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small" for="pickup_notes">Catatan serah terima</label>
                                    <textarea class="form-control" id="pickup_notes" name="notes" rows="2" maxlength="500"></textarea>
                                </div>
                            </div>
                            <div class="handover-error alert alert-danger small py-2 mt-2 d-none" role="alert"></div>
                            <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-box-arrow-up me-1"></i> Serahkan alat</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>

        <?php elseif ($status === BookingStatus::PickedUp): ?>
            <div class="card mb-3 border-info">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-arrow-return-left me-1"></i> Serah terima: pengembalian alat</h2>
                    <form method="post" action="<?= url($base . '/kembali') ?>" class="handover-form" data-confirm="Simpan checklist pengembalian?">
                        <?= csrf_field() ?>
                        <p class="small text-muted">Isi jumlah <em>baik</em>, <em>rusak</em>, dan <em>hilang</em> untuk setiap alat. Unit rusak/hilang otomatis menjadi laporan yang wajib diselesaikan penyewa. Nominal penggantian boleh dikosongkan dan diisi kemudian.</p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle handover-table">
                                <thead><tr class="small text-muted"><th>Alat</th><th class="text-center">Disewa</th><th>Baik</th><th>Rusak</th><th>Hilang</th><th>Catatan</th></tr></thead>
                                <tbody>
                                <?php foreach ($items as $item): $id = (int) $item['id']; $qty = (int) $item['quantity']; ?>
                                    <tr data-handover-row data-qty="<?= $qty ?>">
                                        <td><?= e($item['equipment_name']) ?><?php if ($item['package_name']): ?><div class="small text-muted"><?= e($item['package_name']) ?></div><?php endif ?></td>
                                        <td class="text-center fw-semibold"><?= $qty ?></td>
                                        <td><input class="form-control form-control-sm qty-input" type="number" name="items[<?= $id ?>][good]" value="<?= $qty ?>" min="0" max="<?= $qty ?>" data-role="good" aria-label="Baik <?= e($item['equipment_name']) ?>"></td>
                                        <td><input class="form-control form-control-sm qty-input" type="number" name="items[<?= $id ?>][damaged]" value="0" min="0" max="<?= $qty ?>" data-role="damaged" aria-label="Rusak <?= e($item['equipment_name']) ?>"></td>
                                        <td><input class="form-control form-control-sm qty-input" type="number" name="items[<?= $id ?>][lost]" value="0" min="0" max="<?= $qty ?>" data-role="lost" aria-label="Hilang <?= e($item['equipment_name']) ?>"></td>
                                        <td><input class="form-control form-control-sm" name="items[<?= $id ?>][note]" maxlength="255" aria-label="Catatan <?= e($item['equipment_name']) ?>"></td>
                                    </tr>
                                    <tr class="damage-detail d-none" data-detail-for>
                                        <td colspan="6" class="bg-light">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Keterangan kerusakan/kehilangan</label>
                                                    <input class="form-control form-control-sm" name="items[<?= $id ?>][description]" maxlength="500" placeholder="mis. frame patah, flysheet sobek">
                                                </div>
                                                <div class="col-6 col-md-3" data-charge="damaged">
                                                    <label class="form-label small mb-1">Penggantian rusak (Rp)</label>
                                                    <input class="form-control form-control-sm" type="number" name="items[<?= $id ?>][charge_damaged]" min="0" step="1" placeholder="opsional">
                                                </div>
                                                <div class="col-6 col-md-3" data-charge="lost">
                                                    <label class="form-label small mb-1">Penggantian hilang (Rp)</label>
                                                    <input class="form-control form-control-sm" type="number" name="items[<?= $id ?>][charge_lost]" min="0" step="1" placeholder="opsional">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="reduce_stock" name="reduce_stock" value="1" checked>
                            <label class="form-check-label small" for="reduce_stock">Kurangi stok total sebanyak unit yang hilang</label>
                        </div>
                        <label class="form-label small" for="return_notes">Catatan serah terima</label>
                        <textarea class="form-control" id="return_notes" name="notes" rows="2" maxlength="500"></textarea>
                        <div class="handover-error alert alert-danger small py-2 mt-2 d-none" role="alert"></div>
                        <button class="btn btn-primary mt-3" type="submit"><i class="bi bi-arrow-return-left me-1"></i> Terima pengembalian</button>
                    </form>
                </div>
            </div>

        <?php elseif ($status === BookingStatus::Returned): ?>
            <div class="card mb-3 border-success">
                <div class="card-body">
                    <h2 class="h6"><i class="bi bi-check2-all me-1"></i> Selesaikan booking</h2>
                    <?php if ($unsettled > 0): ?>
                        <p class="small mb-2">Masih ada <strong><?= $unsettled ?> laporan kerusakan/kehilangan</strong> yang belum diselesaikan. Booking baru bisa diselesaikan setelah semua penggantian tuntas.</p>
                        <button class="btn btn-success" type="button" disabled>Selesaikan booking</button>
                    <?php else: ?>
                        <p class="small mb-2">Semua alat sudah kembali<?= $damages !== [] ? ' dan penggantian sudah tuntas' : '' ?>. Pastikan catatan deposit sudah diperbarui.</p>
                        <form method="post" action="<?= url($base . '/selesai') ?>" data-confirm="Nyatakan booking ini selesai?">
                            <?= csrf_field() ?>
                            <button class="btn btn-success" type="submit"><i class="bi bi-check2-all me-1"></i> Selesaikan booking</button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        <?php endif ?>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Rincian alat</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr class="small text-muted"><th>Alat</th><th class="text-end">Jumlah</th><th class="text-end">Tarif/hari</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['equipment_name']) ?><?php if ($item['package_name']): ?><div class="small text-muted"><?= e($item['package_name']) ?></div><?php endif ?></td>
                                <td class="text-end"><?= (int) $item['quantity'] ?></td>
                                <td class="text-end"><?= rupiah($item['daily_rate']) ?></td>
                                <td class="text-end"><?= rupiah($item['line_total']) ?><?php if ((float) $item['discount_amount'] > 0): ?><div class="small text-success">−<?= rupiah($item['discount_amount']) ?></div><?php endif ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($damages !== []): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Kerusakan &amp; kehilangan</h2>
                    <?php App\Core\View::partial('damage-table', ['damages' => $damages, 'showCode' => false, 'staffLinks' => true]) ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($handovers !== []): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Catatan serah terima</h2>
                    <?php App\Core\View::partial('handover-list', ['handovers' => $handovers]) ?>
                </div>
            </div>
        <?php endif ?>

        <?php if ($cancellations !== []): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Pengajuan pembatalan</h2>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($cancellations as $request): ?>
                            <li class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom">
                                <span><a href="<?= url('/staff/pembatalan/' . $request['id']) ?>">#<?= (int) $request['id'] ?></a>
                                    <span class="small text-muted">· <?= e(tanggal($request['created_at'], false, true)) ?></span>
                                    <div class="small"><?= e(excerpt($request['reason'], 100)) ?></div></span>
                                <span class="badge align-self-start <?= e(CancellationRequest::STATUS_BADGES[$request['status']]) ?>"><?= e(CancellationRequest::STATUS_LABELS[$request['status']]) ?></span>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
        <?php endif ?>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Penyewa</h2>
                <dl class="spec-list small mb-0">
                    <div><dt>Nama</dt><dd><?= e($booking['customer_name']) ?></dd></div>
                    <div><dt>Email</dt><dd class="text-break"><?= e($booking['customer_email']) ?></dd></div>
                    <div><dt>Nomor HP</dt><dd><a href="tel:<?= e($booking['customer_phone']) ?>"><?= e($booking['customer_phone']) ?></a></dd></div>
                    <div><dt>Akun</dt><dd><?= $booking['user_id'] ? 'Terdaftar' : 'Tamu' ?></dd></div>
                </dl>
                <?php if ($booking['notes']): ?><p class="small text-muted mt-2 mb-0"><i class="bi bi-chat-left-text"></i> <?= e($booking['notes']) ?></p><?php endif ?>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6">Ringkasan</h2>
                <dl class="summary-totals small mb-0">
                    <div><dt>Tanggal</dt><dd><?= e(tanggal($booking['start_date'])) ?> – <?= e(tanggal($booking['end_date'])) ?></dd></div>
                    <div><dt>Durasi</dt><dd><?= (int) $booking['total_days'] ?> hari</dd></div>
                    <div><dt>Subtotal</dt><dd><?= rupiah($booking['subtotal']) ?></dd></div>
                    <div class="text-success"><dt>Diskon</dt><dd>−<?= rupiah($booking['discount_total']) ?></dd></div>
                    <div class="fw-bold"><dt>Total sewa</dt><dd><?= rupiah($booking['grand_total']) ?></dd></div>
                </dl>
                <?php foreach ($payments as $payment): ?>
                    <div class="small border-top pt-2 mt-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <?= $payment['purpose'] === 'damage' ? 'Penggantian' : 'Sewa' ?> · <?= e($methodLabels[$payment['method']] ?? $payment['method']) ?> · <?= rupiah($payment['amount']) ?>
                        <div class="text-muted">Ref. <?= e($payment['reference']) ?> · <?= e(tanggal($payment['paid_at'], false, true)) ?></div>
                    </div>
                <?php endforeach ?>
            </div>
        </div>

        <?php if (!in_array($booking['status'], ['pending_payment', 'expired'], true)): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6">Deposit <span class="badge text-bg-light border"><?= e(Booking::DEPOSIT_LABELS[$booking['deposit_status']]) ?></span></h2>
                    <p class="small text-muted">Dicatat secara administratif; tidak dihitung otomatis.</p>
                    <form method="post" action="<?= url($base . '/deposit') ?>">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="dep_amount">Nominal (Rp, opsional)</label>
                            <input class="form-control form-control-sm" type="number" id="dep_amount" name="deposit_amount" min="0" step="1"
                                   value="<?= $booking['deposit_amount'] !== null ? (int) $booking['deposit_amount'] : '' ?>">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="dep_status">Status</label>
                            <select class="form-select form-select-sm" id="dep_status" name="deposit_status">
                                <?php foreach (Booking::DEPOSIT_LABELS as $value => $label): ?>
                                    <option value="<?= e($value) ?>"<?= selected($booking['deposit_status'], $value) ?>><?= e($label) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="dep_note">Catatan</label>
                            <input class="form-control form-control-sm" id="dep_note" name="deposit_note" maxlength="255" value="<?= e($booking['deposit_note'] ?? '') ?>">
                        </div>
                        <button class="btn btn-sm btn-outline-primary" type="submit">Simpan deposit</button>
                    </form>
                </div>
            </div>
        <?php endif ?>

        <div class="card">
            <div class="card-body">
                <h2 class="h6">Riwayat status</h2>
                <ol class="timeline small mb-0">
                    <?php foreach ([
                        ['Dibuat', $booking['created_at']], ['Dibayar', $booking['confirmed_at']], ['Diambil', $booking['picked_up_at']],
                        ['Dikembalikan', $booking['returned_at']], ['Selesai', $booking['completed_at']], ['Dibatalkan', $booking['cancelled_at']],
                    ] as [$label, $time]): if ($time === null) continue; ?>
                        <li><div class="fw-semibold"><?= e($label) ?></div><div class="text-muted"><?= e(tanggal($time, false, true)) ?></div></li>
                    <?php endforeach ?>
                    <?php if ($booking['status'] === 'expired'): ?>
                        <li><div class="fw-semibold">Kedaluwarsa</div><div class="text-muted">Batas bayar <?= e(tanggal($booking['payment_due_at'], false, true)) ?></div></li>
                    <?php endif ?>
                </ol>
            </div>
        </div>
    </div>
</div>
