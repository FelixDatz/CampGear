<?php
/**
 * Kartu alat di katalog (beranda dan /alat). Klik kartu membuka quick view (katalog.js);
 * tanpa JavaScript, tautannya menuju halaman detail alat. Stok untuk tanggal trip
 * diisi trip.js lewat data-avail-id.
 *
 * @var array $item
 * @var array $packages paket yang memuat alat ini (id, name, slug)
 * @var bool  $hidden   kartu disembunyikan karena tidak termasuk kategori aktif
 */
$detailUrl = url('/alat/' . $item['slug']);
$emptyStock = (int) $item['stock_total'] . ' unit · pilih tanggal';
$gear = [
    'id'       => (int) $item['id'],
    'name'     => $item['name'],
    'cat'      => $item['category_name'],
    'desc'     => excerpt($item['description'], 260),
    'rate'     => (float) $item['daily_rate'],
    'stock'    => (int) $item['stock_total'],
    'url'      => $detailUrl,
    'packages' => array_map(static fn (array $p): array => [
        'id'   => $p['id'],
        'name' => $p['name'],
        'url'  => url('/paket') . '#' . $p['slug'],
    ], $packages ?? []),
];
?>
<article class="gear-card lamp" data-cat="<?= e($item['category_slug']) ?>" data-gear="<?= e(json_encode($gear, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"<?= !empty($hidden) ? ' hidden' : '' ?>>
    <div class="gear-thumb" aria-hidden="true">
        <?php if ($image = upload_url($item['image'])): ?>
            <img src="<?= e($image) ?>" alt="" loading="lazy">
        <?php else: ?>
            <?php App\Core\View::partial('gear-glyph', ['item' => $item]) ?>
        <?php endif ?>
    </div>
    <div class="gear-body">
        <span class="gear-cat"><?= e($item['category_name']) ?></span>
        <h3 class="gear-name"><a class="stretched-link" href="<?= $detailUrl ?>" data-quick><?= e($item['name']) ?></a></h3>
        <p class="gear-price"><span class="mono"><?= rupiah($item['daily_rate']) ?></span> /hari</p>
        <p class="avail-line" data-avail-id="<?= (int) $item['id'] ?>" data-avail-empty="<?= e($emptyStock) ?>"><?= e($emptyStock) ?></p>
    </div>
    <button type="button" class="gear-add" data-ransel-add="item:<?= (int) $item['id'] ?>" data-name="<?= e($item['name']) ?>" aria-label="Tambah <?= e($item['name']) ?> ke ransel">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Ransel
    </button>
</article>
