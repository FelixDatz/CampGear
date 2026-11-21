<?php /** @var string $code */ ?>
<div class="container auth-wrap">
    <div class="auth-split">
        <?php App\Core\View::partial('auth-aside', [
            'title'  => 'Cari tiket ekspedisimu.',
            'points' => [
                ['bi-upc', 'Kode booking tampil di tiket setelah booking dibuat, formatnya CG…-….'],
                ['bi-at', 'Pakai email atau nomor HP yang sama dengan saat booking.'],
                ['bi-qr-code', 'Setelah dibayar, QR pengambilan alat tampil di tiket.'],
            ],
        ]) ?>
        <div class="auth-main">
            <div class="auth-card">
                <div class="auth-form">
                    <h1 class="h3 mb-1">Cek Status Booking</h1>
                    <p class="text-muted mb-4">Masukkan kode booking beserta email atau nomor HP yang dipakai saat memesan.</p>

                    <form method="post" action="<?= url('/cek-booking') ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="code">Kode booking</label>
                            <input class="form-control font-monospace text-uppercase<?= invalid('code') ?>" id="code" name="code"
                                   value="<?= e($code) ?>" placeholder="CG261118-AB12" required maxlength="20" autocomplete="off" autocapitalize="characters">
                            <?php if ($msg = error('code')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="contact">Email atau nomor HP</label>
                            <input class="form-control<?= invalid('contact') ?>" id="contact" name="contact"
                                   value="<?= e(old('contact')) ?>" required maxlength="150" autocomplete="email">
                            <?php if ($msg = error('contact')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-search me-1"></i> Cek Status</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0 small">Punya akun? <a href="<?= url('/login') ?>">Masuk</a> untuk melihat semua booking-mu.</p>
                </div>
            </div>
        </div>
    </div>
</div>
