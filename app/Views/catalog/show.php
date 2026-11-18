<?php
/**
 * @var array $item
 * @var array $packages
 */
?>
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/alat') ?>">Katalog</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/alat', ['kategori' => $item['category_slug']]) ?>"><?= e($item['category_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($item['name']) ?></li>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-6">
            <div class="gear-thumb gear-thumb-lg">
                <?php if ($image = upload_url($item['image'])): ?>
                    <img src="<?= e($image) ?>" alt="<?= e($item['name']) ?>">
                <?php else: ?>
                    <?php App\Core\View::partial('gear-glyph', ['item' => $item]) ?>
                <?php endif ?>
            </div>
        </div>

        <div class="col-lg-6">
            <span class="tag mb-2"><?= e($item['category_name']) ?></span>
            <h1 class="h2"><?= e($item['name']) ?></h1>
            <p class="mb-4"><span class="price fs-3"><?= rupiah($item['daily_rate']) ?></span> <span class="text-muted">per unit / hari</span></p>

            <dl class="spec-list">
                <div><dt>Kategori</dt><dd><?= e($item['category_name']) ?></dd></div>
                <div><dt>Jumlah unit</dt><dd><?= (int) $item['stock_total'] ?> unit</dd></div>
            </dl>

            <h2 class="h6 mt-4">Deskripsi</h2>
            <p class="text-muted"><?= nl2br(e($item['description'])) ?></p>

            <div class="alert alert-light border d-flex gap-2 small">
                <i class="bi bi-info-circle text-primary"></i>
                <div>Ketersediaan stok dicek untuk setiap hari dalam tanggal sewa yang kamu pilih. Kondisi alat dicatat saat diambil dan saat dikembalikan.</div>
            </div>

            <p class="mb-3"><button type="button" class="avail-line" data-trip-open data-avail-id="<?= (int) $item['id'] ?>" data-avail-empty="Pilih tanggal sewa untuk cek stok">Pilih tanggal sewa untuk cek stok</button></p>

            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary btn-lg" href="<?= url('/sewa', ['alat' => $item['slug']]) ?>" data-sewa-link><i class="bi bi-calendar-check me-1"></i> Sewa alat ini</a>
                <a class="btn btn-outline-primary btn-lg" href="<?= url('/alat', ['kategori' => $item['category_slug']]) ?>">Alat sejenis</a>
                <button type="button" class="btn btn-cta btn-lg btn-ransel" data-ransel-add="item:<?= (int) $item['id'] ?>" data-name="<?= e($item['name']) ?>"><i class="bi bi-backpack" aria-hidden="true"></i> Masukkan ke ransel</button>
            </div>

            <?php if ($packages !== []): ?>
                <h2 class="h6 mt-4">Termasuk dalam paket</h2>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($packages as $package): ?>
                        <li class="mb-1"><i class="bi bi-box2-heart text-primary me-1"></i>
                            <a href="<?= url('/paket') ?>#<?= e($package['slug']) ?>"><?= e($package['name']) ?></a>
                            <span class="text-muted small">(<?= (int) $package['quantity'] ?> unit)</span>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
    </div>
</div>
