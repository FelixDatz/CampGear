<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Penanganan error terpusat.
 *  - Warning/notice PHP diubah menjadi exception agar tidak terlewat.
 *  - Error 5xx dicatat ke storage/logs/app.log.
 *  - Mode debug menampilkan detail; mode produksi hanya pesan umum.
 */
final class ErrorHandler
{
    private const TITLES = [
        400 => 'Permintaan tidak valid',
        401 => 'Perlu login',
        403 => 'Akses ditolak',
        404 => 'Halaman tidak ditemukan',
        405 => 'Metode tidak diizinkan',
        419 => 'Sesi kedaluwarsa',
        422 => 'Data tidak valid',
        429 => 'Terlalu banyak percobaan',
        500 => 'Terjadi kesalahan server',
    ];

    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;
        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI === 'cli' ? '1' : '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        if ($status >= 500) {
            self::log($e);
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
            exit(1);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code($status);
        }

        $title = self::TITLES[$status] ?? 'Terjadi kesalahan';
        $message = ($e instanceof HttpException && $e->getMessage() !== '') ? $e->getMessage() : null;

        if (Request::wantsJson()) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'error'   => $message ?? $title,
                'status'  => $status,
                'debug'   => self::$debug && $status >= 500 ? $e->getMessage() : null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            echo View::render('errors/error', [
                'title'     => $title,
                'status'    => $status,
                'message'   => $message,
                'exception' => self::$debug && $status >= 500 ? $e : null,
            ]);
        } catch (Throwable $renderError) {
            // Halaman error gagal dirender (mis. database mati): tampilkan teks polos.
            self::log($renderError);
            echo '<h1>' . $status . ' — ' . htmlspecialchars($title) . '</h1>';
            if (self::$debug) {
                echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
            }
        }
    }

    private static function log(Throwable $e): void
    {
        $line = sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString(),
        );
        @file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
    }
}
