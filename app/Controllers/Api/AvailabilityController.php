<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Package;

/**
 * GET /api/availability?start=2026-12-01&end=2026-12-03
 *
 * Respons 200:
 *   { "start": "...", "end": "...", "days": 3,
 *     "equipment": { "1": 6, "2": 5, ... },   // id alat => unit tersedia
 *     "packages":  { "1": 4, ... } }          // id paket => jumlah set tersedia
 * Respons 422: { "error": "pesan", "status": 422 }
 */
final class AvailabilityController
{
    public function index(): void
    {
        $start = Request::query('start');
        $end = Request::query('end');

        $error = Booking::rangeError($start, $end);
        if ($error !== null) {
            json_response(['error' => $error, 'status' => 422], 422);
        }

        Booking::expireOverdue();
        $availability = Availability::forRange($start, $end);

        $equipment = [];
        foreach ($availability as $id => $row) {
            $equipment[$id] = $row['available'];
        }

        // Satu set paket butuh qty tertentu per alat; jumlah set = nilai terkecil dari (tersedia / qty).
        $packages = [];
        foreach (Package::activeWithItems() as $package) {
            $sets = $package['is_available'] ? PHP_INT_MAX : 0;
            foreach ($package['items'] as $item) {
                $available = $equipment[(int) $item['equipment_id']] ?? 0;
                $sets = min($sets, intdiv($available, (int) $item['quantity']));
            }
            $packages[(int) $package['id']] = $sets === PHP_INT_MAX ? 0 : $sets;
        }

        header('Cache-Control: no-store');
        json_response([
            'start'     => $start,
            'end'       => $end,
            'days'      => Availability::countDays($start, $end),
            'equipment' => (object) $equipment,
            'packages'  => (object) $packages,
        ]);
    }
}
