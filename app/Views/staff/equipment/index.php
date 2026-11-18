<?php
/**
 * @var array $filters
 * @var array $categories
 * @var array $items
 * @var App\Core\Paginator $pager
 */
?>
<div class="card">
    <div class="card-body">
        <form class="row g-2 mb-3" method="get" action="<?= url('/staff/alat') ?>" role="search">
            <div class="col-md-4"><input class="form-control" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Cari nama alat" aria-label="Cari nama alat"></div>
            <div class="col-6 col-md-3">
                <select class="form-select" name="category" aria-label="Kategori">
                    <option value="0">Semua kategori</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"<?= selected($filters['category'], $category['id']) ?>><?= e($category['name']) ?></option>
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
            <div class="col-6 col-md-1 d-grid"><button class="btn btn-outline-primary" type="submit" aria-label="Terapkan filter"><i class="bi bi-funnel"></i></button></div>
            <div class="col-6 col-md-2 d-grid"><a class="btn btn-primary" href="<?= url('/staff/alat/baru') ?>"><i class="bi bi-plus-lg"></i> Tambah</a></div>
        </form>

        <?php if ($items === []): ?>
            <div class="empty-state py-4"><i class="bi bi-backpack"></i><p class="text-muted mb-0">Tidak ada alat yang cocok.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr class="small text-muted"><th>Alat</th><th>Kategori</th><th class="text-end">Stok total</th><th class="text-end">Tarif/hari</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="<?= (int) $item['is_active'] === 1 ? '' : 'text-muted' ?>">
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="thumb-sm">
                                        <?php if ($image = upload_url($item['image'])): ?><img src="<?= e($image) ?>" alt=""><?php else: ?><i class="bi <?= e($item['category_icon'] ?: 'bi-box') ?>"></i><?php endif ?>
                                    </span>
                                    <div><strong><?= e($item['name']) ?></strong><div class="small text-muted font-monospace"><?= e($item['slug']) ?></div></div>
                                </div>
                            </td>
                            <td class="small"><?= e($item['category_name']) ?></td>
                            <td class="text-end"><?= (int) $item['stock_total'] ?></td>
                            <td class="text-end text-nowrap"><?= rupiah($item['daily_rate']) ?></td>
                            <td><span class="badge <?= (int) $item['is_active'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $item['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('/staff/alat/' . $item['id'] . '/edit') ?>">Ubah</a>
                                <form class="d-inline" method="post" action="<?= url('/staff/alat/' . $item['id']) ?>" data-confirm="Hapus alat <?= e($item['name']) ?>? Alat yang pernah disewa tidak bisa dihapus.">
                                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php App\Core\View::partial('pagination', ['pager' => $pager]) ?>
    </div>
</div>
