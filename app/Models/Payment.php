<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\BusinessRuleException;

/**
 * Logika pembayaran upload-bukti untuk metode transfer dan e-wallet.
 *
 * Aturan bisnis utama:
 * - Deadline = payment_due_at booking; tidak pernah direset.
 * - Satu payment 'pending' per booking per satu waktu (purpose='rental').
 * - Setiap submit bukti menghasilkan baris payment baru; baris lama (termasuk
 *   yang 'failed') tidak dihapus agar riwayat tetap tersedia untuk audit.
 * - Bukti yang dikirim sebelum deadline mempertahankan stok tanpa timeout tambahan.
 * - Jika bukti ditolak setelah deadline, booking menjadi expired (ditangani expireOverdue).
 * - Approval: payment → 'paid', booking → 'confirmed'.
 * - Rejection: payment → 'failed'; pengguna boleh upload ulang jika deadline belum lewat.
 * - Pembayaran tunai tetap melalui Booking::paySimulated() (tidak diubah).
 */
final class Payment
{
    // -------------------------------------------------------------------------
    // Submit bukti pembayaran
    // -------------------------------------------------------------------------

    /**
     * Simpan bukti pembayaran baru untuk booking (transfer / e-wallet).
     *
     * Menciptakan baris payment baru dengan status 'pending'.
     * Payment lama yang berstatus 'failed' tetap tersimpan (tidak dihapus).
     *
     * @param int    $bookingId  ID booking yang sudah divalidasi kepemilikannya di controller
     * @param string $method     'transfer' | 'ewallet'
     * @param string $proofPath  Path relatif bukti (dari Upload::privateImage(), mis. 'payments/abc.jpg')
     * @return int ID payment baru
     * @throws BusinessRuleException bila booking tidak valid, deadline lewat, atau sudah ada pending
     */
    public static function submitProof(int $bookingId, string $method, string $proofPath): int
    {
        return Database::transaction(static function () use ($bookingId, $method, $proofPath): int {
            // Kunci booking agar tidak ada dua submit bersamaan
            $booking = Database::first(
                'SELECT id, status, grand_total, payment_due_at FROM bookings WHERE id = ? FOR UPDATE',
                [$bookingId],
            );

            if ($booking === null) {
                throw new BusinessRuleException('Booking tidak ditemukan.');
            }
            if ($booking['status'] !== 'pending_payment') {
                throw new BusinessRuleException('Booking ini tidak sedang menunggu pembayaran.');
            }

            // Deadline 3 jam sejak booking dibuat; tidak pernah direset
            if (strtotime((string) $booking['payment_due_at']) <= time()) {
                throw new BusinessRuleException(
                    'Batas waktu pengiriman bukti pembayaran sudah lewat. Booking telah kedaluwarsa.'
                );
            }

            // Pastikan tidak ada payment pending rental yang aktif
            $existingPending = (int) Database::value(
                "SELECT COUNT(*) FROM payments
                 WHERE booking_id = ? AND purpose = 'rental' AND status = 'pending'",
                [$bookingId],
            );
            if ($existingPending > 0) {
                throw new BusinessRuleException(
                    'Bukti pembayaran Anda sudah diterima dan sedang menunggu verifikasi staf.'
                );
            }

            return Database::insert(
                "INSERT INTO payments (booking_id, purpose, method, amount, status, proof_image)
                 VALUES (?, 'rental', ?, ?, 'pending', ?)",
                [$bookingId, $method, (float) $booking['grand_total'], $proofPath],
            );
        });
    }

    // -------------------------------------------------------------------------
    // Verifikasi staf: approve
    // -------------------------------------------------------------------------

    /**
     * Staf menyetujui bukti pembayaran.
     *
     * Urutan row locking konsisten untuk memitigasi risiko circular wait / deadlock:
     * 1. Baca booking_id dari payment secara non-locking.
     * 2. Kunci baris booking (SELECT ... FOR UPDATE).
     * 3. Kunci baris payment (SELECT ... FOR UPDATE).
     * 4. Validasi ulang relasi booking_id, purpose, dan status payment di bawah lock.
     * 5. Set payment → 'paid', catat handled_by dan paid_at.
     * 6. Set booking → 'confirmed', catat confirmed_at.
     *
     * Bukti yang dikirim tepat waktu dapat disetujui staf kapan saja,
     * termasuk setelah payment_due_at terlewati.
     *
     * @param int $paymentId  ID payment yang di-approve
     * @param int $staffId    ID staf yang melakukan approval
     * @throws BusinessRuleException bila payment tidak valid atau sudah diproses
     */
    public static function approve(int $paymentId, int $staffId): void
    {
        Database::transaction(static function () use ($paymentId, $staffId): void {
            // 1. Baca metadata payment secara non-locking untuk memperoleh booking_id
            $paymentMeta = Database::first(
                "SELECT id, booking_id, purpose, status FROM payments WHERE id = ?",
                [$paymentId],
            );

            if ($paymentMeta === null) {
                throw new BusinessRuleException('Data pembayaran tidak ditemukan.');
            }
            if ($paymentMeta['purpose'] !== 'rental') {
                throw new BusinessRuleException('Hanya pembayaran sewa yang dapat diverifikasi di sini.');
            }
            if ($paymentMeta['status'] !== 'pending') {
                throw new BusinessRuleException('Pembayaran ini sudah diproses sebelumnya.');
            }

            $bookingId = (int) $paymentMeta['booking_id'];

            // 2. Kunci baris booking terlebih dahulu (konsisten: bookings -> payments)
            $booking = Database::first(
                'SELECT id, status, payment_due_at FROM bookings WHERE id = ? FOR UPDATE',
                [$bookingId],
            );

            if ($booking === null) {
                throw new BusinessRuleException('Booking tidak ditemukan.');
            }
            if ($booking['status'] !== 'pending_payment') {
                throw new BusinessRuleException(
                    'Booking ini sudah tidak dalam status menunggu pembayaran.'
                );
            }

            // 3. Kunci baris payment
            $payment = Database::first(
                "SELECT id, booking_id, purpose, status FROM payments WHERE id = ? FOR UPDATE",
                [$paymentId],
            );

            if ($payment === null) {
                throw new BusinessRuleException('Data pembayaran tidak ditemukan.');
            }

            // 4. Validasi ulang relasi dan status setelah kedua baris terkunci
            if ((int) $payment['booking_id'] !== $bookingId) {
                throw new BusinessRuleException('Relasi pembayaran dan booking tidak valid.');
            }
            if ($payment['purpose'] !== 'rental') {
                throw new BusinessRuleException('Hanya pembayaran sewa yang dapat diverifikasi di sini.');
            }
            if ($payment['status'] !== 'pending') {
                throw new BusinessRuleException('Pembayaran ini sudah diproses sebelumnya.');
            }

            // 5. Set payment → paid
            Database::execute(
                "UPDATE payments SET status = 'paid', handled_by = ?, paid_at = NOW() WHERE id = ?",
                [$staffId, $paymentId],
            );

            // 6. Set booking → confirmed
            Database::execute(
                "UPDATE bookings SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?",
                [$bookingId],
            );
        });
    }

    // -------------------------------------------------------------------------
    // Verifikasi staf: reject
    // -------------------------------------------------------------------------

    /**
     * Staf menolak bukti pembayaran.
     *
     * Urutan row locking konsisten:
     * 1. Baca booking_id dari payment secara non-locking.
     * 2. Kunci baris booking (SELECT ... FOR UPDATE).
     * 3. Kunci baris payment (SELECT ... FOR UPDATE).
     * 4. Validasi ulang relasi booking_id, purpose, dan status payment di bawah lock.
     * 5. Set payment → 'failed', catat handled_by dan catatan penolakan.
     * 6. Periksa batas waktu:
     *    - Jika payment_due_at SUDAH lewat: booking langsung diubah menjadi 'expired'.
     *    - Jika payment_due_at BELUM lewat: booking tetap 'pending_payment' agar pelanggan
     *      dapat mengunggah bukti pengganti tanpa memperpanjang deadline.
     *
     * @param int    $paymentId  ID payment yang ditolak
     * @param int    $staffId    ID staf yang menolak
     * @param string $note       Alasan penolakan (wajib diisi)
     * @throws BusinessRuleException bila payment tidak valid atau sudah diproses
     */
    public static function reject(int $paymentId, int $staffId, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            throw new BusinessRuleException('Alasan penolakan wajib diisi.');
        }

        Database::transaction(static function () use ($paymentId, $staffId, $note): void {
            // 1. Baca metadata payment secara non-locking untuk memperoleh booking_id
            $paymentMeta = Database::first(
                "SELECT id, booking_id, purpose, status FROM payments WHERE id = ?",
                [$paymentId],
            );

            if ($paymentMeta === null) {
                throw new BusinessRuleException('Data pembayaran tidak ditemukan.');
            }
            if ($paymentMeta['purpose'] !== 'rental') {
                throw new BusinessRuleException('Hanya pembayaran sewa yang dapat diverifikasi di sini.');
            }
            if ($paymentMeta['status'] !== 'pending') {
                throw new BusinessRuleException('Pembayaran ini sudah diproses sebelumnya.');
            }

            $bookingId = (int) $paymentMeta['booking_id'];

            // 2. Kunci baris booking terlebih dahulu (konsisten: bookings -> payments)
            $booking = Database::first(
                'SELECT id, status, payment_due_at FROM bookings WHERE id = ? FOR UPDATE',
                [$bookingId],
            );

            if ($booking === null) {
                throw new BusinessRuleException('Booking tidak ditemukan.');
            }
            if ($booking['status'] !== 'pending_payment') {
                throw new BusinessRuleException(
                    'Booking ini sudah tidak dalam status menunggu pembayaran.'
                );
            }

            // 3. Kunci baris payment
            $payment = Database::first(
                "SELECT id, booking_id, purpose, status FROM payments WHERE id = ? FOR UPDATE",
                [$paymentId],
            );

            if ($payment === null) {
                throw new BusinessRuleException('Data pembayaran tidak ditemukan.');
            }

            // 4. Validasi ulang relasi dan status setelah kedua baris terkunci
            if ((int) $payment['booking_id'] !== $bookingId) {
                throw new BusinessRuleException('Relasi pembayaran dan booking tidak valid.');
            }
            if ($payment['purpose'] !== 'rental') {
                throw new BusinessRuleException('Hanya pembayaran sewa yang dapat diverifikasi di sini.');
            }
            if ($payment['status'] !== 'pending') {
                throw new BusinessRuleException('Pembayaran ini sudah diproses sebelumnya.');
            }

            // 5. Set payment → failed
            Database::execute(
                "UPDATE payments SET status = 'failed', handled_by = ?, note = ? WHERE id = ?",
                [$staffId, $note, $paymentId],
            );

            // 6. Evaluasi deadline langsung dalam transaksi ini
            $isOverdue = strtotime((string) $booking['payment_due_at']) <= time();
            if ($isOverdue) {
                Database::execute(
                    "UPDATE bookings SET status = 'expired' WHERE id = ?",
                    [$bookingId],
                );
            }
        });
    }

    // -------------------------------------------------------------------------
    // Pembacaan
    // -------------------------------------------------------------------------

    /**
     * Semua payment rental untuk sebuah booking, dari yang terlama ke terbaru.
     * Mencakup semua status: pending, paid, failed — riwayat lengkap.
     * Untuk payment yang ditolak (failed), rejected_at diambil dari audit_logs.
     */
    public static function rentalHistory(int $bookingId): array
    {
        $payments = Database::select(
            "SELECT p.*, u.name AS staff_name
             FROM payments p
             LEFT JOIN users u ON u.id = p.handled_by
             WHERE p.booking_id = ? AND p.purpose = 'rental'
             ORDER BY p.created_at ASC",
            [$bookingId],
        );

        if ($payments === []) {
            return [];
        }

        // Cek apakah ada payment failed yang membutuhkan rejected_at dari audit log
        $hasFailed = false;
        foreach ($payments as $p) {
            if ($p['status'] === 'failed') {
                $hasFailed = true;
                break;
            }
        }

        $rejections = [];
        if ($hasFailed) {
            $logs = Database::select(
                "SELECT meta, created_at FROM audit_logs
                 WHERE action = 'booking.payment_rejected'
                   AND entity_type = 'booking'
                   AND entity_id = ?
                 ORDER BY id ASC",
                [$bookingId],
            );

            foreach ($logs as $log) {
                if (empty($log['meta']) || !is_string($log['meta'])) {
                    continue;
                }
                $meta = json_decode($log['meta'], true);
                if (is_array($meta) && isset($meta['payment_id']) && is_numeric($meta['payment_id'])) {
                    $rejections[(int) $meta['payment_id']] = (string) $log['created_at'];
                }
            }
        }

        foreach ($payments as &$payment) {
            $paymentId = (int) $payment['id'];
            $payment['rejected_at'] = ($payment['status'] === 'failed')
                ? ($rejections[$paymentId] ?? null)
                : null;
        }
        unset($payment);

        return $payments;
    }

    /**
     * Ambil payment rental yang saat ini berstatus pending untuk booking ini, atau null.
     * Dipakai controller untuk menentukan apakah form upload ditampilkan.
     */
    public static function pendingRental(int $bookingId): ?array
    {
        return Database::first(
            "SELECT * FROM payments
             WHERE booking_id = ? AND purpose = 'rental' AND status = 'pending'
             ORDER BY created_at DESC
             LIMIT 1",
            [$bookingId],
        );
    }

    /**
     * Ambil satu payment berdasarkan ID.
     * Dipakai controller untuk meneruskan ke approve/reject setelah validasi kepemilikan.
     */
    public static function findById(int $paymentId): ?array
    {
        return Database::first('SELECT * FROM payments WHERE id = ?', [$paymentId]);
    }
}
