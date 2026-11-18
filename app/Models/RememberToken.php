<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Token "Ingat saya". Lihat penjelasan di App\Core\Auth. */
final class RememberToken
{
    public static function create(int $userId, string $selector, string $validatorHash, string $expiresAt): void
    {
        Database::insert(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)',
            [$userId, $selector, $validatorHash, $expiresAt],
        );
    }

    public static function findValid(string $selector): ?array
    {
        return Database::first(
            'SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()',
            [$selector],
        );
    }

    public static function deleteBySelector(string $selector): void
    {
        Database::execute('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
    }

    /** Dipanggil saat password diganti, akun dinonaktifkan, atau dicurigai pencurian cookie. */
    public static function deleteForUser(int $userId): void
    {
        Database::execute('DELETE FROM remember_tokens WHERE user_id = ?', [$userId]);
    }
}
