<?php
/**
 * Katalog alat: baris chip kategori yang menempel di bawah navbar selama katalog
 * di-scroll, grid kartu alat, dan dialog quick view. Dipakai di beranda dan /alat;
 * interaksinya diatur katalog.js.
 *
 * @var array  $categories     kategori (id, name, slug, icon, description)
 * @var array  $items          alat yang ditampilkan (hasil Equipment::active)
 * @var array  $packagesByItem equipment_id => paket yang memuatnya
 * @var string $activeSlug     kategori aktif ('' = semua)
 * @var string $search         kata kunci pencarian ('' bila tidak ada)
 * @var bool   $isPage         true di /alat: URL, judul, dan deskripsi ikut berganti saat chip dipilih
 */
$counts = array_count_values(array_map(static fn (array $i): string => (string) $i['category_slug'], $items));
$visible = $activeSlug === '' ? count($items) : ($counts[$activeSlug] ?? 0);
$activeName = '';
foreach ($categories as $cat) {
    if ($cat['slug'] === $activeSlug) {
        $activeName = $cat['name'];
    }
}
$countText = 'Menampilkan ' . $visible . ' alat'
    . ($activeName !== '' ? ' di ' . $activeName : '')
    . ($search !== '' ? ' untuk "' . $search . '"' : '');
$query = static fn (string $slug): array => ($slug !== '' ? ['kategori' => $slug] : []) + ($search !== '' ? ['q' => $search] : []);
?>
<div class="katalog" data-katalog data-active="<?= e($activeSlug) ?>" data-search="<?= e($search) ?>"<?= $isPage ? ' data-katalog-url' : '' ?>>
    <div class="catbar" data-catbar>
        <nav class="chips" aria-label="Filter kategori">
            <a class="chip" href="<?= url('/alat', $query('')) ?>" data-chip="" data-name="Semua alat"<?= $activeSlug === '' ? ' aria-current="true"' : '' ?>>
                Semua <span class="chip-n"><?= count($items) ?></span>
            </a>
            <?php foreach ($categories as $cat): ?>
                <a class="chip<?= isset($counts[$cat['slug']]) ? '' : ' is-empty' ?>" href="<?= url('/alat', $query($cat['slug'])) ?>"
                   data-chip="<?= e($cat['slug']) ?>" data-name="<?= e($cat['name']) ?>" data-desc="<?= e($cat['description'] ?? '') ?>"<?= $activeSlug === $cat['slug'] ? ' aria-current="true"' : '' ?>>
                    <i class="bi <?= e($cat['icon'] ?: 'bi-box') ?>" aria-hidden="true"></i><?= e($cat['name']) ?> <span class="chip-n"><?= $counts[$cat['slug']] ?? 0 ?></span>
                </a>
            <?php endforeach ?>
        </nav>
        <button class="chip chip-trip" type="button" data-trip-open aria-label="Pilih tanggal sewa untuk cek stok">
            <i class="bi bi-calendar3" aria-hidden="true"></i><span data-trip-range data-trip-empty="Pilih tanggal">Pilih tanggal</span>
        </button>
    </div>

    <div class="katalog-meta">
        <p class="katalog-count" data-katalog-count aria-live="polite"><?= e($countText) ?></p>
        <?php if ($search !== ''): ?>
            <a class="small" href="<?= url('/alat') ?>"><i class="bi bi-x-circle" aria-hidden="true"></i> Hapus pencarian</a>
        <?php endif ?>
    </div>

    <div class="katalog-grid" data-katalog-grid>
        <?php foreach ($items as $item): ?>
            <?php App\Core\View::partial('equipment-card', [
                'item'     => $item,
                'packages' => $packagesByItem[(int) $item['id']] ?? [],
                'hidden'   => $activeSlug !== '' && $item['category_slug'] !== $activeSlug,
            ]) ?>
        <?php endforeach ?>
    </div>

    <div class="empty-state" data-katalog-empty<?= $visible > 0 ? ' hidden' : '' ?>>
        <i class="bi bi-search" aria-hidden="true"></i>
        <h2 class="h5">Alat tidak ditemukan</h2>
        <p class="text-muted">Coba kata kunci lain atau lihat semua kategori.</p>
        <a class="btn btn-outline-primary" href="<?= url('/alat') ?>">Lihat semua alat</a>
    </div>
</div>

<!-- Quick view: ringkasan alat tanpa pindah halaman -->
<dialog data-lenis-prevent class="quick" id="quick-view" aria-labelledby="quick-title">
    <div class="quick-art" data-quick-art aria-hidden="true"></div>
    <div class="quick-body">
        <p class="eyebrow" data-quick-cat></p>
        <h2 id="quick-title" data-quick-name></h2>
        <p class="quick-desc" data-quick-desc></p>
        <dl class="quick-spec">
            <div><dt>Tarif</dt><dd class="mono" data-quick-rate></dd></div>
            <div><dt>Jumlah unit</dt><dd data-quick-stock></dd></div>
            <div><dt>Di tanggalmu</dt><dd data-quick-avail></dd></div>
        </dl>
        <p class="quick-pkgs" data-quick-pkgs></p>
        <div class="quick-actions">
            <div class="stepper" role="group" aria-label="Jumlah">
                <button type="button" data-quick-step="-1" aria-label="Kurangi jumlah"><i class="bi bi-dash" aria-hidden="true"></i></button>
                <output data-quick-qty>1</output>
                <button type="button" data-quick-step="1" aria-label="Tambah jumlah"><i class="bi bi-plus" aria-hidden="true"></i></button>
            </div>
            <button type="button" class="btn btn-cta" data-quick-add data-ransel-add="" data-qty="1"><i class="bi bi-backpack me-1" aria-hidden="true"></i>Masukkan ransel</button>
        </div>
        <a class="quick-more" href="<?= url('/alat') ?>" data-quick-link>Lihat detail alat <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <button class="icon-close quick-close" type="button" data-dialog-close aria-label="Tutup" autofocus><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</dialog>
