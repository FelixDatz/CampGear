<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Pembatas percobaan login (brute-force protection).
 * Maksimal 5 kali gagal per kombinasi email + IP, atau 20 kali per IP, dalam 15 menit.
 */
final class LoginAttempt
{
    private const WINDOW_MINUTES = 15;
    private const MAX_PER_EMAIL = 5;
    private const MAX_PER_IP = 20;

    public static function tooMany(string $email, string $ip): bool
    {
        $row = Database::first(
            'SELECT
                SUM(email = ?) AS by_email,
                COUNT(*)       AS by_ip
             FROM login_attempts
             WHERE ip_address = ? AND attempted_at > NOW() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE',
            [mb_strtolower($email), $ip],
        );
        return (int) ($row['by_email'] ?? 0) >= self::MAX_PER_EMAIL
            || (int) ($row['by_ip'] ?? 0) >= self::MAX_PER_IP;
    }

    public static function record(string $email, string $ip): void
    {
        Database::insert(
            'INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)',
            [mb_strtolower(mb_substr($email, 0, 150)), $ip],
        );
        // Bersihkan catatan lama agar tabel tidak terus membesar.
        Database::execute('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }

    public static function clear(string $email, string $ip): void
    {
        Database::execute(
            'DELETE FROM login_attempts WHERE email = ? AND ip_address = ?',
            [mb_strtolower($email), $ip],
        );
    }

    public static function windowMinutes(): int
    {
        return self::WINDOW_MINUTES;
    }
}
