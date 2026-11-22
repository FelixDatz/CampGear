#!/usr/bin/env bash
# =====================================================================
# Pengujian Terarah Tahap 2D-A: Customer Payment UI
# =====================================================================
set -u
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export ROOT="$DIR"
export PHP_BIN="${PHP_BIN:-/d/xampp/php/php.exe}"
export MYSQL_BIN="${MYSQL_BIN:-/d/xampp/mysql/bin/mysql.exe}"
export TEST_DB="${TEST_DB:-campgear_test}"
PORT="8004"
export BASE="http://127.0.0.1:$PORT"

wp() { if command -v cygpath >/dev/null; then cygpath -w "$1"; else echo "$1"; fi; }

WORK="$(mktemp -d)"
CONFIG="$WORK/config.testing.php"
"$PHP_BIN" -r '$c = require $argv[1]; $c["db"]["name"] = $argv[2]; $c["app"]["debug"] = true;
  file_put_contents($argv[3], "<?php\nreturn " . var_export($c, true) . ";\n");' \
  "$(wp "$ROOT/config/config.php")" "$TEST_DB" "$(wp "$CONFIG")"
export CAMPGEAR_CONFIG="$(wp "$CONFIG")"

reset_db() { "$PHP_BIN" "$ROOT/database/setup.php" --fresh >/dev/null; }
reset_db

"$PHP_BIN" -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/index.php" >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null; rm -rf "$WORK"' EXIT

for _ in $(seq 1 20); do curl -s -o /dev/null "$BASE/" && break; sleep 0.5; done

source "$ROOT/tests/lib.sh"

echo "=== Memulai Uji Terarah Tahap 2D-A: Customer Payment UI ==="

# Dummy bukti bayar
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$TMP/proof1.png"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' | base64 -d > "$TMP/proof2.png"

# Setup akun staff
login "$TMP/staff.jar" staff@demo.test 'Staff#2026'

# ---------------------------------------------------------------------
# 1. Booking Baru: Kondisi A (Menunggu Pembayaran & Form Upload Aktif)
# ---------------------------------------------------------------------
echo "--- 1. Tampilan Booking Baru (Pending Payment, Belum Upload) ---"
CODE_A="$(book "$TMP/gA.jar" "$(d 5)" "$(d 7)" --data "items[1]=2")"
echo "    Booking A: $CODE_A"

curl -s -b "$TMP/gA.jar" "$BASE/booking/$CODE_A" -o "$TMP/page_a1.html"

contains "Judul status: Menunggu Pembayaran" "Menunggu Pembayaran" "$TMP/page_a1.html"
contains "Countdown sisa waktu bayar" "data-countdown=" "$TMP/page_a1.html"
contains "Form upload ber-enctype multipart" 'enctype="multipart/form-data"' "$TMP/page_a1.html"
contains "Form mengarah ke endpoint bayar" "action=\"/booking/$CODE_A/bayar\"" "$TMP/page_a1.html"
contains "Opsi Transfer bank" 'Transfer bank' "$TMP/page_a1.html"
contains "Opsi E-wallet" 'E-wallet' "$TMP/page_a1.html"
contains "Field input file proof_image" 'name="proof_image"' "$TMP/page_a1.html"
contains "Accept gambar valid" 'accept="image/jpeg,image/png,image/webp"' "$TMP/page_a1.html"
contains "Tombol Kirim Bukti Pembayaran" "Kirim Bukti Pembayaran" "$TMP/page_a1.html"
contains "Petunjuk pembayaran tampil" "Petunjuk Pembayaran" "$TMP/page_a1.html"
contains "Petunjuk memuat kode booking" "$CODE_A" "$TMP/page_a1.html"
lacks "Bukan lagi tombol simulasi lama" "Bayar Sekarang" "$TMP/page_a1.html"
lacks "Bukan teks simulasi sandbox" "Ini simulasi pembayaran (sandbox)" "$TMP/page_a1.html"

# ---------------------------------------------------------------------
# 2. Upload Bukti: Kondisi B (Menunggu Verifikasi Staf)
# ---------------------------------------------------------------------
echo "--- 2. Tampilan Setelah Upload Bukti (Menunggu Verifikasi) ---"
t="$(token "$TMP/page_a1.html")"
curl -s -b "$TMP/gA.jar" -c "$TMP/gA.jar" -o /dev/null \
  --form-string "_token=$t" \
  --form-string "method=transfer" \
  -F "proof_image=@$(winpath "$TMP/proof1.png");type=image/png" \
  "$BASE/booking/$CODE_A/bayar"

curl -s -b "$TMP/gA.jar" "$BASE/booking/$CODE_A" -o "$TMP/page_a2.html"

contains "Banner Menunggu Verifikasi Staf" "Menunggu Verifikasi Staf" "$TMP/page_a2.html"
contains "Keterangan stok aman tertahan" "Stok alat tetap aman selama menunggu verifikasi" "$TMP/page_a2.html"
contains "Tombol Lihat Bukti Pembayaran" "Lihat Bukti Pembayaran" "$TMP/page_a2.html"

PAYMENT_A1="$($MYSQL "SELECT id FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A') AND purpose='rental' AND status='pending'")"
contains "Tautan privat bukti ID $PAYMENT_A1" "/booking/$CODE_A/bukti/$PAYMENT_A1" "$TMP/page_a2.html"
check "Akses bukti privat mengembalikan 200" 200 "$(status -b "$TMP/gA.jar" "$BASE/booking/$CODE_A/bukti/$PAYMENT_A1")"
check "Header X-Content-Type-Options nosniff" "nosniff" "$(curl -s -I -b "$TMP/gA.jar" "$BASE/booking/$CODE_A/bukti/$PAYMENT_A1" | tr -d '\r' | grep -i '^x-content-type-options:' | awk '{print $2}')"

lacks "Form upload baru disembunyikan saat ada pending" 'name="proof_image"' "$TMP/page_a2.html"
contains "Status payment di ringkasan: Menunggu Verifikasi" "Menunggu Verifikasi" "$TMP/page_a2.html"

# ---------------------------------------------------------------------
# 3. Penolakan Staf Sebelum Deadline: Kondisi C (Opsi Upload Ulang)
# ---------------------------------------------------------------------
echo "--- 3. Tampilan Setelah Bukti Ditolak Staf (Upload Ulang Tersedia) ---"
t_staff="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t_staff" \
  --data-urlencode "note=Foto buram dan struk terpotong" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_A1/tolak"

check "Status payment menjadi failed" failed "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_A1")"
check "Status booking tetap pending_payment" pending_payment "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"

curl -s -b "$TMP/gA.jar" "$BASE/booking/$CODE_A" -o "$TMP/page_a3.html"

contains "Alert Bukti Pembayaran Ditolak" "Bukti Pembayaran Ditolak" "$TMP/page_a3.html"
contains "Alasan penolakan dari staf tampil" "Foto buram dan struk terpotong" "$TMP/page_a3.html"
contains "Tautan ke bukti yang ditolak" "/booking/$CODE_A/bukti/$PAYMENT_A1" "$TMP/page_a3.html"
contains "Keterangan opsi upload ulang" "mengunggah bukti pembayaran pengganti" "$TMP/page_a3.html"
contains "Form upload aktif kembali" 'name="proof_image"' "$TMP/page_a3.html"
contains "Tombol kirim bukti aktif kembali" "Kirim Bukti Pembayaran" "$TMP/page_a3.html"
contains "Status payment ditolak tercatat di ringkasan" "Ditolak" "$TMP/page_a3.html"
contains "Catatan alasan di ringkasan" "Alasan: Foto buram dan struk terpotong" "$TMP/page_a3.html"

# ---------------------------------------------------------------------
# 4. Upload Ulang Bukti Pengganti: Kondisi D
# ---------------------------------------------------------------------
echo "--- 4. Upload Ulang Bukti Pengganti ---"
t="$(token "$TMP/page_a3.html")"
curl -s -b "$TMP/gA.jar" -c "$TMP/gA.jar" -o /dev/null \
  --form-string "_token=$t" \
  --form-string "method=ewallet" \
  -F "proof_image=@$(winpath "$TMP/proof2.png");type=image/png" \
  "$BASE/booking/$CODE_A/bayar"

PAYMENT_A2="$($MYSQL "SELECT id FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A') AND purpose='rental' AND status='pending'")"
check "Payment kedua tercatat pending" 1 "$([[ -n "$PAYMENT_A2" && "$PAYMENT_A2" != "$PAYMENT_A1" ]] && echo 1 || echo 0)"

curl -s -b "$TMP/gA.jar" "$BASE/booking/$CODE_A" -o "$TMP/page_a4.html"
contains "Kembali ke status Menunggu Verifikasi Staf" "Menunggu Verifikasi Staf" "$TMP/page_a4.html"
contains "Tautan privat bukti baru ID $PAYMENT_A2" "/booking/$CODE_A/bukti/$PAYMENT_A2" "$TMP/page_a4.html"
lacks "Form upload kembali disembunyikan" 'name="proof_image"' "$TMP/page_a4.html"

# ---------------------------------------------------------------------
# 5. Persetujuan Staf: Kondisi E (Booking Confirmed)
# ---------------------------------------------------------------------
echo "--- 5. Persetujuan Pembayaran oleh Staf ---"
t_staff="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t_staff" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_A2/setujui"

check "Booking confirmed" confirmed "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"
check "Payment kedua status paid" paid "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_A2")"

curl -s -b "$TMP/gA.jar" "$BASE/booking/$CODE_A" -o "$TMP/page_a5.html"
contains "Status berubah Terkonfirmasi" "Terkonfirmasi" "$TMP/page_a5.html"
contains "Kartu Pengambilan Alat tampil" "Kartu Pengambilan Alat" "$TMP/page_a5.html"
contains "Checklist serah terima tampil" "Checklist serah terima" "$TMP/page_a5.html"
contains "QR code booking tampil" "data-qr=\"$CODE_A\"" "$TMP/page_a5.html"
lacks "Tiket tidak terkunci" "ticket is-locked" "$TMP/page_a5.html"
contains "Badge Lunas di ringkasan" "Lunas" "$TMP/page_a5.html"

# ---------------------------------------------------------------------
# 6. Kedaluwarsa Karena Waktu Habis: Kondisi F
# ---------------------------------------------------------------------
echo "--- 6. Kondisi Batas Waktu Habis (Expired) ---"
CODE_B="$(book "$TMP/gB.jar" "$(d 20)" "$(d 22)" --data "items[2]=1")"
echo "    Booking B: $CODE_B"

# Mundurkan payment_due_at melewati waktu sekarang
$MYSQL "UPDATE bookings SET payment_due_at = NOW() - INTERVAL 10 MINUTE WHERE code='$CODE_B'"

curl -s -b "$TMP/gB.jar" "$BASE/booking/$CODE_B" -o "$TMP/page_b.html"
contains "Booking status berubah Expired" "Kedaluwarsa" "$TMP/page_b.html"
contains "Keterangan kedaluwarsa" "kedaluwarsa karena tidak dibayar" "$TMP/page_b.html"
lacks "Form upload tidak tampil saat expired" 'name="proof_image"' "$TMP/page_b.html"

summary
