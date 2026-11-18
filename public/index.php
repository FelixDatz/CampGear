<?php

declare(strict_types=1);

/**
 * Front controller: satu-satunya pintu masuk aplikasi.
 * Semua request diarahkan ke sini oleh .htaccess (Apache) atau oleh
 * server bawaan PHP:  php -S localhost:8000 -t public public/index.php
 */

// Server bawaan PHP (pengembangan): sajikan file statis (css/js/gambar) secara langsung.
// File .php dan file tersembunyi (.htaccess, dll.) tidak pernah disajikan langsung,
// meniru aturan .htaccess yang tidak dibaca oleh server bawaan PHP.
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    $hidden = str_contains($path, '/.');
    if ($path !== '/' && is_file($file) && !$hidden && !preg_match('/\.ph(p\d?|tml|ar|t)$/i', $file)) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Router;
use App\Core\Session;

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

Session::start();
Auth::restoreFromCookie();

$router = new Router();
require BASE_PATH . '/app/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET');
