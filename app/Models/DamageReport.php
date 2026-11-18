<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\BusinessRuleException;

/**
 * Laporan kerusakan/kehilangan alat. Penyewa wajib melakukan penggantian;
 * bentuk dan nominalnya dicatat staf per kasus. Booking tidak bisa diselesaikan
 * selama masih ada laporan berstatus 'unsettled'.
 */
final class DamageReport
{
    public const TYPE_LABELS = ['damaged' => 'Rusak', 'lost' => 'Hilang'];
    public const STATUS_LABELS = ['unsettled' => 'Belum diselesaikan', 'settled' => 'Selesai'];

    private const SELECT =
        'SELECT dr.*, e.name AS equipment_name, b.code, b.customer_name, b.customer_phone,
                ur.name AS reporter_name, us.name AS settler_name
         FROM damage_reports dr
         JOIN booking_items bi ON bi.id = dr.booking_item_id
         JOIN equipment e ON e.id = bi.equipment_id
         JOIN bookings b ON b.id = dr.booking_id
         LEFT JOIN users ur ON ur.id = dr.reported_by
         LEFT JOIN users us ON us.id = dr.settled_by';

    public static function find(int $id): ?array
    {
        return Database::first(self::SELECT . ' WHERE dr.id = ?', [$id]);
    }

    public static function forBooking(int $bookingId): array
    {
        return Database::select(self::SELECT . ' WHERE dr.booking_id = ? ORDER BY dr.id', [$bookingId]);
    }

    public static function paginate(string $status, int $limit, int $offset): array
    {
        [$where, $params] = self::filter($status);
        return Database::select(
            self::SELECT . " {$where} ORDER BY dr.status = 'unsettled' DESC, dr.created_at DESC LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
    }

    public static function count(string $status): int
    {
        [$where, $params] = self::filter($status);
        return (int) Database::value("SELECT COUNT(*) FROM damage_reports dr {$where}", $params);
    }

    public static function unsettledCount(?int $bookingId = null): int
    {
        return $bookingId === null
            ? (int) Database::value("SELECT COUNT(*) FROM damage_reports WHERE status = 'unsettled'")
            : (int) Database::value("SELECT COUNT(*) FROM damage_reports WHERE status = 'unsettled' AND booking_id = ?", [$bookingId]);
    }

    private static function filter(string $status): array
    {
        return array_key_exists($status, self::STATUS_LABELS) ? ['WHERE dr.status = ?', [$status]] : ['', []];
    }

    /** Perbarui keterangan dan nominal penggantian (selama belum diselesaikan). */
    public static function updateDetails(int $id, ?string $description, ?float $charge): void
    {
        $affected = Database::execute(
            "UPDATE damage_reports SET description = ?, charge_amount = ? WHERE id = ? AND status = 'unsettled'",
            [$description, $charge, $id],
        );
        if ($affected === 0 && Database::value("SELECT status FROM damage_reports WHERE id = ?", [$id]) !== 'unsettled') {
            throw new BusinessRuleException('Laporan yang sudah diselesaikan tidak dapat diubah.');
        }
    }

    /**
     * Nyatakan penggantian tuntas. Bila $paymentMethod diisi dan ada nominal,
     * pembayaran penggantian (simulasi) dicatat di tabel payments.
     */
    public static function settle(int $id, int $staffId, string $note, ?string $paymentMethod): array
    {
        return Database::transaction(static function () use ($id, $staffId, $note, $paymentMethod): array {
            $report = Database::first('SELECT * FROM damage_reports WHERE id = ? FOR UPDATE', [$id]);
            if ($report === null || $report['status'] !== 'unsettled') {
                throw new BusinessRuleException('Laporan ini sudah diselesaikan.');
            }
            if ($paymentMethod !== null) {
                if ($report['charge_amount'] === null || (float) $report['charge_amount'] <= 0) {
                    throw new BusinessRuleException('Isi nominal penggantian terlebih dahulu sebelum mencatat pembayaran.');
                }
                Database::insert(
                    "INSERT INTO payments (booking_id, purpose, method, amount, status, reference, handled_by, paid_at)
                     VALUES (?, 'damage', ?, ?, 'paid', ?, ?, NOW())",
                    [(int) $report['booking_id'], $paymentMethod, (float) $report['charge_amount'], 'DMG-' . strtoupper(bin2hex(random_bytes(5))), $staffId],
                );
            }
            Database::execute(
                "UPDATE damage_reports SET status = 'settled', settlement_note = ?, settled_by = ?, settled_at = NOW() WHERE id = ?",
                [$note, $staffId, $id],
            );
            return $report;
        });
    }
}
