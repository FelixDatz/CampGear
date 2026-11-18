<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pengelola session: konfigurasi cookie session yang aman dan flash data
 * (data yang hanya bertahan sampai request berikutnya, mis. pesan sukses).
 */
final class Session
{
    /** Flash data milik request ini (dikirim oleh request sebelumnya). */
    private static array $flash = [];

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('campgear_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => self::cookiePath(),
            'secure'   => Request::isHttps(),
            'httponly' => true,   // tidak bisa dibaca JavaScript
            'samesite' => 'Lax',  // tidak terkirim dari form situs lain
        ]);
        session_start();

        // Pindahkan flash dari request sebelumnya ke memori, lalu hapus dari session.
        self::$flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Simpan data untuk request berikutnya saja. */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return self::$flash[$key] ?? $default;
    }

    /** Ganti ID session (mencegah session fixation), dipanggil saat login/logout. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function cookiePath(): string
    {
        $base = rtrim((string) config('app.base_path', ''), '/');
        return $base === '' ? '/' : $base . '/';
    }
}
