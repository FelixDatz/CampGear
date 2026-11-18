#!/usr/bin/env bash
# Uji artikel, CRUD inventaris (upload), profil, manajemen akun admin, log aktivitas, statistik.
source "$(dirname "$0")/lib.sh"

login "$TMP/staff.jar" staff@demo.test 'Staff#2026'
login "$TMP/admin.jar" admin@demo.test 'Admin#2026'
login "$TMP/user.jar" user@demo.test 'User#2026'

# form JAR PATH TOKPATH args... (multipart) -> "kode redirect"
form() { local jar="$1" path="$2" tp="$3"; shift 3; local t; t="$(tok "$jar" "$tp")"
  curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' --form-string "_token=$t" "$@" "$BASE$path" | redir; }

echo "== Artikel publik"
curl -s "$BASE/artikel" -o "$TMP/a.html"
check "4 artikel terbit tampil" 4 "$(grep -c 'class="card article-card' "$TMP/a.html")"
check "detail artikel" 200 "$(status "$BASE/artikel/cara-memasang-dan-merawat-tenda-dome")"
curl -s "$BASE/artikel/cara-memasang-dan-merawat-tenda-dome" -o "$TMP/ad.html"
contains "format subjudul -> h2" "<h2>Memilih lokasi</h2>" "$TMP/ad.html"
contains "format daftar -> li" "<li>Cari permukaan datar" "$TMP/ad.html"
contains "format tebal" "<strong>Jangan pernah menyimpan tenda" "$TMP/ad.html"
curl -s "$BASE/" -o "$TMP/home.html"
contains "beranda menampilkan artikel terbaru" "Artikel Terbaru" "$TMP/home.html"
check "beranda menampilkan 4 artikel" 4 "$(grep -c 'class="catatan-item"' "$TMP/home.html")"
check "artikel keempat (carrier)" 200 "$(status "$BASE/artikel/cara-menyusun-isi-carrier")"

echo "== Artikel CRUD (staff)"
BODY=$'Paragraf pertama artikel uji yang cukup panjang untuk lolos validasi minimal lima puluh karakter.\n\n## Subjudul Uji\n- butir satu\n- butir dua\n\n<script>alert(1)</script> **tebal**'
r="$(form "$TMP/staff.jar" /staff/artikel /staff/artikel/baru --form-string "title=Abc" --form-string "body=pendek" --form-string "status=draft")"
check "validasi artikel gagal -> kembali" "302 /staff/artikel/baru" "$r"
r="$(form "$TMP/staff.jar" /staff/artikel /staff/artikel/baru --form-string "title=Artikel Uji Otomatis" --form-string "body=$BODY" --form-string "status=draft")"
check "artikel draf dibuat" "302 /staff/artikel" "$r"
check "draf tidak tampil publik" 404 "$(status "$BASE/artikel/artikel-uji-otomatis")"
AID="$($MYSQL "SELECT id FROM articles WHERE slug='artikel-uji-otomatis'")"
r="$(form "$TMP/staff.jar" "/staff/artikel/$AID" "/staff/artikel/$AID/edit" --form-string "_method=PUT" --form-string "title=Artikel Uji Otomatis" --form-string "body=$BODY" --form-string "status=published")"
check "terbitkan artikel (PUT)" "302 /staff/artikel" "$r"
check "published_at terisi" 1 "$($MYSQL "SELECT published_at IS NOT NULL FROM articles WHERE id=$AID")"
curl -s "$BASE/artikel/artikel-uji-otomatis" -o "$TMP/au.html"
lacks "script di isi artikel tidak dieksekusi" "<script>alert(1)" "$TMP/au.html"
contains "script ditampilkan sebagai teks" "&lt;script&gt;alert(1)&lt;/script&gt;" "$TMP/au.html"
form "$TMP/staff.jar" /staff/artikel /staff/artikel/baru --form-string "title=Artikel Uji Otomatis" --form-string "body=$BODY" --form-string "status=draft" >/dev/null
check "slug kembar dibuat unik" 1 "$($MYSQL "SELECT COUNT(*) FROM articles WHERE slug='artikel-uji-otomatis-2'")"
r="$(form "$TMP/staff.jar" "/staff/artikel/$AID" /staff/artikel --form-string "_method=DELETE")"
check "hapus artikel (DELETE)" "0" "$($MYSQL "SELECT COUNT(*) FROM articles WHERE id=$AID")"
check "audit artikel" 3 "$($MYSQL "SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'article.%' AND entity_id=$AID")"
check "user tidak bisa akses CRUD artikel" 403 "$(status -b "$TMP/user.jar" "$BASE/staff/artikel")"

echo "== Kategori"
r="$(form "$TMP/staff.jar" /staff/kategori /staff/kategori/baru --form-string "name=Ka" --form-string "icon=bi-tree")"
check "nama terlalu pendek ditolak" "302 /staff/kategori/baru" "$r"
r="$(form "$TMP/staff.jar" /staff/kategori /staff/kategori/baru --form-string "name=Perlengkapan Air" --form-string "icon=bi-evil")"
check "ikon di luar daftar ditolak" "302 /staff/kategori/baru" "$r"
form "$TMP/staff.jar" /staff/kategori /staff/kategori/baru --form-string "name=Perlengkapan Air" --form-string "icon=bi-droplet" --form-string "description=Botol, filter air" >/dev/null
CID="$($MYSQL "SELECT id FROM categories WHERE slug='perlengkapan-air'")"
check "kategori dibuat" 1 "$([[ -n "$CID" ]] && echo 1 || echo 0)"
form "$TMP/staff.jar" "/staff/kategori/1" /staff/kategori --form-string "_method=DELETE" >/dev/null
check "kategori berisi alat tidak bisa dihapus" 1 "$($MYSQL "SELECT COUNT(*) FROM categories WHERE id=1")"

echo "== Alat + upload gambar"
echo 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' | base64 -d > "$TMP/ok.png"
printf '<?php echo "pwned"; ?>' > "$TMP/evil.jpg"
r="$(form "$TMP/staff.jar" /staff/alat /staff/alat/baru --form-string "category_id=$CID" --form-string "name=Botol Lipat" --form-string "stock_total=5" --form-string "daily_rate=4000" --form-string "is_active=1" -F "image=@$(winpath "$TMP/evil.jpg");type=image/jpeg")"
check "file PHP berkedok .jpg ditolak" "302 /staff/alat/baru" "$r"
curl -s -b "$TMP/staff.jar" "$BASE/staff/alat/baru" -o "$TMP/e.html"; contains "pesan jenis file" "harus berupa gambar JPG, PNG, atau WEBP" "$TMP/e.html"
check "tidak ada alat tersimpan" 0 "$($MYSQL "SELECT COUNT(*) FROM equipment WHERE name='Botol Lipat'")"
r="$(form "$TMP/staff.jar" /staff/alat /staff/alat/baru --form-string "category_id=999" --form-string "name=Botol Lipat" --form-string "stock_total=-1" --form-string "daily_rate=abc")"
check "kategori/stok/tarif tidak valid ditolak" "302 /staff/alat/baru" "$r"
r="$(form "$TMP/staff.jar" /staff/alat /staff/alat/baru --form-string "category_id=$CID" --form-string "name=Botol Lipat" --form-string "stock_total=5" --form-string "daily_rate=4000" --form-string "is_active=1" -F "image=@$(winpath "$TMP/ok.png");type=image/png")"
check "alat + gambar dibuat" "302 /staff/alat" "$r"
EID="$($MYSQL "SELECT id FROM equipment WHERE slug='botol-lipat'")"
IMG="$($MYSQL "SELECT image FROM equipment WHERE id=$EID")"
check "nama file acak .png" 1 "$([[ "$IMG" =~ ^equipment/[a-f0-9]{32}\.png$ ]] && echo 1 || echo 0)"
check "gambar tersaji" 200 "$(status "$BASE/uploads/$IMG")"
check "alat baru tampil di katalog" 200 "$(status "$BASE/alat/botol-lipat")"
check "audit equipment.create" 1 "$($MYSQL "SELECT COUNT(*) FROM audit_logs WHERE action='equipment.create' AND entity_id=$EID")"
mkdir -p "$ROOT/public/uploads/equipment"; cp "$TMP/evil.jpg" "$ROOT/public/uploads/equipment/zzprobe.php"
check "skrip di folder uploads tidak dieksekusi" 404 "$(status "$BASE/uploads/equipment/zzprobe.php")"
rm -f "$ROOT/public/uploads/equipment/zzprobe.php"

# Stok tidak boleh di bawah pemakaian booking mendatang
C="$(book "$TMP/g.jar" "$(d +3)" "$(d +4)" --data "items[$EID]=4")"
r="$(form "$TMP/staff.jar" "/staff/alat/$EID" "/staff/alat/$EID/edit" --form-string "_method=PUT" --form-string "category_id=$CID" --form-string "name=Botol Lipat" --form-string "stock_total=3" --form-string "daily_rate=4000" --form-string "is_active=1")"
check "stok < booking mendatang ditolak" "302 /staff/alat/$EID/edit" "$r"
curl -s -b "$TMP/staff.jar" "$BASE/staff/alat/$EID/edit" -o "$TMP/e.html"; contains "pesan stok" "tidak boleh kurang dari 4 unit" "$TMP/e.html"
r="$(form "$TMP/staff.jar" "/staff/alat/$EID" "/staff/alat/$EID/edit" --form-string "_method=PUT" --form-string "category_id=$CID" --form-string "name=Botol Lipat" --form-string "stock_total=6" --form-string "daily_rate=5000" --form-string "remove_image=1")"
check "ubah tarif, nonaktifkan, hapus gambar" "302 /staff/alat" "$r"
check "status nonaktif + gambar null + tarif baru" "0 NULL 5000.00" "$($MYSQL "SELECT is_active, IFNULL(image,'NULL'), daily_rate FROM equipment WHERE id=$EID" | tr '\t' ' ')"
check "file gambar lama dihapus" 0 "$([[ -f "$ROOT/public/uploads/$IMG" ]] && echo 1 || echo 0)"
check "alat nonaktif hilang dari katalog" 404 "$(status "$BASE/alat/botol-lipat")"
check "tarif booking lama tetap (snapshot)" 4000.00 "$($MYSQL "SELECT bi.daily_rate FROM booking_items bi JOIN bookings b ON b.id=bi.booking_id WHERE b.code='$C'")"
form "$TMP/staff.jar" "/staff/alat/$EID" /staff/alat --form-string "_method=DELETE" >/dev/null
check "alat yang pernah disewa tidak bisa dihapus" 1 "$($MYSQL "SELECT COUNT(*) FROM equipment WHERE id=$EID")"
form "$TMP/staff.jar" /staff/alat /staff/alat/baru --form-string "category_id=$CID" --form-string "name=Filter Air" --form-string "stock_total=2" --form-string "daily_rate=6000" --form-string "is_active=1" >/dev/null
FID="$($MYSQL "SELECT id FROM equipment WHERE slug='filter-air'")"
form "$TMP/staff.jar" "/staff/alat/$FID" /staff/alat --form-string "_method=DELETE" >/dev/null
check "alat belum pernah disewa bisa dihapus" 0 "$($MYSQL "SELECT COUNT(*) FROM equipment WHERE id=$FID")"
check "GET ke rute PUT -> 405" 405 "$(status -b "$TMP/staff.jar" "$BASE/staff/alat/$EID")"
check "id bukan angka -> 404" 404 "$(status -b "$TMP/staff.jar" "$BASE/staff/alat/abc/edit")"
check "filter alat" 200 "$(status -b "$TMP/staff.jar" "$BASE/staff/alat?q=tenda&category=1&active=1")"

echo "== Paket"
r="$(form "$TMP/staff.jar" /staff/paket /staff/paket/baru --form-string "name=Paket Kosong" --form-string "discount_percent=10")"
check "paket tanpa isi ditolak" "302 /staff/paket/baru" "$r"
r="$(form "$TMP/staff.jar" /staff/paket /staff/paket/baru --form-string "name=Paket Diskon Salah" --form-string "discount_percent=150" --form-string "items[0][equipment_id]=1" --form-string "items[0][quantity]=1")"
check "diskon > 100 ditolak" "302 /staff/paket/baru" "$r"
form "$TMP/staff.jar" /staff/paket /staff/paket/baru --form-string "name=Paket Uji Hemat" --form-string "discount_percent=20" --form-string "is_active=1" \
  --form-string "items[0][equipment_id]=1" --form-string "items[0][quantity]=1" --form-string "items[1][equipment_id]=7" --form-string "items[1][quantity]=2" --form-string "items[2][equipment_id]=7" --form-string "items[2][quantity]=1" >/dev/null
PID="$($MYSQL "SELECT id FROM packages WHERE slug='paket-uji-hemat'")"
check "isi paket (alat sama digabung: matras x3)" "1:1|7:3" "$($MYSQL "SELECT CONCAT(equipment_id,':',quantity) FROM package_items WHERE package_id=$PID ORDER BY equipment_id" | paste -sd'|')"
curl -s "$BASE/paket" -o "$TMP/p.html"; contains "paket baru tampil publik" "Paket Uji Hemat" "$TMP/p.html"
check "set paket di API (matras 20/3=6, tenda 8)" 6 "$(curl -s "$BASE/api/availability?start=$(d +20)&end=$(d +20)" | json "packages.$PID")"
book "$TMP/gp.jar" "$(d +21)" "$(d +21)" --data "packages[$PID]=1" >/dev/null
form "$TMP/staff.jar" "/staff/paket/$PID" /staff/paket --form-string "_method=DELETE" >/dev/null
check "paket yang pernah dipesan tidak bisa dihapus" 1 "$($MYSQL "SELECT COUNT(*) FROM packages WHERE id=$PID")"
form "$TMP/staff.jar" /staff/paket /staff/paket/baru --form-string "name=Paket Sekali Pakai" --form-string "discount_percent=5" --form-string "items[0][equipment_id]=2" --form-string "items[0][quantity]=1" >/dev/null
PID="$($MYSQL "SELECT id FROM packages WHERE slug='paket-sekali-pakai'")"
form "$TMP/staff.jar" "/staff/paket/$PID" /staff/paket --form-string "_method=DELETE" >/dev/null
check "paket belum dipakai terhapus" 0 "$($MYSQL "SELECT COUNT(*) FROM packages WHERE id=$PID")"

echo "== Profil"
check "staff /akun -> /staff" "/staff" "$(curl -s -o /dev/null -w '%{redirect_url}' -b "$TMP/staff.jar" "$BASE/akun" | redir)"
check "profil user" 200 "$(status -b "$TMP/user.jar" "$BASE/akun/profil")"
check "profil staff (panel)" 200 "$(status -b "$TMP/staff.jar" "$BASE/akun/profil")"
TOKPATH=/akun/profil post "$TMP/user.jar" /akun/profil --data-urlencode "name=Pelanggan Demo" --data-urlencode "email=staff@demo.test" --data "phone=081200000003" >/dev/null
curl -s -b "$TMP/user.jar" "$BASE/akun/profil" -o "$TMP/pr.html"; contains "email milik orang lain ditolak" "Email sudah digunakan" "$TMP/pr.html"
TOKPATH=/akun/profil post "$TMP/user.jar" /akun/profil --data-urlencode "name=Pelanggan Demo Baru" --data-urlencode "email=user@demo.test" --data "phone=+62 812-0000-0003" --data-urlencode "address=Jl. Uji No. 1" >/dev/null
check "profil diperbarui + HP dinormalkan" "Pelanggan Demo Baru 081200000003 Jl. Uji No. 1" "$($MYSQL "SELECT name, phone, address FROM users WHERE email='user@demo.test'" | tr '\t' ' ')"
TOKPATH=/akun/profil post "$TMP/user.jar" /akun/password --data-urlencode "current_password=salah123" --data "password=BaruSekali1" --data "password_confirmation=BaruSekali1" >/dev/null
curl -s -b "$TMP/user.jar" "$BASE/akun/profil" -o "$TMP/pr.html"; contains "password lama salah ditolak" "Password saat ini salah" "$TMP/pr.html"
TOKPATH=/akun/profil post "$TMP/user.jar" /akun/password --data-urlencode "current_password=User#2026" --data "password=BaruSekali1" --data "password_confirmation=BaruSekali1" >/dev/null
check "password lama tidak berlaku lagi" 302 "$(login "$TMP/old.jar" user@demo.test 'User#2026' >/dev/null; status -b "$TMP/old.jar" "$BASE/akun")"
check "login dengan password baru" 200 "$(login "$TMP/u2.jar" user@demo.test BaruSekali1 >/dev/null; status -b "$TMP/u2.jar" "$BASE/akun")"
TOKPATH=/akun/profil post "$TMP/u2.jar" /akun/password --data-urlencode "current_password=BaruSekali1" --data "password=User#2026" --data "password_confirmation=User#2026" >/dev/null
check "password demo dikembalikan" 200 "$(login "$TMP/u3.jar" user@demo.test 'User#2026' >/dev/null; status -b "$TMP/u3.jar" "$BASE/akun")"

echo "== Admin: akun"
check "staff tidak bisa buka /admin/pengguna" 403 "$(status -b "$TMP/staff.jar" "$BASE/admin/pengguna")"
check "staff tidak bisa buka /admin/log" 403 "$(status -b "$TMP/staff.jar" "$BASE/admin/log")"
check "admin buka /admin/pengguna" 200 "$(status -b "$TMP/admin.jar" "$BASE/admin/pengguna?q=demo&role=staff&active=1")"
check "admin bisa akses panel staff" 200 "$(status -b "$TMP/admin.jar" "$BASE/staff/booking")"
TOKPATH=/admin/pengguna/baru post "$TMP/admin.jar" /admin/pengguna --data-urlencode "name=Staff Kedua" --data-urlencode "email=staff2@demo.test" --data "phone=081211112222" --data "role=staff" --data "password=StaffDua22" --data "password_confirmation=StaffDua22" >/dev/null
NID="$($MYSQL "SELECT id FROM users WHERE email='staff2@demo.test'")"
check "akun staff baru dibuat" staff "$($MYSQL "SELECT role FROM users WHERE id=$NID")"
TOKPATH=/admin/pengguna/baru post "$TMP/admin.jar" /admin/pengguna --data-urlencode "name=Duplikat" --data-urlencode "email=staff2@demo.test" --data "phone=081211112222" --data "role=user" --data "password=StaffDua22" --data "password_confirmation=StaffDua22" >/dev/null
check "email duplikat ditolak" 1 "$($MYSQL "SELECT COUNT(*) FROM users WHERE email='staff2@demo.test'")"
login "$TMP/s2.jar" staff2@demo.test StaffDua22
check "staff baru bisa login panel" 200 "$(status -b "$TMP/s2.jar" "$BASE/staff")"
TOKPATH="/admin/pengguna/$NID/edit" post "$TMP/admin.jar" "/admin/pengguna/$NID" --data "_method=PUT" --data-urlencode "name=Staff Kedua" --data-urlencode "email=staff2@demo.test" --data "role=staff" >/dev/null
check "dinonaktifkan" 0 "$($MYSQL "SELECT is_active FROM users WHERE id=$NID")"
check "sesi akun nonaktif langsung tidak berlaku" 302 "$(status -b "$TMP/s2.jar" "$BASE/staff")"
login "$TMP/s3.jar" staff2@demo.test StaffDua22
curl -s -b "$TMP/s3.jar" "$BASE/login" -o "$TMP/l.html"; contains "pesan akun nonaktif" "Akun Anda dinonaktifkan" "$TMP/l.html"
TOKPATH="/admin/pengguna/1/edit" post "$TMP/admin.jar" "/admin/pengguna/1" --data "_method=PUT" --data-urlencode "name=Admin CampGear" --data-urlencode "email=admin@demo.test" --data "role=user" --data "is_active=1" >/dev/null
check "admin tidak bisa menurunkan dirinya" admin "$($MYSQL "SELECT role FROM users WHERE id=1")"
TOKPATH="/admin/pengguna/$NID/edit" post "$TMP/admin.jar" "/admin/pengguna/$NID/password" --data "password=ResetBaru99" --data "password_confirmation=ResetBaru99" >/dev/null
check "password diatur ulang" 1 "$("$PHP_BIN" -r 'echo (int) password_verify("ResetBaru99", $argv[1]);' "$($MYSQL "SELECT password_hash FROM users WHERE id=$NID")")"
CNAME="Pelanggan Demo" CEMAIL="user@demo.test" book "$TMP/u3.jar" "$(d +30)" "$(d +30)" --data "items[7]=1" >/dev/null
check "booking user tercatat" 1 "$($MYSQL "SELECT COUNT(*) FROM bookings WHERE user_id=3")"
TOKPATH=/admin/pengguna post "$TMP/admin.jar" "/admin/pengguna/3" --data "_method=DELETE" >/dev/null
check "akun dengan booking tidak dihapus" 1 "$($MYSQL "SELECT COUNT(*) FROM users WHERE id=3")"
TOKPATH=/admin/pengguna post "$TMP/admin.jar" "/admin/pengguna/1" --data "_method=DELETE" >/dev/null
check "admin tidak bisa menghapus dirinya" 1 "$($MYSQL "SELECT COUNT(*) FROM users WHERE id=1")"
TOKPATH=/admin/pengguna/baru post "$TMP/admin.jar" /admin/pengguna --data-urlencode "name=User Sementara" --data-urlencode "email=temp@demo.test" --data "phone=081299990000" --data "role=user" --data "password=Sementara1" --data "password_confirmation=Sementara1" >/dev/null
TID="$($MYSQL "SELECT id FROM users WHERE email='temp@demo.test'")"
TOKPATH=/admin/pengguna post "$TMP/admin.jar" "/admin/pengguna/$TID" --data "_method=DELETE" >/dev/null
check "akun tanpa riwayat terhapus" 0 "$($MYSQL "SELECT COUNT(*) FROM users WHERE id=$TID")"

echo "== Admin: log aktivitas"
curl -s -b "$TMP/admin.jar" "$BASE/admin/log" -o "$TMP/log.html"
contains "log menampilkan aksi staf" "equipment.create" "$TMP/log.html"
curl -s -b "$TMP/admin.jar" "$BASE/admin/log?group=user&user=1" -o "$TMP/log.html"
contains "filter log per kelompok" "user.create" "$TMP/log.html"
lacks "filter menyaring aksi lain" "equipment.create" "$TMP/log.html"
check "filter tanggal" 200 "$(status -b "$TMP/admin.jar" "$BASE/admin/log?from=$(d -1)&to=$(d +0)")"

echo "== Statistik"
check "halaman statistik" 200 "$(status "$BASE/statistik")"
check "API publik tanpa pendapatan" "null" "$(curl -s "$BASE/api/stats" | json revenue)"
check "API staff dengan pendapatan" 1 "$(curl -s -b "$TMP/staff.jar" "$BASE/api/stats?period=week&range=12" | json labels | "$PHP_BIN" -r 'echo count(json_decode(stream_get_contents(STDIN))) === 12 ? 1 : 0;')"
check "rentang API tidak valid -> 422" 422 "$(status "$BASE/api/stats?period=week&range=6")"
curl -s -b "$TMP/staff.jar" "$BASE/staff" -o "$TMP/dash.html"
contains "dashboard memuat Chart.js" "chart.umd.min.js" "$TMP/dash.html"
contains "dashboard agenda" "Jatuh tempo pengembalian" "$TMP/dash.html"

summary
