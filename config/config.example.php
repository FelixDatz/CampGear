<?php

/**
 * Salin file ini menjadi config/config.php lalu sesuaikan nilainya.
 * config/config.php tidak ikut di-commit karena berisi kredensial.
 */
return [
    'app' => [
        'name'     => 'CampGear',
        // Kunci rahasia aplikasi untuk hashing HMAC rate limiting dan token.
        // WAJIB: Ganti dengan kunci acak unik untuk setiap instalasi. JANGAN biarkan kosong di production.
        // Generate: php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
        'key'      => '',
        // true hanya untuk pengembangan lokal: menampilkan detail error.
        'debug'    => false,
        // Kosongkan bila aplikasi berada di root domain.
        // Isi '/subfolder' bila aplikasi diakses lewat http://host/subfolder.
        'base_path' => '',
        'timezone' => 'Asia/Jakarta',
        // Tampilkan daftar akun demo di halaman login (untuk penilaian).
        'show_demo_accounts' => true,
    ],

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'campgear',
        'user' => 'root',
        'pass' => '',
    ],

    // Informasi usaha yang tampil di halaman publik (data fiktif).
    'business' => [
        'name'     => 'CampGear Rental',
        'tagline'  => 'Sewa alat camping lengkap, siap berangkat.',
        'address'  => 'Jl. Pendaki No. 12, Surakarta, Jawa Tengah',
        'phone'    => '0812-0000-2026',
        'whatsapp' => '6281200002026',
        'email'    => 'halo@campgear.test',
        'hours'    => 'Setiap hari, 08.00–20.00 WIB',
    ],

    // Aturan bisnis persewaan.
    // Refund, deposit, dan biaya penggantian TIDAK dihitung otomatis;
    // nominalnya dicatat staf secara administratif.
    'rental' => [
        'payment_window_hours'        => 3,  // batas waktu kirim bukti bayar sebelum booking kedaluwarsa
        'max_rental_days'             => 14, // durasi sewa maksimum per booking
        'max_advance_days'            => 90, // booking paling jauh H+90
        'booking_rate_limit_per_hour' => 60, // batas awal percobaan booking per IP per jam
    ],
];
