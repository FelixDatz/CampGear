<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Enums\BookingStatus;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;

/**
 * Perhitungan ketersediaan stok per rentang tanggal.
 *
 * Stok dihitung PER HARI, bukan dijumlah untuk seluruh rentang.
 * Contoh stok 5 unit, booking A tgl 1–2 (3 unit), booking B tgl 4–5 (3 unit),
 * permintaan baru tgl 1–5:
 *   tgl:      1  2  3  4  5
 *   terpakai: 3  3  0  3  3   -> puncak 3 -> tersedia 5 - 3 = 2 unit
 * Bila A dan B dijumlah langsung (6), hasilnya keliru: seolah stok habis.
 */
final class Availability
{
    /**
     * Sisa stok tiap alat yang bisa disewa untuk SELURUH hari dalam rentang.
     *
     * @param int[] $equipmentIds kosong = semua alat aktif
     * @return array<int, array{stock_total:int, peak_booked:int, available:int}>  key = equipment_id
     */
    public static function forRange(string $start, string $end, array $equipmentIds = []): array
    {
        $equipmentIds = array_values(array_unique(array_map('intval', $equipmentIds)));
        $filter = '';
        if ($equipmentIds !== []) {
            $filter = ' AND %s IN (' . implode(',', array_fill(0, count($equipmentIds), '?')) . ')';
        }

        // 1. Stok total tiap alat.
        $stocks = Database::select(
            'SELECT id, stock_total FROM equipment WHERE is_active = 1' . sprintf($filter, 'id'),
            $equipmentIds,
        );

        // 2. Rincian booking aktif yang tanggalnya beririsan dengan rentang yang diminta.
        //    Booking pending_payment yang sudah lewat deadline DAN tidak ada bukti pending
        //    tidak dihitung (sama seperti expired).
        //    Booking dengan bukti pending yang dikirim sebelum deadline tetap menahan stok.
        $statuses = BookingStatus::holdingStock();
        $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
        $rows = Database::select(
            "SELECT bi.equipment_id, bi.quantity, b.start_date, b.end_date
             FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             WHERE b.status IN ({$statusPlaceholders})
               AND NOT (
                   b.status = 'pending_payment'
                   AND b.payment_due_at <= NOW()
                   AND NOT EXISTS (
                       SELECT 1 FROM payments
                       WHERE payments.booking_id = b.id
                         AND payments.purpose = 'rental'
                         AND payments.status = 'pending'
                   )
               )
               AND b.start_date <= ? AND b.end_date >= ?" . sprintf($filter, 'bi.equipment_id'),
            [...$statuses, $end, $start, ...$equipmentIds],
        );

        // 3. Jumlahkan unit terpakai per alat per hari.
        $usage = [];
        foreach ($rows as $row) {
            $from = max($row['start_date'], $start);
            $to = min($row['end_date'], $end);
            foreach (self::dates($from, $to) as $day) {
                $usage[$row['equipment_id']][$day] = ($usage[$row['equipment_id']][$day] ?? 0) + (int) $row['quantity'];
            }
        }

        // 4. Tersedia = stok total - pemakaian tertinggi pada salah satu hari.
        $result = [];
        foreach ($stocks as $stock) {
            $id = (int) $stock['id'];
            $peak = isset($usage[$id]) ? max($usage[$id]) : 0;
            $result[$id] = [
                'stock_total' => (int) $stock['stock_total'],
                'peak_booked' => $peak,
                'available'   => max(0, (int) $stock['stock_total'] - $peak),
            ];
        }
        return $result;
    }

    /**
     * Pemakaian tertinggi per hari untuk satu alat mulai hari ini ke depan.
     * Dipakai agar stok total tidak diturunkan di bawah unit yang sudah dipesan.
     */
    public static function futurePeak(int $equipmentId, ?int $excludeBookingId = null): int
    {
        $statuses = BookingStatus::holdingStock();
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $today = date('Y-m-d');

        $rows = Database::select(
            "SELECT bi.quantity, b.start_date, b.end_date
             FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             WHERE bi.equipment_id = ?
               AND b.status IN ({$placeholders})
               AND NOT (
                   b.status = 'pending_payment'
                   AND b.payment_due_at <= NOW()
                   AND NOT EXISTS (
                       SELECT 1 FROM payments
                       WHERE payments.booking_id = b.id
                         AND payments.purpose = 'rental'
                         AND payments.status = 'pending'
                   )
               )
               AND b.end_date >= ?
               AND b.id <> ?",
            [$equipmentId, ...$statuses, $today, $excludeBookingId ?? 0],
        );

        $usage = [];
        foreach ($rows as $row) {
            foreach (self::dates(max($row['start_date'], $today), $row['end_date']) as $day) {
                $usage[$day] = ($usage[$day] ?? 0) + (int) $row['quantity'];
            }
        }
        return $usage === [] ? 0 : max($usage);
    }

    /** Daftar tanggal Y-m-d dari $start sampai $end (inklusif). */
    public static function dates(string $start, string $end): array
    {
        $period = new DatePeriod(
            new DateTimeImmutable($start),
            new DateInterval('P1D'),
            (new DateTimeImmutable($end))->modify('+1 day'),
        );
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }
        return $dates;
    }

    /** Jumlah hari sewa (inklusif): 1–3 = 3 hari. */
    public static function countDays(string $start, string $end): int
    {
        return (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
    }
}
