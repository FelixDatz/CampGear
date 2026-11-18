<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use LogicException;

/** Pembuat slug URL yang unik per tabel: "tenda-dome", "tenda-dome-2", ... */
final class Slug
{
    private const TABLES = ['categories', 'equipment', 'packages', 'articles'];

    public static function unique(string $table, string $text, ?int $ignoreId = null): string
    {
        if (!in_array($table, self::TABLES, true)) {
            throw new LogicException("Tabel slug tidak dikenal: {$table}");
        }

        $base = mb_substr(slugify($text), 0, 120);
        $slug = $base;
        for ($i = 2; self::exists($table, $slug, $ignoreId); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    private static function exists(string $table, string $slug, ?int $ignoreId): bool
    {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        return (int) Database::value($sql, $params) > 0;
    }
}
