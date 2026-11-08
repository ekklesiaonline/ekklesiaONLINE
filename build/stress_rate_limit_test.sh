#!/usr/bin/env sh
set -eu

# Staging rate-limit smoke test.
#
# Runs three probes against a non-production deployment:
#   PHASE=auth   authenticated burst on a rate-limited write path (apiRateLimit)
#   PHASE=login  burst of failed logins on POST /login (loginRateLimit)
#   PHASE=anon   unauthenticated burst on a rate-limited write path
#
# Output is a sanitized report on stdout: status codes and Retry-After
# values only. No response headers, cookies, CSRF tokens, or bodies are
# ever printed.
#
# Required env: BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD
# Optional env: REQUESTS, TARGET_PATH, LOGIN_REQUESTS, ANON_REQUESTS,
#               ANON_PATH, DIOCESE_ID, DASHBOARD_PATH,
#               CURL_CONNECT_TIMEOUT, CURL_MAX_TIME, BAD_LOGIN_PASSWORD

: "${BASE_URL:?Set BASE_URL to the staging application URL}"
: "${LOGIN_EMAIL:?Set LOGIN_EMAIL}"
: "${LOGIN_PASSWORD:?Set LOGIN_PASSWORD}"
REQUESTS="${REQUESTS:-30}"
TARGET_PATH="${TARGET_PATH:-province/create-admin}"
LOGIN_REQUESTS="${LOGIN_REQUESTS:-12}"
ANON_REQUESTS="${ANON_REQUESTS:-30}"
ANON_PATH="${ANON_PATH:-$TARGET_PATH}"
DIOCESE_ID="${DIOCESE_ID:-}"
DASHBOARD_PATH="${DASHBOARD_PATH:-province/dashboard?period=month}"
CURL_CONNECT_TIMEOUT="${CURL_CONNECT_TIMEOUT:-5}"
CURL_MAX_TIME="${CURL_MAX_TIME:-20}"
BAD_LOGIN_PASSWORD="${BAD_LOGIN_PASSWORD:-rate-limit-probe-invalid-password}"

TMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ekk-rate-limit.XXXXXX")"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM
COOKIE_FILE="$TMP_DIR/cookie.txt"
LOGIN_HTML="$TMP_DIR/login.html"
LOGIN_RESP="$TMP_DIR/login-response.txt"
DASH_HTML="$TMP_DIR/dashboard.html"
RESP_BODY="$TMP_DIR/response.json"
RESP_HDR="$TMP_DIR/response-headers.txt"
REPORT="$TMP_DIR/report.txt"
: > "$REPORT"

log() {
  printf '%s\n' "$1" >> "$REPORT"
}

err() {
  printf 'ERROR: %s\n' "$1" >> "$REPORT"
  cat "$REPORT"
  printf 'ERROR: %s\n' "$1" >&2
  exit 1
}

extract_csrf_name() {
  grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$1" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\1/'
}

extract_csrf_value() {
  grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$1" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\2/'
}

extract_ajax_token() {
  grep -m1 -o '<meta name="csrf-token" content="[A-Za-z0-9]*">' "$1" | sed -E 's#<meta name="csrf-token" content="([A-Za-z0-9]*)">#\1#'
}

extract_retry_after() {
  awk 'tolower($1) == "retry-after:" { gsub(/\r/, "", $2); print $2; exit }' "$1"
}

fetch_login_csrf() {
  curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -c "$1" "$BASE_URL/login" -o "$LOGIN_HTML"
  csrf_name="$(extract_csrf_name "$LOGIN_HTML" || true)"
  csrf_val="$(extract_csrf_value "$LOGIN_HTML" || true)"
  [ -n "$csrf_name" ] && [ -n "$csrf_val" ]
}

# --- Authenticate ------------------------------------------------------------

if ! fetch_login_csrf "$COOKIE_FILE"; then
  err "unable to parse login CSRF token"
fi

curl -s -i --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/login" \
  --data-urlencode "email=$LOGIN_EMAIL" \
  --data-urlencode "password=$LOGIN_PASSWORD" \
  --data-urlencode "$csrf_name=$csrf_val" > "$LOGIN_RESP"

if ! grep -Eq 'HTTP/[0-9.]+ (302|303|200)' "$LOGIN_RESP"; then
  err "login failed (non-success status)"
fi

run_stamp="$(date +%s)"

# --- PHASE=auth: authenticated write burst on TARGET_PATH --------------------

auth_429=0
auth_retry=0
i=1
diocese_warned=0

while [ "$i" -le "$REQUESTS" ]; do
  dashboard_status="$(curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -w '%{http_code}' -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/$DASHBOARD_PATH" -o "$DASH_HTML")"

  csrf_name="$(extract_csrf_name "$DASH_HTML" || true)"
  csrf_val="$(extract_csrf_value "$DASH_HTML" || true)"
  ajax_token="$(extract_ajax_token "$DASH_HTML" || true)"
  [ -z "$ajax_token" ] && ajax_token="$csrf_val"

  if [ -z "$csrf_name" ] || [ -z "$csrf_val" ]; then
    err "unable to parse dashboard CSRF token on auth iteration $i (status=$dashboard_status path=$DASHBOARD_PATH)"
  fi

  if [ -n "$DIOCESE_ID" ]; then
    diocese_id="$DIOCESE_ID"
  else
    diocese_id="$(grep -m1 -o 'option value="[0-9]\+"' "$DASH_HTML" | sed -E 's/option value="([0-9]+)"/\1/' || true)"
  fi
  if [ -z "$diocese_id" ]; then
    diocese_id=103
    if [ "$diocese_warned" -eq 0 ]; then
      log "WARN: diocese_id not found in dashboard markup; falling back to 103"
      diocese_warned=1
    fi
  fi

  http_code="$(curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -D "$RESP_HDR" -o "$RESP_BODY" -w '%{http_code}' -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/$TARGET_PATH" \
    -H "X-CSRF-TOKEN: $ajax_token" \
    --data-urlencode "$csrf_name=$csrf_val" \
    --data-urlencode "diocese_id=$diocese_id" \
    --data-urlencode "first_name=Rate" \
    --data-urlencode "last_name=Limiter" \
    --data-urlencode "email=rate.limit.test.${run_stamp}.${i}@example.org" \
    --data-urlencode "password=short" \
    --data-urlencode "role=diocesanadmin")"

  retry_after="$(extract_retry_after "$RESP_HDR" || true)"

  if [ "$http_code" = "429" ]; then
    auth_429=$((auth_429 + 1))
    [ -n "$retry_after" ] && auth_retry=$((auth_retry + 1))
  fi

  log "PHASE=auth REQ_$i HTTP=$http_code RETRY_AFTER=${retry_after:-none}"
  i=$((i + 1))
done

# --- PHASE=login: failed-login burst on POST /login --------------------------

login_429=0
login_retry=0
login_cookie="$TMP_DIR/login-cookie.txt"
i=1

while [ "$i" -le "$LOGIN_REQUESTS" ]; do
  if ! fetch_login_csrf "$login_cookie"; then
    err "unable to parse login CSRF token on login iteration $i"
  fi

  http_code="$(curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -D "$RESP_HDR" -o "$RESP_BODY" -w '%{http_code}' -b "$login_cookie" -c "$login_cookie" -X POST "$BASE_URL/login" \
    --data-urlencode "email=$LOGIN_EMAIL" \
    --data-urlencode "password=$BAD_LOGIN_PASSWORD" \
    --data-urlencode "$csrf_name=$csrf_val")"

  retry_after="$(extract_retry_after "$RESP_HDR" || true)"

  if [ "$http_code" = "429" ]; then
    login_429=$((login_429 + 1))
    [ -n "$retry_after" ] && login_retry=$((login_retry + 1))
  fi

  log "PHASE=login REQ_$i HTTP=$http_code RETRY_AFTER=${retry_after:-none}"
  i=$((i + 1))
done

# --- PHASE=anon: unauthenticated burst on ANON_PATH --------------------------

anon_429=0
anon_retry=0
anon_success=0
i=1

while [ "$i" -le "$ANON_REQUESTS" ]; do
  http_code="$(curl -s --connect-timeout "$CURL_CONNECT_TIMEOUT" --max-time "$CURL_MAX_TIME" -D "$RESP_HDR" -o "$RESP_BODY" -w '%{http_code}' -X POST "$BASE_URL/$ANON_PATH" \
    --data-urlencode "probe=rate-limit-anon")"

  retry_after="$(extract_retry_after "$RESP_HDR" || true)"

  if [ "$http_code" = "429" ]; then
    anon_429=$((anon_429 + 1))
    [ -n "$retry_after" ] && anon_retry=$((anon_retry + 1))
  fi
  if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 400 ] && [ "$http_code" != "000" ]; then
    anon_success=$((anon_success + 1))
  fi

  log "PHASE=anon REQ_$i HTTP=$http_code RETRY_AFTER=${retry_after:-none}"
  i=$((i + 1))
done

# --- Totals and assertions ---------------------------------------------------

{
  echo "PHASE=auth   TOTAL_REQUESTS=$REQUESTS TOTAL_429=$auth_429 RETRY_AFTER_ON_429=$auth_retry"
  echo "PHASE=login  TOTAL_REQUESTS=$LOGIN_REQUESTS TOTAL_429=$login_429 RETRY_AFTER_ON_429=$login_retry"
  echo "PHASE=anon   TOTAL_REQUESTS=$ANON_REQUESTS TOTAL_429=$anon_429 RETRY_AFTER_ON_429=$anon_retry TOTAL_SUCCESS=$anon_success"
} >> "$REPORT"

failed=0

if [ "$auth_429" -lt 1 ] || [ "$auth_retry" -lt 1 ]; then
  echo "ASSERTION_FAILED: auth phase expected >=1 429 with Retry-After (got 429=$auth_429 retry=$auth_retry)" >> "$REPORT"
  failed=1
fi

if [ "$login_429" -lt 1 ] || [ "$login_retry" -lt 1 ]; then
  echo "ASSERTION_FAILED: login phase expected >=1 429 with Retry-After (got 429=$login_429 retry=$login_retry)" >> "$REPORT"
  failed=1
fi

if [ "$anon_success" -gt 0 ]; then
  echo "ASSERTION_FAILED: anon phase observed $anon_success non-error responses" >> "$REPORT"
  failed=1
fi

if [ "$failed" -eq 0 ]; then
  echo "ASSERTION_OK: 429+Retry-After observed on auth and login phases; no anonymous successes." >> "$REPORT"
fi

cat "$REPORT"
exit "$failed"
