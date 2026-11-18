<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Controllers\StatsController;
use App\Core\Database;
use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\DamageReport;

/**
 * Dashboard Staff & Admin: penyewaan hari ini, pembatalan menunggu,
 * pendapatan (simulasi), agenda serah terima, grafik, dan tabel transaksi.
 */
final class DashboardController
{
    public function index(): void
    {
        Booking::expireOverdue();
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        view('staff/dashboard', [
            'title' => 'Dashboard',
            'stats' => [
                // Booking terkonfirmasi yang dijadwalkan diambil hari ini
                'pickups_today' => (int) Database::value(
                    "SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND start_date <= ? AND end_date >= ?",
                    [$today, $today],
                ),
                // Alat yang sedang di tangan penyewa
                'active_rentals' => (int) Database::value("SELECT COUNT(*) FROM bookings WHERE status = 'picked_up'"),
                'pending_cancellations' => CancellationRequest::pendingCount(),
                'unsettled_damages'     => DamageReport::unsettledCount(),
                // Pendapatan simulasi bulan ini dari pembayaran berstatus paid
                'revenue_month' => (float) Database::value(
                    "SELECT COALESCE(SUM(amount), 0) FROM payments
                     WHERE status = 'paid' AND paid_at >= ? AND paid_at < ? + INTERVAL 1 MONTH",
                    [$monthStart, $monthStart],
                ),
            ],
            'pickups' => Database::select(
                "SELECT code, customer_name, customer_phone, start_date, end_date FROM bookings
                 WHERE status = 'confirmed' AND start_date <= ? AND end_date >= ? ORDER BY start_date LIMIT 8",
                [$today, $today],
            ),
            'returns' => Database::select(
                "SELECT code, customer_name, customer_phone, end_date FROM bookings
                 WHERE status = 'picked_up' AND end_date <= ? ORDER BY end_date LIMIT 8",
                [$today],
            ),
            'latest'      => Booking::latest(10),
            'pageScripts' => [StatsController::CHART_LIBRARY, 'js/stats.js'],
        ], 'layouts/panel');
    }
}
