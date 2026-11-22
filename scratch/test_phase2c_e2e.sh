#!/usr/bin/env bash
# =====================================================================
# Pengujian Terarah E2E Tahap 2C: Controller & Routing Pembayaran
# =====================================================================
set -u
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export ROOT="$DIR"
export PHP_BIN="${PHP_BIN:-/d/xampp/php/php.exe}"
export MYSQL_BIN="${MYSQL_BIN:-/d/xampp/mysql/bin/mysql.exe}"
export TEST_DB="${TEST_DB:-campgear_test}"
PORT="8003"
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

echo "=== Memulai Uji E2E Tahap 2C: Controller & Routing Pembayaran ==="

# 1. Login user dan staff jar
login "$TMP/user.jar" user@demo.test 'User#2026'
login "$TMP/staff.jar" staff@demo.test 'Staff#2026'

# Buat file gambar dummy valid
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$TMP/proof.png"

# 2. Buat booking tamu A
CODE_A="$(book "$TMP/guestA.jar" "$(d 10)" "$(d 12)" --data "items[2]=2")"
check "Booking A dibuat" 1 "$([[ -n "$CODE_A" && "$CODE_A" =~ ^CG[0-9]{6}-[A-HJ-NP-Z2-9]{4}$ ]] && echo 1 || echo 0)"
echo "    Kode A: $CODE_A"

# 3. Akses bukti sebelum upload -> 404
check "Akses bukti yang belum ada -> 404" 404 "$(status -b "$TMP/guestA.jar" "$BASE/booking/$CODE_A/bukti/1")"

# 4. Upload bukti transfer sebagai pemilik booking (Guest A)
t="$(tok "$TMP/guestA.jar" "/booking/$CODE_A")"
curl -s -b "$TMP/guestA.jar" -c "$TMP/guestA.jar" -o "$TMP/upload_res.html" -w '%{http_code}' \
  --form-string "_token=$t" \
  --form-string "method=transfer" \
  -F "proof_image=@$(winpath "$TMP/proof.png");type=image/png" \
  "$BASE/booking/$CODE_A/bayar" > "$TMP/code.txt"
check "Upload bukti transfer berhasil redirect" 302 "$(<"$TMP/code.txt")"

PAYMENT_ID="$($MYSQL "SELECT id FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A') AND purpose='rental' AND status='pending'")"
check "Payment pending terbuat di DB" 1 "$([[ -n "$PAYMENT_ID" && "$PAYMENT_ID" -gt 0 ]] && echo 1 || echo 0)"
echo "    Payment ID: $PAYMENT_ID"

# 5. Otorisasi akses bukti privat
check "Tamu tanpa sesi ditolak 403 akses bukti" 403 "$(status "$BASE/booking/$CODE_A/bukti/$PAYMENT_ID")"
login "$TMP/user_lain.jar" user2@demo.test 'User#2026' 2>/dev/null || true
check "User lain ditolak 403 akses bukti" 403 "$(status -b "$TMP/user.jar" "$BASE/booking/$CODE_A/bukti/$PAYMENT_ID")"

# 6. Pemilik booking mengakses bukti -> 200 + Content-Type image/png + nosniff
curl -s -b "$TMP/guestA.jar" -I "$BASE/booking/$CODE_A/bukti/$PAYMENT_ID" -o "$TMP/headers.txt"
check "Pemilik booking dapat melihat bukti" 200 "$(status -b "$TMP/guestA.jar" "$BASE/booking/$CODE_A/bukti/$PAYMENT_ID")"
contains "Header Content-Type image/png" "image/png" "$TMP/headers.txt"
contains "Header nosniff terpasang" "nosniff" "$TMP/headers.txt"

# 7. Staff mengakses bukti melalui route staff -> 200
check "Staff dapat melihat bukti privat" 200 "$(status -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A/bukti/$PAYMENT_ID")"

# 8. Staff menolak bukti pembayaran
# 8a. Tanpa note -> ditolak (status tetap pending)
t="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t" \
  --data-urlencode "note=   " \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID/tolak"
check "Tolak tanpa alasan: status tetap pending" "pending" "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_ID")"

# 8b. Dengan note -> status menjadi failed, booking tetap pending_payment
t="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t" \
  --data-urlencode "note=Foto buram tidak jelas" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID/tolak"
check "Tolak dengan alasan: payment status=failed" "failed" "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_ID")"
check "Tolak dengan alasan: catatan tersimpan" "Foto buram tidak jelas" "$($MYSQL "SELECT note FROM payments WHERE id=$PAYMENT_ID")"
check "Booking tetap pending_payment (karena sebelum deadline)" "pending_payment" "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"
check "AuditLog tercatat untuk penolakan" 1 "$($MYSQL "SELECT COUNT(*) FROM audit_logs WHERE action='booking.payment_rejected'")"

# 9. Pemilik upload ulang bukti baru (metode ewallet)
t="$(tok "$TMP/guestA.jar" "/booking/$CODE_A")"
curl -s -b "$TMP/guestA.jar" -c "$TMP/guestA.jar" -o /dev/null \
  --form-string "_token=$t" \
  --form-string "method=ewallet" \
  -F "proof_image=@$(winpath "$TMP/proof.png");type=image/png" \
  "$BASE/booking/$CODE_A/bayar"

PAYMENT_ID_2="$($MYSQL "SELECT id FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A') AND purpose='rental' AND status='pending'")"
check "Payment pending baru terbuat setelah penolakan" 1 "$([[ -n "$PAYMENT_ID_2" && "$PAYMENT_ID_2" -gt "$PAYMENT_ID" ]] && echo 1 || echo 0)"
check "Riwayat payment lama tetap ada (total 2)" 2 "$($MYSQL "SELECT COUNT(*) FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A')")"

# 10. Staff menyetujui bukti pembayaran baru
t="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID_2/setujui"
check "Persetujuan staf: payment status=paid" "paid" "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_ID_2")"
check "Persetujuan staf: booking status=confirmed" "confirmed" "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"
check "AuditLog tercatat untuk approval" 1 "$($MYSQL "SELECT COUNT(*) FROM audit_logs WHERE action='booking.payment_approved'")"

# 11. Staf melakukan pembayaran tunai pada booking lain
CODE_B="$(book "$TMP/guestB.jar" "$(d 15)" "$(d 16)" --data "items[2]=1")"
check "Booking B dibuat" 1 "$([[ -n "$CODE_B" && "$CODE_B" =~ ^CG[0-9]{6}-[A-HJ-NP-Z2-9]{4}$ ]] && echo 1 || echo 0)"
t="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_B")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t" \
  "$BASE/staff/booking/$CODE_B/bayar"
check "Pembayaran tunai staf: booking status=confirmed" "confirmed" "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_B'")"
check "Pembayaran tunai staf: payment method=cash status=paid" "cash paid" "$($MYSQL "SELECT method, status FROM payments WHERE booking_id=(SELECT id FROM bookings WHERE code='$CODE_B')" | tr '\t' ' ')"

summary
