#!/usr/bin/env bash
# Uji operasional: pembatalan, pengambilan, pengembalian, kerusakan, deposit, penyelesaian.
source "$(dirname "$0")/lib.sh"

login "$TMP/staff.jar" staff@demo.test 'Staff#2026'
login "$TMP/user.jar" user@demo.test 'User#2026'

echo "== Akses panel"
check "tamu -> /staff/booking redirect" 302 "$(status "$BASE/staff/booking")"
check "user -> /staff/booking 403" 403 "$(status -b "$TMP/user.jar" "$BASE/staff/booking")"
for p in /staff /staff/booking "/staff/booking?view=pickup" "/staff/booking?q=xyz&status=confirmed" /staff/pembatalan "/staff/pembatalan?status=all" /staff/kerusakan "/staff/kerusakan?status=all"; do
  check "staff GET $p" 200 "$(status -b "$TMP/staff.jar" "$BASE$p")"
done

echo "== Pembatalan oleh penyewa"
C1="$(CNAME="Penyewa Batal" book "$TMP/g1.jar" "$(d +5)" "$(d +6)" --data "items[1]=4")"
echo "    booking C1: $C1"
check "stok tenda 2P terpakai 4" 4 "$(curl -s "$BASE/api/availability?start=$(d +5)&end=$(d +5)" | json equipment.1)"
r="$(TOKPATH="/booking/$C1" post "$TMP/g1.jar" "/booking/$C1/batal" --data-urlencode "reason=pendek")"
curl -s -b "$TMP/g1.jar" "$BASE/booking/$C1" -o "$TMP/b.html"
contains "alasan terlalu pendek ditolak" "minimal 10 karakter" "$TMP/b.html"
check "tamu lain (token sah) -> 403" "403 " "$(TOKPATH=/cek-booking post "$TMP/other.jar" "/booking/$C1/batal" --data-urlencode "reason=Bukan pemilik booking ini")"
check "token CSRF palsu -> 419" 419 "$(curl -s -o /dev/null -w '%{http_code}' -b "$TMP/g1.jar" --data "_token=x&reason=abcdefghijk" "$BASE/booking/$C1/batal")"
r="$(TOKPATH="/booking/$C1" post "$TMP/g1.jar" "/booking/$C1/batal" --data-urlencode "reason=Rencana pendakian ditunda karena cuaca buruk")"
check "pengajuan terkirim" "302 /booking/$C1#pembatalan" "$r"
check "status request pending" pending "$($MYSQL "SELECT cr.status FROM cancellation_requests cr JOIN bookings b ON b.id=cr.booking_id WHERE b.code='$C1'")"
TOKPATH="/booking/$C1" post "$TMP/g1.jar" "/booking/$C1/batal" --data-urlencode "reason=Pengajuan kedua seharusnya ditolak" >/dev/null
check "pengajuan ganda dicegah" 1 "$($MYSQL "SELECT COUNT(*) FROM cancellation_requests cr JOIN bookings b ON b.id=cr.booking_id WHERE b.code='$C1'")"
curl -s -b "$TMP/g1.jar" "$BASE/booking/$C1" -o "$TMP/b.html"
contains "penyewa melihat status Menunggu" ">Menunggu<" "$TMP/b.html"
lacks "form pembatalan disembunyikan saat pending" 'name="reason"' "$TMP/b.html"
REQ1="$($MYSQL "SELECT cr.id FROM cancellation_requests cr JOIN bookings b ON b.id=cr.booking_id WHERE b.code='$C1'")"
curl -s -b "$TMP/staff.jar" "$BASE/staff" -o "$TMP/dash.html"
contains "badge pembatalan di menu" 'rounded-pill text-bg-warning ms-auto">1<' "$TMP/dash.html"

echo "== Tolak lalu ajukan ulang, kemudian setujui"
r="$(TOKPATH="/staff/pembatalan/$REQ1" post "$TMP/staff.jar" "/staff/pembatalan/$REQ1/tolak" --data-urlencode "reject_note=")"
check "tolak tanpa alasan -> kembali" "302 /staff/pembatalan/$REQ1" "$r"
check "masih pending" pending "$($MYSQL "SELECT status FROM cancellation_requests WHERE id=$REQ1")"
TOKPATH="/staff/pembatalan/$REQ1" post "$TMP/staff.jar" "/staff/pembatalan/$REQ1/tolak" --data-urlencode "reject_note=Silakan jadwalkan ulang saja" >/dev/null
check "ditolak + peninjau tercatat" "rejected 2" "$($MYSQL "SELECT status, reviewed_by FROM cancellation_requests WHERE id=$REQ1" | tr '\t' ' ')"
check "booking tetap pending_payment" pending_payment "$($MYSQL "SELECT status FROM bookings WHERE code='$C1'")"
TOKPATH="/booking/$C1" post "$TMP/g1.jar" "/booking/$C1/batal" --data-urlencode "reason=Tetap ingin membatalkan karena izin kantor batal" >/dev/null
REQ2="$($MYSQL "SELECT MAX(cr.id) FROM cancellation_requests cr JOIN bookings b ON b.id=cr.booking_id WHERE b.code='$C1'")"
check "ajukan ulang setelah ditolak" pending "$($MYSQL "SELECT status FROM cancellation_requests WHERE id=$REQ2")"
r="$(TOKPATH="/staff/pembatalan/$REQ2" post "$TMP/staff.jar" "/staff/pembatalan/$REQ2/setujui" --data "refund_amount=50000")"
curl -s -b "$TMP/staff.jar" "$BASE/staff/pembatalan/$REQ2" -o "$TMP/c.html"
contains "refund melebihi pembayaran ditolak" "tidak boleh melebihi" "$TMP/c.html"
TOKPATH="/staff/pembatalan/$REQ2" post "$TMP/staff.jar" "/staff/pembatalan/$REQ2/setujui" --data-urlencode "refund_note=Belum ada pembayaran" >/dev/null
check "disetujui, refund n/a" "approved not_applicable" "$($MYSQL "SELECT status, refund_status FROM cancellation_requests WHERE id=$REQ2" | tr '\t' ' ')"
check "booking cancelled" cancelled "$($MYSQL "SELECT status FROM bookings WHERE code='$C1'")"
check "stok dilepas kembali (8)" 8 "$(curl -s "$BASE/api/availability?start=$(d +5)&end=$(d +5)" | json equipment.1)"
check "audit approve tercatat" 1 "$($MYSQL "SELECT COUNT(*) FROM audit_logs WHERE action='cancellation.approve'")"
check "setujui ulang ditolak" "302 /staff/pembatalan/$REQ2" "$(TOKPATH="/staff/pembatalan/$REQ2" post "$TMP/staff.jar" "/staff/pembatalan/$REQ2/setujui")"
check "status tetap approved" approved "$($MYSQL "SELECT status FROM cancellation_requests WHERE id=$REQ2")"
curl -s -b "$TMP/g1.jar" "$BASE/booking/$C1" -o "$TMP/b.html"
contains "penyewa melihat Disetujui" ">Disetujui<" "$TMP/b.html"
contains "penyewa melihat catatan refund" "Belum ada pembayaran" "$TMP/b.html"

echo "== Pembatalan booking terbayar -> refund perlu diproses"
C2="$(book "$TMP/g2.jar" "$(d +7)" "$(d +8)" --data "items[6]=2")"
TOKPATH="/booking/$C2" post "$TMP/g2.jar" "/booking/$C2/bayar" --data "method=transfer" >/dev/null
TOKPATH="/booking/$C2" post "$TMP/g2.jar" "/booking/$C2/batal" --data-urlencode "reason=Anggota rombongan sakit mendadak" >/dev/null
REQ3="$($MYSQL "SELECT cr.id FROM cancellation_requests cr JOIN bookings b ON b.id=cr.booking_id WHERE b.code='$C2'")"
TOKPATH="/staff/pembatalan/$REQ3" post "$TMP/staff.jar" "/staff/pembatalan/$REQ3/setujui" --data "refund_amount=60000" --data-urlencode "refund_note=Transfer balik (simulasi)" >/dev/null
check "refund pending + nominal" "approved pending 60000.00" "$($MYSQL "SELECT status, refund_status, refund_amount FROM cancellation_requests WHERE id=$REQ3" | tr '\t' ' ')"
TOKPATH="/staff/pembatalan/$REQ3" post "$TMP/staff.jar" "/staff/pembatalan/$REQ3/refund" >/dev/null
check "refund diproses" processed "$($MYSQL "SELECT refund_status FROM cancellation_requests WHERE id=$REQ3")"

echo "== Pembatalan tidak boleh pada/ setelah tanggal mulai"
C3="$(book "$TMP/g3.jar" "$(d +0)" "$(d +2)" --data "items[2]=1" --data "items[4]=2" --data "packages[1]=1")"
curl -s -b "$TMP/g3.jar" "$BASE/booking/$C3" -o "$TMP/b.html"
contains "info batas pembatalan" "sebelum tanggal mulai sewa" "$TMP/b.html"
TOKPATH="/booking/$C3" post "$TMP/g3.jar" "/booking/$C3/batal" --data-urlencode "reason=Mencoba membatalkan di hari H" >/dev/null
check "tidak ada request untuk hari H" 0 "$($MYSQL "SELECT COUNT(*) FROM cancellation_requests cr JOIN bookings b ON b.id=cr.booking_id WHERE b.code='$C3'")"

echo "== Operasional: tunai -> ambil -> kembali (rusak/hilang) -> selesai"
r="$(TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/ambil")"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$C3" -o "$TMP/s.html"
contains "ambil sebelum bayar ditolak" "sudah terkonfirmasi" "$TMP/s.html"
contains "detail staff: tiket yang sama dengan penyewa" 'class="staff-ticket"' "$TMP/s.html"
contains "detail staff: langkah berikutnya" 'class="next-step"' "$TMP/s.html"
TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/bayar" >/dev/null
check "tunai: confirmed + handled_by staff" "confirmed cash 2" "$($MYSQL "SELECT b.status, p.method, p.handled_by FROM bookings b JOIN payments p ON p.booking_id=b.id WHERE b.code='$C3'" | tr '\t' ' ')"
mapfile -t ITEMS < <($MYSQL "SELECT CONCAT(bi.id,':',bi.quantity) FROM booking_items bi JOIN bookings b ON b.id=bi.booking_id WHERE b.code='$C3' ORDER BY bi.id")
args=(); for it in "${ITEMS[@]}"; do id="${it%%:*}"; q="${it##*:}"; args+=(--data "items[$id][good]=$q" --data "items[$id][damaged]=0"); done
bad=("${args[@]}"); bad[1]="items[${ITEMS[0]%%:*}][good]=0"
TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/ambil" "${bad[@]}" >/dev/null
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking/$C3" -o "$TMP/s.html"
contains "jumlah checklist tidak pas ditolak" "harus sama dengan jumlah disewa" "$TMP/s.html"
check "status masih confirmed" confirmed "$($MYSQL "SELECT status FROM bookings WHERE code='$C3'")"
TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/ambil" "${args[@]}" --data "deposit_amount=200000" --data-urlencode "deposit_note=KTP + uang tunai" >/dev/null
check "picked_up + deposit ditahan" "picked_up 200000.00 held" "$($MYSQL "SELECT status, deposit_amount, deposit_status FROM bookings WHERE code='$C3'" | tr '\t' ' ')"
check "checklist pickup tersimpan" "${#ITEMS[@]}" "$($MYSQL "SELECT COUNT(*) FROM handover_items hi JOIN handover_checklists hc ON hc.id=hi.checklist_id JOIN bookings b ON b.id=hc.booking_id WHERE b.code='$C3' AND hc.type='pickup'")"
check "pickup kedua ditolak" picked_up "$(TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/ambil" "${args[@]}" >/dev/null; $MYSQL "SELECT status FROM bookings WHERE code='$C3'")"
curl -s -b "$TMP/g3.jar" "$BASE/booking/$C3" -o "$TMP/b.html"
contains "penyewa melihat Sedang Disewa" "Sedang Disewa" "$TMP/b.html"
contains "penyewa melihat catatan serah terima" "Catatan serah terima" "$TMP/b.html"

STOCK4_BEFORE="$($MYSQL "SELECT stock_total FROM equipment WHERE id=4")"
ret=(); first=1
for it in "${ITEMS[@]}"; do id="${it%%:*}"; q="${it##*:}"; eq="$($MYSQL "SELECT equipment_id FROM booking_items WHERE id=$id")"
  if [[ "$eq" == "4" ]]; then ret+=(--data "items[$id][good]=0" --data "items[$id][damaged]=1" --data "items[$id][lost]=1" --data-urlencode "items[$id][description]=Rangka patah, satu unit hilang" --data "items[$id][charge_lost]=750000");
  else ret+=(--data "items[$id][good]=$q" --data "items[$id][damaged]=0" --data "items[$id][lost]=0"); fi; done
TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/kembali" "${ret[@]}" --data "reduce_stock=1" >/dev/null
check "returned" returned "$($MYSQL "SELECT status FROM bookings WHERE code='$C3'")"
check "2 laporan (rusak & hilang), nominal hilang 750000, rusak NULL" "damaged 1 NULL unsettled|lost 1 750000.00 unsettled" \
  "$($MYSQL "SELECT CONCAT_WS(' ', dr.type, dr.quantity, IFNULL(dr.charge_amount,'NULL'), dr.status) FROM damage_reports dr JOIN bookings b ON b.id=dr.booking_id WHERE b.code='$C3' ORDER BY dr.type" | paste -sd'|')"
check "stok carrier 60L berkurang 1" "$((STOCK4_BEFORE-1))" "$($MYSQL "SELECT stock_total FROM equipment WHERE id=4")"
check "audit pickup & return" 2 "$($MYSQL "SELECT COUNT(*) FROM audit_logs WHERE action IN ('booking.pickup','booking.return')")"

TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/selesai" >/dev/null
check "selesai diblokir selama penggantian belum tuntas" returned "$($MYSQL "SELECT status FROM bookings WHERE code='$C3'")"
curl -s -b "$TMP/g3.jar" "$BASE/booking/$C3" -o "$TMP/b.html"
contains "penyewa melihat kewajiban penggantian" "wajib diselesaikan" "$TMP/b.html"
contains "penyewa melihat nominal belum ditentukan" "Belum ditentukan" "$TMP/b.html"

DMG="$($MYSQL "SELECT dr.id FROM damage_reports dr JOIN bookings b ON b.id=dr.booking_id WHERE b.code='$C3' AND dr.type='damaged'")"
DLOST="$($MYSQL "SELECT dr.id FROM damage_reports dr JOIN bookings b ON b.id=dr.booking_id WHERE b.code='$C3' AND dr.type='lost'")"
check "halaman laporan staff" 200 "$(status -b "$TMP/staff.jar" "$BASE/staff/kerusakan/$DMG")"
TOKPATH="/staff/kerusakan/$DMG" post "$TMP/staff.jar" "/staff/kerusakan/$DMG/selesai" --data-urlencode "settlement_note=Dibayar tunai" --data "payment_method=cash" >/dev/null
curl -s -b "$TMP/staff.jar" "$BASE/staff/kerusakan/$DMG" -o "$TMP/k.html"
contains "bayar tanpa nominal ditolak" "Isi nominal penggantian" "$TMP/k.html"
TOKPATH="/staff/kerusakan/$DMG" post "$TMP/staff.jar" "/staff/kerusakan/$DMG" --data "charge_amount=125000" --data-urlencode "description=Frame patah" >/dev/null
TOKPATH="/staff/kerusakan/$DMG" post "$TMP/staff.jar" "/staff/kerusakan/$DMG/selesai" --data-urlencode "settlement_note=Dibayar tunai di toko" --data "payment_method=cash" >/dev/null
check "rusak tuntas + pembayaran damage" "settled 125000.00" "$($MYSQL "SELECT dr.status, p.amount FROM damage_reports dr JOIN payments p ON p.booking_id=dr.booking_id AND p.purpose='damage' WHERE dr.id=$DMG" | tr '\t' ' ')"
TOKPATH="/staff/kerusakan/$DMG" post "$TMP/staff.jar" "/staff/kerusakan/$DMG" --data "charge_amount=1" >/dev/null
check "laporan tuntas tidak bisa diubah" 125000.00 "$($MYSQL "SELECT charge_amount FROM damage_reports WHERE id=$DMG")"
TOKPATH="/staff/kerusakan/$DLOST" post "$TMP/staff.jar" "/staff/kerusakan/$DLOST/selesai" --data-urlencode "settlement_note=Diganti unit baru oleh penyewa" >/dev/null
check "hilang tuntas tanpa pembayaran" settled "$($MYSQL "SELECT status FROM damage_reports WHERE id=$DLOST")"

TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/deposit" --data "deposit_amount=200000" --data "deposit_status=returned" --data-urlencode "deposit_note=Dikembalikan utuh" >/dev/null
check "deposit dikembalikan" returned "$($MYSQL "SELECT deposit_status FROM bookings WHERE code='$C3'")"
TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/deposit" --data "deposit_status=bogus" >/dev/null
check "status deposit tidak valid ditolak" returned "$($MYSQL "SELECT deposit_status FROM bookings WHERE code='$C3'")"
TOKPATH="/staff/booking/$C3" post "$TMP/staff.jar" "/staff/booking/$C3/selesai" >/dev/null
check "booking completed" completed "$($MYSQL "SELECT status FROM bookings WHERE code='$C3'")"
curl -s -b "$TMP/g3.jar" "$BASE/booking/$C3" -o "$TMP/b.html"
contains "penyewa melihat Selesai" "Booking selesai" "$TMP/b.html"

echo "== Deposit tidak bisa dicatat pada booking belum bayar"
C4="$(book "$TMP/g4.jar" "$(d +9)" "$(d +9)" --data "items[7]=1")"
TOKPATH="/staff/booking/$C4" post "$TMP/staff.jar" "/staff/booking/$C4/deposit" --data "deposit_status=held" >/dev/null
check "deposit pending_payment ditolak" not_recorded "$($MYSQL "SELECT deposit_status FROM bookings WHERE code='$C4'")"
check "user tidak bisa aksi staff (403)" 403 "$(curl -s -o /dev/null -w '%{http_code}' -b "$TMP/user.jar" --data "_token=$(tok "$TMP/user.jar" /akun)" "$BASE/staff/booking/$C4/bayar")"

echo "== Daftar & filter staff"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking?q=Penyewa+Batal" -o "$TMP/l.html"
contains "pencarian nama" "$C1" "$TMP/l.html"
lacks "pencarian menyaring" "$C3" "$TMP/l.html"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking?view=unpaid" -o "$TMP/l.html"
contains "filter menunggu bayar" "$C4" "$TMP/l.html"
curl -s -b "$TMP/staff.jar" "$BASE/staff/booking?status=completed" -o "$TMP/l.html"
contains "filter status completed" "$C3" "$TMP/l.html"
check "kode booking tak ada -> 404" 404 "$(status -b "$TMP/staff.jar" "$BASE/staff/booking/CG000000-XXXX")"
check "id pembatalan bukan angka -> 404" 404 "$(status -b "$TMP/staff.jar" "$BASE/staff/pembatalan/abc")"

summary
