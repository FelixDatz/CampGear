<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\RememberToken;
use App\Models\User;

/**
 * Manajemen akun Admin, Staff, dan User (khusus Admin).
 * Pengaman: admin tidak bisa menurunkan/menonaktifkan/menghapus dirinya sendiri,
 * dan admin aktif terakhir tidak bisa dihilangkan.
 */
final class UserController
{
    private const LABELS = [
        'name' => 'Nama lengkap', 'email' => 'Email', 'phone' => 'Nomor HP', 'address' => 'Alamat',
        'role' => 'Role', 'password' => 'Password',
    ];

    public function index(): void
    {
        $filters = [
            'q'      => mb_substr(Request::query('q'), 0, 100),
            'role'   => Request::query('role'),
            'active' => Request::query('active'),
        ];
        $pager = new Paginator(User::adminCount($filters), 20);
        view('admin/users/index', [
            'title'   => 'Pengguna',
            'filters' => $filters,
            'users'   => User::adminList($filters, $pager->perPage, $pager->offset),
            'pager'   => $pager,
        ], 'layouts/panel');
    }

    public function create(): void
    {
        view('admin/users/form', ['title' => 'Tambah Pengguna', 'account' => null], 'layouts/panel');
    }

    public function store(): void
    {
        $validator = new Validator($_POST, [
            'name'     => 'required|min:3|max:100',
            'email'    => 'required|email|max:150|unique:users,email',
            'phone'    => 'required|phone',
            'role'     => 'required|in:user,staff,admin',
            'password' => 'required|password|max:72|confirmed',
        ], self::LABELS);
        if ($validator->fails()) {
            back_with_errors('/admin/pengguna/baru', $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $id = User::create([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => normalize_phone($data['phone']),
            'password' => $data['password'], 'role' => $data['role'],
        ]);
        AuditLog::record('user.create', 'user', $id, "Membuat akun {$data['email']} sebagai " . Role::from($data['role'])->label());
        flash('success', 'Akun dibuat.');
        redirect('/admin/pengguna');
    }

    public function edit(string $id): void
    {
        view('admin/users/form', ['title' => 'Ubah Pengguna', 'account' => self::findOrFail($id)], 'layouts/panel');
    }

    public function update(string $id): void
    {
        $account = self::findOrFail($id);
        $backTo = '/admin/pengguna/' . $account['id'] . '/edit';
        $validator = new Validator($_POST, [
            'name'    => 'required|min:3|max:100',
            'email'   => 'required|email|max:150|unique:users,email,' . (int) $account['id'],
            'phone'   => 'phone',
            'address' => 'max:255',
            'role'    => 'required|in:user,staff,admin',
        ], self::LABELS);
        $data = $validator->validated();
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;

        $isSelf = (int) $account['id'] === Auth::id();
        if ($isSelf && ($data['role'] !== $account['role'] || $data['is_active'] !== 1)) {
            $validator->addError('role', 'Kamu tidak dapat mengubah role atau menonaktifkan akunmu sendiri.');
        }
        $losesAdmin = $account['role'] === 'admin' && (int) $account['is_active'] === 1
            && ($data['role'] !== 'admin' || $data['is_active'] !== 1);
        if ($losesAdmin && User::otherActiveAdmins((int) $account['id']) === 0) {
            $validator->addError('role', 'Harus ada minimal satu admin aktif.');
        }
        if ($validator->fails()) {
            back_with_errors($backTo, $validator->errors(), $_POST);
        }

        $data['phone'] = $data['phone'] !== null ? normalize_phone($data['phone']) : null;
        User::updateByAdmin((int) $account['id'], $data);
        if ($data['is_active'] === 0) {
            RememberToken::deleteForUser((int) $account['id']);
        }
        AuditLog::record('user.update', 'user', (int) $account['id'], "Mengubah akun {$data['email']}", [
            'before' => ['role' => $account['role'], 'is_active' => (int) $account['is_active'], 'email' => $account['email']],
            'after'  => ['role' => $data['role'], 'is_active' => $data['is_active'], 'email' => $data['email']],
        ]);
        flash('success', 'Akun diperbarui.');
        redirect('/admin/pengguna');
    }

    /** POST /admin/pengguna/{id}/password — admin menetapkan password baru. */
    public function resetPassword(string $id): void
    {
        $account = self::findOrFail($id);
        $backTo = '/admin/pengguna/' . $account['id'] . '/edit';
        $validator = new Validator($_POST, ['password' => 'required|password|max:72|confirmed'], self::LABELS);
        if ($validator->fails()) {
            back_with_errors($backTo . '#password', $validator->errors(), []);
        }
        $newHash = User::updatePassword((int) $account['id'], $validator->validated()['password']);
        RememberToken::deleteForUser((int) $account['id']);
        if ((int) $account['id'] === Auth::id()) {
            Auth::refreshPasswordSignature($newHash);
        }
        AuditLog::record('user.password_reset', 'user', (int) $account['id'], "Mengatur ulang password {$account['email']}");
        flash('success', 'Password akun diatur ulang.');
        redirect($backTo);
    }

    public function destroy(string $id): void
    {
        $account = self::findOrFail($id);
        if ((int) $account['id'] === Auth::id()) {
            flash('error', 'Kamu tidak dapat menghapus akunmu sendiri.');
            redirect('/admin/pengguna');
        }
        if ($account['role'] === 'admin' && User::otherActiveAdmins((int) $account['id']) === 0) {
            flash('error', 'Harus ada minimal satu admin aktif.');
            redirect('/admin/pengguna');
        }
        $blocker = User::deleteBlocker((int) $account['id']);
        if ($blocker !== null) {
            flash('error', $blocker);
            redirect('/admin/pengguna');
        }
        User::delete((int) $account['id']);
        AuditLog::record('user.delete', 'user', (int) $account['id'], "Menghapus akun {$account['email']}");
        flash('success', 'Akun dihapus.');
        redirect('/admin/pengguna');
    }

    private static function findOrFail(string $id): array
    {
        return User::find(int_param($id)) ?? throw new HttpException(404, 'Pengguna tidak ditemukan.');
    }
}
