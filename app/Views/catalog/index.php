<?php
/**
 * @var array $categories
 * @var array|null $category
 * @var string $search
 * @var array $items
 */
$activeSlug = $category['slug'] ?? '';
?>
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                <li class="breadcrumb-item<?= $category ? '' : ' active' ?>"<?= $category ? '' : ' aria-current="page"' ?>>
                    <?php if ($category): ?><a href="<?= url('/alat') ?>">Katalog</a><?php else: ?>Katalog<?php endif ?>
                </li>
                <?php if ($category): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= e($category['name']) ?></li>
                <?php endif ?>
            </ol>
        </nav>
        <h1 class="h2 mb-1"><?= e($category['name'] ?? 'Katalog Alat') ?></h1>
        <p class="text-muted mb-0"><?= e($category['description'] ?? 'Semua alat camping yang bisa kamu sewa harian.') ?></p>
    </div>
</section>

<div class="container py-4">
    <div class="row g-4">
        <aside class="col-lg-3">
            <form class="mb-3" method="get" action="<?= url('/alat') ?>" role="search">
                <?php if ($activeSlug !== ''): ?>
                    <input type="hidden" name="kategori" value="<?= e($activeSlug) ?>">
                <?php endif ?>
                <label class="form-label small fw-semibold" for="q">Cari alat</label>
                <div class="input-group">
                    <input class="form-control" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="mis. tenda, carrier" maxlength="100">
                    <button class="btn btn-primary" type="submit" aria-label="Cari"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <div class="small fw-semibold mb-2">Kategori</div>
            <nav class="category-filter" aria-label="Filter kategori">
                <a class="<?= $activeSlug === '' ? 'active' : '' ?>" href="<?= url('/alat', $search !== '' ? ['q' => $search] : []) ?>">
                    <i class="bi bi-grid"></i> Semua
                </a>
                <?php foreach ($categories as $cat):
                    $query = ['kategori' => $cat['slug']] + ($search !== '' ? ['q' => $search] : []);
                ?>
                    <a class="<?= $activeSlug === $cat['slug'] ? 'active' : '' ?>" href="<?= url('/alat', $query) ?>">
                        <i class="bi <?= e($cat['icon'] ?: 'bi-box') ?>"></i> <?= e($cat['name']) ?>
                        <span class="ms-auto small opacity-75"><?= (int) $cat['equipment_count'] ?></span>
                    </a>
                <?php endforeach ?>
            </nav>
        </aside>

        <div class="col-lg-9">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <p class="mb-0 text-muted small">
                    Menampilkan <strong><?= count($items) ?></strong> alat
                    <?php if ($search !== ''): ?> untuk "<strong><?= e($search) ?></strong>"<?php endif ?>
                </p>
                <?php if ($search !== '' || $activeSlug !== ''): ?>
                    <a class="small" href="<?= url('/alat') ?>"><i class="bi bi-x-circle"></i> Hapus filter</a>
                <?php endif ?>
            </div>

            <?php if ($items === []): ?>
                <div class="empty-state">
                    <i class="bi bi-search"></i>
                    <h2 class="h5">Alat tidak ditemukan</h2>
                    <p class="text-muted">Coba kata kunci lain atau lihat semua kategori.</p>
                    <a class="btn btn-outline-primary" href="<?= url('/alat') ?>">Lihat semua alat</a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($items as $item): ?>
                        <div class="col-sm-6 col-xl-4">
                            <?php App\Core\View::partial('equipment-card', ['item' => $item]) ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>
</div>
