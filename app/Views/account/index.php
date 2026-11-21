<?php
/**
 * Akun pelanggan: hal yang perlu ditindaklanjuti, perjalanan aktif, dan riwayat,
 * masing-masing booking tampil sebagai tiket mini dengan stempel status.
 *
 * @var array $user
 * @var array $bookings
 */
use App\Enums\BookingStatus;
use App\Models\CancellationRequest;

$active = [];
$history = [];
$attention = [];
foreach ($bookings as $booking) {
    $status = BookingStatus::from($booking['status']);
    if (in_array($status, [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::Expired], true)) {
        $history[] = $booking;
    } else {
        $active[] = $booking;
    }
    if ($status === BookingStatus::PendingPayment) {
        $attention[] = ['warn', 'bi-credit-card', 'Selesaikan pembayaran ' . $booking['code'], 'Batas bayar ' . tanggal($booking['payment_due_at'], true, true) . ' WIB.', 'Bayar', $booking['code']];
    }
    if ((int) $booking['unsettled_damage'] > 0) {
        $attention[] = ['bad', 'bi-shield-exclamation', 'Penggantian belum tuntas · ' . $booking['code'], 'Ada laporan kerusakan atau kehilangan yang wajib diselesaikan.', 'Lihat', $booking['code']];
    }
}
$completed = count(array_filter($bookings, static fn ($b) => $b['status'] === BookingStatus::Completed->value));
$sections = [
    ['Perjalanan aktif', $active, 'Belum ada perjalanan aktif. Pilih tanggal dan alat untuk memulai.'],
    ['Riwayat', $history, 'Booking yang selesai, dibatalkan, atau kedaluwarsa akan tampil di sini.'],
];
?>
<section class="page-header">
    <div class="container akun-head">
        <div>
            <p class="eyebrow mb-2">Akun saya</p>
            <h1 class="h2 mb-1">Halo, <?= e($user['name']) ?></h1>
            <p class="text-muted mb-0">Semua tiket ekspedisimu ada di sini.</p>
        </div>
        <div class="akun-stats">
            <div class="kpi"><span class="kpi-label">Booking</span><span class="kpi-value"><?= count($bookings) ?></span></div>
            <div class="kpi"><span class="kpi-label">Aktif</span><span class="kpi-value"><?= count($active) ?></span></div>
            <div class="kpi"><span class="kpi-label">Selesai</span><span class="kpi-value"><?= $completed ?></span></div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="akun-grid">
        <div class="akun-main">
            <?php foreach ($attention as [$tone, $icon, $title, $text, $cta, $code]): ?>
                <div class="akun-alert" data-tone="<?= e($tone) ?>">
                    <i class="bi <?= e($icon) ?>" aria-hidden="true"></i>
                    <div><b><?= e($title) ?></b><span><?= e($text) ?></span></div>
                    <a class="btn btn-sm <?= $tone === 'warn' ? 'btn-cta' : 'btn-outline-secondary' ?>" href="<?= url('/booking/' . $code) ?>"><?= e($cta) ?></a>
                </div>
            <?php endforeach ?>

            <?php foreach ($sections as [$heading, $list, $emptyText]): ?>
                <section class="akun-section" aria-label="<?= e($heading) ?>">
                    <div class="agenda-head">
                        <h2 class="h5 mb-0"><?= e($heading) ?></h2>
                        <span class="small text-muted"><?= count($list) ?> booking</span>
                    </div>
                    <?php if ($list === []): ?>
                        <p class="akun-empty"><?= e($emptyText) ?></p>
                    <?php else: ?>
                        <div class="tix-list">
                            <?php foreach ($list as $booking): $status = BookingStatus::from($booking['status']); ?>
                                <a class="tix" href="<?= url('/booking/' . $booking['code']) ?>">
                                    <span class="tix-code"><?= e($booking['code']) ?></span>
                                    <span class="tix-route">
                                        <b><?= e(tanggal_singkat($booking['start_date'])) ?></b>
                                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                                        <b><?= e(tanggal_singkat($booking['end_date'])) ?></b>
                                        <small><?= (int) $booking['total_days'] ?> hari · <?= (int) $booking['unit_count'] ?> unit alat</small>
                                    </span>
                                    <span class="tix-total mono"><?= rupiah($booking['grand_total']) ?></span>
                                    <span class="tix-end">
                                        <span class="stamp-mini" data-tone="<?= e($status->tone()) ?>"><?= e($status->stamp()) ?></span>
                                        <?php if ($booking['cancel_status'] !== null): ?>
                                            <span class="badge <?= e(CancellationRequest::STATUS_BADGES[$booking['cancel_status']]) ?>">Pembatalan: <?= e(CancellationRequest::STATUS_LABELS[$booking['cancel_status']]) ?></span>
                                        <?php endif ?>
                                        <?php if ((int) $booking['unsettled_damage'] > 0): ?>
                                            <span class="badge text-bg-danger">Penggantian belum tuntas</span>
                                        <?php endif ?>
                                    </span>
                                </a>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </section>
            <?php endforeach ?>
        </div>

        <aside class="akun-side">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar-lg"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate"><?= e($user['name']) ?></div>
                            <div class="small text-muted text-truncate"><?= e($user['email']) ?></div>
                        </div>
                    </div>
                    <dl class="spec-list small mb-0">
                        <div><dt>Nomor HP</dt><dd><?= e($user['phone'] ?: '-') ?></dd></div>
                        <div><dt>Alamat</dt><dd><?= e($user['address'] ?: '-') ?></dd></div>
                        <div><dt>Bergabung</dt><dd><?= e(tanggal($user['created_at'], true)) ?></dd></div>
                    </dl>
                    <a class="btn btn-outline-primary btn-sm w-100 mt-3" href="<?= url('/akun/profil') ?>"><i class="bi bi-person-gear me-1" aria-hidden="true"></i> Ubah profil &amp; password</a>
                </div>
            </div>
            <div class="akun-cta">
                <p class="eyebrow">Perjalanan berikutnya</p>
                <p>Booking tamu yang memakai email ini ikut tampil di akunmu.</p>
                <a class="btn btn-cta w-100" href="<?= url('/sewa') ?>" data-sewa-link><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Booking baru</a>
            </div>
        </aside>
    </div>
</div>
