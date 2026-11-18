<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Models\Booking;
use App\Models\Stats;

/**
 * GET /api/stats?period=month&range=6
 *
 * Data agregat untuk grafik (tanpa data pribadi). Pendapatan hanya disertakan
 * bila yang meminta adalah Staff/Admin.
 */
final class StatsController
{
    public function index(): void
    {
        $period = array_key_exists(Request::query('period'), Stats::PERIODS) ? Request::query('period') : 'month';
        $config = Stats::PERIODS[$period];
        $range = (int) Request::query('range', (string) $config['default']);
        if (!in_array($range, $config['ranges'], true)) {
            json_response(['error' => 'Rentang tidak didukung. Pilihan: ' . implode(', ', $config['ranges']) . '.', 'status' => 422], 422);
        }

        Booking::expireOverdue();
        $buckets = Stats::buckets($period, $range);
        $rentals = Stats::rentals($buckets);
        $cancellations = Stats::cancellations($buckets);
        $utilization = Stats::utilization($buckets);

        $payload = [
            'period'        => $period,
            'range'         => $range,
            'from'          => $buckets[0]['start'],
            'to'            => end($buckets)['end'],
            'labels'        => array_column($buckets, 'label'),
            'rentals'       => $rentals,
            'cancellations' => $cancellations,
            'utilization'   => $utilization['per_bucket'],
            'categories'    => $utilization['per_category'],
            'totals'        => [
                'rentals'       => array_sum($rentals),
                'cancellations' => array_sum(array_map('array_sum', $cancellations)),
                'approved'      => array_sum($cancellations['approved']),
                'utilization'   => $utilization['overall'],
            ],
        ];
        if (Auth::isStaff()) {
            $payload['revenue'] = Stats::revenue($buckets);
            $payload['totals']['revenue'] = array_sum($payload['revenue']);
        }

        header('Cache-Control: no-store');
        json_response($payload);
    }
}
