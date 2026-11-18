<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card auth-card">
                <div class="card-body p-4 p-md-5">
                    <h1 class="h3 mb-1">Masuk</h1>
                    <p class="text-muted mb-4">Masuk untuk melihat riwayat sewa dan mengelola akunmu.</p>

                    <form method="post" action="<?= url('/login') ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control<?= invalid('email') ?>" type="email" id="email" name="email"
                                   value="<?= e(old('email')) ?>" autocomplete="email" required maxlength="150" autofocus>
                            <?php if ($msg = error('email')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group has-validation">
                                <input class="form-control<?= invalid('password') ?>" type="password" id="password" name="password"
                                       autocomplete="current-password" required maxlength="255">
                                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Tampilkan password">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <?php if ($msg = error('password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                            <label class="form-check-label" for="remember">Ingat saya selama 30 hari</label>
                        </div>
                        <button class="btn btn-primary w-100 btn-lg" type="submit">Masuk</button>
                    </form>

                    <p class="text-center text-muted mt-4 mb-0">Belum punya akun? <a href="<?= url('/register') ?>">Daftar</a></p>
                </div>
            </div>

            <?php if (config('app.show_demo_accounts')): ?>
                <div class="card mt-3 border-dashed">
                    <div class="card-body small">
                        <div class="fw-semibold mb-2"><i class="bi bi-person-badge me-1"></i> Akun demo</div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle">
                                <tbody>
                                <?php foreach ([['Admin', 'admin@demo.test', 'Admin#2026'], ['Staff', 'staff@demo.test', 'Staff#2026'], ['User', 'user@demo.test', 'User#2026']] as [$role, $email, $pass]): ?>
                                    <tr>
                                        <td class="text-muted"><?= e($role) ?></td>
                                        <td><code><?= e($email) ?></code></td>
                                        <td><code><?= e($pass) ?></code></td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary" type="button" data-fill-login data-email="<?= e($email) ?>" data-password="<?= e($pass) ?>">Isi</button>
                                        </td>
                                    </tr>
                                <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
