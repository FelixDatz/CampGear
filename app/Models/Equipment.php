<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Equipment
{
    private const SELECT_WITH_CATEGORY =
        'SELECT e.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon
         FROM equipment e
         JOIN categories c ON c.id = e.category_id';

    /** Alat aktif untuk katalog publik, dengan filter kategori dan kata kunci opsional. */
    public static function active(?int $categoryId = null, string $search = ''): array
    {
        $sql = self::SELECT_WITH_CATEGORY . ' WHERE e.is_active = 1';
        $params = [];

        if ($categoryId !== null) {
            $sql .= ' AND e.category_id = ?';
            $params[] = $categoryId;
        }
        if ($search !== '') {
            $sql .= ' AND (e.name LIKE ? OR e.description LIKE ?)';
            $like = '%' . self::escapeLike($search) . '%';
            array_push($params, $like, $like);
        }
        $sql .= ' ORDER BY c.id, e.daily_rate DESC, e.name';

        return Database::select($sql, $params);
    }

    public static function featured(int $limit = 6): array
    {
        return Database::select(
            self::SELECT_WITH_CATEGORY . ' WHERE e.is_active = 1 ORDER BY e.id LIMIT ?',
            [$limit],
        );
    }

    public static function findActiveBySlug(string $slug): ?array
    {
        return Database::first(
            self::SELECT_WITH_CATEGORY . ' WHERE e.slug = ? AND e.is_active = 1',
            [$slug],
        );
    }

    public static function countActive(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM equipment WHERE is_active = 1');
    }

    // ---- Panel staff (CRUD) -----------------------------------------------

    /** @return array{0: string, 1: array} */
    private static function adminFilter(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $where[] = 'e.name LIKE ?';
            $params[] = '%' . self::escapeLike($filters['q']) . '%';
        }
        if (($filters['category'] ?? 0) > 0) {
            $where[] = 'e.category_id = ?';
            $params[] = (int) $filters['category'];
        }
        if (in_array($filters['active'] ?? '', ['1', '0'], true)) {
            $where[] = 'e.is_active = ?';
            $params[] = (int) $filters['active'];
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    public static function adminCount(array $filters): int
    {
        [$where, $params] = self::adminFilter($filters);
        return (int) Database::value("SELECT COUNT(*) FROM equipment e {$where}", $params);
    }

    public static function adminList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::adminFilter($filters);
        return Database::select(
            self::SELECT_WITH_CATEGORY . " {$where} ORDER BY e.is_active DESC, c.name, e.name LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first(self::SELECT_WITH_CATEGORY . ' WHERE e.id = ?', [$id]);
    }

    /** Daftar ringkas untuk pilihan isi paket. */
    public static function options(): array
    {
        return Database::select('SELECT id, name, daily_rate, is_active FROM equipment ORDER BY is_active DESC, name');
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO equipment (category_id, name, slug, description, image, stock_total, daily_rate, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['category_id'], $data['name'], Slug::unique('equipment', $data['slug'] ?? $data['name']),
                $data['description'], $data['image'], $data['stock_total'], $data['daily_rate'], $data['is_active'],
            ],
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE equipment SET category_id = ?, name = ?, slug = ?, description = ?, image = ?,
                    stock_total = ?, daily_rate = ?, is_active = ?
             WHERE id = ?',
            [
                $data['category_id'], $data['name'], Slug::unique('equipment', $data['slug'] ?? $data['name'], $id),
                $data['description'], $data['image'], $data['stock_total'], $data['daily_rate'], $data['is_active'], $id,
            ],
        );
    }

    /**
     * Alat yang pernah disewa atau menjadi isi paket tidak boleh dihapus,
     * agar riwayat transaksi tetap utuh. Gunakan status nonaktif sebagai gantinya.
     */
    public static function deleteBlocker(int $id): ?string
    {
        $booked = (int) Database::value('SELECT COUNT(*) FROM booking_items WHERE equipment_id = ?', [$id]);
        if ($booked > 0) {
            return 'Alat ini sudah pernah disewa sehingga tidak dapat dihapus. Nonaktifkan saja agar tidak tampil di katalog.';
        }
        $inPackage = (int) Database::value('SELECT COUNT(*) FROM package_items WHERE equipment_id = ?', [$id]);
        if ($inPackage > 0) {
            return 'Alat ini menjadi isi paket. Keluarkan dari paket terlebih dahulu, atau nonaktifkan alatnya.';
        }
        return null;
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM equipment WHERE id = ?', [$id]);
    }

    /** Karakter % dan _ dalam kata kunci diperlakukan sebagai teks biasa, bukan wildcard. */
    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
