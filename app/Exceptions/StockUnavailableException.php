<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Stok tidak cukup untuk satu atau beberapa alat pada rentang tanggal yang diminta.
 * $shortages berisi daftar ['name' => ..., 'requested' => ..., 'available' => ...].
 */
final class StockUnavailableException extends BusinessRuleException
{
    public function __construct(public readonly array $shortages)
    {
        $parts = array_map(
            static fn (array $s) => "{$s['name']} (diminta {$s['requested']}, tersedia {$s['available']})",
            $shortages,
        );
        parent::__construct('Stok tidak mencukupi untuk tanggal tersebut: ' . implode('; ', $parts) . '.');
    }
}
