<?php
/**
 * @var array $filters
 * @var array $users
 * @var App\Core\Paginator $pager
 */
use App\Enums\Role;
?>
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get" action="<?= url('/admin/pengguna') ?>" role="search">
            <div class="col-md-4"><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Nama, email, atau HP" aria-label="Cari pengguna"></div>
            <div class="col-6 col-md-2">
                <select class="form-select" name="role" aria-label="Role">
                    <option value="">Semua role</option>
                    <?php foreach (Role::cases() as $role): ?>
                        <option value="<?= e($role->value) ?>"<?= selected($filters['role'], $role->value) ?>><?= e($role->label()) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select class="form-select" name="active" aria-label="Status">
                    <option value="">Semua status</option>
                    <option value="1"<?= selected($filters['active'], '1') ?>>Aktif</option>
                    <option value="0"<?= selected($filters['active'], '0') ?>>Nonaktif</option>
                </select>
            </div>
            <div class="col-6 col-md-2 d-grid"><button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button></div>
            <div class="col-6 col-md-2 d-grid"><a class="btn btn-primary" href="<?= url('/admin/pengguna/baru') ?>"><i class="bi bi-person-plus"></i> Tambah</a></div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr class="small text-muted"><th>Nama</th><th>Role</th><th>Status</th><th class="text-end">Booking</th><th>Login terakhir</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($users as $account): $role = Role::from($account['role']); $isSelf = (int) $account['id'] === App\Core\Auth::id(); ?>
                    <tr class="<?= (int) $account['is_active'] === 1 ? '' : 'text-muted' ?>">
                        <td><strong><?= e($account['name']) ?></strong><?= $isSelf ? ' <span class="badge text-bg-light border">Kamu</span>' : '' ?>
                            <div class="small text-muted text-break"><?= e($account['email']) ?> · <?= e($account['phone'] ?? '-') ?></div></td>
                        <td><span class="badge <?= e($role->badgeClass()) ?>"><?= e($role->label()) ?></span></td>
                        <td><span class="badge <?= (int) $account['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $account['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                        <td class="text-end"><?= (int) $account['booking_count'] ?></td>
                        <td class="small text-nowrap"><?= e(tanggal($account['last_login_at'], false, true)) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('/admin/pengguna/' . $account['id'] . '/edit') ?>">Ubah</a>
                            <?php if (!$isSelf): ?>
                                <form class="d-inline" method="post" action="<?= url('/admin/pengguna/' . $account['id']) ?>" data-confirm="Hapus akun <?= e($account['email']) ?>? Akun dengan riwayat tidak bisa dihapus.">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
