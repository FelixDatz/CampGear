<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Exception yang membawa HTTP status code (403, 404, 419, ...).
 * Ditangkap oleh ErrorHandler lalu ditampilkan sebagai halaman/JSON error.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
