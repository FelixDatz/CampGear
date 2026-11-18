<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-8 col-lg-6">
            <div class="card auth-card">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">Daftar Akun</h1>
                    <p class="text-muted mb-4">Dengan akun, riwayat sewa dan status pembatalanmu tersimpan di satu tempat.</p>

                    <form method="post" action="<?= url('/register') ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama lengkap</label>
                            <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e(old('name')) ?>"
                                   autocomplete="name" required minlength="3" maxlength="100" autofocus>
                            <?php if ($msg = error('name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="email">Email</label>
                                <input class="form-control<?= invalid('email') ?>" type="email" id="email" name="email"
                                       value="<?= e(old('email')) ?>" autocomplete="email" required maxlength="150">
                                <?php if ($msg = error('email')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Nomor HP</label>
                                <input class="form-control<?= invalid('phone') ?>" type="tel" id="phone" name="phone"
                                       value="<?= e(old('phone')) ?>" autocomplete="tel" inputmode="tel" placeholder="08xxxxxxxxxx" required maxlength="20">
                                <?php if ($msg = error('phone')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="password">Password</label>
                                <div class="input-group has-validation">
                                    <input class="form-control<?= invalid('password') ?>" type="password" id="password" name="password"
                                           autocomplete="new-password" required minlength="8" maxlength="72" aria-describedby="passwordHelp">
                                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Tampilkan password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php if ($msg = error('password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                                </div>
                                <div class="form-text" id="passwordHelp">Minimal 8 karakter, berisi huruf dan angka.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Ulangi password</label>
                                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation"
                                       autocomplete="new-password" required maxlength="72">
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 btn-lg" type="submit">Buat Akun</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">Sudah punya akun? <a href="<?= url('/login') ?>">Masuk</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
