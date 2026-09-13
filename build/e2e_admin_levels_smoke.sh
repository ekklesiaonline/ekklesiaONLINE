#!/bin/sh
set -eu

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
COOKIE_FILE="/tmp/ekk_cookie.txt"
LOGIN_HTML="/tmp/ekk_login.html"
LOGIN_RESP="/tmp/ekk_login_resp.txt"
REPORT="/tmp/ekk_e2e_admin_levels_report.txt"

rm -f "$COOKIE_FILE" "$LOGIN_HTML" "$LOGIN_RESP" "$REPORT"

login_as() {
  email="$1"
  password="$2"

  curl -s -c "$COOKIE_FILE" "$BASE_URL/login" -o "$LOGIN_HTML"
  csrf_line=$(grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$LOGIN_HTML" || true)
  csrf_name=$(echo "$csrf_line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\1/')
  csrf_val=$(echo "$csrf_line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\2/')

  if [ -z "$csrf_name" ] || [ -z "$csrf_val" ]; then
    echo "ERROR: csrf token parse failed for $email" >> "$REPORT"
    return 1
  fi

  curl -s -i -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/login" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=$password" \
    --data-urlencode "$csrf_name=$csrf_val" > "$LOGIN_RESP"

  login_http=$(grep -Eo 'HTTP/1.1 (302|303|200|401|403)' "$LOGIN_RESP" | head -n1 || true)
  echo "LOGIN_${email}_HTTP=${login_http:-missing}" >> "$REPORT"
}

check_get() {
  label="$1"
  path="$2"
  code=$(curl -s -o /dev/null -w '%{http_code}' -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL$path")
  echo "${label}_HTTP=$code" >> "$REPORT"
}

# Province admin assertions
login_as "admin@ekklesiaRD.org" "password@123"
check_get "PROVINCE_ADMIN_PROVINCE_DASH" "/province/dashboard"
check_get "PROVINCE_ADMIN_DIOCESAN_CHART" "/chart-accounts"
check_get "PROVINCE_ADMIN_LITURGICAL_SOURCE" "/liturgical-source"

# Diocesan admin assertions
login_as "diocesanadmin@firstchurch.org" "password@123"
check_get "DIOCESAN_ADMIN_PROVINCE_DASH" "/province/dashboard"
check_get "DIOCESAN_ADMIN_DIOCESAN_CHART" "/chart-accounts"
check_get "DIOCESAN_ADMIN_LITURGICAL_SOURCE" "/liturgical-source"

echo "EXPECTED: province dashboard should be non-404 and diocesan chart/liturgical routes should be non-404 for diocesan admin." >> "$REPORT"
cat "$REPORT"
