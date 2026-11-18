<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

/**
 * Catatan tindakan Staff/Admin. Dipanggil di setiap aksi yang mengubah data.
 *
 *   AuditLog::record('equipment.update', 'equipment', 12, 'Mengubah tarif Tenda Dome 2P');
 */
final class AuditLog
{
    public static function record(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        string $description = '',
        array $meta = [],
    ): void {
        Database::insert(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, meta, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                Auth::id(),
                $action,
                $entityType,
                $entityId,
                mb_substr($description, 0, 255),
                $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                Request::ip(),
            ],
        );
    }

    /** Label kelompok aksi untuk filter. */
    public const GROUPS = [
        'auth'         => 'Login/Logout',
        'booking'      => 'Operasional booking',
        'cancellation' => 'Pembatalan',
        'damage'       => 'Kerusakan/kehilangan',
        'equipment'    => 'Alat',
        'category'     => 'Kategori',
        'package'      => 'Paket',
        'article'      => 'Artikel',
        'user'         => 'Akun pengguna',
        'profile'      => 'Profil sendiri',
    ];

    /** @return array{0: string, 1: array} */
    private static function filter(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['user'] ?? 0) > 0) {
            $where[] = 'l.user_id = ?';
            $params[] = (int) $filters['user'];
        }
        if (array_key_exists($filters['group'] ?? '', self::GROUPS)) {
            $where[] = 'l.action LIKE ?';
            $params[] = $filters['group'] . '.%';
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'l.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'l.created_at < ? + INTERVAL 1 DAY';
            $params[] = $filters['to'];
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    public static function count(array $filters): int
    {
        [$where, $params] = self::filter($filters);
        return (int) Database::value("SELECT COUNT(*) FROM audit_logs l {$where}", $params);
    }

    public static function paginate(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::filter($filters);
        return Database::select(
            "SELECT l.*, u.name AS user_name, u.role AS user_role, b.code AS booking_code
             FROM audit_logs l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN bookings b ON l.entity_type = 'booking' AND b.id = l.entity_id
             {$where}
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
    }

    /** Tautan ke objek terkait (booking/alat/artikel) bila masih ada. */
    public static function entityLink(array $log): ?string
    {
        if ($log['entity_id'] === null) {
            return null;
        }
        return match ($log['entity_type']) {
            'booking'   => !empty($log['booking_code']) ? '/staff/booking/' . $log['booking_code'] : null,
            'equipment' => '/staff/alat/' . $log['entity_id'] . '/edit',
            'article'   => '/staff/artikel/' . $log['entity_id'] . '/edit',
            'package'   => '/staff/paket/' . $log['entity_id'] . '/edit',
            'category'  => '/staff/kategori/' . $log['entity_id'] . '/edit',
            'user'      => '/admin/pengguna/' . $log['entity_id'] . '/edit',
            default     => null,
        };
    }
}
