#!/usr/bin/env sh
set -eu

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
LOGIN_EMAIL="${LOGIN_EMAIL:-admin@ekklesiaRD.org}"
LOGIN_PASSWORD="${LOGIN_PASSWORD:-password@123}"

TMP_DIR="${TMPDIR:-/tmp}/ekk_targeted_smoke.$$"
COOKIE_FILE="$TMP_DIR/cookies.txt"
LOGIN_HTML="$TMP_DIR/login.html"
LOGIN_RESP="$TMP_DIR/login_response.txt"
HOME_HTML="$TMP_DIR/home.html"
MEMBERS_HTML="$TMP_DIR/members.html"
ATTENDANCE_HTML="$TMP_DIR/attendance.html"
DONATIONS_HTML="$TMP_DIR/donations.html"
MEMBERS_LIST_HTML="$TMP_DIR/members_list.html"
SERVICES_LIST_HTML="$TMP_DIR/services_list.html"
VOL_TEAMS_HTML="$TMP_DIR/volunteer_teams.html"
VOL_SCHEDULES_HTML="$TMP_DIR/volunteer_schedules.html"
AJAX1_JSON="$TMP_DIR/attendance_mark.json"
AJAX2_JSON="$TMP_DIR/donation_delete.json"
AJAX3_JSON="$TMP_DIR/member_attach.json"
AJAX4_JSON="$TMP_DIR/followup_update_status.json"
AJAX5_JSON="$TMP_DIR/service_delete.json"
AJAX6_JSON="$TMP_DIR/expense_delete.json"
KEEPALIVE_UNAUTH_JSON="$TMP_DIR/keepalive_unauth.json"
STAY_LOGGED_IN_BLOCK="$TMP_DIR/stay_logged_in_block.js"
REPORT="$TMP_DIR/report.txt"

mkdir -p "$TMP_DIR"
cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

fetch_login_page() {
  curl -sS --connect-timeout 5 --max-time 20 -c "$COOKIE_FILE" "$BASE_URL/login" -o "$LOGIN_HTML"
}

parse_login_csrf() {
  csrf_line=$(grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$LOGIN_HTML" || true)
  csrf_name=$(echo "$csrf_line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\1/')
  csrf_val=$(echo "$csrf_line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\2/')

  if [ -z "${csrf_name:-}" ] || [ -z "${csrf_val:-}" ]; then
    echo "ERROR: unable to parse login CSRF token" >&2
    exit 1
  fi
}

login() {
  curl -sS -i --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/login" \
    --data-urlencode "email=$LOGIN_EMAIL" \
    --data-urlencode "password=$LOGIN_PASSWORD" \
    --data-urlencode "$csrf_name=$csrf_val" > "$LOGIN_RESP"
}

extract_page_csrf() {
  curl -sS --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/dashboard" -o "$HOME_HTML" || true

  ajax_csrf_token=$(grep -m1 -o '<meta name="csrf-token" content="[A-Za-z0-9]*">' "$HOME_HTML" | sed -E 's#<meta name="csrf-token" content="([A-Za-z0-9]*)">#\1#' || true)
  form_csrf_val=$(grep -m1 -o "name=\"$csrf_name\" value=\"[A-Za-z0-9]*\"" "$HOME_HTML" | sed -E "s/name=\"$csrf_name\" value=\"([A-Za-z0-9]*)\"/\1/" || true)

  if [ -z "${ajax_csrf_token:-}" ]; then
    ajax_csrf_token="$csrf_val"
  fi
  if [ -z "${form_csrf_val:-}" ]; then
    form_csrf_val="$csrf_val"
  fi
}

assert_json_error_shape() {
  file="$1"
  label="$2"

  has_success=$(grep -c '"success"[[:space:]]*:[[:space:]]*false' "$file" || true)
  has_message=$(grep -c '"message"[[:space:]]*:' "$file" || true)

  echo "${label}_HAS_SUCCESS_FALSE=$has_success" >> "$REPORT"
  echo "${label}_HAS_MESSAGE=$has_message" >> "$REPORT"
}

fetch_login_page
parse_login_csrf
login
extract_page_csrf

login_http=$(grep -Eo 'HTTP/[0-9.]+ (302|303|200|401|403)' "$LOGIN_RESP" | head -n1 || true)
echo "LOGIN_HTTP=${login_http:-missing}" > "$REPORT"

members_http=$(curl -sS -o "$MEMBERS_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/members/create")
attendance_http=$(curl -sS -o "$ATTENDANCE_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/attendance/reports")
donations_http=$(curl -sS -o "$DONATIONS_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/donations/create")

# Re-resolve CSRF from the first accessible app page (members/attendance/donations),
# then fall back to dashboard/login values.
for page in "$MEMBERS_HTML" "$ATTENDANCE_HTML" "$DONATIONS_HTML" "$HOME_HTML"; do
  if [ -z "${ajax_csrf_token:-}" ] || [ "$ajax_csrf_token" = "$csrf_val" ]; then
    token_candidate=$(grep -m1 -o '<meta name="csrf-token" content="[A-Za-z0-9]*">' "$page" | sed -E 's#<meta name="csrf-token" content="([A-Za-z0-9]*)">#\1#' || true)
    [ -n "$token_candidate" ] && ajax_csrf_token="$token_candidate"
  fi

  if [ -z "${form_csrf_val:-}" ] || [ "$form_csrf_val" = "$csrf_val" ]; then
    form_candidate=$(grep -m1 -o "name=\"$csrf_name\" value=\"[A-Za-z0-9]*\"" "$page" | sed -E "s/name=\"$csrf_name\" value=\"([A-Za-z0-9]*)\"/\1/" || true)
    [ -n "$form_candidate" ] && form_csrf_val="$form_candidate"
  fi
done

echo "MEMBERS_CREATE_HTTP=$members_http" >> "$REPORT"
echo "ATTENDANCE_REPORTS_HTTP=$attendance_http" >> "$REPORT"
echo "DONATIONS_CREATE_HTTP=$donations_http" >> "$REPORT"

# Session keepalive regression guard:
# 1) keepalive without auth must return JSON 401 (not login redirect HTML)
# 2) stayLoggedIn() catch path must hard-redirect to /login in the rendered dashboard script
keepalive_unauth_http=$(curl -sS -o "$KEEPALIVE_UNAUTH_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 "$BASE_URL/session/keepalive")
keepalive_unauth_success_false=$(grep -c '"success"[[:space:]]*:[[:space:]]*false' "$KEEPALIVE_UNAUTH_JSON" || true)
keepalive_unauth_code=$(grep -c '"code"[[:space:]]*:[[:space:]]*"UNAUTHENTICATED"' "$KEEPALIVE_UNAUTH_JSON" || true)

awk '/function stayLoggedIn\(\)/,/\.finally\(function\(\)/ { print }' "$HOME_HTML" > "$STAY_LOGGED_IN_BLOCK" || true
stay_logged_in_redirect_login=$(grep -Ec "window\.location\.href[[:space:]]*=[[:space:]]*'http://[^']*/login'" "$STAY_LOGGED_IN_BLOCK" || true)
stay_logged_in_catch_toast=$(grep -c 'showAppToast' "$STAY_LOGGED_IN_BLOCK" || true)

echo "SESSION_KEEPALIVE_UNAUTH_HTTP=$keepalive_unauth_http" >> "$REPORT"
echo "SESSION_KEEPALIVE_UNAUTH_JSON_SUCCESS_FALSE=$keepalive_unauth_success_false" >> "$REPORT"
echo "SESSION_KEEPALIVE_UNAUTH_JSON_CODE_UNAUTHENTICATED=$keepalive_unauth_code" >> "$REPORT"
echo "SESSION_STAY_LOGGED_IN_REDIRECT_LOGIN_PRESENT=$stay_logged_in_redirect_login" >> "$REPORT"
echo "SESSION_STAY_LOGGED_IN_CATCH_SHOWAPPTOAST_COUNT=$stay_logged_in_catch_toast" >> "$REPORT"

# Duplicate-submit UX guard presence check in rendered form pages.
guard_members=$(grep -c 'Submission in progress. Please wait.' "$MEMBERS_HTML" || true)
guard_donations=$(grep -c 'Submission in progress. Please wait.' "$DONATIONS_HTML" || true)
processing_members=$(grep -c 'Processing...' "$MEMBERS_HTML" || true)
processing_donations=$(grep -c 'Processing...' "$DONATIONS_HTML" || true)

echo "DUPLICATE_GUARD_MEMBERS_PRESENT=$guard_members" >> "$REPORT"
echo "DUPLICATE_GUARD_DONATIONS_PRESENT=$guard_donations" >> "$REPORT"
echo "PROCESSING_LABEL_MEMBERS_PRESENT=$processing_members" >> "$REPORT"
echo "PROCESSING_LABEL_DONATIONS_PRESENT=$processing_donations" >> "$REPORT"

# Pagination polish checks: per-page selector and query-preserving pagination links.
members_list_http=$(curl -sS -o "$MEMBERS_LIST_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/members?status=active&per_page=50&page=2")
services_list_http=$(curl -sS -o "$SERVICES_LIST_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/services?per_page=50&page=2")
vol_teams_http=$(curl -sS -o "$VOL_TEAMS_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/volunteers?per_page=50&page=2")
vol_schedules_http=$(curl -sS -o "$VOL_SCHEDULES_HTML" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL/volunteers/schedules?per_page=50&page=2")

echo "MEMBERS_LIST_HTTP=$members_list_http" >> "$REPORT"
echo "SERVICES_LIST_HTTP=$services_list_http" >> "$REPORT"
echo "VOL_TEAMS_HTTP=$vol_teams_http" >> "$REPORT"
echo "VOL_SCHEDULES_HTTP=$vol_schedules_http" >> "$REPORT"

members_per_page_selector=$(grep -c 'name="per_page"' "$MEMBERS_LIST_HTML" || true)
services_per_page_selector=$(grep -c 'name="per_page"' "$SERVICES_LIST_HTML" || true)
vol_teams_per_page_selector=$(grep -c 'name="per_page"' "$VOL_TEAMS_HTML" || true)
vol_schedules_per_page_selector=$(grep -c 'name="per_page"' "$VOL_SCHEDULES_HTML" || true)

members_preserve_query_links=$(grep -Ec 'status=active(&|&amp;)per_page=50(&|&amp;)page=' "$MEMBERS_LIST_HTML" || true)
services_preserve_query_links=$(grep -Ec 'per_page=50(&|&amp;)page=' "$SERVICES_LIST_HTML" || true)
vol_teams_preserve_query_links=$(grep -Ec 'per_page=50(&|&amp;)page=' "$VOL_TEAMS_HTML" || true)
vol_schedules_preserve_query_links=$(grep -Ec 'per_page=50(&|&amp;)page=' "$VOL_SCHEDULES_HTML" || true)

echo "MEMBERS_PER_PAGE_SELECTOR_PRESENT=$members_per_page_selector" >> "$REPORT"
echo "SERVICES_PER_PAGE_SELECTOR_PRESENT=$services_per_page_selector" >> "$REPORT"
echo "VOL_TEAMS_PER_PAGE_SELECTOR_PRESENT=$vol_teams_per_page_selector" >> "$REPORT"
echo "VOL_SCHEDULES_PER_PAGE_SELECTOR_PRESENT=$vol_schedules_per_page_selector" >> "$REPORT"

echo "MEMBERS_PAGINATION_PRESERVES_QUERY=$members_preserve_query_links" >> "$REPORT"
echo "SERVICES_PAGINATION_PRESERVES_QUERY=$services_preserve_query_links" >> "$REPORT"
echo "VOL_TEAMS_PAGINATION_PRESERVES_QUERY=$vol_teams_preserve_query_links" >> "$REPORT"
echo "VOL_SCHEDULES_PAGINATION_PRESERVES_QUERY=$vol_schedules_preserve_query_links" >> "$REPORT"

# Targeted AJAX endpoint checks for normalized JSON error payloads + statuses.
attendance_mark_http=$(curl -sS -o "$AJAX1_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/attendance/mark" \
  -H "X-CSRF-TOKEN: $ajax_csrf_token" \
  --data-urlencode "$csrf_name=$form_csrf_val" \
  --data-urlencode "service_id=9999999" \
  --data-urlencode "user_id=1" \
  --data-urlencode "attended=1" \
  --data-urlencode "communicant=1")

donation_delete_http=$(curl -sS -o "$AJAX2_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/donations/delete/9999999" \
  -H "X-CSRF-TOKEN: $ajax_csrf_token" \
  --data-urlencode "$csrf_name=$form_csrf_val")

member_attach_http=$(curl -sS -o "$AJAX3_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/members/attach-parish" \
  -H "X-CSRF-TOKEN: $ajax_csrf_token" \
  --data-urlencode "$csrf_name=$form_csrf_val" \
  --data-urlencode "profile_id=9999999" \
  --data-urlencode "church_id=1" \
  --data-urlencode "membership_status=active" \
  --data-urlencode "is_home=1")

followup_update_http=$(curl -sS -o "$AJAX4_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/followup/update-status/9999999" \
  -H "X-CSRF-TOKEN: $ajax_csrf_token" \
  --data-urlencode "$csrf_name=$form_csrf_val" \
  --data-urlencode "status=completed")

service_delete_http=$(curl -sS -o "$AJAX5_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/services/delete/9999999" \
  -H "X-CSRF-TOKEN: $ajax_csrf_token" \
  --data-urlencode "$csrf_name=$form_csrf_val")

expense_delete_http=$(curl -sS -o "$AJAX6_JSON" -w '%{http_code}' --connect-timeout 5 --max-time 20 -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/expenses/delete/9999999" \
  -H "X-CSRF-TOKEN: $ajax_csrf_token" \
  --data-urlencode "$csrf_name=$form_csrf_val")

echo "ATTENDANCE_MARK_HTTP=$attendance_mark_http" >> "$REPORT"
echo "DONATION_DELETE_HTTP=$donation_delete_http" >> "$REPORT"
echo "MEMBER_ATTACH_HTTP=$member_attach_http" >> "$REPORT"
echo "FOLLOWUP_UPDATE_HTTP=$followup_update_http" >> "$REPORT"
echo "SERVICE_DELETE_HTTP=$service_delete_http" >> "$REPORT"
echo "EXPENSE_DELETE_HTTP=$expense_delete_http" >> "$REPORT"

assert_json_error_shape "$AJAX1_JSON" "ATTENDANCE_MARK"
assert_json_error_shape "$AJAX2_JSON" "DONATION_DELETE"
assert_json_error_shape "$AJAX3_JSON" "MEMBER_ATTACH"
assert_json_error_shape "$AJAX4_JSON" "FOLLOWUP_UPDATE"
assert_json_error_shape "$AJAX5_JSON" "SERVICE_DELETE"
assert_json_error_shape "$AJAX6_JSON" "EXPENSE_DELETE"

session_modal_present=$(grep -c 'id="sessionExpiryModal"' "$HOME_HTML" || true)
session_keepalive_url_present=$(grep -c '/session/keepalive' "$HOME_HTML" || true)

echo "SESSION_MODAL_PRESENT=$session_modal_present" >> "$REPORT"
echo "SESSION_KEEPALIVE_URL_PRESENT=$session_keepalive_url_present" >> "$REPORT"

if [ "$session_modal_present" -gt 0 ] && [ "$session_keepalive_url_present" -gt 0 ]; then
  session_regression_failures=0

  if [ "$keepalive_unauth_http" != "401" ]; then
    echo "SESSION_REGRESSION_FAIL=expected keepalive unauth HTTP 401, got $keepalive_unauth_http" >> "$REPORT"
    session_regression_failures=$((session_regression_failures + 1))
  fi

  if [ "$keepalive_unauth_success_false" -lt 1 ] || [ "$keepalive_unauth_code" -lt 1 ]; then
    echo "SESSION_REGRESSION_FAIL=keepalive unauth response missing JSON error contract" >> "$REPORT"
    session_regression_failures=$((session_regression_failures + 1))
  fi

  if [ "$stay_logged_in_redirect_login" -lt 1 ]; then
    echo "SESSION_REGRESSION_FAIL=stayLoggedIn catch missing login redirect" >> "$REPORT"
    session_regression_failures=$((session_regression_failures + 1))
  fi

  if [ "$stay_logged_in_catch_toast" -ne 0 ]; then
    echo "SESSION_REGRESSION_FAIL=stayLoggedIn catch should not call showAppToast" >> "$REPORT"
    session_regression_failures=$((session_regression_failures + 1))
  fi

  if [ "$session_regression_failures" -gt 0 ]; then
    cat "$REPORT"
    echo "SESSION_KEEPALIVE_REGRESSION_CHECK=FAILED" >&2
    exit 1
  fi

  echo "SESSION_KEEPALIVE_REGRESSION_CHECK=PASSED" >> "$REPORT"
else
  echo "SESSION_KEEPALIVE_REGRESSION_CHECK=SKIPPED" >> "$REPORT"
fi

cat "$REPORT"
