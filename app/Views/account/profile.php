<?php
/** @var array $user */
$inPanel = App\Core\Auth::isStaff();
?>
<?php if (!$inPanel): ?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/akun') ?>">Akun Saya</a></li>
                <li class="breadcrumb-item active" aria-current="page">Profil &amp; Password</li>
            </ol>
        </nav>
        <h1 class="h2 mb-0">Profil &amp; Password</h1>
    </div>
</section>
<?php endif ?>

<div class="<?= $inPanel ? '' : 'container py-4' ?>">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5">Data pribadi</h2>
                    <form method="post" action="<?= url('/akun/profil') ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama lengkap</label>
                            <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name', $user['name'])) ?>" autocomplete="name" required maxlength="100">
                            <?php if ($msg = error('name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control<?= invalid('email') ?>" type="email" id="email" name="email" value="<?= e(old('email', $user['email'])) ?>" autocomplete="email" required maxlength="150">
                                <?php if ($msg = error('email')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Nomor HP</label>
                                <input class="form-control<?= invalid('phone') ?>" type="tel" id="phone" name="phone" value="<?= e(old('phone', $user['phone'] ?? '')) ?>" autocomplete="tel" required maxlength="20">
                                <?php if ($msg = error('phone')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="address">Alamat <span class="text-muted small">(opsional)</span></label>
                            <textarea class="form-control<?= invalid('address') ?>" id="address" name="address" rows="2" maxlength="255" autocomplete="street-address"><?= e(old('address', $user['address'] ?? '')) ?></textarea>
                            <?php if ($msg = error('address')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <?php if (!$inPanel): ?>
                            <p class="small text-muted">Booking tamu yang memakai email akun ini ikut tampil di riwayat penyewaanmu.</p>
                        <?php endif ?>
                        <button class="btn btn-primary" type="submit">Simpan data</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card" id="password">
                <div class="card-body">
                    <h2 class="h5">Ganti password</h2>
                    <form method="post" action="<?= url('/akun/password') ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Password saat ini</label>
                            <input class="form-control<?= invalid('current_password') ?>" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                            <?php if ($msg = error('current_password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password baru</label>
                            <input class="form-control<?= invalid('password') ?>" type="password" id="password" name="password" autocomplete="new-password" required minlength="8" maxlength="72">
                            <?php if ($msg = error('password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            <div class="form-text">Minimal 8 karakter, berisi huruf dan angka.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password_confirmation">Ulangi password baru</label>
                            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required maxlength="72">
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Ganti password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
