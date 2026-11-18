#!/usr/bin/env bash
# Uji booking: validasi rentang, stok per hari, paket, diskon, validasi input, hak akses, cek status, pembayaran, kedaluwarsa.
source "$(dirname "$0")/lib.sh"

# Versi book khusus suite ini: mengembalikan "HTTP REDIRECT" (bukan hanya kode booking).
book() {
  local jar="$1" start="$2" end="$3"; shift 3
  curl -s -c "$jar" -b "$jar" "$BASE/sewa" -o "$TMP/f.html"
  local t; t="$(token "$TMP/f.html")"
  curl -s -c "$jar" -b "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' \
    --data-urlencode "_token=$t" --data-urlencode "start_date=$start" --data-urlencode "end_date=$end" \
    --data-urlencode "customer_name=Tamu Uji" --data-urlencode "customer_email=tamu@contoh.test" \
    --data-urlencode "customer_phone=0812 3333 4444" "$@" "$BASE/sewa" | sed 's#https\?://[^/]*##'
}

echo "== API ketersediaan: validasi rentang"
check "format salah" 422 "$(status "$BASE/api/availability?start=abc&end=2026-01-01")"
check "tanggal lampau" 422 "$(status "$BASE/api/availability?start=$(d -1)&end=$(d 1)")"
check "selesai < mulai" 422 "$(status "$BASE/api/availability?start=$(d 5)&end=$(d 3)")"
check "lebih dari 14 hari" 422 "$(status "$BASE/api/availability?start=$(d 1)&end=$(d 15)")"
check "tepat 14 hari" 200 "$(status "$BASE/api/availability?start=$(d 1)&end=$(d 14)")"
check "mulai > H+90" 422 "$(status "$BASE/api/availability?start=$(d 91)&end=$(d 91)")"
curl -s "$BASE/api/availability?start=$(d 1)&end=$(d 14)" -o "$TMP/a.json"
check "pesan error JSON" "Durasi sewa maksimal 14 hari." "$(curl -s "$BASE/api/availability?start=$(d 1)&end=$(d 15)" | json error)"
check "content-type JSON" "application/json; charset=utf-8" "$(curl -s -o /dev/null -w '%{content_type}' "$BASE/api/availability?start=$(d 1)&end=$(d 2)")"
check "stok awal tenda 4P = 5" 5 "$(json equipment.2 < "$TMP/a.json")"
check "jumlah hari" 14 "$(json days < "$TMP/a.json")"
check "set paket family (tenda 6P stok 3)" 3 "$(json packages.3 < "$TMP/a.json")"

echo "== Membuat booking (tamu)"
r="$(book "$TMP/guest.jar" "$(d 10)" "$(d 12)" --data "items[2]=3")"
CODE_A="$(echo "$r" | sed -n 's#^302 /booking/##p')"
check "booking A dibuat" "302" "${r%% *}"
echo "    kode A: $CODE_A"
check "format kode" 1 "$([[ "$CODE_A" =~ ^CG[0-9]{6}-[A-HJ-NP-Z2-9]{4}$ ]] && echo 1 || echo 0)"
curl -s -b "$TMP/guest.jar" "$BASE/booking/$CODE_A" -o "$TMP/show.html"
contains "detail menampilkan status menunggu bayar" "Menunggu Pembayaran" "$TMP/show.html"
check "total A = 3 x 55000 x 3 hari" "495000.00 3 tamu@contoh.test 081233334444 pending_payment" \
  "$($MYSQL "SELECT grand_total, total_days, customer_email, customer_phone, status FROM bookings WHERE code='$CODE_A'" | tr '\t' ' ')"

echo "== Stok per hari"
check "tumpang tindih (hari 12-13): 5-3=2" 2 "$(curl -s "$BASE/api/availability?start=$(d 12)&end=$(d 13)" | json equipment.2)"
check "tidak tumpang tindih (hari 13-14): 5" 5 "$(curl -s "$BASE/api/availability?start=$(d 13)&end=$(d 14)" | json equipment.2)"
r="$(book "$TMP/g2.jar" "$(d 14)" "$(d 15)" --data "items[2]=3")"; check "booking B hari 14-15 qty 3" 302 "${r%% *}"
check "rentang 10-15: puncak 3 per hari, tersedia 2 (bukan -1)" 2 "$(curl -s "$BASE/api/availability?start=$(d 10)&end=$(d 15)" | json equipment.2)"
r="$(book "$TMP/g3.jar" "$(d 11)" "$(d 11)" --data "items[2]=3")"
check "minta 3 saat tersisa 2 -> ditolak" "302 /sewa" "$r"
curl -s -b "$TMP/g3.jar" "$BASE/sewa" -o "$TMP/err.html"
contains "pesan kekurangan stok" "Tenda Dome 4 Orang (diminta 3, tersedia 2)" "$TMP/err.html"
contains "input tanggal lama dipertahankan" "value=\"$(d 11)\"" "$TMP/err.html"
contains "jumlah lama dipertahankan" 'name="items\[2\]" value="3"' "$TMP/err.html"

echo "== Paket + alat satuan digabung"
r="$(book "$TMP/g4.jar" "$(d 20)" "$(d 21)" --data "packages[3]=2" --data "items[3]=2")"
check "paket family x2 + tenda 6P x2 (stok 3) ditolak" "302 /sewa" "$r"
curl -s -b "$TMP/g4.jar" "$BASE/sewa" -o "$TMP/err2.html"
contains "kebutuhan gabungan terdeteksi" "Tenda Family 6 Orang (diminta 4, tersedia 3)" "$TMP/err2.html"

echo "== Diskon paket"
r="$(book "$TMP/g5.jar" "$(d 30)" "$(d 31)" --data "packages[1]=1")"
CODE_P="$(echo "$r" | sed -n 's#^302 /booking/##p')"
check "paket solo 2 hari: subtotal 176000, diskon 17600, total 158400" "176000.00 17600.00 158400.00" \
  "$($MYSQL "SELECT subtotal, discount_total, grand_total FROM bookings WHERE code='$CODE_P'" | tr '\t' ' ')"
check "5 baris item dengan package_id" 5 "$($MYSQL "SELECT COUNT(*) FROM booking_items bi JOIN bookings b ON b.id=bi.booking_id WHERE b.code='$CODE_P' AND bi.package_id=1")"

echo "== Validasi input"
r="$(book "$TMP/g6.jar" "$(d 3)" "$(d 4)")"; check "tanpa alat ditolak" "302 /sewa" "$r"
curl -s -b "$TMP/g6.jar" "$BASE/sewa" -o "$TMP/v.html"; contains "pesan pilih alat" "Pilih minimal satu alat atau paket" "$TMP/v.html"
r="$(book "$TMP/g7.jar" "$(d 3)" "$(d 4)" --data "items[2]=-1")"; check "jumlah negatif ditolak" "302 /sewa" "$r"
r="$(book "$TMP/g8.jar" "$(d 3)" "$(d 4)" --data "items[abc]=1")"; check "id alat bukan angka ditolak" "302 /sewa" "$r"
r="$(book "$TMP/g9.jar" "$(d 3)" "$(d 4)" --data "items[999]=1")"; check "id alat tak dikenal ditolak" "302 /sewa" "$r"
r="$(book "$TMP/g10.jar" "$(d 3)" "$(d 4)" --data "items=1")"; check "items bukan array ditolak" "302 /sewa" "$r"
r="$(book "$TMP/g11.jar" "$(d -1)" "$(d 1)" --data "items[2]=1")"; check "tanggal lampau ditolak" "302 /sewa" "$r"
check "jumlah booking tetap 3" 3 "$($MYSQL 'SELECT COUNT(*) FROM bookings')"

echo "== Hak akses & cek status"
check "tamu lain dialihkan ke cek-booking" "302" "$(status "$BASE/booking/$CODE_A")"
check "arah redirect" "/cek-booking?kode=$CODE_A" "$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE/booking/$CODE_A" | sed 's#https\?://[^/]*##')"
check "kode tidak ada -> 404" 404 "$(status "$BASE/booking/CG000000-XXXX")"
lookup() { # jar code contact
  curl -s -c "$1" -b "$1" "$BASE/cek-booking" -o "$TMP/l.html"; local t; t="$(token "$TMP/l.html")"
  curl -s -c "$1" -b "$1" -o /dev/null -w '%{redirect_url}' --data-urlencode "_token=$t" --data-urlencode "code=$2" --data-urlencode "contact=$3" "$BASE/cek-booking" | sed 's#https\?://[^/]*##'
}
check "kontak salah ditolak" "/cek-booking" "$(lookup "$TMP/o.jar" "$CODE_A" salah@contoh.test)"
curl -s -b "$TMP/o.jar" "$BASE/cek-booking" -o "$TMP/l2.html"; contains "pesan generik" "Kode booking atau email/nomor HP tidak cocok" "$TMP/l2.html"
check "email benar (huruf besar) diterima" "/booking/$CODE_A" "$(lookup "$TMP/o.jar" "$CODE_A" TAMU@contoh.test)"
check "setelah cek, detail bisa dibuka" 200 "$(status -b "$TMP/o.jar" "$BASE/booking/$CODE_A")"
check "nomor HP format +62 diterima" "/booking/$CODE_A" "$(lookup "$TMP/o2.jar" "$(echo "$CODE_A" | tr 'A-Z' 'a-z')" "+62 812-3333-4444")"

echo "== Pembayaran simulasi"
curl -s -b "$TMP/o3.jar" -c "$TMP/o3.jar" "$BASE/cek-booking" -o "$TMP/x.html"; t="$(token "$TMP/x.html")"
check "bayar tanpa hak akses -> 403" 403 "$(status -b "$TMP/o3.jar" --data-urlencode "_token=$t" --data "method=transfer" "$BASE/booking/$CODE_A/bayar")"
curl -s -b "$TMP/guest.jar" "$BASE/booking/$CODE_A" -o "$TMP/s.html"; t="$(token "$TMP/s.html")"
check "bayar tanpa CSRF -> 419" 419 "$(status -b "$TMP/guest.jar" --data "method=transfer" "$BASE/booking/$CODE_A/bayar")"
check "metode tidak valid ditolak" 302 "$(status -b "$TMP/guest.jar" --data-urlencode "_token=$t" --data "method=bitcoin" "$BASE/booking/$CODE_A/bayar")"
check "status masih pending" pending_payment "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_A'")"
check "bayar transfer" 302 "$(status -b "$TMP/guest.jar" --data-urlencode "_token=$t" --data "method=transfer" "$BASE/booking/$CODE_A/bayar")"
check "status confirmed + payment paid sebesar total" "confirmed paid 495000.00" \
  "$($MYSQL "SELECT b.status, p.status, p.amount FROM bookings b JOIN payments p ON p.booking_id=b.id WHERE b.code='$CODE_A'" | tr '\t' ' ')"
curl -s -b "$TMP/guest.jar" "$BASE/booking/$CODE_A" -o "$TMP/s2.html"
contains "kartu pengambilan tampil" "Kartu Pengambilan Alat" "$TMP/s2.html"
contains "QR dengan kode booking" "data-qr=\"$CODE_A\"" "$TMP/s2.html"
contains "pustaka QR dimuat" "qrcode.min.js" "$TMP/s2.html"
contains "checklist serah terima" "Checklist serah terima" "$TMP/s2.html"
contains "flash sukses pembayaran" "Pembayaran simulasi berhasil" "$TMP/s2.html"
t="$(token "$TMP/s2.html")"
curl -s -b "$TMP/guest.jar" -o /dev/null --data-urlencode "_token=$t" --data "method=ewallet" "$BASE/booking/$CODE_A/bayar"
check "bayar ulang tidak membuat payment ganda" 1 "$($MYSQL "SELECT COUNT(*) FROM payments p JOIN bookings b ON b.id=p.booking_id WHERE b.code='$CODE_A'")"

echo "== Kedaluwarsa"
$MYSQL "UPDATE bookings SET payment_due_at = NOW() - INTERVAL 1 MINUTE WHERE code='$CODE_P'"
check "stok tenda 2P dilepas sebelum status diubah" 8 "$(curl -s "$BASE/api/availability?start=$(d 30)&end=$(d 31)" | json equipment.1)"
check "status menjadi expired" expired "$($MYSQL "SELECT status FROM bookings WHERE code='$CODE_P'")"
curl -s -b "$TMP/g5.jar" "$BASE/booking/$CODE_P" -o "$TMP/e.html"; contains "halaman menampilkan kedaluwarsa" "kedaluwarsa karena tidak dibayar" "$TMP/e.html"
t="$(token "$TMP/e.html")"
curl -s -b "$TMP/g5.jar" -o /dev/null --data-urlencode "_token=$t" --data "method=transfer" "$BASE/booking/$CODE_P/bayar"
check "booking expired tidak bisa dibayar" "expired 0" "$($MYSQL "SELECT b.status, COUNT(p.id) FROM bookings b LEFT JOIN payments p ON p.booking_id=b.id WHERE b.code='$CODE_P' GROUP BY b.id" | tr '\t' ' ')"

echo "== Booking oleh pelanggan login"
curl -s -c "$TMP/u.jar" -b "$TMP/u.jar" "$BASE/login" -o "$TMP/lg.html"; t="$(token "$TMP/lg.html")"
curl -s -c "$TMP/u.jar" -b "$TMP/u.jar" -o /dev/null --data-urlencode "_token=$t" --data-urlencode "email=user@demo.test" --data-urlencode "password=User#2026" "$BASE/login"
curl -s -b "$TMP/u.jar" "$BASE/sewa" -o "$TMP/uf.html"
contains "kontak terisi otomatis" 'value="user@demo.test"' "$TMP/uf.html"
t="$(token "$TMP/uf.html")"
r="$(curl -s -c "$TMP/u.jar" -b "$TMP/u.jar" -o /dev/null -w '%{redirect_url}' --data-urlencode "_token=$t" \
  --data "start_date=$(d 5)" --data "end_date=$(d 6)" --data-urlencode "customer_name=Pelanggan Demo" \
  --data-urlencode "customer_email=user@demo.test" --data "customer_phone=081200000003" --data "items[6]=2" "$BASE/sewa" | sed 's#https\?://[^/]*##')"
CODE_U="${r#/booking/}"
check "booking user tercatat user_id=3" 3 "$($MYSQL "SELECT user_id FROM bookings WHERE code='$CODE_U'")"
curl -s -b "$TMP/u.jar" "$BASE/akun" -o "$TMP/akun.html"; contains "riwayat di halaman akun" "$CODE_U" "$TMP/akun.html"
check "user tidak bisa lihat booking tamu lain" 302 "$(status -b "$TMP/u.jar" "$BASE/booking/$CODE_A")"

echo "== Staff"
curl -s -c "$TMP/s.jar" -b "$TMP/s.jar" "$BASE/login" -o "$TMP/lg.html"; t="$(token "$TMP/lg.html")"
curl -s -c "$TMP/s.jar" -b "$TMP/s.jar" -o /dev/null --data-urlencode "_token=$t" --data-urlencode "email=staff@demo.test" --data-urlencode "password=Staff#2026" "$BASE/login"
check "staff bisa lihat booking mana pun" 200 "$(status -b "$TMP/s.jar" "$BASE/booking/$CODE_A")"
curl -s -b "$TMP/s.jar" "$BASE/staff" -o "$TMP/dash.html"; contains "dashboard menampilkan transaksi" "$CODE_A" "$TMP/dash.html"
curl -s -b "$TMP/s.jar" "$BASE/sewa" -o "$TMP/sf.html"
if grep -q 'value="staff@demo.test"' "$TMP/sf.html"; then FAIL=$((FAIL+1)); echo "  FAIL kontak staff ikut terisi"; else PASS=$((PASS+1)); echo "  ok   staff membuat booking untuk pelanggan (kontak kosong)"; fi

summary
