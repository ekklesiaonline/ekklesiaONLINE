#!/usr/bin/env sh
set -eu

: "${BASE_URL:?Set BASE_URL to the staging application URL}"
: "${PROVINCE_ADMIN_EMAIL:?Set PROVINCE_ADMIN_EMAIL}"
: "${PROVINCE_ADMIN_PASSWORD:?Set PROVINCE_ADMIN_PASSWORD}"
: "${DIOCESAN_ADMIN_EMAIL:?Set DIOCESAN_ADMIN_EMAIL}"
: "${DIOCESAN_ADMIN_PASSWORD:?Set DIOCESAN_ADMIN_PASSWORD}"

CURL_CONNECT_TIMEOUT="${CURL_CONNECT_TIMEOUT:-5}"
CURL_MAX_TIME="${CURL_MAX_TIME:-20}"
TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ekk-admin-smoke.XXXXXX")"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM
COOKIE_FILE="$TMP_DIR/cookie.txt"
LOGIN_HTML="$TMP_DIR/login.html"
LOGIN_RESP="$TMP_DIR/login-response.txt"
REPORT="$TMP_DIR/report.txt"

login_as() {
  email="$1"
  password="$2"

  curl --fail --silent --show-error --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" \
    -c "$COOKIE_FILE" "$BASE_URL/login" -o "$LOGIN_HTML"
  csrf_line=$(grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$LOGIN_HTML" || true)
  csrf_name=$(printf '%s' "$csrf_line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\1/')
  csrf_val=$(printf '%s' "$csrf_line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\2/')

  if [ -z "$csrf_name" ] || [ -z "$csrf_val" ]; then
    echo "ERROR: unable to parse login CSRF token" >&2
    return 1
  fi

  curl --silent --show-error --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" \
    -i -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/login" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=$password" \
    --data-urlencode "$csrf_name=$csrf_val" > "$LOGIN_RESP"

  if ! grep -Eq 'HTTP/[0-9.]+ (200|302|303)' "$LOGIN_RESP"; then
    echo "ERROR: login failed" >&2
    return 1
  fi
}

check_get() {
  label="$1"
  path="$2"
  expected="$3"
  code=$(curl --silent --show-error --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" \
    -o /dev/null -w '%{http_code}' -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL$path")
  echo "${label}_HTTP=$code EXPECTED=$expected" >> "$REPORT"
  if [ "$code" != "$expected" ]; then
    echo "ERROR: $label returned $code; expected $expected" >&2
    return 1
  fi
}

login_as "$PROVINCE_ADMIN_EMAIL" "$PROVINCE_ADMIN_PASSWORD"
check_get "PROVINCE_ADMIN_PROVINCE_DASH" "/province/dashboard" "${PROVINCE_DASH_EXPECTED:-200}"
check_get "PROVINCE_ADMIN_DIOCESAN_CHART" "/chart-accounts" "${PROVINCE_CHART_EXPECTED:-403}"
check_get "PROVINCE_ADMIN_LITURGICAL_SOURCE" "/liturgical-source" "${PROVINCE_LITURGY_EXPECTED:-403}"

login_as "$DIOCESAN_ADMIN_EMAIL" "$DIOCESAN_ADMIN_PASSWORD"
check_get "DIOCESAN_ADMIN_PROVINCE_DASH" "/province/dashboard" "${DIOCESAN_PROVINCE_DASH_EXPECTED:-403}"
check_get "DIOCESAN_ADMIN_DIOCESAN_CHART" "/chart-accounts" "${DIOCESAN_CHART_EXPECTED:-200}"
check_get "DIOCESAN_ADMIN_LITURGICAL_SOURCE" "/liturgical-source" "${DIOCESAN_LITURGY_EXPECTED:-200}"

cat "$REPORT"
echo "ASSERTION_OK: admin-level route expectations passed."
