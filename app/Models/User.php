<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::first('SELECT * FROM users WHERE email = ?', [mb_strtolower($email)]);
    }

    /** Membuat akun baru. Password selalu disimpan dalam bentuk hash. */
    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO users (name, email, phone, password_hash, role) VALUES (?, ?, ?, ?, ?)',
            [
                $data['name'],
                mb_strtolower($data['email']),
                $data['phone'] ?? null,
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'] ?? 'user',
            ],
        );
    }

    public static function updatePassword(int $id, string $plainPassword): string
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        Database::execute(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [$hash, $id],
        );
        return $hash;
    }

    public static function touchLastLogin(int $id): void
    {
        Database::execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    /** Perubahan data pribadi oleh pemilik akun (tanpa role & status). */
    public static function updateProfile(int $id, array $data): void
    {
        Database::execute(
            'UPDATE users SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?',
            [$data['name'], mb_strtolower($data['email']), $data['phone'], $data['address'], $id],
        );
    }

    // ---- Manajemen akun oleh Admin ----------------------------------------

    /** @return array{0: string, 1: array} */
    private static function adminFilter(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        if (in_array($filters['role'] ?? '', ['user', 'staff', 'admin'], true)) {
            $where[] = 'u.role = ?';
            $params[] = $filters['role'];
        }
        if (in_array($filters['active'] ?? '', ['1', '0'], true)) {
            $where[] = 'u.is_active = ?';
            $params[] = (int) $filters['active'];
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    public static function adminCount(array $filters): int
    {
        [$where, $params] = self::adminFilter($filters);
        return (int) Database::value("SELECT COUNT(*) FROM users u {$where}", $params);
    }

    public static function adminList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::adminFilter($filters);
        return Database::select(
            "SELECT u.*, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count
             FROM users u {$where}
             ORDER BY FIELD(u.role, 'admin', 'staff', 'user'), u.name
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
    }

    public static function updateByAdmin(int $id, array $data): void
    {
        Database::execute(
            'UPDATE users SET name = ?, email = ?, phone = ?, address = ?, role = ?, is_active = ? WHERE id = ?',
            [$data['name'], mb_strtolower($data['email']), $data['phone'], $data['address'], $data['role'], $data['is_active'], $id],
        );
    }

    /** Jumlah admin aktif selain $exceptId (agar admin terakhir tidak bisa dinonaktifkan/diturunkan). */
    public static function otherActiveAdmins(int $exceptId): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?",
            [$exceptId],
        );
    }

    /** Akun dengan riwayat transaksi atau catatan aktivitas tidak dihapus agar jejaknya tetap utuh. */
    public static function deleteBlocker(int $id): ?string
    {
        $bookings = (int) Database::value('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$id]);
        if ($bookings > 0) {
            return "Akun ini memiliki {$bookings} booking. Nonaktifkan saja agar riwayatnya tetap tersimpan.";
        }
        $logs = (int) Database::value('SELECT COUNT(*) FROM audit_logs WHERE user_id = ?', [$id]);
        if ($logs > 0) {
            return 'Akun ini memiliki catatan aktivitas. Nonaktifkan saja agar jejak tindakannya tetap tersimpan.';
        }
        return null;
    }

    public static function delete(int $id): void
    {
        Database::execute('DELETE FROM users WHERE id = ?', [$id]);
    }

    /** Akun staff & admin untuk filter log aktivitas. */
    public static function staffOptions(): array
    {
        return Database::select("SELECT id, name, role FROM users WHERE role IN ('staff','admin') ORDER BY name");
    }
}
