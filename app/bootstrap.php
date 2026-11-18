<?php

declare(strict_types=1);

/**
 * Bootstrap aplikasi: autoloader, konfigurasi, zona waktu, dan penanganan error.
 * Dipakai oleh public/index.php (web) dan skrip CLI di folder database/.
 */

define('BASE_PATH', dirname(__DIR__));

// Autoloader sederhana (konsep require + konvensi nama):
// kelas App\Core\Router  ->  app/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = BASE_PATH . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

// Variabel lingkungan CAMPGEAR_CONFIG dapat menunjuk file konfigurasi lain
// (dipakai pengujian otomatis agar memakai database terpisah).
$configFile = getenv('CAMPGEAR_CONFIG') ?: BASE_PATH . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Konfigurasi belum ada. Salin config/config.example.php menjadi config/config.php.');
}

App\Core\Config::load(require $configFile);

date_default_timezone_set((string) config('app.timezone', 'Asia/Jakarta'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register((bool) config('app.debug', false));
