<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\RememberToken;
use App\Models\User;

/**
 * Autentikasi berbasis session + fitur "Ingat saya" berbasis cookie.
 *
 * Cookie "Ingat saya" berisi  selector:validator.
 *  - selector  : dicari di database (tidak rahasia)
 *  - validator : rahasia; yang disimpan di database hanya hash SHA-256-nya,
 *                sehingga kebocoran database tidak membuat cookie bisa dipalsukan.
 * Token diganti setiap kali dipakai (rotasi).
 */
final class Auth
{
    private const REMEMBER_COOKIE = 'campgear_remember';
    private const REMEMBER_DAYS = 30;

    /** Hash acak untuk menyamakan waktu respons saat email tidak terdaftar. */
    private const DUMMY_HASH = '$2y$10$I9m0hRiSgwt/AYcq10qH4.ci1V5a7zrB3axMlJxtb1WmqCjUZwuwG';

    private static ?array $user = null;
    private static bool $resolved = false;

    /** Mengembalikan data user bila email & password cocok, null bila tidak. */
    public static function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user === null || !$valid) {
            return null;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::updatePassword((int) $user['id'], $password);
        }
        return $user;
    }

    public static function login(array $user, bool $remember = false): void
    {
        Session::regenerate();
        Csrf::rotate();
        $_SESSION['user_id'] = (int) $user['id'];

        self::$user = $user;
        self::$resolved = true;
        User::touchLastLogin((int) $user['id']);

        if ($remember) {
            self::issueRememberToken((int) $user['id']);
        }
    }

    public static function logout(): void
    {
        self::forgetRememberToken();
        $_SESSION = [];
        Session::regenerate();
        Csrf::rotate();
        self::$user = null;
        self::$resolved = true;
    }

    /**
     * User yang sedang login. Data dibaca ulang dari database setiap request,
     * sehingga perubahan role atau penonaktifan akun langsung berlaku.
     */
    public static function user(): ?array
    {
        if (!self::$resolved) {
            self::$resolved = true;
            $id = $_SESSION['user_id'] ?? null;
            if (is_int($id)) {
                $user = User::find($id);
                if ($user !== null && (int) $user['is_active'] === 1) {
                    self::$user = $user;
                } else {
                    unset($_SESSION['user_id']);
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user === null ? null : (int) $user['id'];
    }

    public static function hasRole(string ...$roles): bool
    {
        $user = self::user();
        return $user !== null && in_array($user['role'], $roles, true);
    }

    public static function isStaff(): bool
    {
        return self::hasRole('staff', 'admin');
    }

    /** Halaman utama setelah login, sesuai role. */
    public static function homePath(): string
    {
        return self::isStaff() ? '/staff' : '/akun';
    }

    // ---- Middleware -------------------------------------------------------

    public static function requireLogin(): void
    {
        if (self::check()) {
            return;
        }
        if (Request::wantsJson()) {
            throw new HttpException(401, 'Silakan login terlebih dahulu.');
        }
        if (Request::method() === 'GET') {
            Session::put('intended', Request::fullPath());
        }
        Session::flash('warning', 'Silakan login terlebih dahulu.');
        redirect('/login');
    }

    public static function requireGuest(): void
    {
        if (self::check()) {
            redirect(self::homePath());
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!self::hasRole(...$roles)) {
            throw new HttpException(403);
        }
    }

    // ---- "Ingat saya" -----------------------------------------------------

    /** Login otomatis dari cookie "Ingat saya" bila session sudah habis. */
    public static function restoreFromCookie(): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (!is_string($cookie) || self::check()) {
            return;
        }

        $parts = explode(':', $cookie, 2);
        if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) {
            self::clearRememberCookie();
            return;
        }
        [$selector, $validator] = $parts;

        $token = RememberToken::findValid($selector);
        if ($token === null || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            if ($token !== null) {
                // Selector benar tapi validator salah: kemungkinan cookie dicuri.
                RememberToken::deleteForUser((int) $token['user_id']);
            }
            self::clearRememberCookie();
            return;
        }

        RememberToken::deleteBySelector($selector);
        $user = User::find((int) $token['user_id']);
        if ($user === null || (int) $user['is_active'] !== 1) {
            self::clearRememberCookie();
            return;
        }
        self::login($user, true);
    }

    private static function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::REMEMBER_DAYS * 86400;

        RememberToken::create($userId, $selector, hash('sha256', $validator), date('Y-m-d H:i:s', $expires));
        self::setRememberCookie($selector . ':' . $validator, $expires);
    }

    private static function forgetRememberToken(): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? null;
        if (is_string($cookie) && str_contains($cookie, ':')) {
            $selector = explode(':', $cookie, 2)[0];
            if (ctype_xdigit($selector)) {
                RememberToken::deleteBySelector($selector);
            }
        }
        self::clearRememberCookie();
    }

    private static function clearRememberCookie(): void
    {
        self::setRememberCookie('', time() - 3600);
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    private static function setRememberCookie(string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => Session::cookiePath(),
            'secure'   => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
