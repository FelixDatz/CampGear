#!/usr/bin/env bash
# Uji konkurensi: dua proses paralel memperebutkan stok terakhir pada 40 tanggal.
# Lulus bila tidak ada satu tanggal pun yang melebihi stok dan tepat satu pemenang per tanggal.
source "$(dirname "$0")/lib.sh"

ROUNDS=40
START="$("$PHP_BIN" -r 'echo microtime(true) + 2;')"
OUT_A="$TMP/a.txt"; OUT_B="$TMP/b.txt"
"$PHP_BIN" "$ROOT/tests/race.php" A "$ROUNDS" "$START" >"$OUT_A" 2>&1 &
"$PHP_BIN" "$ROOT/tests/race.php" B "$ROUNDS" "$START" >"$OUT_B" 2>&1 &
wait
echo "  pekerja: $(tr -d '\r\n' < "$OUT_A") $(tr -d '\r\n' < "$OUT_B")"

check "jumlah booking = jumlah tanggal (satu pemenang per tanggal)" "$ROUNDS" \
  "$($MYSQL "SELECT COUNT(*) FROM bookings WHERE customer_email='race@test.local'")"
check "tanggal yang melebihi stok (overbooking)" 0 \
  "$($MYSQL "SELECT COUNT(*) FROM (SELECT b.start_date, SUM(bi.quantity) q FROM bookings b JOIN booking_items bi ON bi.booking_id=b.id WHERE bi.equipment_id=3 AND b.customer_email='race@test.local' GROUP BY b.start_date HAVING q > 3) x")"
check "tidak ada error di pekerja" 0 "$(cat "$OUT_A" "$OUT_B" | grep -ci 'error\|exception')"

summary
