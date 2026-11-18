<?php
/** @var array|null $account */
use App\Enums\Role;

$isEdit = $account !== null;
$isSelf = $isEdit && (int) $account['id'] === App\Core\Auth::id();
$value = static fn (string $key, string $default = '') => old($key, isset($account[$key]) ? (string) $account[$key] : $default);
$active = old('_submitted') !== '' ? old('is_active') !== '' : (!$isEdit || (int) $account['is_active'] === 1);
?>
<a class="btn btn-sm btn-outline-secondary mb-3" href="<?= url('/admin/pengguna') ?>"><i class="bi bi-arrow-left"></i> Kembali</a>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h2 class="h6">Data akun</h2>
                <form method="post" action="<?= url($isEdit ? '/admin/pengguna/' . $account['id'] : '/admin/pengguna') ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif ?>
                    <input type="hidden" name="_submitted" value="1">
                    <div class="mb-3">
                        <label class="form-label" for="name">Nama lengkap</label>
                        <input class="form-control<?= invalid('name') ?>" id="name" name="name" value="<?= e($value('name')) ?>" required maxlength="100">
                        <?php if ($msg = error('name')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control<?= invalid('email') ?>" type="email" id="email" name="email" value="<?= e($value('email')) ?>" required maxlength="150">
                            <?php if ($msg = error('email')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Nomor HP</label>
                            <input class="form-control<?= invalid('phone') ?>" type="tel" id="phone" name="phone" value="<?= e($value('phone')) ?>" maxlength="20"<?= $isEdit ? '' : ' required' ?>>
                            <?php if ($msg = error('phone')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                    </div>
                    <?php if ($isEdit): ?>
                        <div class="mb-3">
                            <label class="form-label" for="address">Alamat</label>
                            <input class="form-control<?= invalid('address') ?>" id="address" name="address" value="<?= e($value('address')) ?>" maxlength="255">
                        </div>
                    <?php endif ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="role">Role</label>
                            <select class="form-select<?= invalid('role') ?>" id="role" name="role"<?= $isSelf ? ' disabled' : '' ?>>
                                <?php foreach (Role::cases() as $role): ?>
                                    <option value="<?= e($role->value) ?>"<?= selected($value('role', 'user'), $role->value) ?>><?= e($role->label()) ?></option>
                                <?php endforeach ?>
                            </select>
                            <?php if ($isSelf): ?><input type="hidden" name="role" value="<?= e($account['role']) ?>"><?php endif ?>
                            <?php if ($msg = error('role')): ?><div class="invalid-feedback d-block"><?= e($msg) ?></div><?php endif ?>
                            <div class="form-text">Staff mengelola operasional & konten. Admin juga mengelola akun dan melihat log aktivitas.</div>
                        </div>
                        <?php if ($isEdit): ?>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"<?= checked($active) ?><?= $isSelf ? ' disabled' : '' ?>>
                                    <label class="form-check-label" for="is_active">Akun aktif (bisa login)</label>
                                    <?php if ($isSelf): ?><input type="hidden" name="is_active" value="1"><?php endif ?>
                                </div>
                            </div>
                        <?php endif ?>
                    </div>
                    <?php if (!$isEdit): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="password">Password awal</label>
                                <input class="form-control<?= invalid('password') ?>" type="password" id="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                                <?php if ($msg = error('password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Ulangi password</label>
                                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required maxlength="72" autocomplete="new-password">
                            </div>
                        </div>
                    <?php endif ?>
                    <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Simpan perubahan' : 'Buat akun' ?></button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="col-lg-5">
            <div class="card" id="password">
                <div class="card-body">
                    <h2 class="h6">Atur ulang password</h2>
                    <p class="small text-muted">Login "Ingat saya" milik akun ini akan dikeluarkan.</p>
                    <form method="post" action="<?= url('/admin/pengguna/' . $account['id'] . '/password') ?>" novalidate data-confirm="Atur ulang password akun ini?">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="new_password">Password baru</label>
                            <input class="form-control<?= invalid('password') ?>" type="password" id="new_password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
                            <?php if ($msg = error('password')): ?><div class="invalid-feedback"><?= e($msg) ?></div><?php endif ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="new_password_confirmation">Ulangi password baru</label>
                            <input class="form-control" type="password" id="new_password_confirmation" name="password_confirmation" required maxlength="72" autocomplete="new-password">
                        </div>
                        <button class="btn btn-outline-primary" type="submit">Atur ulang</button>
                    </form>
                    <hr>
                    <dl class="spec-list small mb-0">
                        <div><dt>Dibuat</dt><dd><?= e(tanggal($account['created_at'], false, true)) ?></dd></div>
                        <div><dt>Login terakhir</dt><dd><?= e(tanggal($account['last_login_at'], false, true)) ?></dd></div>
                    </dl>
                </div>
            </div>
        </div>
    <?php endif ?>
</div>
