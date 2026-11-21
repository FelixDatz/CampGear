<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Package;

final class CatalogController
{
    /**
     * GET /alat?kategori=tenda&q=dome
     *
     * Semua alat hasil pencarian dikirim ke tampilan. Kategori hanya menandai kartu mana
     * yang tampil, sehingga chip kategori bisa berganti tanpa memuat ulang halaman
     * (katalog.js). Tanpa JavaScript, kartu kategori lain tetap tersembunyi.
     */
    public function index(): void
    {
        $categorySlug = Request::query('kategori');
        $search = mb_substr(Request::query('q'), 0, 100);

        $category = $categorySlug !== '' ? Category::findBySlug($categorySlug) : null;
        if ($categorySlug !== '' && $category === null) {
            throw new HttpException(404, 'Kategori tidak ditemukan.');
        }

        view('catalog/index', [
            'title'          => $category ? $category['name'] : 'Katalog Alat',
            'categories'     => Category::allWithCounts(),
            'category'       => $category,
            'search'         => $search,
            'items'          => Equipment::active(null, $search),
            'packagesByItem' => Package::mapByEquipment(Package::available()),
            'pageScripts'    => ['js/katalog.js'],
        ]);
    }

    /** GET /alat/{slug} */
    public function show(string $slug): void
    {
        $item = Equipment::findActiveBySlug($slug);
        if ($item === null) {
            throw new HttpException(404, 'Alat tidak ditemukan atau sudah tidak disewakan.');
        }

        // Alat lain di kategori yang sama (maksimal empat) untuk bagian "Alat lain".
        $related = array_values(array_filter(
            Equipment::active((int) $item['category_id']),
            static fn (array $other): bool => (int) $other['id'] !== (int) $item['id'],
        ));

        view('catalog/show', [
            'title'       => $item['name'],
            'item'        => $item,
            'packages'    => Package::containingEquipment((int) $item['id']),
            'related'     => array_slice($related, 0, 4),
            'pageScripts' => ['js/alat.js'],
        ]);
    }

    /** GET /paket */
    public function packages(): void
    {
        $packages = Package::activeWithItems();
        foreach ($packages as &$package) {
            $package['sleepers'] = Package::sleepers($package);
        }
        unset($package);

        view('catalog/packages', [
            'title'    => 'Paket Sewa',
            'packages' => $packages,
        ]);
    }
}
