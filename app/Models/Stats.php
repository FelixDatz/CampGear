<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Statistik untuk grafik:
 *  - penyewaan per periode       : booking yang benar-benar berjalan (dibayar), dikelompokkan menurut tanggal mulai
 *  - pembatalan per periode      : pengajuan pembatalan per status, menurut tanggal pengajuan
 *  - tingkat pemakaian stok      : unit-hari tersewa / (stok total x jumlah hari) dalam periode
 *  - pendapatan (khusus staff)   : pembayaran simulasi berstatus paid, menurut tanggal bayar
 *
 * Kapasitas memakai stok total alat aktif saat ini (pendekatan; riwayat stok tidak disimpan).
 */
final class Stats
{
    /** Status booking yang dihitung sebagai penyewaan nyata. */
    private const RENTED = ['confirmed', 'picked_up', 'returned', 'completed'];

    public const PERIODS = [
        'month' => ['label' => 'Bulanan', 'ranges' => [6, 12], 'default' => 6],
        'week'  => ['label' => 'Mingguan', 'ranges' => [8, 12], 'default' => 8],
    ];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /**
     * Rentang waktu (bucket) berakhir di periode berjalan.
     * @return array<int, array{label: string, start: string, end: string}>
     */
    public static function buckets(string $period, int $count): array
    {
        $today = new DateTimeImmutable('today');
        $buckets = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            if ($period === 'week') {
                $start = $today->modify('monday this week')->modify("-{$i} week");
                $end = $start->modify('+6 days');
                $label = $start->format('j') . ' ' . self::MONTHS[(int) $start->format('n') - 1];
            } else {
                $start = $today->modify('first day of this month')->modify("-{$i} month");
                $end = $start->modify('last day of this month');
                $label = self::MONTHS[(int) $start->format('n') - 1] . ' ' . $start->format('y');
            }
            $buckets[] = ['label' => $label, 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
        }
        return $buckets;
    }

    /** Indeks bucket untuk sebuah tanggal, atau null bila di luar rentang. */
    private static function bucketIndex(array $buckets, string $date): ?int
    {
        $day = substr($date, 0, 10);
        foreach ($buckets as $i => $bucket) {
            if ($day >= $bucket['start'] && $day <= $bucket['end']) {
                return $i;
            }
        }
        return null;
    }

    private static function inList(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /** Jumlah penyewaan per bucket. */
    public static function rentals(array $buckets): array
    {
        $counts = array_fill(0, count($buckets), 0);
        $rows = Database::select(
            'SELECT start_date FROM bookings WHERE status IN (' . self::inList(self::RENTED) . ') AND start_date BETWEEN ? AND ?',
            [...self::RENTED, $buckets[0]['start'], end($buckets)['end']],
        );
        foreach ($rows as $row) {
            $i = self::bucketIndex($buckets, $row['start_date']);
            if ($i !== null) {
                $counts[$i]++;
            }
        }
        return $counts;
    }

    /** Pengajuan pembatalan per status per bucket. */
    public static function cancellations(array $buckets): array
    {
        $series = [];
        foreach (array_keys(CancellationRequest::STATUS_LABELS) as $status) {
            $series[$status] = array_fill(0, count($buckets), 0);
        }
        $rows = Database::select(
            'SELECT status, created_at FROM cancellation_requests WHERE created_at >= ? AND created_at < ? + INTERVAL 1 DAY',
            [$buckets[0]['start'] . ' 00:00:00', end($buckets)['end']],
        );
        foreach ($rows as $row) {
            $i = self::bucketIndex($buckets, $row['created_at']);
            if ($i !== null) {
                $series[$row['status']][$i]++;
            }
        }
        return $series;
    }

    /**
     * Tingkat pemakaian stok (persen) per bucket dan per kategori untuk seluruh rentang.
     * @return array{per_bucket: float[], per_category: array}
     */
    public static function utilization(array $buckets): array
    {
        $rangeStart = $buckets[0]['start'];
        $rangeEnd = end($buckets)['end'];

        $categories = Database::select(
            'SELECT c.id, c.name, COALESCE(SUM(e.stock_total), 0) AS stock
             FROM categories c
             LEFT JOIN equipment e ON e.category_id = c.id AND e.is_active = 1
             GROUP BY c.id ORDER BY c.name',
        );
        $totalStock = array_sum(array_map(static fn ($c) => (int) $c['stock'], $categories));

        $rows = Database::select(
            'SELECT bi.quantity, b.start_date, b.end_date, e.category_id
             FROM booking_items bi
             JOIN bookings b ON b.id = bi.booking_id
             JOIN equipment e ON e.id = bi.equipment_id
             WHERE b.status IN (' . self::inList(self::RENTED) . ') AND b.start_date <= ? AND b.end_date >= ?',
            [...self::RENTED, $rangeEnd, $rangeStart],
        );

        $bucketUnitDays = array_fill(0, count($buckets), 0);
        $categoryUnitDays = [];
        foreach ($rows as $row) {
            foreach ($buckets as $i => $bucket) {
                $days = self::overlapDays($row['start_date'], $row['end_date'], $bucket['start'], $bucket['end']);
                if ($days > 0) {
                    $bucketUnitDays[$i] += $days * (int) $row['quantity'];
                    $categoryUnitDays[$row['category_id']] = ($categoryUnitDays[$row['category_id']] ?? 0) + $days * (int) $row['quantity'];
                }
            }
        }

        $perBucket = [];
        foreach ($buckets as $i => $bucket) {
            $capacity = $totalStock * Availability::countDays($bucket['start'], $bucket['end']);
            $perBucket[] = $capacity > 0 ? round($bucketUnitDays[$i] / $capacity * 100, 1) : 0.0;
        }

        $rangeDays = Availability::countDays($rangeStart, $rangeEnd);
        $perCategory = [];
        foreach ($categories as $category) {
            $capacity = (int) $category['stock'] * $rangeDays;
            $used = $categoryUnitDays[$category['id']] ?? 0;
            $perCategory[] = [
                'name'      => $category['name'],
                'unit_days' => $used,
                'capacity'  => $capacity,
                'percent'   => $capacity > 0 ? round($used / $capacity * 100, 1) : 0.0,
            ];
        }

        $overall = $totalStock * $rangeDays;
        return [
            'per_bucket'   => $perBucket,
            'per_category' => $perCategory,
            'overall'      => $overall > 0 ? round(array_sum($bucketUnitDays) / $overall * 100, 1) : 0.0,
        ];
    }

    /** Pendapatan simulasi (pembayaran paid) per bucket — hanya untuk staff. */
    public static function revenue(array $buckets): array
    {
        $totals = array_fill(0, count($buckets), 0.0);
        $rows = Database::select(
            "SELECT amount, paid_at FROM payments WHERE status = 'paid' AND paid_at >= ? AND paid_at < ? + INTERVAL 1 DAY",
            [$buckets[0]['start'] . ' 00:00:00', end($buckets)['end']],
        );
        foreach ($rows as $row) {
            $i = self::bucketIndex($buckets, $row['paid_at']);
            if ($i !== null) {
                $totals[$i] += (float) $row['amount'];
            }
        }
        return $totals;
    }

    private static function overlapDays(string $aStart, string $aEnd, string $bStart, string $bEnd): int
    {
        $start = max($aStart, $bStart);
        $end = min($aEnd, $bEnd);
        return $start > $end ? 0 : Availability::countDays($start, $end);
    }
}
