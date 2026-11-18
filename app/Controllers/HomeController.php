<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Equipment;
use App\Models\Package;

final class HomeController
{
    /** Pustaka smooth scroll untuk beranda (inersia scroll yang halus). */
    private const LENIS = 'https://unpkg.com/lenis@1.1.13/dist/lenis.min.js';

    public function index(): void
    {
        // Alat aktif: isi kartu "Pilih medan" (per kategori) dan katalog di beranda.
        $equipment = Equipment::active();
        $itemsByCategory = [];
        foreach ($equipment as $item) {
            $itemsByCategory[(int) $item['category_id']][] = $item;
        }

        // Paket yang bisa disewa. "sleepers" = jumlah sleeping bag di paket,
        // dipakai sebagai perkiraan jumlah orang untuk saran paket.
        $packages = Package::available();
        foreach ($packages as &$package) {
            $package['sleepers'] = 0;
            foreach ($package['items'] as $item) {
                if (gear_kind($item['name'] . ' ' . $item['slug']) === 'sleeping-bag') {
                    $package['sleepers'] += (int) $item['quantity'];
                }
            }
        }
        unset($package);

        // "Ada di paket": paket yang memuat tiap alat, lalu dirangkum per kategori.
        $packagesByItem = Package::mapByEquipment($packages);
        $packagesByCategory = [];
        foreach ($equipment as $item) {
            foreach ($packagesByItem[(int) $item['id']] ?? [] as $ref) {
                $packagesByCategory[(int) $item['category_id']][$ref['id']] = $ref;
            }
        }

        // Isi tiket contoh bila ransel pengunjung masih kosong: paket untuk dua orang.
        $sample = $packages[0] ?? null;
        foreach ($packages as $package) {
            if ($package['sleepers'] === 2) {
                $sample = $package;
                break;
            }
        }

        view('home/index', [
            'title'              => 'Sewa Alat Camping',
            'categories'         => Category::allWithCounts(),
            'equipment'          => $equipment,
            'itemsByCategory'    => $itemsByCategory,
            'packages'           => $packages,
            'packagesByItem'     => $packagesByItem,
            'packagesByCategory' => array_map(static function (array $refs): array {
                ksort($refs); // urut sesuai id paket, sama dengan urutan tab di section 3
                return array_values($refs);
            }, $packagesByCategory),
            'sample'             => $sample,
            'articles'           => Article::published(4),
            'rental'             => config('rental'),
            'business'           => config('business'),
            'pageScripts'        => [self::LENIS, 'js/katalog.js', 'js/home.js'],
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
