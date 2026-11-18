<?php

declare(strict_types=1);

/**
 * Daftar route aplikasi.
 * Format: $router->METHOD(path, [Controller::class, 'method'], [middleware...]);
 *
 * @var App\Core\Router $router
 */

use App\Controllers\AccountController;
use App\Controllers\Admin\AuditLogController as AdminAuditLog;
use App\Controllers\Admin\UserController as AdminUser;
use App\Controllers\Api\AvailabilityController;
use App\Controllers\Api\StatsController as ApiStats;
use App\Controllers\ArticleController;
use App\Controllers\StatsController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\CatalogController;
use App\Controllers\HomeController;
use App\Controllers\Staff\ArticleController as StaffArticle;
use App\Controllers\Staff\BookingController as StaffBooking;
use App\Controllers\Staff\CategoryController as StaffCategory;
use App\Controllers\Staff\EquipmentController as StaffEquipment;
use App\Controllers\Staff\PackageController as StaffPackage;
use App\Controllers\Staff\CancellationController as StaffCancellation;
use App\Controllers\Staff\DamageController as StaffDamage;
use App\Controllers\Staff\DashboardController as StaffDashboard;

// ---- Halaman publik -------------------------------------------------------
$router->get('/', [HomeController::class, 'index']);
$router->get('/cara-reservasi', [HomeController::class, 'howTo']);
$router->get('/kontak', [HomeController::class, 'contact']);

$router->get('/alat', [CatalogController::class, 'index']);
$router->get('/alat/{slug}', [CatalogController::class, 'show']);
$router->get('/paket', [CatalogController::class, 'packages']);
$router->get('/artikel', [ArticleController::class, 'index']);
$router->get('/artikel/{slug}', [ArticleController::class, 'show']);
$router->get('/statistik', [StatsController::class, 'index']);

// ---- Booking --------------------------------------------------------------
$router->get('/sewa', [BookingController::class, 'create']);
$router->post('/sewa', [BookingController::class, 'store']);
$router->get('/booking/{code}', [BookingController::class, 'show']);
$router->post('/booking/{code}/bayar', [BookingController::class, 'pay']);
$router->post('/booking/{code}/batal', [BookingController::class, 'cancel']);
$router->get('/cek-booking', [BookingController::class, 'lookupForm']);
$router->post('/cek-booking', [BookingController::class, 'lookup']);

// ---- API (JSON) -----------------------------------------------------------
$router->get('/api/availability', [AvailabilityController::class, 'index']);
$router->get('/api/stats', [ApiStats::class, 'index']);

// ---- Autentikasi ----------------------------------------------------------
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest']);
$router->get('/register', [AuthController::class, 'showRegister'], ['guest']);
$router->post('/register', [AuthController::class, 'register'], ['guest']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth']);

// ---- Area pelanggan -------------------------------------------------------
$router->get('/akun', [AccountController::class, 'index'], ['auth']);
$router->get('/akun/profil', [AccountController::class, 'profile'], ['auth']);
$router->post('/akun/profil', [AccountController::class, 'updateProfile'], ['auth']);
$router->post('/akun/password', [AccountController::class, 'updatePassword'], ['auth']);

// ---- Panel Staff & Admin --------------------------------------------------
$staff = ['role:staff,admin'];
$router->get('/staff', [StaffDashboard::class, 'index'], $staff);

$router->get('/staff/booking', [StaffBooking::class, 'index'], $staff);
$router->get('/staff/booking/{code}', [StaffBooking::class, 'show'], $staff);
$router->post('/staff/booking/{code}/bayar', [StaffBooking::class, 'confirmPayment'], $staff);
$router->post('/staff/booking/{code}/ambil', [StaffBooking::class, 'pickup'], $staff);
$router->post('/staff/booking/{code}/kembali', [StaffBooking::class, 'receiveReturn'], $staff);
$router->post('/staff/booking/{code}/deposit', [StaffBooking::class, 'deposit'], $staff);
$router->post('/staff/booking/{code}/selesai', [StaffBooking::class, 'complete'], $staff);

$router->get('/staff/pembatalan', [StaffCancellation::class, 'index'], $staff);
$router->get('/staff/pembatalan/{id}', [StaffCancellation::class, 'show'], $staff);
$router->post('/staff/pembatalan/{id}/setujui', [StaffCancellation::class, 'approve'], $staff);
$router->post('/staff/pembatalan/{id}/tolak', [StaffCancellation::class, 'reject'], $staff);
$router->post('/staff/pembatalan/{id}/refund', [StaffCancellation::class, 'refundProcessed'], $staff);

$router->get('/staff/kerusakan', [StaffDamage::class, 'index'], $staff);
$router->get('/staff/kerusakan/{id}', [StaffDamage::class, 'show'], $staff);
$router->post('/staff/kerusakan/{id}', [StaffDamage::class, 'update'], $staff);
$router->post('/staff/kerusakan/{id}/selesai', [StaffDamage::class, 'settle'], $staff);

// Inventaris (REST: GET daftar/form, POST simpan, PUT ubah, DELETE hapus)
foreach (['alat' => StaffEquipment::class, 'kategori' => StaffCategory::class, 'paket' => StaffPackage::class, 'artikel' => StaffArticle::class] as $segment => $controller) {
    $router->get("/staff/{$segment}", [$controller, 'index'], $staff);
    $router->get("/staff/{$segment}/baru", [$controller, 'create'], $staff);
    $router->post("/staff/{$segment}", [$controller, 'store'], $staff);
    $router->get("/staff/{$segment}/{id}/edit", [$controller, 'edit'], $staff);
    $router->put("/staff/{$segment}/{id}", [$controller, 'update'], $staff);
    $router->delete("/staff/{$segment}/{id}", [$controller, 'destroy'], $staff);
}

// ---- Khusus Admin ---------------------------------------------------------
$admin = ['role:admin'];
$router->get('/admin/pengguna', [AdminUser::class, 'index'], $admin);
$router->get('/admin/pengguna/baru', [AdminUser::class, 'create'], $admin);
$router->post('/admin/pengguna', [AdminUser::class, 'store'], $admin);
$router->get('/admin/pengguna/{id}/edit', [AdminUser::class, 'edit'], $admin);
$router->put('/admin/pengguna/{id}', [AdminUser::class, 'update'], $admin);
$router->post('/admin/pengguna/{id}/password', [AdminUser::class, 'resetPassword'], $admin);
$router->delete('/admin/pengguna/{id}', [AdminUser::class, 'destroy'], $admin);
$router->get('/admin/log', [AdminAuditLog::class, 'index'], $admin);
