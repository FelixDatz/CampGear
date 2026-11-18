<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;

/**
 * Checklist serah terima alat.
 *  - pickup : saat penyewa mengambil alat (confirmed -> picked_up), deposit dapat dicatat.
 *  - return : saat alat dikembalikan (picked_up -> returned). Unit rusak/hilang
 *             otomatis menjadi laporan kerusakan/kehilangan yang wajib diselesaikan.
 */
final class Handover
{
    public const TYPE_LABELS = ['pickup' => 'Pengambilan', 'return' => 'Pengembalian'];

    /**
     * @param array $rows [booking_item_id => ['good' => int, 'damaged' => int, 'note' => ?string]]
     */
    public static function recordPickup(int $bookingId, int $staffId, array $rows, ?string $notes, ?float $depositAmount, ?string $depositNote): array
    {
        return Database::transaction(static function () use ($bookingId, $staffId, $rows, $notes, $depositAmount, $depositNote): array {
            $booking = self::lockBooking($bookingId, BookingStatus::Confirmed, 'Pengambilan hanya untuk booking yang sudah terkonfirmasi (dibayar).');
            $today = date('Y-m-d');
            if ($today < $booking['start_date']) {
                throw new BusinessRuleException('Alat baru dapat diambil mulai ' . tanggal($booking['start_date'], true) . '.');
            }
            if ($today > $booking['end_date']) {
                throw new BusinessRuleException('Periode sewa booking ini sudah lewat. Alat tidak dapat diserahkan.');
            }

            $items = self::validateRows($bookingId, $rows, false);
            $checklistId = self::insertChecklist($bookingId, 'pickup', $staffId, $notes, $items);

            $hasDeposit = $depositAmount !== null || ($depositNote !== null && $depositNote !== '');
            Database::execute(
                "UPDATE bookings SET status = 'picked_up', picked_up_at = NOW()"
                . ($hasDeposit ? ", deposit_amount = ?, deposit_note = ?, deposit_status = 'held'" : '')
                . ' WHERE id = ?',
                $hasDeposit ? [$depositAmount, $depositNote, $bookingId] : [$bookingId],
            );

            return ['booking' => $booking, 'checklist_id' => $checklistId, 'deposit' => $hasDeposit];
        });
    }

    /**
     * @param array $rows [booking_item_id => ['good','damaged','lost','note','description','charge_damaged','charge_lost']]
     */
    public static function recordReturn(int $bookingId, int $staffId, array $rows, ?string $notes, bool $reduceStockForLost): array
    {
        return Database::transaction(static function () use ($bookingId, $staffId, $rows, $notes, $reduceStockForLost): array {
            $booking = self::lockBooking($bookingId, BookingStatus::PickedUp, 'Pengembalian hanya untuk booking yang alatnya sedang disewa.');

            $items = self::validateRows($bookingId, $rows, true);
            $checklistId = self::insertChecklist($bookingId, 'return', $staffId, $notes, $items);

            $reports = 0;
            $stockWarnings = [];
            foreach ($items as $item) {
                foreach (['damaged' => 'charge_damaged', 'lost' => 'charge_lost'] as $type => $chargeKey) {
                    if ($item[$type] === 0) {
                        continue;
                    }
                    Database::insert(
                        'INSERT INTO damage_reports (booking_id, booking_item_id, type, quantity, description, charge_amount, reported_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$bookingId, $item['id'], $type, $item[$type], $item['description'], $item[$chargeKey], $staffId],
                    );
                    $reports++;
                }

                if ($reduceStockForLost && $item['lost'] > 0) {
                    // Unit hilang tidak lagi dimiliki: stok total dikurangi.
                    Database::execute(
                        'UPDATE equipment SET stock_total = GREATEST(CAST(stock_total AS SIGNED) - ?, 0) WHERE id = ?',
                        [$item['lost'], $item['equipment_id']],
                    );
                    $peak = Availability::futurePeak((int) $item['equipment_id'], $bookingId);
                    $stock = (int) Database::value('SELECT stock_total FROM equipment WHERE id = ?', [$item['equipment_id']]);
                    if ($peak > $stock) {
                        $stockWarnings[] = "{$item['equipment_name']}: stok kini {$stock}, sedangkan booking mendatang memakai hingga {$peak} unit";
                    }
                }
            }

            Database::execute("UPDATE bookings SET status = 'returned', returned_at = NOW() WHERE id = ?", [$bookingId]);

            return ['booking' => $booking, 'reports' => $reports, 'stock_warnings' => $stockWarnings];
        });
    }

    /** Checklist sebuah booking beserta rinciannya. */
    public static function forBooking(int $bookingId): array
    {
        $checklists = Database::select(
            'SELECT hc.*, u.name AS staff_name
             FROM handover_checklists hc
             LEFT JOIN users u ON u.id = hc.handled_by
             WHERE hc.booking_id = ?
             ORDER BY hc.created_at',
            [$bookingId],
        );
        foreach ($checklists as &$checklist) {
            $checklist['items'] = Database::select(
                'SELECT hi.*, e.name AS equipment_name, bi.quantity
                 FROM handover_items hi
                 JOIN booking_items bi ON bi.id = hi.booking_item_id
                 JOIN equipment e ON e.id = bi.equipment_id
                 WHERE hi.checklist_id = ?
                 ORDER BY hi.id',
                [(int) $checklist['id']],
            );
        }
        unset($checklist);
        return $checklists;
    }

    // ---- Bantuan internal -------------------------------------------------

    private static function lockBooking(int $bookingId, BookingStatus $expected, string $message): array
    {
        $booking = Database::first('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
        if ($booking === null) {
            throw new BusinessRuleException('Booking tidak ditemukan.');
        }
        if ($booking['status'] !== $expected->value) {
            throw new BusinessRuleException($message);
        }
        return $booking;
    }

    /**
     * Pastikan setiap alat di booking punya baris checklist dan jumlahnya pas:
     * baik + rusak (+ hilang) = jumlah disewa.
     */
    private static function validateRows(int $bookingId, array $rows, bool $isReturn): array
    {
        $bookingItems = Database::select(
            'SELECT bi.id, bi.equipment_id, bi.quantity, e.name AS equipment_name
             FROM booking_items bi JOIN equipment e ON e.id = bi.equipment_id
             WHERE bi.booking_id = ? ORDER BY bi.id',
            [$bookingId],
        );

        $result = [];
        foreach ($bookingItems as $item) {
            $row = $rows[$item['id']] ?? null;
            if (!is_array($row)) {
                throw new BusinessRuleException("Kondisi {$item['equipment_name']} belum diisi.");
            }
            $good = self::count($row['good'] ?? null);
            $damaged = self::count($row['damaged'] ?? null);
            $lost = $isReturn ? self::count($row['lost'] ?? null) : 0;
            if ($good === null || $damaged === null || $lost === null) {
                throw new BusinessRuleException("Jumlah kondisi {$item['equipment_name']} tidak valid.");
            }
            if ($good + $damaged + $lost !== (int) $item['quantity']) {
                throw new BusinessRuleException(
                    "Jumlah kondisi {$item['equipment_name']} harus sama dengan jumlah disewa ({$item['quantity']} unit)."
                );
            }

            $result[] = [
                'id'             => (int) $item['id'],
                'equipment_id'   => (int) $item['equipment_id'],
                'equipment_name' => $item['equipment_name'],
                'good'           => $good,
                'damaged'        => $damaged,
                'lost'           => $lost,
                'note'           => self::text($row['note'] ?? null, 255),
                'description'    => self::text($row['description'] ?? null, 500),
                'charge_damaged' => self::money($row['charge_damaged'] ?? null, $item['equipment_name']),
                'charge_lost'    => self::money($row['charge_lost'] ?? null, $item['equipment_name']),
            ];
        }
        return $result;
    }

    private static function insertChecklist(int $bookingId, string $type, int $staffId, ?string $notes, array $items): int
    {
        $checklistId = Database::insert(
            'INSERT INTO handover_checklists (booking_id, type, handled_by, notes) VALUES (?, ?, ?, ?)',
            [$bookingId, $type, $staffId, $notes],
        );
        foreach ($items as $item) {
            Database::insert(
                'INSERT INTO handover_items (checklist_id, booking_item_id, qty_good, qty_damaged, qty_lost, note)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$checklistId, $item['id'], $item['good'], $item['damaged'], $item['lost'], $item['note']],
            );
        }
        return $checklistId;
    }

    private static function count(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        $number = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]]) : false;
        return $number === false ? null : $number;
    }

    private static function text(mixed $value, int $max): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return mb_substr(trim($value), 0, $max);
    }

    private static function money(mixed $value, string $name): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $amount = parse_money($value);
        if ($amount === null || $amount < 0) {
            throw new BusinessRuleException("Nominal penggantian {$name} tidak valid.");
        }
        return $amount;
    }
}
