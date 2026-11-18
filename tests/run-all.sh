#!/usr/bin/env bash
# =====================================================================
# Menjalankan seluruh pengujian end-to-end CampGear.
#
#   bash tests/run-all.sh                 semua suite
#   bash tests/run-all.sh smoke booking   suite tertentu
#
# Keamanan data: pengujian memakai database TERPISAH (default campgear_test)
# yang dibuat ulang sebelum setiap suite. Database utama tidak disentuh.
#
# Kebutuhan: Git Bash (Windows) atau bash, curl, PHP CLI, klien mysql, server MySQL aktif.
# Path default mengikuti XAMPP di D:\xampp; ubah lewat variabel lingkungan:
#   PHP_BIN=/c/xampp/php/php.exe MYSQL_BIN=/c/xampp/mysql/bin/mysql.exe bash tests/run-all.sh
# =====================================================================
set -u
DIR="$(cd "$(dirname "$0")" && pwd)"
export ROOT="$(cd "$DIR/.." && pwd)"
export PHP_BIN="${PHP_BIN:-/d/xampp/php/php.exe}"
export MYSQL_BIN="${MYSQL_BIN:-/d/xampp/mysql/bin/mysql.exe}"
export TEST_DB="${TEST_DB:-campgear_test}"
PORT="${TEST_PORT:-8001}"
export BASE="http://127.0.0.1:$PORT"

if [[ "$TEST_DB" == "campgear" ]]; then
  echo "TEST_DB tidak boleh sama dengan database utama." >&2; exit 1
fi
if [[ ! -f "$ROOT/config/config.php" ]]; then
  echo "config/config.php belum ada. Salin dari config.example.php terlebih dahulu." >&2; exit 1
fi

# Path untuk program Windows (PHP/MySQL XAMPP) bila berjalan di Git Bash.
wp() { if command -v cygpath >/dev/null; then cygpath -w "$1"; else echo "$1"; fi; }

# Konfigurasi uji: salinan config.php dengan nama database diganti.
WORK="$(mktemp -d)"
CONFIG="$WORK/config.testing.php"
"$PHP_BIN" -r '$c = require $argv[1]; $c["db"]["name"] = $argv[2]; $c["app"]["debug"] = true;
  file_put_contents($argv[3], "<?php\nreturn " . var_export($c, true) . ";\n");' \
  "$(wp "$ROOT/config/config.php")" "$TEST_DB" "$(wp "$CONFIG")"
export CAMPGEAR_CONFIG="$(wp "$CONFIG")"

reset_db() { "$PHP_BIN" "$ROOT/database/setup.php" --fresh >/dev/null; }

# Server uji (server bawaan PHP) dengan konfigurasi uji.
reset_db
"$PHP_BIN" -S "127.0.0.1:$PORT" -t "$ROOT/public" "$ROOT/public/index.php" >"$WORK/server.log" 2>&1 &
SERVER_PID=$!
trap 'kill $SERVER_PID 2>/dev/null; rm -rf "$WORK"' EXIT
for _ in $(seq 1 20); do curl -s -o /dev/null "$BASE/" && break; sleep 0.5; done

SUITES=("$@")
[[ ${#SUITES[@]} -eq 0 ]] && SUITES=(smoke booking operations crud race)

total_pass=0; total_fail=0
for suite in "${SUITES[@]}"; do
  reset_db
  echo "################ $suite"
  if [[ "$suite" == "race" ]]; then
    out="$(bash "$DIR/race.sh" 2>&1)"
  else
    out="$(bash "$DIR/$suite.sh" 2>&1)"
  fi
  echo "$out" | grep -v '^  ok'
  p=$(echo "$out" | sed -n 's/^HASIL: \([0-9]*\) lulus, \([0-9]*\) gagal/\1/p')
  f=$(echo "$out" | sed -n 's/^HASIL: \([0-9]*\) lulus, \([0-9]*\) gagal/\2/p')
  total_pass=$((total_pass + ${p:-0})); total_fail=$((total_fail + ${f:-1}))
done
reset_db

echo
echo "TOTAL: $total_pass lulus, $total_fail gagal"
[[ $total_fail -eq 0 ]]
