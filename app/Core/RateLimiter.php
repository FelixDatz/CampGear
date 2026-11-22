<?php

declare(strict_types=1);

namespace App\Core;

use mysqli_sql_exception;

/**
 * Pembatas frekuensi permintaan (Rate Limiter) berbasis IP & kunci hash HMAC.
 * Menggunakan operasi SQL atomik pada tabel rate_limits.
 */
final class RateLimiter
{
    /**
     * Catat percobaan dan periksa apakah masih dalam batas kuota.
     * Mengembalikan true bila masih diizinkan, false bila kuota habis.
     */
    public static function attempt(string $ip, int $maxAttempts = 60, int $windowSeconds = 3600): bool
    {
        $rateKey = self::ipKey($ip);

        // Pembersihan probabilistik (1 dari 100 request) agar tabel tetap ramping
        if (random_int(1, 100) === 1) {
            self::prune();
        }

        try {
            Database::execute(
                'INSERT INTO rate_limits (rate_key, hits, reset_at)
                 VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))
                 ON DUPLICATE KEY UPDATE
                    hits = IF(reset_at <= NOW(), 1, hits + 1),
                    reset_at = IF(reset_at <= NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), reset_at)',
                [$rateKey, $windowSeconds, $windowSeconds],
            );

            $hits = (int) Database::value(
                'SELECT hits FROM rate_limits WHERE rate_key = ?',
                [$rateKey],
            );

            return $hits <= $maxAttempts;
        } catch (mysqli_sql_exception $e) {
            return self::handleDbError($e, failOpenValue: true);
        }
    }

    /**
     * Periksa apakah kuota untuk IP ini sudah terlampaui tanpa menambah hitungan.
     */
    public static function tooMany(string $ip, int $maxAttempts = 60): bool
    {
        $rateKey = self::ipKey($ip);
        try {
            $hits = (int) Database::value(
                'SELECT hits FROM rate_limits WHERE rate_key = ? AND reset_at > NOW()',
                [$rateKey],
            );
            return $hits >= $maxAttempts;
        } catch (mysqli_sql_exception $e) {
            self::handleDbError($e, failOpenValue: false);
            return false;
        }
    }

    /**
     * Sisa kuota yang masih dapat digunakan dalam jendela saat ini.
     */
    public static function remaining(string $ip, int $maxAttempts = 60): int
    {
        $rateKey = self::ipKey($ip);
        try {
            $hits = (int) Database::value(
                'SELECT hits FROM rate_limits WHERE rate_key = ? AND reset_at > NOW()',
                [$rateKey],
            );
            return max(0, $maxAttempts - $hits);
        } catch (mysqli_sql_exception $e) {
            self::handleDbError($e, failOpenValue: false);
            return 0;
        }
    }

    /**
     * Bersihkan seluruh rekaman rate limit yang telah kedaluwarsa.
     */
    public static function prune(): int
    {
        try {
            return Database::execute('DELETE FROM rate_limits WHERE reset_at < NOW()');
        } catch (mysqli_sql_exception) {
            return 0;
        }
    }

    /**
     * Buat kunci rate limit berbobot HMAC-SHA256 dari IP pengguna.
     * Hasil hashing 64 hex karakter diawali prefix (panjang total <= 80 karakter).
     */
    public static function ipKey(string $ip, string $prefix = 'bk'): string
    {
        $secret = (string) config('app.key');
        if ($secret === '') {
            throw new \RuntimeException(
                'app.key belum dikonfigurasi. Hasilkan kunci acak dengan: ' .
                'php -r "echo \'base64:\' . base64_encode(random_bytes(32)) . PHP_EOL;"'
            );
        }
        $hmac = hash_hmac('sha256', trim($ip), $secret);
        return substr($prefix . ':' . $hmac, 0, 80);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Tangani error database pada metode rate limiter secara konsisten.
     *
     * - Errno 1146 (ER_NO_SUCH_TABLE): tabel rate_limits belum dimigrasikan.
     *   · Debug mode (dev) → fail-open: kembalikan $failOpenValue, log WARNING.
     *   · Production       → fail-closed: kembalikan kebalikan $failOpenValue, log CRITICAL.
     * - Error lain (koneksi putus, dll.) → selalu fail-closed, log ERROR.
     *
     * Mengembalikan nilai yang sesuai dengan konteks (bool untuk attempt/tooMany,
     * tetapi pemanggil harus tetap mengembalikan nilainya sendiri).
     */
    private static function handleDbError(mysqli_sql_exception $e, bool $failOpenValue): bool
    {
        $isTableMissing = $e->getCode() === 1146;

        if ($isTableMissing) {
            if ((bool) config('app.debug')) {
                // Dev: fail-open — jangan ganggu pengembangan lokal, tetapi catat peringatan
                error_log('[WARNING] RateLimiter: Tabel rate_limits tidak ditemukan. '
                    . 'Jalankan migrasi schema sebelum ke production. '
                    . 'Detail: ' . $e->getMessage());
                return $failOpenValue;          // true untuk attempt/tooMany(false)
            }
            // Production: fail-closed — lindungi endpoint meski tabel belum ada
            error_log('[CRITICAL] RateLimiter: Tabel rate_limits tidak ditemukan di production. '
                . 'Rate limiting TIDAK aktif. Migrasi segera! '
                . 'Detail: ' . $e->getMessage());
            return !$failOpenValue;             // false untuk attempt, true untuk tooMany
        }

        // Error database lain (koneksi, timeout, dsb.) — selalu fail-closed
        error_log('[ERROR] RateLimiter: Gagal query database. '
            . 'Detail: ' . $e->getMessage());
        return !$failOpenValue;
    }
}
