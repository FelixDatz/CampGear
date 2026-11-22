<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\RememberToken;
use App\Models\User;

/** Area akun: riwayat penyewaan (pelanggan) dan pengelolaan data pribadi (semua role). */
final class AccountController
{
    /** GET /akun */
    public function index(): void
    {
        if (Auth::isStaff()) {
            redirect('/staff');
        }
        Booking::expireOverdue();
        $user = Auth::user();

        view('account/index', [
            'title'    => 'Akun Saya',
            'user'     => $user,
            'bookings' => Booking::forCustomer((int) $user['id'], $user['email']),
        ]);
    }

    /** GET /akun/profil */
    public function profile(): void
    {
        view('account/profile', [
            'title' => 'Profil & Password',
            'user'  => Auth::user(),
        ], Auth::isStaff() ? 'layouts/panel' : 'layouts/app');
    }

    /** POST /akun/profil */
    public function updateProfile(): void
    {
        $user = Auth::user();
        $validator = new Validator($_POST, [
            'name'    => 'required|min:3|max:100',
            'email'   => 'required|email|max:150|unique:users,email,' . (int) $user['id'],
            'phone'   => 'required|phone',
            'address' => 'max:255',
        ], ['name' => 'Nama lengkap', 'email' => 'Email', 'phone' => 'Nomor HP', 'address' => 'Alamat']);
        if ($validator->fails()) {
            back_with_errors('/akun/profil', $validator->errors(), $_POST);
        }

        $data = $validator->validated();
        $data['phone'] = normalize_phone($data['phone']);
        User::updateProfile((int) $user['id'], $data);
        if (Auth::isStaff()) {
            AuditLog::record('profile.update', 'user', (int) $user['id'], 'Memperbarui data profil sendiri');
        }
        flash('success', 'Data profil diperbarui.');
        redirect('/akun/profil');
    }

    /** POST /akun/password */
    public function updatePassword(): void
    {
        $user = Auth::user();
        $validator = new Validator($_POST, [
            'current_password' => 'required|max:255',
            'password'         => 'required|password|max:72|confirmed',
        ], ['current_password' => 'Password saat ini', 'password' => 'Password baru']);

        if (!$validator->fails() && !password_verify((string) $_POST['current_password'], $user['password_hash'])) {
            $validator->addError('current_password', 'Password saat ini salah.');
        }
        if ($validator->fails()) {
            back_with_errors('/akun/profil#password', $validator->errors(), []);
        }

        $newHash = User::updatePassword((int) $user['id'], $validator->validated()['password']);
        // Keluarkan sesi "Ingat saya" di perangkat lain dan ganti ID sesi ini.
        RememberToken::deleteForUser((int) $user['id']);
        Session::regenerate();
        Auth::refreshPasswordSignature($newHash);
        if (Auth::isStaff()) {
            AuditLog::record('profile.password', 'user', (int) $user['id'], 'Mengganti password sendiri');
        }
        flash('success', 'Password berhasil diganti. Login "Ingat saya" di perangkat lain telah dikeluarkan.');
        redirect('/akun/profil');
    }
}
