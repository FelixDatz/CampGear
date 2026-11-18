<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis yang pesannya aman ditampilkan ke pengguna,
 * mis. "Booking ini sudah tidak bisa dibayar." Ditangkap di controller.
 */
class BusinessRuleException extends RuntimeException
{
}
