<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\BusinessRuleException;

final class Category
{
    /** Ikon Bootstrap Icons yang bisa dipilih untuk kategori. */
    public const ICONS = [
        'bi-house-door' => 'Tenda', 'bi-backpack' => 'Tas', 'bi-moon-stars' => 'Tidur', 'bi-fire' => 'Masak',
        'bi-lightbulb' => 'Lampu', 'bi-compass' => 'Navigasi', 'bi-tree' => 'Pohon', 'bi-droplet' => 'Air',
        'bi-umbrella' => 'Payung', 'bi-bicycle' => 'Sepeda', 'bi-tools' => 'Peralatan', 'bi-box' => 'Lainnya',
    ];

    /** Semua kategori beserta jumlah alat aktif di dalamnya (LEFT JOIN + GROUP BY). */
    public static function allWithCounts(): array
    {
        return Database::select(
            'SELECT c.*, COUNT(e.id) AS equipment_count
             FROM categories c
             LEFT JOIN equipment e ON e.category_id = c.id AND e.is_active = 1
             GROUP BY c.id
             ORDER BY c.id',
        );
    }

    /** Untuk panel staff: jumlah alat aktif dan total (termasuk nonaktif). */
    public static function allForAdmin(): array
    {
        return Database::select(
            'SELECT c.*, COUNT(e.id) AS total_count, COALESCE(SUM(e.is_active = 1), 0) AS active_count
             FROM categories c
             LEFT JOIN equipment e ON e.category_id = c.id
             GROUP BY c.id
             ORDER BY c.name',
        );
    }

    public static function options(): array
    {
        return Database::select('SELECT id, name FROM categories ORDER BY name');
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO categories (name, slug, description, icon) VALUES (?, ?, ?, ?)',
            [$data['name'], Slug::unique('categories', $data['slug'] ?? $data['name']), $data['description'], $data['icon']],
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE categories SET name = ?, slug = ?, description = ?, icon = ? WHERE id = ?',
            [$data['name'], Slug::unique('categories', $data['slug'] ?? $data['name'], $id), $data['description'], $data['icon'], $id],
        );
    }

    /** Kategori hanya bisa dihapus bila tidak ada alat di dalamnya. */
    public static function delete(int $id): void
    {
        $count = (int) Database::value('SELECT COUNT(*) FROM equipment WHERE category_id = ?', [$id]);
        if ($count > 0) {
            throw new BusinessRuleException("Kategori masih berisi {$count} alat. Pindahkan atau hapus alatnya terlebih dahulu.");
        }
        Database::execute('DELETE FROM categories WHERE id = ?', [$id]);
    }
}
