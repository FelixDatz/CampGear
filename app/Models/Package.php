<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Package
{
    /**
     * Paket aktif beserta isi alatnya dan harga yang sudah dihitung:
     *   normal_daily = jumlah (qty x tarif harian) semua alat
     *   daily_price  = normal_daily setelah diskon paket
     */
    public static function activeWithItems(): array
    {
        $packages = Database::select('SELECT * FROM packages WHERE is_active = 1 ORDER BY id');
        return self::attachItems($packages);
    }

    /** Paket aktif yang memuat alat tertentu. */
    /** Paket aktif yang semua alatnya masih aktif, sehingga bisa disewa. */
    public static function available(): array
    {
        return array_values(array_filter(self::activeWithItems(), static fn (array $p): bool => $p['is_available']));
    }

    /**
     * Peta equipment_id => daftar paket (id, name, slug) yang memuat alat itu.
     * Dipakai quick view katalog ("termasuk dalam paket") dan kartu kategori di beranda.
     */
    public static function mapByEquipment(array $packages): array
    {
        $map = [];
        foreach ($packages as $package) {
            foreach ($package['items'] as $item) {
                $map[(int) $item['equipment_id']][] = [
                    'id'   => (int) $package['id'],
                    'name' => $package['name'],
                    'slug' => $package['slug'],
                ];
            }
        }
        return $map;
    }

    public static function containingEquipment(int $equipmentId): array
    {
        return Database::select(
            'SELECT p.id, p.name, p.slug, pi.quantity
             FROM packages p
             JOIN package_items pi ON pi.package_id = p.id
             WHERE p.is_active = 1 AND pi.equipment_id = ?
             ORDER BY p.id',
            [$equipmentId],
        );
    }

    // ---- Panel staff (CRUD) -----------------------------------------------

    /** Semua paket (aktif & nonaktif) beserta isi dan harga. */
    public static function allForAdmin(): array
    {
        return self::attachItems(Database::select('SELECT * FROM packages ORDER BY is_active DESC, name'));
    }

    public static function find(int $id): ?array
    {
        $package = Database::first('SELECT * FROM packages WHERE id = ?', [$id]);
        return $package === null ? null : self::attachItems([$package])[0];
    }

    /**
     * @param array $items [['equipment_id' => int, 'quantity' => int], ...]
     */
    public static function save(?int $id, array $data, array $items): int
    {
        return Database::transaction(static function () use ($id, $data, $items): int {
            $slug = Slug::unique('packages', $data['slug'] ?? $data['name'], $id);
            if ($id === null) {
                $id = Database::insert(
                    'INSERT INTO packages (name, slug, description, image, discount_percent, is_active) VALUES (?, ?, ?, ?, ?, ?)',
                    [$data['name'], $slug, $data['description'], $data['image'], $data['discount_percent'], $data['is_active']],
                );
            } else {
                Database::execute(
                    'UPDATE packages SET name = ?, slug = ?, description = ?, image = ?, discount_percent = ?, is_active = ? WHERE id = ?',
                    [$data['name'], $slug, $data['description'], $data['image'], $data['discount_percent'], $data['is_active'], $id],
                );
                Database::execute('DELETE FROM package_items WHERE package_id = ?', [$id]);
            }
            foreach ($items as $item) {
                Database::insert(
                    'INSERT INTO package_items (package_id, equipment_id, quantity) VALUES (?, ?, ?)',
                    [$id, $item['equipment_id'], $item['quantity']],
                );
            }
            return $id;
        });
    }

    /** Paket yang pernah dipesan tidak dihapus agar label paket di riwayat booking tetap ada. */
    public static function deleteBlocker(int $id): ?string
    {
        $used = (int) Database::value('SELECT COUNT(*) FROM booking_items WHERE package_id = ?', [$id]);
        return $used > 0 ? 'Paket ini sudah pernah dipesan sehingga tidak dapat dihapus. Nonaktifkan saja.' : null;
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM packages WHERE id = ?', [$id]);
    }

    private static function attachItems(array $packages): array
    {
        if ($packages === []) {
            return [];
        }

        $ids = array_column($packages, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = Database::select(
            "SELECT pi.package_id, pi.quantity, e.id AS equipment_id, e.name, e.slug,
                    e.daily_rate, e.stock_total, e.is_active
             FROM package_items pi
             JOIN equipment e ON e.id = pi.equipment_id
             WHERE pi.package_id IN ({$placeholders})
             ORDER BY pi.package_id, e.category_id, e.name",
            array_map('intval', $ids),
        );

        $byPackage = [];
        foreach ($items as $item) {
            $byPackage[$item['package_id']][] = $item;
        }

        foreach ($packages as &$package) {
            $package['items'] = $byPackage[$package['id']] ?? [];
            $normal = 0.0;
            $available = $package['items'] !== [];
            foreach ($package['items'] as $item) {
                $normal += $item['quantity'] * (float) $item['daily_rate'];
                $available = $available && (int) $item['is_active'] === 1;
            }
            $package['normal_daily'] = $normal;
            $package['daily_price'] = round($normal * (1 - (float) $package['discount_percent'] / 100));
            $package['is_available'] = $available;
        }
        unset($package);

        return $packages;
    }
}
