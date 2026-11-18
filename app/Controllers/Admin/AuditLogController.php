<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\User;

/** Catatan tindakan Staff & Admin (khusus Admin). */
final class AuditLogController
{
    public function index(): void
    {
        $filters = [
            'user'  => (int) Request::query('user', '0'),
            'group' => Request::query('group'),
            'from'  => Validator::isDate(Request::query('from')) ? Request::query('from') : '',
            'to'    => Validator::isDate(Request::query('to')) ? Request::query('to') : '',
        ];
        $pager = new Paginator(AuditLog::count($filters), 30);

        view('admin/audit/index', [
            'title'   => 'Log Aktivitas',
            'filters' => $filters,
            'actors'  => User::staffOptions(),
            'logs'    => AuditLog::paginate($filters, $pager->perPage, $pager->offset),
            'pager'   => $pager,
        ], 'layouts/panel');
    }
}
