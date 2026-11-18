<?php
/**
 * Katalog alat. Judul dan deskripsi ikut berganti saat chip kategori dipilih (katalog.js).
 *
 * @var array $categories
 * @var array|null $category
 * @var string $search
 * @var array $items
 * @var array $packagesByItem
 */
$defaultTitle = 'Katalog Alat';
$defaultDesc = 'Semua alat camping yang bisa kamu sewa harian. Pilih tanggal untuk melihat stok yang tersedia.';
?>
<section class="page-header">
    <div class="container katalog-header">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Katalog</li>
                </ol>
            </nav>
            <h1 class="h2 mb-1" data-katalog-title data-default="<?= e($defaultTitle) ?>"><?= e($category['name'] ?? $defaultTitle) ?></h1>
            <p class="text-muted mb-0" data-katalog-desc data-default="<?= e($defaultDesc) ?>"><?= e($category['description'] ?? $defaultDesc) ?></p>
        </div>
        <form class="katalog-search" method="get" action="<?= url('/alat') ?>" role="search">
            <label class="visually-hidden" for="q">Cari alat</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Cari alat, mis. tenda" maxlength="100">
        </form>
    </div>
</section>

<div class="container py-4">
    <?php App\Core\View::partial('catalog-browser', [
        'categories'     => $categories,
        'items'          => $items,
        'packagesByItem' => $packagesByItem,
        'activeSlug'     => $category['slug'] ?? '',
        'search'         => $search,
        'isPage'         => true,
    ]) ?>
</div>
