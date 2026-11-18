# Fungsi bersama untuk skrip uji CampGear (di-source oleh setiap suite).
# Variabel lingkungan: BASE (URL server uji), TEST_DB, PHP_BIN, MYSQL_BIN — diatur oleh run-all.sh.
BASE="${BASE:-http://127.0.0.1:8001}"
TEST_DB="${TEST_DB:-campgear_test}"
PHP_BIN="${PHP_BIN:-/d/xampp/php/php.exe}"
MYSQL_BIN="${MYSQL_BIN:-/d/xampp/mysql/bin/mysql.exe}"
ROOT="${ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
TMP="$(mktemp -d)"
PASS=0; FAIL=0; FAILS=()

# mysql.exe di Windows menulis \r\n; \r dibuang agar perbandingan teks akurat.
sqlq() { "$MYSQL_BIN" -u root "$TEST_DB" -N -B -e "$1" | tr -d '\r'; }
MYSQL=sqlq

check() { if [[ "$2" == "$3" ]]; then PASS=$((PASS+1)); echo "  ok   $1 ($3)"; else FAIL=$((FAIL+1)); FAILS+=("$1: expected [$2] got [$3]"); echo "  FAIL $1: expected [$2] got [$3]"; fi; }
contains() { if grep -q -- "$2" "$3"; then PASS=$((PASS+1)); echo "  ok   $1"; else FAIL=$((FAIL+1)); FAILS+=("$1: [$2] tidak ditemukan"); echo "  FAIL $1: [$2] not found"; fi; }
lacks() { if grep -q -- "$2" "$3"; then FAIL=$((FAIL+1)); FAILS+=("$1: [$2] seharusnya tidak ada"); echo "  FAIL $1: [$2] should be absent"; else PASS=$((PASS+1)); echo "  ok   $1"; fi; }
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
token() { grep -o 'name="_token" value="[a-f0-9]*"' "$1" | head -1 | sed 's/.*value="\([a-f0-9]*\)"/\1/'; }
d() { date -d "$1 day" +%F; }
json() { "$PHP_BIN" -r '$d=json_decode(stream_get_contents(STDIN),true); foreach(explode(".",$argv[1]) as $x){$d=$d[$x]??null;} echo is_scalar($d)?$d:json_encode($d);' "$1"; }
redir() { sed 's#https\?://[^/]*##'; }
# Path file untuk curl -F: curl build Windows (mingw) butuh path Windows.
winpath() { if command -v cygpath >/dev/null; then cygpath -w "$1"; else echo "$1"; fi; }

# login JAR EMAIL PASSWORD
login() {
  curl -s -c "$1" -b "$1" "$BASE/login" -o "$TMP/lf.html"
  curl -s -c "$1" -b "$1" -o /dev/null --data-urlencode "_token=$(token "$TMP/lf.html")" \
    --data-urlencode "email=$2" --data-urlencode "password=$3" "$BASE/login"
}
# tok JAR PATH -> token CSRF dari halaman
tok() { curl -s -b "$1" -c "$1" "$BASE$2" -o "$TMP/t.html"; token "$TMP/t.html"; }
# post JAR PATH args... -> "kode redirect" (token diambil dari TOKPATH atau PATH)
post() { local jar="$1" path="$2"; shift 2; local t; t="$(tok "$jar" "${TOKPATH:-$path}")"
  curl -s -b "$jar" -c "$jar" -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "_token=$t" "$@" "$BASE$path" | redir; }
# book JAR START END args... -> kode booking
book() { local jar="$1" s="$2" e="$3"; shift 3
  TOKPATH=/sewa post "$jar" /sewa --data "start_date=$s" --data "end_date=$e" --data-urlencode "customer_name=${CNAME:-Tamu Uji}" \
    --data-urlencode "customer_email=${CEMAIL:-tamu@contoh.test}" --data "customer_phone=081233334444" "$@" | sed -n 's#^302 /booking/##p'; }
summary() { echo; echo "HASIL: $PASS lulus, $FAIL gagal"; for f in "${FAILS[@]}"; do echo "  - $f"; done; rm -rf "$TMP"; }
