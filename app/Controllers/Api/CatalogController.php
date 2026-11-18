<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Models\Article;
use App\Models\Equipment;
use App\Models\Package;

/**
 * GET /api/katalog
 *
 * Daftar ringkas alat, paket, dan artikel untuk palet pencarian (Ctrl+K) dan ransel.
 * Hanya data publik: nama, slug, kategori, harga, stok total.
 *
 * Respons 200:
 *   { "equipment": [{ "id", "name", "slug", "category", "rate", "stock" }],
 *     "packages":  [{ "id", "name", "slug", "discount", "daily_price", "items": [{ "equipment_id", "quantity" }] }],
 *     "articles":  [{ "title", "slug" }] }
 */
final class CatalogController
{
    public function index(): void
    {
        $equipment = array_map(static fn (array $e): array => [
            'id'       => (int) $e['id'],
            'name'     => $e['name'],
            'slug'     => $e['slug'],
            'category' => $e['category_name'],
            'rate'     => (float) $e['daily_rate'],
            'stock'    => (int) $e['stock_total'],
        ], Equipment::active());

        $available = array_filter(Package::activeWithItems(), static fn (array $p): bool => $p['is_available']);
        $packages = array_map(static fn (array $p): array => [
            'id'          => (int) $p['id'],
            'name'        => $p['name'],
            'slug'        => $p['slug'],
            'discount'    => (float) $p['discount_percent'],
            'daily_price' => (float) $p['daily_price'],
            'items'       => array_map(static fn (array $i): array => [
                'equipment_id' => (int) $i['equipment_id'],
                'quantity'     => (int) $i['quantity'],
            ], $p['items']),
        ], array_values($available));

        $articles = array_map(static fn (array $a): array => [
            'title' => $a['title'],
            'slug'  => $a['slug'],
        ], Article::published(30));

        header('Cache-Control: no-store');
        json_response(['equipment' => $equipment, 'packages' => $packages, 'articles' => $articles]);
    }
}
