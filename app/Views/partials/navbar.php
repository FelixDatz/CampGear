<?php
/**
 * Navbar halaman publik. Navigasi dikelompokkan menurut tujuan:
 *   Jelajahi (katalog & paket), Panduan (informasi), lalu ikon dalam frame
 *   seragam: langit/tema, cari, cek booking, ransel, akun. Satu tombol utama: Sewa Sekarang.
 * @var bool $isHome
 */
$user = auth_user();

// Isi mega panel "Jelajahi". Bila database bermasalah (mis. saat menampilkan
// halaman error), navbar tetap tampil tanpa pratinjau.
try {
    $megaCategories = App\Models\Category::allWithCounts();
    $megaItems = [];
    foreach (App\Models\Equipment::active() as $item) {
        $megaItems[(int) $item['category_id']][] = $item;
    }
    $megaPackages = array_values(array_filter(App\Models\Package::activeWithItems(), static fn ($p) => $p['is_available']));
} catch (Throwable) {
    $megaCategories = $megaItems = $megaPackages = [];
}
$firstSlug = $megaCategories[0]['slug'] ?? '';
$guides = [
    ['/cara-reservasi', 'Cara Reservasi', 'Alur sewa dan ketentuannya'],
    ['/artikel', 'Artikel', 'Checklist dan tips perjalanan'],
    ['/statistik', 'Statistik', 'Penyewaan dan pemakaian stok per periode'],
    ['/kontak', 'Kontak & FAQ', 'WhatsApp, alamat, jam buka'],
];
?>
<header class="sitebar" id="sitebar" data-sitebar>
    <div class="sitebar-inner">
        <a class="sitebar-brand" href="<?= url('/') ?>" aria-label="<?= e(config('app.name')) ?>, ke beranda">
            <svg class="brand-mark" viewBox="0 0 28 24" aria-hidden="true"><path class="m" d="M1 21 10 6l4.5 7.2L18 8l9 13z"/><circle class="s" cx="22.5" cy="4.5" r="2.6"/></svg>
            <span><?= e(config('app.name')) ?></span>
        </a>

        <nav class="sitebar-menu" aria-label="Navigasi utama">
            <div class="menu-group has-mega" data-menu>
                <button class="frame lamp" type="button" aria-expanded="false" aria-controls="menu-jelajahi" data-menu-toggle>
                    Jelajahi <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="mega" id="menu-jelajahi" data-menu-panel>
                    <ul class="mega-cats">
                        <?php foreach ($megaCategories as $category): ?>
                            <li>
                                <a class="mega-cat<?= $category['slug'] === $firstSlug ? ' is-on' : '' ?>" href="<?= url('/alat', ['kategori' => $category['slug']]) ?>" data-preview="<?= e($category['slug']) ?>">
                                    <i class="bi <?= e($category['icon'] ?: 'bi-box') ?>" aria-hidden="true"></i>
                                    <span><?= e($category['name']) ?></span>
                                    <span class="count"><?= (int) $category['equipment_count'] ?></span>
                                </a>
                            </li>
                        <?php endforeach ?>
                        <li class="mega-all"><a class="mega-cat" href="<?= url('/alat') ?>"><i class="bi bi-grid" aria-hidden="true"></i><span>Semua alat</span></a></li>
                    </ul>
                    <div>
                        <?php foreach ($megaCategories as $category): $items = $megaItems[(int) $category['id']] ?? []; ?>
                            <div class="mega-prev" data-preview-for="<?= e($category['slug']) ?>"<?= $category['slug'] === $firstSlug ? '' : ' hidden' ?>>
                                <h3><?= e($category['name']) ?></h3>
                                <?php if ($category['description']): ?><p><?= e($category['description']) ?></p><?php endif ?>
                                <?php if ($items !== []): ?>
                                    <ul class="mega-items">
                                        <?php foreach ($items as $item): ?>
                                            <li><span><?= e($item['name']) ?></span><span class="mono"><?= rupiah($item['daily_rate']) ?>/hari</span></li>
                                        <?php endforeach ?>
                                    </ul>
                                <?php endif ?>
                                <a class="fw-semibold" href="<?= url('/alat', ['kategori' => $category['slug']]) ?>">Lihat <?= count($items) ?> alat di katalog <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?php if ($megaPackages !== []): ?>
                        <div class="mega-foot">
                            <?php foreach ($megaPackages as $package): ?>
                                <a class="mega-pkg lamp" href="<?= url('/paket') ?>#<?= e($package['slug']) ?>">
                                    <b><?= e($package['name']) ?></b>
                                    <span><?= rupiah($package['daily_price']) ?>/hari<?php if ((float) $package['discount_percent'] > 0): ?> · hemat <?= (float) $package['discount_percent'] ?>%<?php endif ?></span>
                                </a>
                            <?php endforeach ?>
                        </div>
                    <?php endif ?>
                </div>
            </div>

            <div class="menu-group" data-menu>
                <button class="frame lamp" type="button" aria-expanded="false" aria-controls="menu-panduan" data-menu-toggle>
                    Panduan <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="dropmenu" id="menu-panduan" data-menu-panel>
                    <?php foreach ($guides as [$path, $label, $hint]): ?>
                        <a class="drop-item" href="<?= url($path) ?>"<?= nav_active($path) ? ' aria-current="page"' : '' ?>><b><?= e($label) ?></b><span><?= e($hint) ?></span></a>
                    <?php endforeach ?>
                </div>
            </div>
        </nav>

        <button class="trip-chip" type="button" data-trip-open data-trip-chip hidden>
            <i class="bi bi-calendar3" aria-hidden="true"></i><span data-trip-label></span>
        </button>

        <div class="sitebar-tools">
            <button class="frame lamp" type="button" popovertarget="pop-langit" aria-label="Langit dan tema tampilan" data-sky-button>
                <i class="bi bi-sun" aria-hidden="true" data-sky-icon></i><span class="frame-tip" data-sky-tip>Langit</span>
            </button>
            <button class="frame lamp" type="button" data-palette-open aria-label="Cari alat, paket, atau halaman (Ctrl+K)">
                <i class="bi bi-search" aria-hidden="true"></i><span class="frame-tip">Cari · Ctrl K</span>
            </button>
            <button class="frame lamp hide-mobile" type="button" popovertarget="pop-cek" aria-label="Cek status booking">
                <i class="bi bi-ticket-perforated" aria-hidden="true"></i><span class="frame-tip">Cek booking</span>
            </button>
            <button class="frame lamp hide-mobile" type="button" data-ransel-open aria-label="Ransel">
                <i class="bi bi-backpack" aria-hidden="true"></i><span class="frame-badge" data-ransel-count hidden>0</span><span class="frame-tip">Ransel</span>
            </button>
            <button class="frame lamp hide-mobile" type="button" popovertarget="pop-akun" aria-label="<?= $user ? 'Akun ' . e($user['name']) : 'Masuk atau daftar' ?>">
                <?php if ($user): ?>
                    <span class="frame-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span><span class="frame-tip">Akun</span>
                <?php else: ?>
                    <i class="bi bi-person" aria-hidden="true"></i><span class="frame-tip">Masuk</span>
                <?php endif ?>
            </button>
            <a class="btn btn-cta sitebar-cta" href="<?= url('/sewa') ?>" data-sewa-link>Sewa Sekarang</a>
        </div>
    </div>
</header>
