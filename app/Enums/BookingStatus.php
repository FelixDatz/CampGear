<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status booking. Alur normal:
 *   pending_payment -> confirmed -> picked_up -> returned -> completed
 * Alur lain:
 *   pending_payment -> expired    (tidak dibayar sampai batas waktu)
 *   pending_payment/confirmed -> cancelled (pembatalan disetujui staf)
 */
enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case PickedUp = 'picked_up';
    case Returned = 'returned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::Confirmed      => 'Terkonfirmasi',
            self::PickedUp       => 'Sedang Disewa',
            self::Returned       => 'Sudah Dikembalikan',
            self::Completed      => 'Selesai',
            self::Cancelled      => 'Dibatalkan',
            self::Expired        => 'Kedaluwarsa',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PendingPayment => 'text-bg-warning',
            self::Confirmed      => 'text-bg-primary',
            self::PickedUp       => 'text-bg-info',
            self::Returned       => 'text-bg-secondary',
            self::Completed      => 'text-bg-success',
            self::Cancelled      => 'text-bg-danger',
            self::Expired        => 'text-bg-dark',
        };
    }

    /** Status yang menahan stok: unit dianggap terpakai pada tanggal sewanya. */
    public static function holdingStock(): array
    {
        return [self::PendingPayment->value, self::Confirmed->value, self::PickedUp->value];
    }
}
