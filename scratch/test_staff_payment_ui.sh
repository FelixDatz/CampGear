#!/usr/bin/env bash
# =====================================================================
# Pengujian Terarah Tahap 2D-B: Staff Payment Verification UI
# =====================================================================
set -u
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export ROOT="$DIR"
export PHP_BIN="${PHP_BIN:-/d/xampp/php/php.exe}"
export MYSQL_BIN="${MYSQL_BIN:-/d/xampp/mysql/bin/mysql.exe}"
export TEST_DB="${TEST_DB:-campgear_test}"
PORT="8005"
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

echo "=== Memulai Uji Terarah Tahap 2D-B: Staff Payment Verification UI ==="

# Dummy bukti bayar
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$TMP/proof1.png"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' | base64 -d > "$TMP/proof2.png"

# Setup sesi
login "$TMP/staff.jar" staff@demo.test 'Staff#2026'
login "$TMP/user.jar" user@demo.test 'User#2026'

# ---------------------------------------------------------------------
# 1. Hak Akses (Otorisasi)
# ---------------------------------------------------------------------
echo "--- 1. Uji Otorisasi Halaman & Bukti Privat Staf ---"
CODE_A="$(book "$TMP/gA.jar" "$(d 5)" "$(d 7)" --data "items[1]=2")"
echo "    Booking A: $CODE_A"

# Upload bukti transfer oleh tamu
t="$(tok "$TMP/gA.jar" "/booking/$CODE_A")"
curl -s -b "$TMP/gA.jar" -c "$TMP/gA.jar" -o /dev/null \
  --form-string "_token=$t" \
  --form-string "method=transfer" \
  -F "proof_image=@$(winpath "$TMP/proof1.png");type=image/png" \
  "$BASE/booking/$CODE_A/bayar"

PAYMENT_ID1="$($MYSQL "SELECT id FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A') AND purpose='rental' AND status='pending'")"

# Tamu & User biasa tidak boleh akses panel staf maupun endpoint bukti privat staf
check "Tamu akses /staff/booking/$CODE_A -> 302" 302 "$(status "$BASE/staff/booking/$CODE_A")"
check "User biasa akses /staff/booking/$CODE_A -> 403" 403 "$(status -b "$TMP/user.jar" "$BASE/staff/booking/$CODE_A")"
check "Tamu akses endpoint bukti staf -> 302" 302 "$(status "$BASE/staff/booking/$CODE_A/bukti/$PAYMENT_ID1")"
check "User biasa akses endpoint bukti staf -> 403" 403 "$(status -b "$TMP/user.jar" "$BASE/staff/booking/$CODE_A/bukti/$PAYMENT_ID1")"
check "Staf akses endpoint bukti staf -> 200" 200 "$(status -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A/bukti/$PAYMENT_ID1")"
check "Header bukti staf: nosniff" "nosniff" "$(curl -s -I -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A/bukti/$PAYMENT_ID1" | tr -d '\r' | grep -i '^x-content-type-options:' | awk '{print $2}')"

# ---------------------------------------------------------------------
# 2. Preview Bukti & UI Verifikasi Staf
# ---------------------------------------------------------------------
echo "--- 2. Tampilan Panel Verifikasi Pembayaran oleh Staf ---"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A" -o "$TMP/staff_detail.html"

contains "Judul card Verifikasi Bukti Pembayaran" "Verifikasi Bukti Pembayaran" "$TMP/staff_detail.html"
contains "Badge status Menunggu Verifikasi" "Menunggu Verifikasi" "$TMP/staff_detail.html"
contains "Preview gambar bukti privat ada" "src=\"/staff/booking/$CODE_A/bukti/$PAYMENT_ID1\"" "$TMP/staff_detail.html"
contains "Tautan buka ukuran penuh" "href=\"/staff/booking/$CODE_A/bukti/$PAYMENT_ID1\"" "$TMP/staff_detail.html"
contains "Metode pembayaran tampil" "Transfer bank" "$TMP/staff_detail.html"
contains "Nominal tampil" "Rp" "$TMP/staff_detail.html"
contains "Tombol Setujui Pembayaran" "Setujui Pembayaran" "$TMP/staff_detail.html"
contains "Konfirmasi sebelum setujui" "data-confirm=" "$TMP/staff_detail.html"
contains "Tombol Tolak Pembayaran" "Tolak Pembayaran" "$TMP/staff_detail.html"
contains "Modal Tolak Pembayaran ada" "id=\"rejectPaymentModal\"" "$TMP/staff_detail.html"
contains "Field alasan penolakan note required" 'name="note"' "$TMP/staff_detail.html"
contains "Card Riwayat Pembayaran Sewa tampil" "Riwayat Pembayaran Sewa" "$TMP/staff_detail.html"
contains "Opsi pembayaran tunai tetap tersedia" "Pembayaran tunai di lokasi" "$TMP/staff_detail.html"

# ---------------------------------------------------------------------
# 3. Penolakan Pembayaran (Wajib Alasan)
# ---------------------------------------------------------------------
echo "--- 3. Penolakan Bukti Pembayaran oleh Staf ---"
t_staff="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"

# Tolak tanpa alasan -> Ditolak oleh controller (kembali / error)
r_empty="$(curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$t_staff" \
  --data-urlencode "note=" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID1/tolak")"
check "Tolak tanpa alasan redirect" 302 "$r_empty"
check "Status payment tetap pending" pending "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_ID1")"

# Tolak dengan alasan sah
t_staff="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t_staff" \
  --data-urlencode "note=Struk tidak terbaca dan tanggal buram" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID1/tolak"

check "Status payment berubah failed" failed "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_ID1")"
check "Alasan penolakan tersimpan di database" "Struk tidak terbaca dan tanggal buram" "$($MYSQL "SELECT note FROM payments WHERE id=$PAYMENT_ID1")"
STAFF_ID="$($MYSQL "SELECT id FROM users WHERE email='staff@demo.test'")"
check "Staf peninjau tercatat handled_by" "$STAFF_ID" "$($MYSQL "SELECT handled_by FROM payments WHERE id=$PAYMENT_ID1")"
check "Status booking tetap pending_payment (karena belum deadline)" pending_payment "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"

# ---------------------------------------------------------------------
# 4. Tampilan Riwayat Setelah Ditolak & Upload Ulang
# ---------------------------------------------------------------------
echo "--- 4. Riwayat Bukti Lama Setelah Ditolak & Upload Ulang ---"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A" -o "$TMP/staff_after_reject.html"
contains "Riwayat menampilkan status Ditolak" "Ditolak" "$TMP/staff_after_reject.html"
contains "Riwayat memuat nama staf peninjau" "Staff CampGear" "$TMP/staff_after_reject.html"
contains "Riwayat memuat alasan penolakan" "Struk tidak terbaca dan tanggal buram" "$TMP/staff_after_reject.html"
contains "Tautan bukti privat lama tetap tersedia" "/staff/booking/$CODE_A/bukti/$PAYMENT_ID1" "$TMP/staff_after_reject.html"
check "Bukti lama yang ditolak tetap dapat diakses 200" 200 "$(status -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A/bukti/$PAYMENT_ID1")"

# Validasi waktu penolakan berasal dari audit log
AUDIT_REJECT_AT="$($MYSQL "SELECT created_at FROM audit_logs WHERE action='booking.payment_rejected' AND entity_id=(SELECT id FROM bookings WHERE code='$CODE_A') ORDER BY id DESC LIMIT 1")"
AUDIT_REJECT_FMT="$($PHP_BIN -r 'require "app/helpers.php"; echo tanggal($argv[1], false, true);' "$AUDIT_REJECT_AT")"
contains "Waktu penolakan dari audit log tampil di riwayat" "$AUDIT_REJECT_FMT" "$TMP/staff_after_reject.html"

CREATED_AT1="$($MYSQL "SELECT created_at FROM payments WHERE id=$PAYMENT_ID1")"
CREATED_FMT1="$($PHP_BIN -r 'require "app/helpers.php"; echo tanggal($argv[1], false, true);' "$CREATED_AT1")"
contains "Waktu upload bukti 1 tampil terpisah" "$CREATED_FMT1" "$TMP/staff_after_reject.html"

# Tamu mengunggah bukti kedua (pengganti)
t_guest="$(tok "$TMP/gA.jar" "/booking/$CODE_A")"
curl -s -b "$TMP/gA.jar" -c "$TMP/gA.jar" -o /dev/null \
  --form-string "_token=$t_guest" \
  --form-string "method=ewallet" \
  -F "proof_image=@$(winpath "$TMP/proof2.png");type=image/png" \
  "$BASE/booking/$CODE_A/bayar"

PAYMENT_ID2="$($MYSQL "SELECT id FROM payments WHERE booking_id = (SELECT id FROM bookings WHERE code='$CODE_A') AND purpose='rental' AND status='pending'")"
check "Payment kedua ID berbeda dari pertama" 1 "$([[ "$PAYMENT_ID2" != "$PAYMENT_ID1" ]] && echo 1 || echo 0)"

# Staf melihat detail booking dengan bukti kedua
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A" -o "$TMP/staff_with_second.html"
contains "Preview memuat bukti kedua" "src=\"/staff/booking/$CODE_A/bukti/$PAYMENT_ID2\"" "$TMP/staff_with_second.html"
contains "Metode ewallet tampil" "E-wallet" "$TMP/staff_with_second.html"
# Memastikan riwayat memuat bukti lama (1) dan bukti baru (2)
contains "Riwayat memuat tautan bukti 1" "/staff/booking/$CODE_A/bukti/$PAYMENT_ID1" "$TMP/staff_with_second.html"
contains "Riwayat memuat tautan bukti 2" "/staff/booking/$CODE_A/bukti/$PAYMENT_ID2" "$TMP/staff_with_second.html"

# ---------------------------------------------------------------------
# 5. Persetujuan Pembayaran
# ---------------------------------------------------------------------
echo "--- 5. Persetujuan Pembayaran oleh Staf ---"
t_staff="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null \
  --data-urlencode "_token=$t_staff" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID2/setujui"

check "Payment kedua menjadi paid" paid "$($MYSQL "SELECT status FROM payments WHERE id=$PAYMENT_ID2")"
check "Booking menjadi confirmed" confirmed "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"
check "handled_by staf tercatat pada payment paid" "$STAFF_ID" "$($MYSQL "SELECT handled_by FROM payments WHERE id=$PAYMENT_ID2")"
PAID_AT="$($MYSQL "SELECT paid_at FROM payments WHERE id=$PAYMENT_ID2")"
check "paid_at terisi" 1 "$([[ -n "$PAID_AT" && "$PAID_AT" != "NULL" ]] && echo 1 || echo 0)"

# Coba setujui ulang payment yang sudah paid -> harus gagal/ditolak
t_staff="$(tok "$TMP/staff.jar" "/staff/booking/$CODE_A")"
r_dup="$(curl -s -b "$TMP/staff.jar" -c "$TMP/staff.jar" -o /dev/null -w '%{http_code}' \
  --data-urlencode "_token=$t_staff" \
  "$BASE/staff/booking/$CODE_A/pembayaran/$PAYMENT_ID2/setujui")"
check "Setujui ulang ditolak redirect" 302 "$r_dup"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A" -o "$TMP/staff_dup.html"
contains "Flash error pembayaran sudah diproses" "sudah diproses sebelumnya" "$TMP/staff_dup.html"

# Tampilan detail staf setelah confirmed
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$CODE_A" -o "$TMP/staff_confirmed.html"
contains "Status booking Terkonfirmasi" "Terkonfirmasi" "$TMP/staff_confirmed.html"
lacks "Card verifikasi hilang setelah payment lunas" "Verifikasi Bukti Pembayaran" "$TMP/staff_confirmed.html"
contains "Riwayat menampilkan status Disetujui" "Disetujui" "$TMP/staff_confirmed.html"
contains "Riwayat menampilkan nama staf pada payment disetujui" "Staff CampGear" "$TMP/staff_confirmed.html"

PAID_AT_FMT2="$($PHP_BIN -r 'require "app/helpers.php"; echo tanggal($argv[1], false, true);' "$PAID_AT")"
contains "Waktu approval tampil pada payment 2" "$PAID_AT_FMT2" "$TMP/staff_confirmed.html"
contains "Waktu penolakan bukti 1 tetap tampil dan tidak tertukar" "$AUDIT_REJECT_FMT" "$TMP/staff_confirmed.html"

contains "Badge Lunas di ringkasan" "Lunas" "$TMP/staff_confirmed.html"

summary
