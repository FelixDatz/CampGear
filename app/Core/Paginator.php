<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Paginasi sederhana berbasis LIMIT/OFFSET.
 *
 *   $total = (int) Database::value('SELECT COUNT(*) FROM ...');
 *   $pager = new Paginator($total, 20);
 *   $rows  = Database::select('... LIMIT ? OFFSET ?', [..., $pager->perPage, $pager->offset]);
 */
final class Paginator
{
    public readonly int $page;
    public readonly int $lastPage;
    public readonly int $offset;

    public function __construct(public readonly int $total, public readonly int $perPage = 20)
    {
        $this->lastPage = max(1, (int) ceil($total / $perPage));
        $requested = filter_var(Request::query('page', '1'), FILTER_VALIDATE_INT) ?: 1;
        $this->page = min(max(1, $requested), $this->lastPage);
        $this->offset = ($this->page - 1) * $perPage;
    }

    /** URL halaman tertentu dengan mempertahankan filter lain di query string. */
    public function url(int $page): string
    {
        $query = array_filter($_GET, static fn ($v) => is_string($v) && $v !== '');
        $query['page'] = (string) $page;
        return url(Request::path(), $query);
    }

    /** Nomor halaman yang ditampilkan (jendela di sekitar halaman aktif). */
    public function pages(int $window = 2): array
    {
        $from = max(1, $this->page - $window);
        $to = min($this->lastPage, $this->page + $window);
        return range($from, $to);
    }

    /** Keterangan "Menampilkan 21–40 dari 57". */
    public function summary(): string
    {
        if ($this->total === 0) {
            return 'Tidak ada data';
        }
        $from = $this->offset + 1;
        $to = min($this->offset + $this->perPage, $this->total);
        return "Menampilkan {$from}–{$to} dari {$this->total}";
    }
}
