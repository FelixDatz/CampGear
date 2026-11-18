<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;

final class AuthController
{
    private const LABELS = [
        'name'     => 'Nama lengkap',
        'email'    => 'Email',
        'phone'    => 'Nomor HP',
        'password' => 'Password',
    ];

    public function showLogin(): void
    {
        view('auth/login', ['title' => 'Masuk']);
    }

    public function login(): void
    {
        $validator = new Validator($_POST, [
            'email'    => 'required|email|max:150',
            'password' => 'required|max:255',
        ], self::LABELS);

        if ($validator->fails()) {
            back_with_errors('/login', $validator->errors(), $_POST);
        }

        $data = $validator->validated();
        $email = mb_strtolower($data['email']);
        $ip = Request::ip();

        if (LoginAttempt::tooMany($email, $ip)) {
            back_with_errors('/login', [
                'email' => 'Terlalu banyak percobaan login. Coba lagi dalam ' . LoginAttempt::windowMinutes() . ' menit.',
            ], $_POST);
        }

        $user = Auth::attempt($email, $data['password']);
        if ($user === null) {
            LoginAttempt::record($email, $ip);
            back_with_errors('/login', ['email' => 'Email atau password salah.'], $_POST);
        }
        if ((int) $user['is_active'] !== 1) {
            back_with_errors('/login', ['email' => 'Akun Anda dinonaktifkan. Silakan hubungi admin.'], $_POST);
        }

        LoginAttempt::clear($email, $ip);
        Auth::login($user, isset($_POST['remember']));

        if (Auth::isStaff()) {
            AuditLog::record('auth.login', 'user', (int) $user['id'], 'Login ke sistem');
        }

        flash('success', 'Selamat datang, ' . $user['name'] . '!');
        redirect(self::intendedPath());
    }

    public function showRegister(): void
    {
        view('auth/register', ['title' => 'Daftar Akun']);
    }

    public function register(): void
    {
        $validator = new Validator($_POST, [
            'name'     => 'required|min:3|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'phone'    => 'required|phone',
            'password' => 'required|password|max:72|confirmed',
        ], self::LABELS);

        if ($validator->fails()) {
            back_with_errors('/register', $validator->errors(), $_POST);
        }

        $data = $validator->validated();
        $id = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'phone'    => normalize_phone($data['phone']),
            'password' => $data['password'],
            'role'     => 'user', // pendaftaran publik selalu menjadi pelanggan
        ]);

        Auth::login(User::find($id));
        flash('success', 'Akun berhasil dibuat. Selamat datang di CampGear!');
        redirect('/akun');
    }

    public function logout(): void
    {
        if (Auth::isStaff()) {
            AuditLog::record('auth.logout', 'user', Auth::id(), 'Logout dari sistem');
        }
        Auth::logout();
        flash('success', 'Anda telah keluar.');
        redirect('/');
    }

    /** Halaman tujuan setelah login: halaman yang tadi diminta, atau dashboard sesuai role. */
    private static function intendedPath(): string
    {
        $intended = Session::get('intended');
        Session::forget('intended');

        // Hanya path internal yang diterima (mencegah open redirect ke situs lain).
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            return $intended;
        }
        return Auth::homePath();
    }
}
