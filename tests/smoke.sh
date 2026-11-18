#!/usr/bin/env bash
# Uji dasar: halaman publik, proteksi rute, CSRF, login per role, registrasi, "Ingat saya", brute-force.
source "$(dirname "$0")/lib.sh"

echo "== Halaman publik"
for p in / /alat "/alat?kategori=tenda" "/alat?q=dome" /alat/tenda-dome-2-orang /paket /cara-reservasi /kontak /login /register; do
  check "GET $p" 200 "$(status "$BASE$p")"
done
check "404 halaman tak ada" 404 "$(status "$BASE/tidak-ada")"
check "404 alat tak ada" 404 "$(status "$BASE/alat/xyz")"
check "404 kategori tak ada" 404 "$(status "$BASE/alat?kategori=xyz")"
check "405 GET /logout" 405 "$(status "$BASE/logout")"
check "asset css" 200 "$(status "$BASE/assets/css/app.css")"
curl -s "$BASE/alat?q=%25" -o "$TMP/like.html"
check "LIKE wildcard di-escape (q=%): tidak ada alat" 0 "$(grep -c '<article class="gear-card' "$TMP/like.html")"
contains "pesan 'Alat tidak ditemukan' tampil" 'data-katalog-empty>' "$TMP/like.html"
curl -s "$BASE/alat?q=%3Cscript%3Ealert(1)%3C/script%3E" -o "$TMP/xss.html"
if grep -q '<script>alert(1)' "$TMP/xss.html"; then FAIL=$((FAIL+1)); echo "  FAIL XSS tidak di-escape"; else PASS=$((PASS+1)); echo "  ok   input pencarian di-escape"; fi
check "array injection ?q[]=x" 200 "$(status "$BASE/alat?q[]=x")"

echo "== Beranda, navigasi, dan API katalog"
curl -s "$BASE/" -o "$TMP/home.html"
contains "matahari pengatur tema (slider)" 'role="slider"' "$TMP/home.html"
contains "kartu trip di beranda" "data-trip-card" "$TMP/home.html"
contains "popover cek booking" 'id="pop-cek"' "$TMP/home.html"
contains "popover cek booking mengirim ke /cek-booking" 'action="/cek-booking"' "$TMP/home.html"
contains "mega panel Jelajahi" 'id="menu-jelajahi"' "$TMP/home.html"
check "asset site.css" 200 "$(status "$BASE/assets/css/site.css")"
check "asset home.js" 200 "$(status "$BASE/assets/js/home.js")"
check "GET /api/katalog" 200 "$(status "$BASE/api/katalog")"
curl -s "$BASE/api/katalog" -o "$TMP/katalog.json"
count() { "$PHP_BIN" -r '$d = json_decode(stream_get_contents(STDIN), true); echo count($d[$argv[1]] ?? []);' "$1"; }
check "API katalog: 15 alat aktif" 15 "$(count equipment < "$TMP/katalog.json")"
check "API katalog: 3 paket" 3 "$(count packages < "$TMP/katalog.json")"
check "API katalog: 4 artikel" 4 "$(count articles < "$TMP/katalog.json")"

echo "== Beranda: grid kategori, katalog, tiket"
check "section 2: 6 kartu kategori" 6 "$(grep -c 'data-medan-card' "$TMP/home.html")"
check "section 2: tombol ransel per alat" 15 "$(grep -c 'class="medan-add"' "$TMP/home.html")"
check "katalog beranda: 15 kartu alat" 15 "$(grep -c '<article class="gear-card' "$TMP/home.html")"
contains "katalog beranda: quick view" 'id="quick-view"' "$TMP/home.html"
contains "tiket contoh di beranda" 'data-tiket-demo' "$TMP/home.html"
check "urutan: jalur, katalog, tiket, artikel" "jalur katalog tiket catatan" \
  "$(grep -o 'id="\(jalur\|katalog\|tiket\|catatan\)"' "$TMP/home.html" | sed 's/id="\(.*\)"/\1/' | tr '\n' ' ' | sed 's/ $//')"
check "altimeter naik berurutan" "95 700 1250 1800 2300 2700 3000 3265" \
  "$(grep -o 'data-mdpl="[0-9]*"' "$TMP/home.html" | grep -o '[0-9]\+' | tr '\n' ' ' | sed 's/ $//')"

echo "== Katalog: chip kategori & quick view"
curl -s "$BASE/alat" -o "$TMP/alat.html"
check "chip kategori (Semua + 6)" 7 "$(grep -c 'data-chip="' "$TMP/alat.html")"
contains "bar chip menempel" 'data-catbar' "$TMP/alat.html"
contains "quick view di /alat" 'id="quick-view"' "$TMP/alat.html"
curl -s "$BASE/alat?kategori=tenda" -o "$TMP/tenda.html"
contains "chip Tenda aktif" 'data-chip="tenda" data-name="Tenda"[^>]*aria-current="true"' "$TMP/tenda.html"
check "?kategori=tenda: 3 kartu tampil" 3 "$(grep '<article class="gear-card' "$TMP/tenda.html" | grep -vc ' hidden>')"
check "?kategori=tenda: 12 kartu lain tersembunyi" 12 "$(grep -c '<article class="gear-card[^>]* hidden>' "$TMP/tenda.html")"

echo "== Proteksi rute (tamu)"
check "tamu -> /akun redirect" 302 "$(status "$BASE/akun")"
check "tamu -> /staff redirect" 302 "$(status "$BASE/staff")"
check "redirect ke /login" "/login" "$(curl -s -o /dev/null -w '%{redirect_url}' "$BASE/staff" | sed 's#^https\?://[^/]*##')"

echo "== CSRF"
check "POST /login tanpa token" 419 "$(status -X POST -d 'email=a@b.c&password=x' "$BASE/login")"

login() { # jar email password [remember]
  curl -s -c "$1" -b "$1" "$BASE/login" -o "$TMP/form.html"
  local t; t="$(token "$TMP/form.html")"
  curl -s -c "$1" -b "$1" -o /dev/null -w '%{http_code} %{redirect_url}' \
    --data-urlencode "_token=$t" --data-urlencode "email=$2" --data-urlencode "password=$3" ${4:+--data "remember=1"} "$BASE/login"
}

echo "== Login per role"
r="$(login "$TMP/admin.jar" admin@demo.test 'Admin#2026')"; check "admin login -> /staff" "302 /staff" "$(echo "$r" | sed 's#https\?://[^/]*##')"
check "admin buka /staff" 200 "$(status -b "$TMP/admin.jar" "$BASE/staff")"
r="$(login "$TMP/staff.jar" staff@demo.test 'Staff#2026')"; check "staff login -> /staff" "302 /staff" "$(echo "$r" | sed 's#https\?://[^/]*##')"
check "staff buka /staff" 200 "$(status -b "$TMP/staff.jar" "$BASE/staff")"
r="$(login "$TMP/user.jar" user@demo.test 'User#2026')"; check "user login -> /akun" "302 /akun" "$(echo "$r" | sed 's#https\?://[^/]*##')"
check "user buka /akun" 200 "$(status -b "$TMP/user.jar" "$BASE/akun")"
check "user buka /staff -> 403" 403 "$(status -b "$TMP/user.jar" "$BASE/staff")"
check "user login buka /login -> redirect" 302 "$(status -b "$TMP/user.jar" "$BASE/login")"
curl -s -b "$TMP/user.jar" "$BASE/akun" -o "$TMP/akun.html"
contains "halaman akun menampilkan nama" "Pelanggan Demo" "$TMP/akun.html"

r="$(login "$TMP/bad.jar" user@demo.test 'salah123')"; check "password salah -> kembali ke /login" "302 /login" "$(echo "$r" | sed 's#https\?://[^/]*##')"
curl -s -b "$TMP/bad.jar" "$BASE/login" -o "$TMP/bad.html"
contains "pesan error login" "Email atau password salah" "$TMP/bad.html"
contains "input email lama dipertahankan" 'value="user@demo.test"' "$TMP/bad.html"

echo "== Intended redirect"
curl -s -c "$TMP/int.jar" -b "$TMP/int.jar" -o /dev/null "$BASE/staff"
r="$(login "$TMP/int.jar" admin@demo.test 'Admin#2026')"; check "kembali ke halaman yang diminta" "302 /staff" "$(echo "$r" | sed 's#https\?://[^/]*##')"

echo "== Logout"
curl -s -b "$TMP/user.jar" "$BASE/akun" -o "$TMP/akun.html"
t="$(token "$TMP/akun.html")"
check "logout" 302 "$(status -c "$TMP/user.jar" -b "$TMP/user.jar" --data-urlencode "_token=$t" "$BASE/logout")"
check "setelah logout /akun redirect" 302 "$(status -b "$TMP/user.jar" "$BASE/akun")"

echo "== Ingat saya"
login "$TMP/rem.jar" staff@demo.test 'Staff#2026' 1 >/dev/null
contains "cookie remember diset" "campgear_remember" "$TMP/rem.jar"
grep -v campgear_sid "$TMP/rem.jar" > "$TMP/rem-only.jar"
check "login otomatis dari cookie (tanpa session)" 200 "$(status -c "$TMP/rem-only.jar" -b "$TMP/rem-only.jar" "$BASE/staff")"
OLD="$(grep campgear_remember "$TMP/rem.jar" | awk '{print $7}')"
NEW="$(grep campgear_remember "$TMP/rem-only.jar" | awk '{print $7}')"
if [[ -n "$OLD" && -n "$NEW" && "$OLD" != "$NEW" ]]; then PASS=$((PASS+1)); echo "  ok   token dirotasi setelah dipakai"; else FAIL=$((FAIL+1)); echo "  FAIL token tidak dirotasi"; fi
printf '127.0.0.1\tFALSE\t/\tFALSE\t0\tcampgear_remember\t%s\n' "$OLD" > "$TMP/stale.jar"
check "token lama ditolak" 302 "$(status -b "$TMP/stale.jar" "$BASE/staff")"

echo "== Registrasi"
curl -s -c "$TMP/reg.jar" -b "$TMP/reg.jar" "$BASE/register" -o "$TMP/reg.html"; t="$(token "$TMP/reg.html")"
curl -s -c "$TMP/reg.jar" -b "$TMP/reg.jar" -o /dev/null --data-urlencode "_token=$t" \
  --data-urlencode "name=Ab" --data-urlencode "email=user@demo.test" --data-urlencode "phone=123" \
  --data-urlencode "password=abc" --data-urlencode "password_confirmation=xyz" "$BASE/register"
curl -s -b "$TMP/reg.jar" "$BASE/register" -o "$TMP/reg2.html"
contains "validasi nama" "Nama lengkap minimal 3 karakter" "$TMP/reg2.html"
contains "validasi email unik" "Email sudah digunakan" "$TMP/reg2.html"
contains "validasi HP" "nomor HP Indonesia" "$TMP/reg2.html"
contains "validasi password" "mengandung huruf serta angka" "$TMP/reg2.html"
EMAIL="tes$(date +%s)@contoh.test"
t="$(token "$TMP/reg2.html")"
r="$(curl -s -c "$TMP/reg.jar" -b "$TMP/reg.jar" -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_token=$t" \
  --data-urlencode "name=Penguji Baru" --data-urlencode "email=$EMAIL" --data-urlencode "phone=+62 812-3456-7890" \
  --data-urlencode "password=rahasia123" --data-urlencode "password_confirmation=rahasia123" --data-urlencode "role=admin" "$BASE/register")"
check "registrasi sukses -> /akun" "302 /akun" "$(echo "$r" | sed 's#https\?://[^/]*##')"
check "user baru tidak bisa buka /staff (role dipaksa user)" 403 "$(status -b "$TMP/reg.jar" "$BASE/staff")"
echo "$EMAIL" > "$TMP/new-email.txt"

echo "== Pembatas brute-force"
for i in 1 2 3 4 5; do login "$TMP/bf.jar" brute@demo.test "salah$i" >/dev/null; done
login "$TMP/bf.jar" brute@demo.test 'salah6' >/dev/null
curl -s -b "$TMP/bf.jar" "$BASE/login" -o "$TMP/bf.html"
contains "diblokir setelah 5 kali gagal" "Terlalu banyak percobaan" "$TMP/bf.html"

echo
summary
