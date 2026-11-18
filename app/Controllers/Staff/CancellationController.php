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
use App\Models\Booking;
use App\Models\CancellationRequest;

/** Peninjauan pengajuan pembatalan oleh Staff/Admin. */
final class CancellationController
{
    /** GET /staff/pembatalan?status=pending */
    public function index(): void
    {
        $status = Request::query('status', 'pending');
        if ($status !== 'all' && !array_key_exists($status, CancellationRequest::STATUS_LABELS)) {
            $status = 'pending';
        }
        $pager = new Paginator(CancellationRequest::count($status), 20);

        view('staff/cancellations/index', [
            'title'    => 'Pembatalan',
            'status'   => $status,
            'requests' => CancellationRequest::paginate($status, $pager->perPage, $pager->offset),
            'pager'    => $pager,
        ], 'layouts/panel');
    }

    /** GET /staff/pembatalan/{id} */
    public function show(string $id): void
    {
        $request = self::findOrFail($id);
        view('staff/cancellations/show', [
            'title'    => 'Pengajuan Pembatalan #' . $request['id'],
            'request'  => $request,
            'items'    => Booking::items((int) $request['booking_id']),
            'payments' => Booking::payments((int) $request['booking_id']),
        ], 'layouts/panel');
    }

    /** POST /staff/pembatalan/{id}/setujui */
    public function approve(string $id): void
    {
        $request = self::findOrFail($id);
        $path = '/staff/pembatalan/' . $request['id'];

        $validator = new Validator($_POST, [
            'refund_amount' => 'numeric|min:0',
            'refund_note'   => 'max:500',
            'review_note'   => 'max:500',
        ], ['refund_amount' => 'Nominal refund', 'refund_note' => 'Catatan refund/deposit', 'review_note' => 'Catatan peninjauan']);
        if ($validator->fails()) {
            back_with_errors($path, $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $refund = $data['refund_amount'] === null ? null : round((float) $data['refund_amount'], 2);

        try {
            $result = CancellationRequest::approve((int) $request['id'], (int) Auth::id(), $refund, $data['refund_note'], $data['review_note']);
            AuditLog::record('cancellation.approve', 'booking', (int) $request['booking_id'],
                "Menyetujui pembatalan {$request['code']}; booking dibatalkan dan stok dilepas",
                ['request_id' => (int) $request['id'], 'refund_amount' => $refund, 'paid' => $result['paid']]);
            flash('success', "Pembatalan disetujui. Booking {$request['code']} kini berstatus Dibatalkan dan stok sudah dilepas.");
        } catch (BusinessRuleException $e) {
            back_with_errors($path, ['refund_amount' => $e->getMessage()], $_POST);
        }
        redirect($path);
    }

    /** POST /staff/pembatalan/{id}/tolak */
    public function reject(string $id): void
    {
        $request = self::findOrFail($id);
        $path = '/staff/pembatalan/' . $request['id'];

        $validator = new Validator($_POST, ['reject_note' => 'required|min:5|max:500'], ['reject_note' => 'Alasan penolakan']);
        if ($validator->fails()) {
            back_with_errors($path, $validator->errors(), $_POST);
        }

        try {
            CancellationRequest::reject((int) $request['id'], (int) Auth::id(), $validator->validated()['reject_note']);
            AuditLog::record('cancellation.reject', 'booking', (int) $request['booking_id'],
                "Menolak pembatalan {$request['code']}", ['request_id' => (int) $request['id']]);
            flash('success', 'Pengajuan pembatalan ditolak. Booking tetap berjalan.');
        } catch (BusinessRuleException $e) {
            flash('error', $e->getMessage());
        }
        redirect($path);
    }

    /** POST /staff/pembatalan/{id}/refund — tandai refund sudah diproses (administratif). */
    public function refundProcessed(string $id): void
    {
        $request = self::findOrFail($id);
        $note = is_string($_POST['refund_note'] ?? null) && trim($_POST['refund_note']) !== ''
            ? mb_substr(trim($_POST['refund_note']), 0, 500) : null;

        try {
            CancellationRequest::markRefundProcessed((int) $request['id'], $note);
            AuditLog::record('cancellation.refund_processed', 'booking', (int) $request['booking_id'],
                "Menandai refund pembatalan {$request['code']} sudah diproses", ['request_id' => (int) $request['id']]);
            flash('success', 'Refund ditandai sudah diproses.');
        } catch (BusinessRuleException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/staff/pembatalan/' . $request['id']);
    }

    private static function findOrFail(string $id): array
    {
        $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return ($id === false ? null : CancellationRequest::find($id))
            ?? throw new HttpException(404, 'Pengajuan pembatalan tidak ditemukan.');
    }
}
