<?php

declare(strict_types=1);

/**
 * Fungsi bantu global yang dipakai controller dan view.
 */

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Escape output HTML. WAJIB dipakai untuk setiap data dinamis di view. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL internal aplikasi, memperhitungkan base_path. */
function url(string $path = '/', array $query = []): string
{
    $base = rtrim((string) config('app.base_path', ''), '/');
    $url = $base . '/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }
    return $url;
}

/** URL file di public/assets dengan versi berdasarkan waktu ubah file (cache busting). */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('/assets/' . ltrim($path, '/')) . '?v=' . $version;
}

/** URL file unggahan (gambar alat, artikel). */
function upload_url(?string $filename): ?string
{
    return ($filename === null || $filename === '') ? null : url('/uploads/' . $filename);
}

function redirect(string $path, int $status = 302): never
{
    header('Location: ' . url($path), true, $status);
    exit;
}

function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
{
    echo View::render($template, $data, $layout);
}

function json_response(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Kembali ke form dengan pesan error dan input lama (tanpa field password). */
function back_with_errors(string $path, array $errors, array $input = []): never
{
    $input = array_filter(
        $input,
        static fn ($key) => is_string($key) && !str_contains($key, 'password') && $key !== '_token',
        ARRAY_FILTER_USE_KEY,
    );
    Session::flash('errors', $errors);
    Session::flash('old', $input);
    redirect($path);
}

function old(string $key, mixed $default = ''): mixed
{
    return Session::getFlash('old', [])[$key] ?? $default;
}

function error(string $key): ?string
{
    return Session::getFlash('errors', [])[$key] ?? null;
}

/** Kelas Bootstrap 'is-invalid' bila field punya error. */
function invalid(string $key): string
{
    return error($key) !== null ? ' is-invalid' : '';
}

function flash(string $key, string $message): void
{
    Session::flash($key, $message);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function auth_user(): ?array
{
    return Auth::user();
}

function rupiah(float|int|string|null $amount): string
{
    return 'Rp' . number_format((float) $amount, 0, ',', '.');
}

/** Format tanggal Indonesia: 18 Nov 2026 / 18 November 2026. */
function tanggal(?string $date, bool $long = false, bool $withTime = false): string
{
    if ($date === null || $date === '') {
        return '-';
    }
    $months = $long
        ? ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
        : ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $time = strtotime($date);
    if ($time === false) {
        return '-';
    }
    $text = date('j', $time) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);
    return $withTime ? $text . ', ' . date('H.i', $time) : $text;
}

/** Penanda menu aktif di navigasi. */
function nav_active(string $prefix): string
{
    $path = Request::path();
    $active = $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, $prefix . '/'));
    return $active ? ' active' : '';
}

/** Ubah teks menjadi slug URL: "Tenda Dome 2 Orang" -> "tenda-dome-2-orang". */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-') ?: 'item';
}

/** Seragamkan nomor HP: "+62 812-3456" / "62812..." -> "0812...". */
function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone) ?? '';
    if (str_starts_with($digits, '62')) {
        $digits = '0' . substr($digits, 2);
    }
    return $digits;
}

/**
 * Ubah teks artikel sederhana menjadi HTML yang AMAN.
 * Teks di-escape lebih dulu, baru diberi format:
 *   baris kosong      -> paragraf baru
 *   "## Judul"        -> subjudul
 *   "- item"          -> daftar berbutir
 *   "1. item"         -> daftar bernomor
 *   "**tebal**"       -> teks tebal
 */
function format_text(?string $text): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
    if ($text === '') {
        return '';
    }

    $inline = static fn (string $line): string => preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($line)) ?? e($line);
    $html = '';

    foreach (preg_split('/\n{2,}/', $text) as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), static fn ($l) => $l !== ''));
        while ($lines !== [] && str_starts_with($lines[0], '## ')) {
            $html .= '<h2>' . $inline(substr(array_shift($lines), 3)) . '</h2>';
        }
        if ($lines === []) {
            continue;
        }
        if (array_filter($lines, static fn ($l) => !str_starts_with($l, '- ')) === []) {
            $html .= '<ul>' . implode('', array_map(static fn ($l) => '<li>' . $inline(substr($l, 2)) . '</li>', $lines)) . '</ul>';
        } elseif (array_filter($lines, static fn ($l) => !preg_match('/^\d+\.\s/', $l)) === []) {
            $html .= '<ol>' . implode('', array_map(static fn ($l) => '<li>' . $inline(preg_replace('/^\d+\.\s/', '', $l)) . '</li>', $lines)) . '</ol>';
        } else {
            $html .= '<p>' . implode('<br>', array_map($inline, $lines)) . '</p>';
        }
    }
    return $html;
}

/** Parameter ID dari URL sebagai int positif; selain itu 404. */
function int_param(string $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        throw new App\Core\HttpException(404);
    }
    return $id;
}

/** Atribut selected/checked untuk elemen form. */
function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/** Angka uang dari input form ("150.000" atau "150000") -> float, atau null bila kosong. */
function parse_money(mixed $value): ?float
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }
    $clean = str_replace(['.', ' ', 'Rp', 'rp'], '', trim($value));
    $clean = str_replace(',', '.', $clean);
    return is_numeric($clean) ? round((float) $clean, 2) : null;
}

/** Batasi panjang teks untuk ringkasan. */
function excerpt(?string $text, int $length = 120): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)) ?? '');
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . '…' : $text;
}
