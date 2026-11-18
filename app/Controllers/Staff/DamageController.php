<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\DamageReport;

/** Pengelolaan laporan kerusakan/kehilangan dan penyelesaian penggantian. */
final class DamageController
{
    /** GET /staff/kerusakan?status=unsettled */
    public function index(): void
    {
        $status = Request::query('status', 'unsettled');
        if ($status !== 'all' && !array_key_exists($status, DamageReport::STATUS_LABELS)) {
            $status = 'unsettled';
        }
        $pager = new Paginator(DamageReport::count($status), 20);

        view('staff/damages/index', [
            'title'   => 'Kerusakan & Kehilangan',
            'status'  => $status,
            'damages' => DamageReport::paginate($status, $pager->perPage, $pager->offset),
            'pager'   => $pager,
        ], 'layouts/panel');
    }

    /** GET /staff/kerusakan/{id} */
    public function show(string $id): void
    {
        $damage = self::findOrFail($id);
        view('staff/damages/show', [
            'title'  => 'Laporan ' . \App\Models\DamageReport::TYPE_LABELS[$damage['type']] . ' #' . $damage['id'],
            'damage' => $damage,
        ], 'layouts/panel');
    }

    /** POST /staff/kerusakan/{id} — ubah keterangan & nominal penggantian. */
    public function update(string $id): void
    {
        $damage = self::findOrFail($id);
        $path = '/staff/kerusakan/' . $damage['id'];

        $validator = new Validator($_POST, [
            'description'   => 'max:500',
            'charge_amount' => 'numeric|min:0|max:100000000',
        ], ['description' => 'Keterangan', 'charge_amount' => 'Nominal penggantian']);
        if ($validator->fails()) {
            back_with_errors($path, $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $charge = $data['charge_amount'] === null ? null : round((float) $data['charge_amount'], 2);

        try {
            DamageReport::updateDetails((int) $damage['id'], $data['description'], $charge);
            AuditLog::record('damage.update', 'booking', (int) $damage['booking_id'],
                "Memperbarui laporan kerusakan #{$damage['id']} ({$damage['code']})",
                ['previous_charge' => $damage['charge_amount'], 'charge' => $charge]);
            flash('success', 'Laporan diperbarui.');
        } catch (BusinessRuleException $e) {
            flash('error', $e->getMessage());
        }
        redirect($path);
    }

    /** POST /staff/kerusakan/{id}/selesai — nyatakan penggantian tuntas. */
    public function settle(string $id): void
    {
        $damage = self::findOrFail($id);
        $path = '/staff/kerusakan/' . $damage['id'];

        $validator = new Validator($_POST, [
            'settlement_note' => 'required|min:5|max:500',
            'payment_method'  => 'in:cash,transfer,ewallet',
        ], ['settlement_note' => 'Catatan penyelesaian', 'payment_method' => 'Metode pembayaran']);
        if ($validator->fails()) {
            back_with_errors($path, $validator->errors(), $_POST);
        }
        $data = $validator->validated();

        try {
            DamageReport::settle((int) $damage['id'], (int) Auth::id(), $data['settlement_note'], $data['payment_method']);
            AuditLog::record('damage.settle', 'booking', (int) $damage['booking_id'],
                "Menyelesaikan penggantian #{$damage['id']} ({$damage['code']})",
                ['payment_method' => $data['payment_method'], 'charge' => $damage['charge_amount']]);
            flash('success', 'Penggantian dinyatakan tuntas.');
        } catch (BusinessRuleException $e) {
            back_with_errors($path, ['settlement_note' => $e->getMessage()], $_POST);
        }
        redirect($path);
    }

    private static function findOrFail(string $id): array
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return ($id === false ? null : DamageReport::find($id))
            ?? throw new HttpException(404, 'Laporan tidak ditemukan.');
    }
}
