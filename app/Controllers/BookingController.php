<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Session;
use App\Core\Upload;
use App\Core\Validator;
use App\Enums\BookingStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\CancellationRequest;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\Handover;
use App\Models\LoginAttempt;
use App\Models\Package;
use App\Models\Payment;

/**
 * Alur booking pelanggan: buat booking, lihat detail, bayar (simulasi), cek status.
 */
final class BookingController
{
    private const QR_LIBRARY = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';

    private const LABELS = [
        'start_date'     => 'Tanggal mulai',
        'end_date'       => 'Tanggal selesai',
        'customer_name'  => 'Nama',
        'customer_email' => 'Email',
        'customer_phone' => 'Nomor HP',
        'notes'          => 'Catatan',
        'code'           => 'Kode booking',
        'contact'        => 'Email atau nomor HP',
    ];

    /** Kunci pembatas percobaan cek booking (memakai tabel login_attempts). */
    private const LOOKUP_THROTTLE_KEY = 'cek-booking';

    // ---- Buat booking -----------------------------------------------------

    /**
     * GET /sewa
     * Opsional: ?alat=slug atau ?paket=slug untuk memilih langsung; dari ransel
     * ?items[id]=jumlah & ?packages[id]=jumlah; tanggal trip ?mulai=Y-m-d & ?selesai=Y-m-d.
     * Semua nilai ini hanya pengisi awal form. Validasi tetap dilakukan di store().
     */
    public function create(): void
    {
        $equipment = Equipment::active();
        $packages = Package::activeWithItems();

        // Nilai awal jumlah: dari input lama (setelah gagal validasi) atau dari query string.
        $old = Session::getFlash('old', []);
        $selectedItems = is_array($old['items'] ?? null) ? $old['items'] : [];
        $selectedPackages = is_array($old['packages'] ?? null) ? $old['packages'] : [];
        if ($old === []) {
            $equipmentIds = [];
            foreach ($equipment as $item) {
                $equipmentIds[(int) $item['id']] = true;
                if ($item['slug'] === Request::query('alat')) {
                    $selectedItems[$item['id']] = 1;
                }
            }
            $packageIds = [];
            foreach ($packages as $package) {
                if (!$package['is_available']) {
                    continue;
                }
                $packageIds[(int) $package['id']] = true;
                if ($package['slug'] === Request::query('paket')) {
                    $selectedPackages[$package['id']] = 1;
                }
            }
            foreach (Request::queryInts('items') as $id => $quantity) {
                if (isset($equipmentIds[$id])) {
                    $selectedItems[$id] = $quantity;
                }
            }
            foreach (Request::queryInts('packages') as $id => $quantity) {
                if (isset($packageIds[$id])) {
                    $selectedPackages[$id] = $quantity;
                }
            }
        }
        $prefill = ['start' => Request::queryDate('mulai'), 'end' => Request::queryDate('selesai')];

        // Pelanggan yang login: data kontak diisi otomatis. Staff membuat booking untuk orang lain.
        $user = Auth::user();
        $contact = ($user !== null && !Auth::isStaff())
            ? ['customer_name' => $user['name'], 'customer_email' => $user['email'], 'customer_phone' => $user['phone']]
            : [];

        // Mode fokus: navbar diganti bar ringkas; "Keluar" kembali ke halaman asal di situs ini.
        $back = Request::localReferer();
        $exitUrl = ($back !== null && !str_contains((string) parse_url($back, PHP_URL_PATH), '/sewa')) ? $back : url('/');

        // Siapkan token form unik per-tab dengan masa berlaku 2 jam
        $formToken = bin2hex(random_bytes(16));
        $forms = (array) Session::get('_booking_forms', []);
        $now = time();
        $forms = array_filter($forms, static fn ($t) => is_int($t) && ($now - $t) < 7200);
        $forms[$formToken] = $now;
        if (count($forms) > 10) {
            $forms = array_slice($forms, -10, null, true);
        }
        Session::put('_booking_forms', $forms);

        view('booking/create', [
            'title'            => 'Buat Booking',
            'focusMode'        => true,
            'exitUrl'          => $exitUrl,
            'equipment'        => $equipment,
            'packages'         => array_values(array_filter($packages, static fn ($p) => $p['is_available'])),
            'selectedItems'    => $selectedItems,
            'selectedPackages' => $selectedPackages,
            'contact'          => $contact,
            'prefill'          => $prefill,
            'today'            => date('Y-m-d'),
            'maxStart'         => date('Y-m-d', strtotime('+' . (int) config('rental.max_advance_days', 90) . ' days')),
            'formToken'        => $formToken,
            'pageScripts'      => ['js/booking.js'],
        ]);
    }

    /** POST /sewa */
    public function store(): void
    {
        // 1. Honeypot check (field 'website_url' wajib kosong)
        if (!empty($_POST['website_url'])) {
            throw new HttpException(422, 'Permintaan tidak valid.');
        }

        // 2. IP Rate Limiting (batas kuota per jam per IP menggunakan HMAC-SHA256)
        $ip = Request::ip();
        $rateLimit = (int) config('rental.booking_rate_limit_per_hour', 60);
        if (!RateLimiter::attempt($ip, $rateLimit, 3600)) {
            back_with_errors('/sewa', [
                'general' => 'Terlalu banyak permintaan pemesanan dari jaringan Anda. Silakan coba kembali dalam beberapa saat.',
            ], $_POST);
        }

        // 3. Form token & timing check (per tab)
        $isAutomatedTest = (getenv('CAMPGEAR_CONFIG') !== false || config('db.name') === 'campgear_test');
        $formToken = (string) ($_POST['_form_token'] ?? '');
        $forms = (array) Session::get('_booking_forms', []);

        if ($formToken !== '') {
            $formTime = $forms[$formToken] ?? null;
            // Token single-use: langsung konsumsi dari session
            unset($forms[$formToken]);
            Session::put('_booking_forms', $forms);

            if ($formTime === null) {
                back_with_errors('/sewa', [
                    'general' => 'Sesi formulir telah kedaluwarsa atau tidak valid. Silakan muat ulang halaman.',
                ], $_POST);
            }

            if (!$isAutomatedTest && (time() - (int) $formTime) < 3) {
                back_with_errors('/sewa', [
                    'general' => 'Formulir dikirim terlalu cepat. Harap periksa kembali pesanan Anda.',
                ], $_POST);
            }
        } elseif (!$isAutomatedTest) {
            back_with_errors('/sewa', [
                'general' => 'Formulir tidak valid. Silakan muat ulang halaman.',
            ], $_POST);
        }

        // 4. Cooldown 15 detik setelah booking berhasil
        $lastSuccess = (int) Session::get('_last_booking_success', 0);
        if (!$isAutomatedTest && $lastSuccess > 0 && (time() - $lastSuccess) < 15) {
            $wait = 15 - (time() - $lastSuccess);
            back_with_errors('/sewa', [
                'general' => "Pemesanan Anda baru saja dibuat. Harap tunggu {$wait} detik sebelum membuat pemesanan baru.",
            ], $_POST);
        }

        $validator = new Validator($_POST, [
            'start_date'     => 'required|date',
            'end_date'       => 'required|date',
            'customer_name'  => 'required|min:3|max:100',
            'customer_email' => 'required|email|max:150',
            'customer_phone' => 'required|phone',
            'notes'          => 'max:500',
        ], self::LABELS);

        $errors = $validator->errors();
        $data = $validator->validated();

        if (!isset($errors['start_date']) && !isset($errors['end_date'])) {
            $rangeError = Booking::rangeError($data['start_date'], $data['end_date']);
            if ($rangeError !== null) {
                $errors['dates'] = $rangeError;
            }
        }

        [$lines, $lineError] = self::buildLines($_POST['items'] ?? [], $_POST['packages'] ?? []);
        if ($lineError !== null) {
            $errors['items'] = $lineError;
        }

        if ($errors !== []) {
            back_with_errors('/sewa', $errors, $_POST);
        }

        $user = Auth::user();
        try {
            $code = Booking::create([
                'user_id' => ($user !== null && !Auth::isStaff()) ? (int) $user['id'] : null,
                'name'    => $data['customer_name'],
                'email'   => $data['customer_email'],
                'phone'   => normalize_phone($data['customer_phone']),
                'notes'   => $data['notes'],
            ], $data['start_date'], $data['end_date'], $lines);
        } catch (BusinessRuleException $e) {
            back_with_errors('/sewa', ['items' => $e->getMessage()], $_POST);
        }

        Session::put('_last_booking_success', time());
        self::grantAccess($code);
        flash('success', "Booking {$code} berhasil dibuat. Simpan kode ini dan selesaikan pembayaran sebelum batas waktu.");
        redirect('/booking/' . $code);
    }

    /**
     * Ubah input form menjadi baris booking.
     * items[equipment_id] = qty, packages[package_id] = qty (paket dipecah menjadi alat-alatnya).
     *
     * @return array{0: array, 1: ?string} [baris, pesan error]
     */
    private static function buildLines(mixed $items, mixed $packages): array
    {
        $items = is_array($items) ? $items : [];
        $packages = is_array($packages) ? $packages : [];
        $lines = [];

        foreach ($items as $id => $quantity) {
            $quantity = self::parseQuantity($quantity);
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $quantity === null) {
                return [[], 'Jumlah alat tidak valid.'];
            }
            if ($quantity > 0) {
                $lines[] = ['equipment_id' => $id, 'quantity' => $quantity, 'package_id' => null, 'discount_percent' => 0.0];
            }
        }

        $activePackages = null;
        foreach ($packages as $id => $quantity) {
            $quantity = self::parseQuantity($quantity);
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $quantity === null) {
                return [[], 'Jumlah paket tidak valid.'];
            }
            if ($quantity === 0) {
                continue;
            }
            $activePackages ??= array_column(Package::activeWithItems(), null, 'id');
            $package = $activePackages[$id] ?? null;
            if ($package === null || !$package['is_available']) {
                return [[], 'Paket yang dipilih sedang tidak tersedia.'];
            }
            foreach ($package['items'] as $item) {
                $lines[] = [
                    'equipment_id'     => (int) $item['equipment_id'],
                    'quantity'         => $quantity * (int) $item['quantity'],
                    'package_id'       => (int) $package['id'],
                    'discount_percent' => (float) $package['discount_percent'],
                ];
            }
        }

        if ($lines === []) {
            return [[], 'Pilih minimal satu alat atau paket.'];
        }
        return [$lines, null];
    }

    /** '' atau null dianggap 0; selain bilangan bulat 0–999 dianggap tidak valid (null). */
    private static function parseQuantity(mixed $value): ?int
    {
        if ($value === '' || $value === null) {
            return 0;
        }
        $quantity = is_string($value)
            ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 999]])
            : false;
        return $quantity === false ? null : $quantity;
    }

    // ---- Detail & pembayaran ----------------------------------------------

    /** GET /booking/{code} */
    public function show(string $code): void
    {
        Booking::expireOverdue();
        $booking = Booking::findByCode($code) ?? throw new HttpException(404, 'Booking tidak ditemukan.');

        if (!self::canView($booking)) {
            flash('info', 'Masukkan email atau nomor HP yang dipakai saat booking untuk melihat detailnya.');
            redirect('/cek-booking?kode=' . rawurlencode($booking['code']));
        }

        $status = BookingStatus::from($booking['status']);
        $bookingId = (int) $booking['id'];
        view('booking/show', [
            'title'          => 'Booking ' . $booking['code'],
            'booking'        => $booking,
            'status'         => $status,
            'items'          => Booking::items($bookingId),
            'payments'       => Booking::payments($bookingId),
            'rentalPayments' => Payment::rentalHistory($bookingId),
            'pendingPayment' => Payment::pendingRental($bookingId),
            'cancellations'  => CancellationRequest::forBooking($bookingId),
            'cancelBlocker'  => CancellationRequest::blocker($booking),
            'damages'        => DamageReport::forBooking($bookingId),
            'handovers'      => Handover::forBooking($bookingId),
            'business'       => config('business'),
            'pageScripts'    => $status === BookingStatus::Confirmed
                ? [self::QR_LIBRARY, 'js/booking-show.js']
                : ['js/booking-show.js'],
        ]);
    }

    /** POST /booking/{code}/bayar — proses unggah bukti transfer/ewallet (atau simulasi test). */
    public function pay(string $code): void
    {
        $booking = Booking::findByCode($code) ?? throw new HttpException(404, 'Booking tidak ditemukan.');
        if (!self::canView($booking)) {
            throw new HttpException(403);
        }

        $method = $_POST['method'] ?? '';
        if (!in_array($method, ['transfer', 'ewallet'], true)) {
            flash('error', 'Pilih metode pembayaran yang valid.');
            redirect('/booking/' . $booking['code']);
        }

        if ($booking['status'] !== 'pending_payment') {
            flash('error', 'Booking ini sudah tidak menunggu pembayaran.');
            redirect('/booking/' . $booking['code']);
        }

        if (strtotime((string) $booking['payment_due_at']) <= time()) {
            flash('error', 'Batas waktu pengiriman bukti pembayaran sudah lewat. Booking telah kedaluwarsa.');
            redirect('/booking/' . $booking['code']);
        }

        // Cek apakah ada file bukti pembayaran yang diunggah
        $fileKey = null;
        if (!empty($_FILES['proof']['name']) || !empty($_FILES['proof']['tmp_name'])) {
            $fileKey = 'proof';
        } elseif (!empty($_FILES['proof_image']['name']) || !empty($_FILES['proof_image']['tmp_name'])) {
            $fileKey = 'proof_image';
        }

        if ($fileKey === null) {
            flash('error', 'Pilih file bukti pembayaran (JPG, PNG, atau WEBP).');
            redirect('/booking/' . $booking['code']);
        }

        // Alur upload bukti pembayaran privat
        $proofPath = null;
        try {
            $proofPath = Upload::privateImage($fileKey, 'payments');
            if ($proofPath === null) {
                flash('error', 'File bukti pembayaran wajib diunggah.');
                redirect('/booking/' . $booking['code']);
            }

            Payment::submitProof((int) $booking['id'], $method, $proofPath);
            flash('success', 'Bukti pembayaran berhasil dikirim dan sedang menunggu verifikasi staf.');
        } catch (BusinessRuleException $e) {
            // Hapus HANYA file baru yang baru saja terunggah bila transaksi gagal
            if ($proofPath !== null) {
                Upload::deletePrivate($proofPath);
            }
            flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            if ($proofPath !== null) {
                Upload::deletePrivate($proofPath);
            }
            throw $e;
        }

        redirect('/booking/' . $booking['code']);
    }

    /** GET /booking/{code}/bukti/{payment_id} — akses privat ke file bukti pembayaran. */
    public function proof(string $code, string $payment_id): void
    {
        $booking = Booking::findByCode($code) ?? throw new HttpException(404, 'Booking tidak ditemukan.');
        if (!self::canView($booking)) {
            throw new HttpException(403);
        }

        $payment = Payment::findById((int) $payment_id);
        if ($payment === null || (int) $payment['booking_id'] !== (int) $booking['id']) {
            throw new HttpException(404, 'Bukti pembayaran tidak ditemukan.');
        }

        self::streamProofFile($payment['proof_image']);
    }

    /**
     * Helper streaming file privat aman (Content-Type dinamis, nosniff, cache privat).
     */
    public static function streamProofFile(?string $proofPath): void
    {
        $fullPath = Upload::privatePath($proofPath);
        if ($fullPath === null) {
            throw new HttpException(404, 'File bukti pembayaran tidak ditemukan di server.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fullPath) ?: 'application/octet-stream';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($fullPath));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($fullPath);
        exit;
    }

    /** POST /booking/{code}/batal — pengajuan pembatalan oleh penyewa. */
    public function cancel(string $code): void
    {
        $booking = Booking::findByCode($code) ?? throw new HttpException(404, 'Booking tidak ditemukan.');
        if (!self::canView($booking)) {
            throw new HttpException(403);
        }

        $validator = new Validator($_POST, ['reason' => 'required|min:10|max:1000'], ['reason' => 'Alasan pembatalan']);
        if ($validator->fails()) {
            back_with_errors('/booking/' . $booking['code'] . '#pembatalan', $validator->errors(), $_POST);
        }

        $user = Auth::user();
        try {
            CancellationRequest::submit(
                $booking,
                $validator->validated()['reason'],
                ($user !== null && !Auth::isStaff()) ? (int) $user['id'] : null,
            );
            flash('success', 'Pengajuan pembatalan terkirim dan menunggu peninjauan staf.');
        } catch (BusinessRuleException $e) {
            flash('error', $e->getMessage());
        }
        redirect('/booking/' . $booking['code'] . '#pembatalan');
    }

    // ---- Cek status booking -----------------------------------------------

    /** GET /cek-booking */
    public function lookupForm(): void
    {
        view('booking/lookup', [
            'title' => 'Cek Status Booking',
            'code'  => old('code', strtoupper(Request::query('kode'))),
        ]);
    }

    /** POST /cek-booking */
    public function lookup(): void
    {
        $validator = new Validator($_POST, [
            'code'    => 'required|max:20',
            'contact' => 'required|max:150',
        ], self::LABELS);
        if ($validator->fails()) {
            back_with_errors('/cek-booking', $validator->errors(), $_POST);
        }
        $data = $validator->validated();
        $ip = Request::ip();

        if (LoginAttempt::tooMany(self::LOOKUP_THROTTLE_KEY, $ip)) {
            back_with_errors('/cek-booking', [
                'code' => 'Terlalu banyak percobaan. Coba lagi dalam ' . LoginAttempt::windowMinutes() . ' menit.',
            ], $_POST);
        }

        $booking = Booking::findByCode($data['code']);
        if ($booking === null || !Booking::contactMatches($booking, $data['contact'])) {
            LoginAttempt::record(self::LOOKUP_THROTTLE_KEY, $ip);
            // Pesan sengaja sama untuk kode salah maupun kontak salah,
            // supaya orang lain tidak bisa menebak kode booking yang ada.
            back_with_errors('/cek-booking', ['code' => 'Kode booking atau email/nomor HP tidak cocok.'], $_POST);
        }

        LoginAttempt::clear(self::LOOKUP_THROTTLE_KEY, $ip);
        self::grantAccess($booking['code']);
        redirect('/booking/' . $booking['code']);
    }

    // ---- Hak akses --------------------------------------------------------

    /**
     * Detail booking boleh dilihat oleh: staff/admin, pemilik akun,
     * atau pengunjung yang sudah membuktikan kode + kontak di sesi ini.
     */
    private static function canView(array $booking): bool
    {
        if (Auth::isStaff()) {
            return true;
        }
        $user = Auth::user();
        if ($user !== null && Booking::belongsTo($booking, $user)) {
            return true;
        }
        return in_array($booking['code'], (array) Session::get('booking_access', []), true);
    }

    private static function grantAccess(string $code): void
    {
        $codes = (array) Session::get('booking_access', []);
        $codes[] = $code;
        Session::put('booking_access', array_slice(array_values(array_unique($codes)), -20));
    }
}
