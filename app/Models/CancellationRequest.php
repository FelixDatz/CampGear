<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\BusinessRuleException;

/**
 * Pengajuan pembatalan booking.
 *
 * Alur: pending -> approved (booking menjadi cancelled, stok dilepas)
 *              -> rejected (booking tetap berjalan)
 * Refund tidak dihitung otomatis; staf mencatat nominal (opsional) dan catatan.
 */
final class CancellationRequest
{
    public const STATUS_LABELS = ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
    public const STATUS_BADGES = ['pending' => 'text-bg-warning', 'approved' => 'text-bg-success', 'rejected' => 'text-bg-danger'];
    public const REFUND_LABELS = ['not_applicable' => 'Tidak ada pembayaran', 'pending' => 'Perlu diproses', 'processed' => 'Sudah diproses'];

    /** Status booking yang masih boleh dibatalkan. */
    private const CANCELLABLE = ['pending_payment', 'confirmed'];

    /** Alasan booking tidak bisa diajukan pembatalan, atau null bila boleh. */
    public static function blocker(array $booking): ?string
    {
        if (!in_array($booking['status'], self::CANCELLABLE, true)) {
            return 'Booking dengan status ini tidak dapat dibatalkan.';
        }
        if ($booking['start_date'] <= date('Y-m-d')) {
            return 'Pembatalan hanya dapat diajukan sebelum tanggal mulai sewa.';
        }
        if (self::pendingFor((int) $booking['id']) !== null) {
            return 'Masih ada pengajuan pembatalan yang menunggu peninjauan.';
        }
        return null;
    }

    public static function submit(array $booking, string $reason, ?int $requestedBy): int
    {
        return Database::transaction(static function () use ($booking, $reason, $requestedBy): int {
            // Kunci booking agar dua pengajuan bersamaan tidak sama-sama lolos.
            $fresh = Database::first('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [(int) $booking['id']]);
            $blocker = $fresh === null ? 'Booking tidak ditemukan.' : self::blocker($fresh);
            if ($blocker !== null) {
                throw new BusinessRuleException($blocker);
            }
            return Database::insert(
                'INSERT INTO cancellation_requests (booking_id, reason, requested_by) VALUES (?, ?, ?)',
                [(int) $booking['id'], $reason, $requestedBy],
            );
        });
    }

    public static function pendingFor(int $bookingId): ?array
    {
        return Database::first(
            "SELECT * FROM cancellation_requests WHERE booking_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
            [$bookingId],
        );
    }

    /** Riwayat pengajuan sebuah booking beserta nama peninjau. */
    public static function forBooking(int $bookingId): array
    {
        return Database::select(
            'SELECT cr.*, u.name AS reviewer_name
             FROM cancellation_requests cr
             LEFT JOIN users u ON u.id = cr.reviewed_by
             WHERE cr.booking_id = ?
             ORDER BY cr.created_at DESC, cr.id DESC',
            [$bookingId],
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT cr.*, b.code, b.status AS booking_status, b.customer_name, b.customer_email, b.customer_phone,
                    b.start_date, b.end_date, b.grand_total, b.deposit_amount, b.deposit_status,
                    u.name AS reviewer_name
             FROM cancellation_requests cr
             JOIN bookings b ON b.id = cr.booking_id
             LEFT JOIN users u ON u.id = cr.reviewed_by
             WHERE cr.id = ?',
            [$id],
        );
    }

    /** Daftar untuk panel staff dengan filter status. */
    public static function paginate(string $status, int $limit, int $offset): array
    {
        [$where, $params] = self::filter($status);
        return Database::select(
            "SELECT cr.*, b.code, b.status AS booking_status, b.customer_name, b.start_date, b.end_date, b.grand_total
             FROM cancellation_requests cr
             JOIN bookings b ON b.id = cr.booking_id
             {$where}
             ORDER BY cr.status = 'pending' DESC, cr.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
    }

    public static function count(string $status): int
    {
        [$where, $params] = self::filter($status);
        return (int) Database::value("SELECT COUNT(*) FROM cancellation_requests cr {$where}", $params);
    }

    public static function pendingCount(): int
    {
        return (int) Database::value("SELECT COUNT(*) FROM cancellation_requests WHERE status = 'pending'");
    }

    private static function filter(string $status): array
    {
        return array_key_exists($status, self::STATUS_LABELS)
            ? ['WHERE cr.status = ?', [$status]]
            : ['', []];
    }

    /**
     * Setujui pembatalan: booking menjadi cancelled (stok otomatis dilepas karena
     * status cancelled tidak lagi dihitung sebagai pemakai stok), catatan refund disimpan.
     */
    public static function approve(int $id, int $staffId, ?float $refundAmount, ?string $refundNote, ?string $reviewNote): array
    {
        return Database::transaction(static function () use ($id, $staffId, $refundAmount, $refundNote, $reviewNote): array {
            $request = Database::first('SELECT * FROM cancellation_requests WHERE id = ? FOR UPDATE', [$id]);
            if ($request === null || $request['status'] !== 'pending') {
                throw new BusinessRuleException('Pengajuan ini sudah ditinjau.');
            }
            $booking = Database::first('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [(int) $request['booking_id']]);
            if (!in_array($booking['status'], self::CANCELLABLE, true)) {
                $label = \App\Enums\BookingStatus::from($booking['status'])->label();
                throw new BusinessRuleException("Booking sudah berstatus \"{$label}\" sehingga tidak dapat dibatalkan. Tolak pengajuan ini dengan catatan.");
            }

            $paid = (float) Database::value(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = ? AND purpose = 'rental' AND status = 'paid'",
                [(int) $booking['id']],
            );
            if ($refundAmount !== null && $refundAmount > $paid) {
                throw new BusinessRuleException('Nominal refund tidak boleh melebihi total yang sudah dibayar (' . rupiah($paid) . ').');
            }

            Database::execute(
                "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?",
                [(int) $booking['id']],
            );
            Database::execute(
                "UPDATE cancellation_requests
                 SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), review_note = ?,
                     refund_amount = ?, refund_note = ?, refund_status = ?
                 WHERE id = ?",
                [$staffId, $reviewNote, $refundAmount, $refundNote, $paid > 0 ? 'pending' : 'not_applicable', $id],
            );

            return ['booking' => $booking, 'paid' => $paid];
        });
    }

    public static function reject(int $id, int $staffId, string $reviewNote): array
    {
        return Database::transaction(static function () use ($id, $staffId, $reviewNote): array {
            $request = Database::first('SELECT * FROM cancellation_requests WHERE id = ? FOR UPDATE', [$id]);
            if ($request === null || $request['status'] !== 'pending') {
                throw new BusinessRuleException('Pengajuan ini sudah ditinjau.');
            }
            Database::execute(
                "UPDATE cancellation_requests
                 SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), review_note = ?
                 WHERE id = ?",
                [$staffId, $reviewNote, $id],
            );
            return $request;
        });
    }

    /** Tandai refund sudah diproses secara administratif. */
    public static function markRefundProcessed(int $id, ?string $refundNote): void
    {
        $affected = Database::execute(
            "UPDATE cancellation_requests
             SET refund_status = 'processed', refund_note = COALESCE(?, refund_note)
             WHERE id = ? AND status = 'approved' AND refund_status = 'pending'",
            [$refundNote, $id],
        );
        if ($affected === 0) {
            throw new BusinessRuleException('Refund pengajuan ini tidak dalam status "perlu diproses".');
        }
    }
}
