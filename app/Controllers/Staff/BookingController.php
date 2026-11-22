<?php

declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Controllers\BookingController as CustomerBookingController;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\DamageReport;
use App\Models\Handover;
use App\Models\Payment;

/**
 * Operasional booking oleh Staff/Admin:
 * daftar & pencarian, konfirmasi pembayaran tunai, pengambilan, pengembalian,
 * pencatatan deposit, dan penyelesaian booking.
 */
final class BookingController
{
    /** GET /staff/booking */
    public function index(): void
    {
        Booking::expireOverdue();
        $filters = [
            'q'      => mb_substr(Request::query('q'), 0, 100),
            'status' => Request::query('status'),
            'view'   => array_key_exists(Request::query('view'), Booking::STAFF_VIEWS) ? Request::query('view') : '',
        ];
        $pager = new Paginator(Booking::staffCount($filters), 20);

        view('staff/bookings/index', [
            'title'    => 'Booking',
            'filters'  => $filters,
            'bookings' => Booking::staffList($filters, $pager->perPage, $pager->offset),
            'pager'    => $pager,
        ], 'layouts/panel');
    }

    /** GET /staff/booking/{code} */
    public function show(string $code): void
    {
        Booking::expireOverdue();
        $booking = self::findOrFail($code);
        $id = (int) $booking['id'];
        $today = date('Y-m-d');

        view('staff/bookings/show', [
            'title'          => 'Booking ' . $booking['code'],
            'booking'        => $booking,
            'status'         => BookingStatus::from($booking['status']),
            'items'          => Booking::items($id),
            'payments'       => Booking::payments($id),
            'rentalPayments' => Payment::rentalHistory($id),
            'pendingPayment' => Payment::pendingRental($id),
            'cancellations'  => CancellationRequest::forBooking($id),
            'damages'        => DamageReport::forBooking($id),
            'handovers'      => Handover::forBooking($id),
            'canPickup'      => $booking['status'] === 'confirmed' && $today >= $booking['start_date'] && $today <= $booking['end_date'],
        ], 'layouts/panel');
    }

    /** POST /staff/booking/{code}/bayar — pembayaran tunai diterima di lokasi. */
    public function confirmPayment(string $code): void
    {
        $booking = self::findOrFail($code);
        $this->attempt($booking, function () use ($booking): string {
            $reference = Booking::paySimulated((int) $booking['id'], 'cash', Auth::id());
            AuditLog::record('booking.payment_cash', 'booking', (int) $booking['id'],
                "Menerima pembayaran tunai {$booking['code']} sebesar " . rupiah($booking['grand_total']), ['reference' => $reference]);
            return 'Pembayaran tunai dicatat. Booking terkonfirmasi.';
        });
    }

    /** POST /staff/booking/{code}/pembayaran/{payment_id}/setujui — verifikasi & setujui bukti. */
    public function approvePayment(string $code, string $payment_id): void
    {
        $booking = self::findOrFail($code);
        $paymentId = (int) $payment_id;

        $this->attempt($booking, function () use ($booking, $paymentId): string {
            $payment = Payment::findById($paymentId);
            if ($payment === null || (int) $payment['booking_id'] !== (int) $booking['id']) {
                throw new BusinessRuleException('Data pembayaran tidak ditemukan untuk booking ini.');
            }

            Payment::approve($paymentId, (int) Auth::id());

            AuditLog::record(
                'booking.payment_approved',
                'booking',
                (int) $booking['id'],
                "Menyetujui pembayaran sewa {$booking['code']} (ID payment: {$paymentId})",
                ['payment_id' => $paymentId]
            );

            return 'Pembayaran disetujui. Booking terkonfirmasi.';
        });
    }

    /** POST /staff/booking/{code}/pembayaran/{payment_id}/tolak — tolak bukti pembayaran. */
    public function rejectPayment(string $code, string $payment_id): void
    {
        $booking = self::findOrFail($code);
        $paymentId = (int) $payment_id;

        $note = trim((string) ($_POST['note'] ?? $_POST['reason'] ?? ''));
        if ($note === '') {
            flash('error', 'Alasan penolakan wajib diisi.');
            redirect('/staff/booking/' . $booking['code']);
        }

        $this->attempt($booking, function () use ($booking, $paymentId, $note): string {
            $payment = Payment::findById($paymentId);
            if ($payment === null || (int) $payment['booking_id'] !== (int) $booking['id']) {
                throw new BusinessRuleException('Data pembayaran tidak ditemukan untuk booking ini.');
            }

            Payment::reject($paymentId, (int) Auth::id(), $note);

            AuditLog::record(
                'booking.payment_rejected',
                'booking',
                (int) $booking['id'],
                "Menolak pembayaran sewa {$booking['code']} (ID payment: {$paymentId}): {$note}",
                ['payment_id' => $paymentId, 'note' => $note]
            );

            return 'Bukti pembayaran ditolak.';
        });
    }

    /** GET /staff/booking/{code}/bukti/{payment_id} — pratinjau bukti bayar privat oleh staf. */
    public function proof(string $code, string $payment_id): void
    {
        $booking = self::findOrFail($code);
        $payment = Payment::findById((int) $payment_id);
        if ($payment === null || (int) $payment['booking_id'] !== (int) $booking['id']) {
            throw new HttpException(404, 'Bukti pembayaran tidak ditemukan.');
        }

        CustomerBookingController::streamProofFile($payment['proof_image']);
    }

    /** POST /staff/booking/{code}/ambil — checklist pengambilan + deposit. */
    public function pickup(string $code): void
    {
        $booking = self::findOrFail($code);
        $this->attempt($booking, function () use ($booking): string {
            $depositAmount = self::moneyInput('deposit_amount', 'Nominal deposit');
            $result = Handover::recordPickup(
                (int) $booking['id'],
                (int) Auth::id(),
                is_array($_POST['items'] ?? null) ? $_POST['items'] : [],
                self::textInput('notes', 500),
                $depositAmount,
                self::textInput('deposit_note', 255),
            );
            AuditLog::record('booking.pickup', 'booking', (int) $booking['id'],
                "Menyerahkan alat booking {$booking['code']}",
                ['deposit_amount' => $depositAmount, 'deposit_recorded' => $result['deposit']]);
            return 'Pengambilan alat dicatat. Status booking: Sedang Disewa.';
        });
    }

    /** POST /staff/booking/{code}/kembali — checklist pengembalian + laporan kerusakan/kehilangan. */
    public function receiveReturn(string $code): void
    {
        $booking = self::findOrFail($code);
        $this->attempt($booking, function () use ($booking): string {
            $result = Handover::recordReturn(
                (int) $booking['id'],
                (int) Auth::id(),
                is_array($_POST['items'] ?? null) ? $_POST['items'] : [],
                self::textInput('notes', 500),
                isset($_POST['reduce_stock']),
            );
            AuditLog::record('booking.return', 'booking', (int) $booking['id'],
                "Menerima pengembalian alat booking {$booking['code']}",
                ['damage_reports' => $result['reports'], 'reduce_stock' => isset($_POST['reduce_stock'])]);

            $message = 'Pengembalian alat dicatat.';
            if ($result['reports'] > 0) {
                $message .= " {$result['reports']} laporan kerusakan/kehilangan dibuat dan wajib diselesaikan sebelum booking selesai.";
            }
            if ($result['stock_warnings'] !== []) {
                flash('warning', 'Periksa stok: ' . implode('; ', $result['stock_warnings']) . '.');
            }
            return $message;
        });
    }

    /** POST /staff/booking/{code}/deposit — catat deposit secara administratif. */
    public function deposit(string $code): void
    {
        $booking = self::findOrFail($code);
        $this->attempt($booking, function () use ($booking): string {
            $amount = self::moneyInput('deposit_amount', 'Nominal deposit');
            $status = is_string($_POST['deposit_status'] ?? null) ? $_POST['deposit_status'] : '';
            $note = self::textInput('deposit_note', 255);
            Booking::updateDeposit((int) $booking['id'], $amount, $status, $note);
            AuditLog::record('booking.deposit', 'booking', (int) $booking['id'],
                "Memperbarui catatan deposit {$booking['code']}: " . (Booking::DEPOSIT_LABELS[$status] ?? $status),
                ['amount' => $amount, 'status' => $status, 'previous_status' => $booking['deposit_status']]);
            return 'Catatan deposit diperbarui.';
        });
    }

    /** POST /staff/booking/{code}/selesai */
    public function complete(string $code): void
    {
        $booking = self::findOrFail($code);
        $this->attempt($booking, function () use ($booking): string {
            Booking::complete((int) $booking['id']);
            AuditLog::record('booking.complete', 'booking', (int) $booking['id'], "Menyelesaikan booking {$booking['code']}");
            $message = 'Booking dinyatakan selesai.';
            if ($booking['deposit_status'] === 'held') {
                flash('warning', 'Deposit booking ini masih berstatus "Ditahan". Perbarui catatan deposit bila sudah dikembalikan.');
            }
            return $message;
        });
    }

    // ---- Bantuan ----------------------------------------------------------

    /** Jalankan aksi; pesan BusinessRuleException ditampilkan sebagai flash error. */
    private function attempt(array $booking, callable $action): never
    {
        try {
            flash('success', $action());
        } catch (BusinessRuleException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/staff/booking/' . $booking['code']);
    }

    private static function findOrFail(string $code): array
    {
        return Booking::findByCode($code) ?? throw new HttpException(404, 'Booking tidak ditemukan.');
    }

    private static function textInput(string $key, int $max): ?string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $max) : null;
    }

    private static function moneyInput(string $key, string $label): ?float
    {
        $raw = $_POST[$key] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        $amount = parse_money($raw);
        if ($amount === null || $amount < 0 || $amount > 100000000) {
            throw new BusinessRuleException("{$label} tidak valid.");
        }
        return $amount;
    }
}
