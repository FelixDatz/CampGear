<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Category;
use App\Models\Equipment;
use App\Models\Package;

final class HomeController
{
    public function index(): void
    {
        $categories = Category::allWithCounts();
        $packages = Package::activeWithItems();

        view('home/index', [
            'title'       => 'Sewa Alat Camping',
            'categories'  => $categories,
            'featured'    => Equipment::featured(6),
            'packages'    => $packages,
            'articles'    => \App\Models\Article::published(3),
            'stats'       => [
                'equipment'  => Equipment::countActive(),
                'categories' => count($categories),
                'packages'   => count($packages),
            ],
        ]);
    }

    public function howTo(): void
    {
        view('pages/how-to', [
            'title'  => 'Cara Reservasi',
            'rental' => config('rental'),
        ]);
    }

    public function contact(): void
    {
        view('pages/contact', [
            'title'    => 'Kontak',
            'business' => config('business'),
        ]);
    }
}
