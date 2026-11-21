<?php
/**
 * Detail alat: gambar di kiri (menempel saat di-scroll), keterangan di kanan.
 * Jumlah, stok di tanggal trip, dan perkiraan biaya diatur alat.js.
 *
 * @var array $item
 * @var array $packages paket yang memuat alat ini (id, name, slug, quantity)
 * @var array $related  alat lain di kategori yang sama
 */
$stock = (int) $item['stock_total'];
?>
<div class="container alat-page">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= url('/') ?>">Beranda</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/alat') ?>">Katalog</a></li>
            <li class="breadcrumb-item"><a href="<?= url('/alat', ['kategori' => $item['category_slug']]) ?>"><?= e($item['category_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($item['name']) ?></li>
        </ol>
    </nav>

    <div class="alat-grid" data-alat data-id="<?= (int) $item['id'] ?>" data-slug="<?= e($item['slug']) ?>" data-rate="<?= (float) $item['daily_rate'] ?>" data-stock="<?= $stock ?>">
        <div class="alat-art">
            <div class="gear-thumb gear-thumb-lg">
                <?php if ($image = upload_url($item['image'])): ?>
                    <img src="<?= e($image) ?>" alt="<?= e($item['name']) ?>">
                <?php else: ?>
                    <?php App\Core\View::partial('gear-glyph', ['item' => $item]) ?>
                <?php endif ?>
            </div>
        </div>

        <div class="alat-info">
            <p class="eyebrow"><a href="<?= url('/alat', ['kategori' => $item['category_slug']]) ?>"><?= e($item['category_name']) ?></a></p>
            <h1 class="alat-title"><?= e($item['name']) ?></h1>
            <p class="alat-desc"><?= nl2br(e($item['description'])) ?></p>

            <dl class="quick-spec">
                <div><dt>Tarif</dt><dd class="mono"><?= rupiah($item['daily_rate']) ?> / hari</dd></div>
                <div><dt>Jumlah unit</dt><dd><?= $stock ?> unit</dd></div>
                <div><dt>Di tanggalmu</dt><dd><button type="button" class="avail-line" data-trip-open data-avail-id="<?= (int) $item['id'] ?>" data-avail-empty="Pilih tanggal sewa">Pilih tanggal sewa</button></dd></div>
            </dl>

            <div class="alat-buy">
                <div class="stepper" role="group" aria-label="Jumlah unit">
                    <button type="button" data-alat-step="-1" aria-label="Kurangi jumlah"><i class="bi bi-dash" aria-hidden="true"></i></button>
                    <output data-alat-qty>1</output>
                    <button type="button" data-alat-step="1" aria-label="Tambah jumlah"><i class="bi bi-plus" aria-hidden="true"></i></button>
                </div>
                <button type="button" class="btn btn-cta btn-lg btn-ransel" data-ransel-add="item:<?= (int) $item['id'] ?>" data-qty="1" data-name="<?= e($item['name']) ?>"><i class="bi bi-backpack" aria-hidden="true"></i> Masukkan ke ransel</button>
                <a class="btn btn-outline-primary btn-lg" href="<?= url('/sewa', ['alat' => $item['slug']]) ?>" data-sewa-link data-alat-sewa><i class="bi bi-calendar-check me-1" aria-hidden="true"></i> Sewa alat ini</a>
            </div>
            <p class="alat-estimate" data-alat-estimate aria-live="polite">Pilih tanggal untuk melihat perkiraan biaya.</p>

            <?php if ($packages !== []): ?>
                <div class="alat-packs">
                    <h2 class="h6">Termasuk dalam paket</h2>
                    <ul>
                        <?php foreach ($packages as $package): ?>
                            <li><a href="<?= url('/paket') ?>#<?= e($package['slug']) ?>"><i class="bi bi-box2-heart" aria-hidden="true"></i> <?= e($package['name']) ?> <span>×<?= (int) $package['quantity'] ?></span></a></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endif ?>

            <p class="alat-note"><i class="bi bi-clipboard-check" aria-hidden="true"></i>
                Stok dicek untuk setiap hari dalam tanggal sewamu. Kondisi alat dicatat bersama staf saat diambil dan saat dikembalikan.</p>
        </div>
    </div>

    <?php if ($related !== []): ?>
        <section class="alat-related" aria-labelledby="related-title">
            <div class="sec-head">
                <div class="scene-head">
                    <p class="eyebrow">Masih di <?= e($item['category_name']) ?></p>
                    <h2 id="related-title">Alat lain yang sejenis</h2>
                </div>
                <a class="fw-semibold" href="<?= url('/alat', ['kategori' => $item['category_slug']]) ?>">Lihat semua <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
            <div class="katalog-grid">
                <?php foreach ($related as $other): ?>
                    <?php App\Core\View::partial('equipment-card', ['item' => $other, 'packages' => [], 'hidden' => false]) ?>
                <?php endforeach ?>
            </div>
        </section>
    <?php endif ?>
</div>
