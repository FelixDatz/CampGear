<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Proteksi CSRF: setiap form POST wajib menyertakan token rahasia milik session.
 * Situs lain tidak bisa mengetahui token ini, sehingga tidak bisa memalsukan request.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function verify(): void
    {
        // Form mengirim lewat field _token; fetch() mengirim lewat header X-CSRF-Token.
        $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || $sent === '' || !hash_equals(self::token(), $sent)) {
            throw new HttpException(419, 'Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        }
    }
}
