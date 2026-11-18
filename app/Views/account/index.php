<?php
/**
 * @var array $user
 * @var array $bookings
 */
?>
<section class="page-header">
    <div class="container">
        <h1 class="h2 mb-1">Halo, <?= e($user['name']) ?></h1>
        <p class="text-muted mb-0">Kelola penyewaan dan data pribadimu di sini.</p>
    </div>
</section>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-4">
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
                    <a class="btn btn-outline-primary btn-sm w-100 mt-3" href="<?= url('/akun/profil') ?>"><i class="bi bi-person-gear me-1"></i> Ubah profil &amp; password</a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h2 class="h5 mb-0">Riwayat Penyewaan</h2>
                        <a class="btn btn-sm btn-primary" href="<?= url('/sewa') ?>"><i class="bi bi-plus-lg"></i> Booking baru</a>
                    </div>
                    <?php if ($bookings === []): ?>
                        <div class="empty-state py-4">
                            <i class="bi bi-calendar2-week"></i>
                            <p class="text-muted mb-3">Belum ada penyewaan. Booking yang kamu buat akan muncul di sini.</p>
                            <a class="btn btn-outline-primary" href="<?= url('/alat') ?>">Mulai cari alat</a>
                        </div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($bookings as $booking): $status = App\Enums\BookingStatus::from($booking['status']); ?>
                                <a class="booking-row" href="<?= url('/booking/' . $booking['code']) ?>">
                                    <div>
                                        <div class="font-monospace fw-semibold"><?= e($booking['code']) ?></div>
                                        <div class="small text-muted">
                                            <?= e(tanggal($booking['start_date'])) ?> – <?= e(tanggal($booking['end_date'])) ?>
                                            · <?= (int) $booking['unit_count'] ?> unit alat
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?= e($status->badgeClass()) ?>"><?= e($status->label()) ?></span>
                                        <?php if ($booking['cancel_status'] !== null): ?>
                                            <span class="badge <?= e(App\Models\CancellationRequest::STATUS_BADGES[$booking['cancel_status']]) ?>">Pembatalan: <?= e(App\Models\CancellationRequest::STATUS_LABELS[$booking['cancel_status']]) ?></span>
                                        <?php endif ?>
                                        <?php if ((int) $booking['unsettled_damage'] > 0): ?>
                                            <span class="badge text-bg-danger">Penggantian belum tuntas</span>
                                        <?php endif ?>
                                        <div class="small fw-semibold mt-1"><?= rupiah($booking['grand_total']) ?></div>
                                    </div>
                                </a>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>
        </div>
    </div>
</div>
