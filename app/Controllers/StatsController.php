<?php

declare(strict_types=1);

namespace App\Controllers;

/** Halaman statistik publik. Datanya dimuat lewat AJAX dari /api/stats. */
final class StatsController
{
    public const CHART_LIBRARY = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';

    public function index(): void
    {
        view('stats/index', [
            'title'           => 'Statistik Penyewaan',
            'metaDescription' => 'Grafik penyewaan per periode, pembatalan, dan tingkat pemakaian stok alat camping.',
            'pageScripts'     => [self::CHART_LIBRARY, 'js/stats.js'],
        ]);
    }
}
