<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Role pengguna (enum PHP 8.1). Nilainya sama dengan kolom users.role.
 */
enum Role: string
{
    case User = 'user';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User  => 'Pelanggan',
            self::Staff => 'Staff',
            self::Admin => 'Admin',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::User  => 'text-bg-secondary',
            self::Staff => 'text-bg-info',
            self::Admin => 'text-bg-dark',
        };
    }
}
