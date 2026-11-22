<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\StockUnavailableException;
use DateTimeImmutable;

final class Booking
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // tanpa 0/O dan 1/I agar tidak tertukar

    // ---- Aturan rentang tanggal -------------------------------------------

    /** Pesan kesalahan bila rentang tanggal melanggar aturan sewa, atau null bila valid. */
    public static function rangeError(string $start, string $end): ?string
    {
        if (!self::isDate($start) || !self::isDate($end)) {
            return 'Tanggal mulai dan selesai wajib diisi dengan format yang benar.';
        }
        $today = date('Y-m-d');
        $maxStart = (new DateTimeImmutable($today))->modify('+' . (int) config('rental.max_advance_days', 90) . ' days')->format('Y-m-d');
        $maxDays = (int) config('rental.max_rental_days', 14);

        return match (true) {
            $start < $today   => 'Tanggal mulai tidak boleh sebelum hari ini.',
            $start > $maxStart => 'Tanggal mulai paling lambat ' . tanggal($maxStart, true) . '.',
            $end < $start     => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            Availability::countDays($start, $end) > $maxDays => "Durasi sewa maksimal {$maxDays} hari.",
            default           => null,
        };
    }

    private static function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    // ---- Membuat booking --------------------------------------------------

    /**
     * Membuat booking baru dalam satu transaksi.
     *
     * @param array $customer ['name','email','phone','notes','user_id']
     * @param array $lines    [['equipment_id'=>int,'quantity'=>int,'package_id'=>?int,'discount_percent'=>float], ...]
     * @return string kode booking
     * @throws StockUnavailableException bila stok tidak cukup
     * @throws BusinessRuleException     bila alat tidak aktif / tidak ditemukan
     */
    public static function create(array $customer, string $start, string $end, array $lines): string
    {
        $days = Availability::countDays($start, $end);

        return Database::transaction(static function () use ($customer, $start, $end, $lines, $days): string {
            // 1. Kunci baris alat yang dipesan (SELECT ... FOR UPDATE).
            //    Request lain yang memesan alat yang sama harus menunggu transaksi ini selesai,
            //    sehingga dua orang tidak bisa sama-sama mendapatkan unit terakhir.
            $ids = array_values(array_unique(array_column($lines, 'equipment_id')));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $equipment = [];
            foreach (Database::select(
                "SELECT id, name, daily_rate, is_active FROM equipment WHERE id IN ({$placeholders}) FOR UPDATE",
                $ids,
            ) as $row) {
                $equipment[(int) $row['id']] = $row;
            }

            foreach ($ids as $id) {
                if (!isset($equipment[$id]) || (int) $equipment[$id]['is_active'] !== 1) {
                    throw new BusinessRuleException('Salah satu alat yang dipilih sudah tidak disewakan. Muat ulang halaman lalu pilih kembali.');
                }
            }

            // 2. Hitung total permintaan per alat (alat satuan + alat dari paket digabung).
            $demand = [];
            foreach ($lines as $line) {
                $demand[$line['equipment_id']] = ($demand[$line['equipment_id']] ?? 0) + $line['quantity'];
            }

            // 3. Cek stok per hari. Karena dibaca setelah kunci didapat,
            //    booking yang baru saja disimpan request lain sudah ikut terhitung.
            $availability = Availability::forRange($start, $end, $ids);
            $shortages = [];
            foreach ($demand as $id => $quantity) {
                $available = $availability[$id]['available'] ?? 0;
                if ($quantity > $available) {
                    $shortages[] = ['name' => $equipment[$id]['name'], 'requested' => $quantity, 'available' => $available];
                }
            }
            if ($shortages !== []) {
                throw new StockUnavailableException($shortages);
            }

            // 4. Hitung biaya dengan tarif saat ini (disimpan sebagai snapshot).
            $subtotal = 0.0;
            $discountTotal = 0.0;
            foreach ($lines as $i => $line) {
                $rate = (float) $equipment[$line['equipment_id']]['daily_rate'];
                $lineTotal = $line['quantity'] * $rate * $days;
                $discount = round($lineTotal * ($line['discount_percent'] ?? 0) / 100);
                $lines[$i] += ['daily_rate' => $rate, 'line_total' => $lineTotal, 'discount_amount' => $discount];
                $subtotal += $lineTotal;
                $discountTotal += $discount;
            }

            // 5. Simpan booking dan rinciannya.
            $code = self::generateCode();
            $bookingId = Database::insert(
                'INSERT INTO bookings
                    (code, user_id, customer_name, customer_email, customer_phone, start_date, end_date, total_days,
                     subtotal, discount_total, grand_total, status, notes, payment_due_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() + INTERVAL ? HOUR)',
                [
                    $code,
                    $customer['user_id'] ?? null,
                    $customer['name'],
                    mb_strtolower($customer['email']),
                    $customer['phone'],
                    $start,
                    $end,
                    $days,
                    $subtotal,
                    $discountTotal,
                    $subtotal - $discountTotal,
                    BookingStatus::PendingPayment->value,
                    $customer['notes'] ?? null,
                    (int) config('rental.payment_window_hours', 24),
                ],
            );

            foreach ($lines as $line) {
                Database::insert(
                    'INSERT INTO booking_items
                        (booking_id, equipment_id, package_id, quantity, daily_rate, line_total, discount_amount)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $bookingId,
                        $line['equipment_id'],
                        $line['package_id'] ?? null,
                        $line['quantity'],
                        $line['daily_rate'],
                        $line['line_total'],
                        $line['discount_amount'],
                    ],
                );
            }

            return $code;
        });
    }

    /** Kode booking unik, mis. CG261118-7KQ2. */
    private static function generateCode(): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 4; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'CG' . date('ymd') . '-' . $suffix;
            if (Database::value('SELECT COUNT(*) FROM bookings WHERE code = ?', [$code]) == 0) {
                return $code;
            }
        }
        throw new \RuntimeException('Gagal membuat kode booking unik.');
    }

    // ---- Pembayaran (simulasi) --------------------------------------------

    /**
     * Membayar booking secara simulasi: catat payment 'paid' dan ubah status menjadi confirmed.
     * $handledBy diisi id staf bila pembayaran tunai diterima di lokasi.
     */
    public static function paySimulated(int $bookingId, string $method, ?int $handledBy = null): string
    {
        return Database::transaction(static function () use ($bookingId, $method, $handledBy): string {
            // Kunci baris booking agar pembayaran ganda (klik dua kali) tidak tercatat dua kali.
            $booking = Database::first('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if ($booking === null) {
                throw new BusinessRuleException('Booking tidak ditemukan.');
            }
            if ($booking['status'] !== BookingStatus::PendingPayment->value) {
                throw new BusinessRuleException('Booking ini sudah tidak menunggu pembayaran.');
            }
            if (strtotime((string) $booking['payment_due_at']) <= time()) {
                // Status 'expired' akan ditetapkan oleh expireOverdue() (di luar transaksi ini,
                // karena transaksi ini akan di-rollback oleh exception).
                throw new BusinessRuleException('Batas waktu pembayaran sudah lewat. Booking telah kedaluwarsa.');
            }

            $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(5)));
            Database::insert(
                "INSERT INTO payments (booking_id, purpose, method, amount, status, reference, handled_by, paid_at)
                 VALUES (?, 'rental', ?, ?, 'paid', ?, ?, NOW())",
                [$bookingId, $method, (float) $booking['grand_total'], $reference, $handledBy],
            );
            Database::execute(
                "UPDATE bookings SET status = 'confirmed', confirmed_at = NOW() WHERE id = ?",
                [$bookingId],
            );
            return $reference;
        });
    }

    // ---- Operasional staff ------------------------------------------------

    public const DEPOSIT_LABELS = ['not_recorded' => 'Belum dicatat', 'held' => 'Ditahan', 'returned' => 'Dikembalikan'];

    /** Catat deposit secara administratif (nominal opsional, tanpa formula). */
    public static function updateDeposit(int $bookingId, ?float $amount, string $status, ?string $note): array
    {
        return Database::transaction(static function () use ($bookingId, $amount, $status, $note): array {
            $booking = Database::first('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if ($booking === null) {
                throw new BusinessRuleException('Booking tidak ditemukan.');
            }
            if (in_array($booking['status'], ['pending_payment', 'expired'], true)) {
                throw new BusinessRuleException('Deposit dicatat setelah booking terkonfirmasi.');
            }
            if (!array_key_exists($status, self::DEPOSIT_LABELS)) {
                throw new BusinessRuleException('Status deposit tidak valid.');
            }
            Database::execute(
                'UPDATE bookings SET deposit_amount = ?, deposit_status = ?, deposit_note = ? WHERE id = ?',
                [$amount, $status, $note, $bookingId],
            );
            return $booking;
        });
    }

    /** Selesaikan booking: alat sudah kembali dan semua penggantian sudah tuntas. */
    public static function complete(int $bookingId): array
    {
        return Database::transaction(static function () use ($bookingId): array {
            $booking = Database::first('SELECT * FROM bookings WHERE id = ? FOR UPDATE', [$bookingId]);
            if ($booking === null || $booking['status'] !== BookingStatus::Returned->value) {
                throw new BusinessRuleException('Hanya booking yang alatnya sudah dikembalikan yang dapat diselesaikan.');
            }
            $unsettled = (int) Database::value(
                "SELECT COUNT(*) FROM damage_reports WHERE booking_id = ? AND status = 'unsettled'",
                [$bookingId],
            );
            if ($unsettled > 0) {
                throw new BusinessRuleException("Masih ada {$unsettled} laporan kerusakan/kehilangan yang belum diselesaikan. Penyewa wajib melakukan penggantian terlebih dahulu.");
            }
            Database::execute("UPDATE bookings SET status = 'completed', completed_at = NOW() WHERE id = ?", [$bookingId]);
            return $booking;
        });
    }

    /** Pilihan tampilan cepat di daftar booking staff. */
    public const STAFF_VIEWS = [
        ''          => 'Semua',
        'pickup'    => 'Ambil hari ini',
        'active'    => 'Sedang disewa',
        'due'       => 'Jatuh tempo kembali',
        'returned'  => 'Menunggu diselesaikan',
        'unpaid'    => 'Menunggu bayar',
    ];

    /** @return array{0: string, 1: array} klausa WHERE dan parameternya */
    private static function staffFilter(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        $today = date('Y-m-d');

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(b.code LIKE ? OR b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.customer_phone LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && BookingStatus::tryFrom($status) !== null) {
            $where[] = 'b.status = ?';
            $params[] = $status;
        }
        switch ($filters['view'] ?? '') {
            case 'pickup':
                $where[] = "b.status = 'confirmed' AND b.start_date <= ? AND b.end_date >= ?";
                array_push($params, $today, $today);
                break;
            case 'active':
                $where[] = "b.status = 'picked_up'";
                break;
            case 'due':
                $where[] = "b.status = 'picked_up' AND b.end_date <= ?";
                $params[] = $today;
                break;
            case 'returned':
                $where[] = "b.status = 'returned'";
                break;
            case 'unpaid':
                $where[] = "b.status = 'pending_payment'";
                break;
        }
        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    public static function staffCount(array $filters): int
    {
        [$where, $params] = self::staffFilter($filters);
        return (int) Database::value("SELECT COUNT(*) FROM bookings b {$where}", $params);
    }

    public static function staffList(array $filters, int $limit, int $offset): array
    {
        [$where, $params] = self::staffFilter($filters);
        return Database::select(
            "SELECT b.*,
                    (SELECT COALESCE(SUM(bi.quantity), 0) FROM booking_items bi WHERE bi.booking_id = b.id) AS unit_count,
                    (SELECT COUNT(*) FROM cancellation_requests cr WHERE cr.booking_id = b.id AND cr.status = 'pending') AS pending_cancel,
                    (SELECT COUNT(*) FROM damage_reports dr WHERE dr.booking_id = b.id AND dr.status = 'unsettled') AS unsettled_damage
             FROM bookings b
             {$where}
             ORDER BY b.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
    }

    // ---- Kedaluwarsa ------------------------------------------------------

    /**
     * Ubah booking yang melewati batas bayar menjadi 'expired'.
     * Hosting gratis tidak menyediakan cron, jadi fungsi ini dipanggil
     * setiap kali data booking/stok akan dibaca (lazy expiration).
     *
     * Aturan expiry:
     * - Booking TIDAK diexpire jika memiliki payment rental berstatus 'pending'
     *   (bukti sudah dikirim sebelum deadline → stok ditahan sampai staf verifikasi).
     * - Booking DIEXPIRE jika deadline lewat DAN tidak ada payment rental pending.
     *   Ini mencakup: belum upload sama sekali, atau bukti terakhir sudah ditolak ('failed').
     */
    public static function expireOverdue(): int
    {
        return Database::execute(
            "UPDATE bookings SET status = 'expired'
             WHERE status = 'pending_payment'
               AND payment_due_at <= NOW()
               AND NOT EXISTS (
                   SELECT 1 FROM payments
                   WHERE payments.booking_id = bookings.id
                     AND payments.purpose = 'rental'
                     AND payments.status = 'pending'
               )",
        );
    }

    // ---- Pembacaan --------------------------------------------------------

    public static function findByCode(string $code): ?array
    {
        return Database::first('SELECT * FROM bookings WHERE code = ?', [strtoupper(trim($code))]);
    }

    /** Rincian alat sebuah booking beserta nama alat dan paketnya. */
    public static function items(int $bookingId): array
    {
        return Database::select(
            'SELECT bi.*, e.name AS equipment_name, e.slug AS equipment_slug, p.name AS package_name
             FROM booking_items bi
             JOIN equipment e ON e.id = bi.equipment_id
             LEFT JOIN packages p ON p.id = bi.package_id
             WHERE bi.booking_id = ?
             ORDER BY bi.package_id IS NULL, bi.package_id, e.category_id, e.name',
            [$bookingId],
        );
    }

    public static function payments(int $bookingId): array
    {
        return Database::select(
            'SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at',
            [$bookingId],
        );
    }

    /**
     * Riwayat booking milik pelanggan: booking yang dibuat saat login,
     * ditambah booking tamu yang memakai email akun tersebut.
     */
    public static function forCustomer(int $userId, string $email): array
    {
        return Database::select(
            'SELECT b.*, COUNT(bi.id) AS line_count, COALESCE(SUM(bi.quantity), 0) AS unit_count,
                    (SELECT cr.status FROM cancellation_requests cr WHERE cr.booking_id = b.id ORDER BY cr.id DESC LIMIT 1) AS cancel_status,
                    (SELECT COUNT(*) FROM damage_reports dr WHERE dr.booking_id = b.id AND dr.status = \'unsettled\') AS unsettled_damage
             FROM bookings b
             LEFT JOIN booking_items bi ON bi.booking_id = b.id
             WHERE b.user_id = ? OR (b.user_id IS NULL AND b.customer_email = ?)
             GROUP BY b.id
             ORDER BY b.created_at DESC',
            [$userId, mb_strtolower($email)],
        );
    }

    /** Booking terbaru untuk dashboard staff. */
    public static function latest(int $limit = 10): array
    {
        return Database::select(
            'SELECT b.*, COALESCE(SUM(bi.quantity), 0) AS unit_count
             FROM bookings b
             LEFT JOIN booking_items bi ON bi.booking_id = b.id
             GROUP BY b.id
             ORDER BY b.created_at DESC
             LIMIT ?',
            [$limit],
        );
    }

    /** Apakah booking ini milik pelanggan yang sedang login? */
    public static function belongsTo(array $booking, array $user): bool
    {
        if ($booking['user_id'] !== null) {
            return (int) $booking['user_id'] === (int) $user['id'];
        }
        return mb_strtolower($booking['customer_email']) === mb_strtolower($user['email']);
    }

    /** Cocokkan kontak (email atau nomor HP) untuk fitur cek status booking. */
    public static function contactMatches(array $booking, string $contact): bool
    {
        $contact = trim($contact);
        if (str_contains($contact, '@')) {
            return hash_equals(mb_strtolower($booking['customer_email']), mb_strtolower($contact));
        }
        $phone = normalize_phone($contact);
        return $phone !== '' && hash_equals(normalize_phone($booking['customer_phone']), $phone);
    }
}
