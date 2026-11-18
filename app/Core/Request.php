<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Pembaca informasi request HTTP dari superglobal $_SERVER, $_GET, $_POST.
 */
final class Request
{
    /** Path URL tanpa base_path dan query string, mis. "/alat/tenda-dome". */
    public static function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = rawurldecode($path);

        $base = rtrim((string) config('app.base_path', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        // Akses langsung lewat /public/... tetap dikenali.
        if ($path === '/public' || str_starts_with($path, '/public/')) {
            $path = substr($path, strlen('/public'));
        }

        $path = '/' . trim($path, '/');
        return $path;
    }

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Ambil nilai dari $_GET (string terpangkas), aman terhadap array injection. */
    public static function query(string $key, string $default = ''): string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function isApi(): bool
    {
        return str_starts_with(self::path(), '/api/');
    }

    public static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return self::isApi()
            || str_contains($accept, 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /** Body JSON (untuk request fetch dengan Content-Type: application/json). */
    public static function json(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    /** URL lengkap halaman saat ini (path + query), untuk redirect setelah login. */
    public static function fullPath(): string
    {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        return self::path() . ($query !== '' ? '?' . $query : '');
    }
}
