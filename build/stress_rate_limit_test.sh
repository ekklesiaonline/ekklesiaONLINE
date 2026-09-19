#!/usr/bin/env sh
set -eu

: "${BASE_URL:?Set BASE_URL to the staging application URL}"
: "${LOGIN_EMAIL:?Set LOGIN_EMAIL}"
: "${LOGIN_PASSWORD:?Set LOGIN_PASSWORD}"
REQUESTS="${REQUESTS:-30}"
TARGET_PATH="${TARGET_PATH:-province/create-admin}"
CURL_CONNECT_TIMEOUT="${CURL_CONNECT_TIMEOUT:-5}"
CURL_MAX_TIME="${CURL_MAX_TIME:-20}"
DASHBOARD_PATH="${DASHBOARD_PATH:-province/dashboard?period=month}"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ekk-rate-limit.XXXXXX")"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM
COOKIE_FILE="$TMP_DIR/cookie.txt"
LOGIN_HTML="$TMP_DIR/login.html"
LOGIN_RESP="$TMP_DIR/login-response.txt"
DASH_HTML="$TMP_DIR/dashboard.html"
RESP_BODY="$TMP_DIR/response.json"
RESP_HDR="$TMP_DIR/response-headers.txt"
REPORT="$TMP_DIR/report.txt"

extract_csrf_name() {
  file="$1"
  grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$file" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\1/'
}

extract_csrf_value() {
  file="$1"
  grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$file" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\2/'
}

extract_ajax_token() {
  file="$1"
  grep -m1 -o '<meta name="csrf-token" content="[A-Za-z0-9]*">' "$file" | sed -E 's#<meta name="csrf-token" content="([A-Za-z0-9]*)">#\1#'
}

extract_retry_after() {
  file="$1"
  awk 'tolower($1) == "retry-after:" { gsub(/\r/, "", $2); print $2; exit }' "$file"
}

curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -c "$COOKIE_FILE" "$BASE_URL/login" -o "$LOGIN_HTML"
csrf_name="$(extract_csrf_name "$LOGIN_HTML" || true)"
csrf_val="$(extract_csrf_value "$LOGIN_HTML" || true)"

if [ -z "$csrf_name" ] || [ -z "$csrf_val" ]; then
  echo "ERROR: unable to parse login CSRF token"
  head -n 40 "$LOGIN_HTML" || true
  exit 1
fi

curl -s -i --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/login" \
  --data-urlencode "email=$LOGIN_EMAIL" \
  --data-urlencode "password=$LOGIN_PASSWORD" \
  --data-urlencode "$csrf_name=$csrf_val" > "$LOGIN_RESP"

if ! grep -Eq 'HTTP/1.1 (302|303|200)' "$LOGIN_RESP"; then
  echo "ERROR: login failed"
  cat "$LOGIN_RESP"
  exit 1
fi

total_429=0
retry_after_seen=0
i=1
run_stamp="$(date +%s)"

while [ "$i" -le "$REQUESTS" ]; do
  dashboard_status="$(curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -w '%{http_code}' -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/$DASHBOARD_PATH" -o "$DASH_HTML")"

  csrf_name="$(extract_csrf_name "$DASH_HTML" || true)"
  csrf_val="$(extract_csrf_value "$DASH_HTML" || true)"
  ajax_token="$(extract_ajax_token "$DASH_HTML" || true)"
  [ -z "$ajax_token" ] && ajax_token="$csrf_val"

  if [ -z "$csrf_name" ] || [ -z "$csrf_val" ]; then
    echo "ERROR: unable to parse dashboard CSRF token on iteration $i (status=$dashboard_status path=$DASHBOARD_PATH)"
    head -n 40 "$DASH_HTML" || true
    exit 1
  fi

  diocese_id="$(grep -m1 -o 'option value="[0-9]\+"' "$DASH_HTML" | sed -E 's/option value="([0-9]+)"/\1/' || true)"
  [ -z "$diocese_id" ] && diocese_id=103

  email="rate.limit.test.${run_stamp}.${i}@example.org"

  http_code="$(curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -D "$RESP_HDR" -o "$RESP_BODY" -w '%{http_code}' -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/$TARGET_PATH" \
    -H "X-CSRF-TOKEN: $ajax_token" \
    --data-urlencode "$csrf_name=$csrf_val" \
    --data-urlencode "diocese_id=$diocese_id" \
    --data-urlencode "first_name=Rate" \
    --data-urlencode "last_name=Limiter" \
    --data-urlencode "email=invalid-email" \
    --data-urlencode "password=short" \
    --data-urlencode "role=diocesanadmin")"

  retry_after="$(extract_retry_after "$RESP_HDR" || true)"

  if [ "$http_code" = "429" ]; then
    total_429=$((total_429 + 1))
    if [ -n "$retry_after" ]; then
      retry_after_seen=$((retry_after_seen + 1))
    fi
  fi

  echo "REQ_$i HTTP=$http_code RETRY_AFTER=${retry_after:-none}" >> "$REPORT"
  i=$((i + 1))
done

{
  echo "TOTAL_REQUESTS=$REQUESTS"
  echo "TOTAL_429=$total_429"
  echo "RETRY_AFTER_ON_429=$retry_after_seen"
} >> "$REPORT"

cat "$REPORT"

if [ "$total_429" -lt 1 ]; then
  echo "ASSERTION_FAILED: expected at least one 429 response"
  exit 1
fi

if [ "$retry_after_seen" -lt 1 ]; then
  echo "ASSERTION_FAILED: expected Retry-After header on throttled responses"
  exit 1
fi

echo "ASSERTION_OK: observed 429 and Retry-After under burst load."