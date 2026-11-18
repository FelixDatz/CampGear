<?php /** @var array $categories */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted mb-0 small">Kategori mengelompokkan alat di katalog. Kategori yang masih berisi alat tidak dapat dihapus.</p>
    <a class="btn btn-primary" href="<?= url('/staff/kategori/baru') ?>"><i class="bi bi-plus-lg"></i> Tambah</a>
</div>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr class="small text-muted"><th>Kategori</th><th>Slug</th><th class="text-end">Alat aktif / total</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><i class="bi <?= e($category['icon'] ?: 'bi-box') ?> text-primary me-2"></i><strong><?= e($category['name']) ?></strong>
                            <div class="small text-muted"><?= e($category['description'] ?? '') ?></div></td>
                        <td class="small font-monospace"><?= e($category['slug']) ?></td>
                        <td class="text-end"><?= (int) $category['active_count'] ?> / <?= (int) $category['total_count'] ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= url('/staff/kategori/' . $category['id'] . '/edit') ?>">Ubah</a>
                            <form class="d-inline" method="post" action="<?= url('/staff/kategori/' . $category['id']) ?>" data-confirm="Hapus kategori <?= e($category['name']) ?>?">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit"<?= (int) $category['total_count'] > 0 ? ' disabled title="Masih berisi alat"' : '' ?>>Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
