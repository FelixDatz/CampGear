<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Artikel informatif seputar camping. Hanya artikel 'published' yang tampil di publik. */
final class Article
{
    public const STATUS_LABELS = ['draft' => 'Draf', 'published' => 'Terbit'];

    private const SELECT = 'SELECT a.*, u.name AS author_name FROM articles a LEFT JOIN users u ON u.id = a.author_id';

    public static function publishedCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM articles WHERE status = 'published' AND published_at <= NOW()");
    }

    public static function published(int $limit, int $offset = 0): array
    {
        return Database::select(
            self::SELECT . " WHERE a.status = 'published' AND a.published_at <= NOW()
                             ORDER BY a.published_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset],
        );
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        return Database::first(
            self::SELECT . " WHERE a.slug = ? AND a.status = 'published' AND a.published_at <= NOW()",
            [$slug],
        );
    }

    /** Artikel lain untuk bagian "Baca juga". */
    public static function related(int $exceptId, int $limit = 3): array
    {
        return Database::select(
            self::SELECT . " WHERE a.status = 'published' AND a.published_at <= NOW() AND a.id <> ?
                             ORDER BY a.published_at DESC LIMIT ?",
            [$exceptId, $limit],
        );
    }

    // ---- Panel staff ------------------------------------------------------

    public static function adminCount(string $status): int
    {
        [$where, $params] = self::filter($status);
        return (int) Database::value("SELECT COUNT(*) FROM articles a {$where}", $params);
    }

    public static function adminList(string $status, int $limit, int $offset): array
    {
        [$where, $params] = self::filter($status);
        return Database::select(self::SELECT . " {$where} ORDER BY a.updated_at DESC LIMIT ? OFFSET ?", [...$params, $limit, $offset]);
    }

    private static function filter(string $status): array
    {
        return array_key_exists($status, self::STATUS_LABELS) ? ['WHERE a.status = ?', [$status]] : ['', []];
    }

    public static function find(int $id): ?array
    {
        return Database::first(self::SELECT . ' WHERE a.id = ?', [$id]);
    }

    public static function create(array $data, int $authorId): int
    {
        return Database::insert(
            'INSERT INTO articles (author_id, title, slug, excerpt, body, cover_image, status, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $authorId, $data['title'], Slug::unique('articles', $data['slug'] ?? $data['title']),
                $data['excerpt'], $data['body'], $data['cover_image'], $data['status'],
                $data['status'] === 'published' ? date('Y-m-d H:i:s') : null,
            ],
        );
    }

    /** Tanggal terbit diisi saat pertama kali diterbitkan dan dipertahankan setelahnya. */
    public static function update(int $id, array $data, array $current): void
    {
        $publishedAt = $current['published_at'];
        if ($data['status'] === 'published' && $publishedAt === null) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        Database::execute(
            'UPDATE articles SET title = ?, slug = ?, excerpt = ?, body = ?, cover_image = ?, status = ?, published_at = ? WHERE id = ?',
            [
                $data['title'], Slug::unique('articles', $data['slug'] ?? $data['title'], $id),
                $data['excerpt'], $data['body'], $data['cover_image'], $data['status'], $publishedAt, $id,
            ],
        );
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM articles WHERE id = ?', [$id]);
    }

    /** Perkiraan waktu baca (200 kata per menit). */
    public static function readingMinutes(string $body): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags($body)) / 200));
    }
}
