#!/bin/sh
set -eu

: "${BASE_URL:?Set BASE_URL to the staging application URL}"
: "${LOGIN_EMAIL:?Set LOGIN_EMAIL}"
: "${LOGIN_PASSWORD:?Set LOGIN_PASSWORD}"
MEMBERS_EMAIL="${MEMBERS_EMAIL:-$LOGIN_EMAIL}"
MEMBERS_PASSWORD="${MEMBERS_PASSWORD:-$LOGIN_PASSWORD}"
FAMILIES_EMAIL="${FAMILIES_EMAIL:-$LOGIN_EMAIL}"
FAMILIES_PASSWORD="${FAMILIES_PASSWORD:-$LOGIN_PASSWORD}"
FOLLOWUP_EMAIL="${FOLLOWUP_EMAIL:-$LOGIN_EMAIL}"
FOLLOWUP_PASSWORD="${FOLLOWUP_PASSWORD:-$LOGIN_PASSWORD}"
STRICT="${STRICT:-1}"

# Additional families contract codes intentionally tracked in contract tests:
# FAMILY_MEMBER_ALREADY_EXISTS
# FAMILY_MEMBER_ALREADY_IN_ANOTHER_FAMILY
# FAMILY_RELATIONSHIP_INVALID

COOKIE_FILE="/tmp/ekk_mff_cookie.txt"
LOGIN_HTML="/tmp/ekk_mff_login.html"
AUTH_HTML="/tmp/ekk_mff_auth.html"
RESP_MEMBERS="/tmp/ekk_mff_members_resp.json"
RESP_FAMILIES="/tmp/ekk_mff_families_resp.json"
RESP_FOLLOWUP="/tmp/ekk_mff_followup_resp.json"
REPORT="/tmp/ekk_mff_smoke_report.txt"

rm -f "$COOKIE_FILE" "$LOGIN_HTML" "$AUTH_HTML" "$RESP_MEMBERS" "$RESP_FAMILIES" "$RESP_FOLLOWUP" "$REPORT"

parse_csrf_from_html() {
  html_file="$1"
  line=$(grep -m1 -o 'name="[A-Za-z0-9_]*" value="[A-Za-z0-9]*"' "$html_file" || true)
  csrf_name=$(echo "$line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\1/')
  csrf_val=$(echo "$line" | sed -E 's/name="([A-Za-z0-9_]*)" value="([A-Za-z0-9]*)"/\2/')
  echo "${csrf_name}|${csrf_val}"
}

json_error_code() {
  file="$1"
  grep -o '"code"[[:space:]]*:[[:space:]]*"[A-Z0-9_]*"' "$file" | head -n1 | sed -E 's/.*"([A-Z0-9_]+)"/\1/'
}

json_has_contract_error() {
  file="$1"
  has_error=$(grep -c '"error"[[:space:]]*:' "$file" || true)
  has_status=$(grep -c '"status"[[:space:]]*:[[:space:]]*"error"' "$file" || true)
  if [ "$has_error" -gt 0 ] && [ "$has_status" -gt 0 ]; then
    echo "1"
    return
  fi
  echo "0"
}

login() {
  email="$1"
  pass="$2"

  curl -s -c "$COOKIE_FILE" "$BASE_URL/login" -o "$LOGIN_HTML"
  csrf_pair=$(parse_csrf_from_html "$LOGIN_HTML")
  csrf_name=$(echo "$csrf_pair" | cut -d'|' -f1)
  csrf_val=$(echo "$csrf_pair" | cut -d'|' -f2)

  if [ -z "$csrf_name" ] || [ -z "$csrf_val" ]; then
    echo "0"
    return
  fi

  curl -s -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$BASE_URL/login" \
    --data-urlencode "email=$email" \
    --data-urlencode "password=$pass" \
    --data-urlencode "$csrf_name=$csrf_val" >/dev/null

  echo "1"
}

refresh_csrf_for_page() {
  page="$1"
  curl -s -b "$COOKIE_FILE" "$BASE_URL$page" -o "$AUTH_HTML"
  auth_http=$(curl -s -o /dev/null -w '%{http_code}' -b "$COOKIE_FILE" "$BASE_URL$page")
  pair=$(parse_csrf_from_html "$AUTH_HTML")
  form_name=$(echo "$pair" | cut -d'|' -f1)
  form_val=$(echo "$pair" | cut -d'|' -f2)
  header_val=$(grep -m1 -o '<meta name="csrf-token" content="[A-Za-z0-9]*">' "$AUTH_HTML" | sed -E 's#<meta name="csrf-token" content="([A-Za-z0-9]*)">#\1#' || true)
  if [ -z "$header_val" ]; then
    header_val="$form_val"
  fi
  echo "${auth_http}|${form_name}|${form_val}|${header_val}"
}

run_case() {
  case_name="$1"
  email="$2"
  pass="$3"
  preflight_page="$4"
  endpoint="$5"
  expected_code="$6"
  response_file="$7"
  extra_args="$8"

  login_ok=$(login "$email" "$pass")
  if [ "$login_ok" != "1" ]; then
    echo "${case_name}_AUTH_HTTP=0" >> "$REPORT"
    echo "${case_name}_ERROR_CODE=" >> "$REPORT"
    echo "${case_name}_CONTRACT_OK=0" >> "$REPORT"
    return
  fi

  csrf_triplet=$(refresh_csrf_for_page "$preflight_page")
  auth_http=$(echo "$csrf_triplet" | cut -d'|' -f1)
  form_name=$(echo "$csrf_triplet" | cut -d'|' -f2)
  form_val=$(echo "$csrf_triplet" | cut -d'|' -f3)
  header_val=$(echo "$csrf_triplet" | cut -d'|' -f4)

  http=0
  if [ "$case_name" = "MEMBERS" ]; then
    http=$(curl -s -o "$response_file" -w '%{http_code}' -b "$COOKIE_FILE" -X POST "$BASE_URL$endpoint" \
      -H 'X-Requested-With: XMLHttpRequest' \
      -H "X-CSRF-TOKEN: $header_val" \
      --data-urlencode "$form_name=$form_val" \
      --data-urlencode 'profile_id=999999' \
      --data-urlencode 'parish_id=1')
  elif [ "$case_name" = "FAMILIES" ]; then
    http=$(curl -s -o "$response_file" -w '%{http_code}' -b "$COOKIE_FILE" -X POST "$BASE_URL$endpoint" \
      -H 'X-Requested-With: XMLHttpRequest' \
      -H "X-CSRF-TOKEN: $header_val" \
      --data-urlencode "$form_name=$form_val")
  else
    http=$(curl -s -o "$response_file" -w '%{http_code}' -b "$COOKIE_FILE" -X POST "$BASE_URL$endpoint" \
      -H 'X-Requested-With: XMLHttpRequest' \
      -H "X-CSRF-TOKEN: $header_val" \
      --data-urlencode "$form_name=$form_val" \
      --data-urlencode 'status=completed')
  fi

  code=$(json_error_code "$response_file")
  contract=$(json_has_contract_error "$response_file")

  echo "${case_name}_AUTH_HTTP=$auth_http" >> "$REPORT"
  echo "${case_name}_HTTP=$http" >> "$REPORT"
  echo "${case_name}_EXPECTED_CODE=$expected_code" >> "$REPORT"
  echo "${case_name}_ERROR_CODE=$code" >> "$REPORT"
  echo "${case_name}_CONTRACT_OK=$contract" >> "$REPORT"
}

run_case "MEMBERS" "$MEMBERS_EMAIL" "$MEMBERS_PASSWORD" "/members" "/members/detach-parish" "MEMBER_NOT_FOUND_IN_SCOPE" "$RESP_MEMBERS" ""
run_case "FAMILIES" "$FAMILIES_EMAIL" "$FAMILIES_PASSWORD" "/families" "/families/delete/999999" "FAMILY_NOT_FOUND_IN_SCOPE" "$RESP_FAMILIES" ""
run_case "FOLLOWUP" "$FOLLOWUP_EMAIL" "$FOLLOWUP_PASSWORD" "/followup" "/followup/updateStatus/999999" "FOLLOWUP_TASK_NOT_FOUND" "$RESP_FOLLOWUP" ""

cat "$REPORT"

if [ "$STRICT" != "1" ]; then
  exit 0
fi

failed=0

members_auth=$(grep '^MEMBERS_AUTH_HTTP=' "$REPORT" | cut -d'=' -f2)
members_code=$(grep '^MEMBERS_ERROR_CODE=' "$REPORT" | cut -d'=' -f2)
members_contract=$(grep '^MEMBERS_CONTRACT_OK=' "$REPORT" | cut -d'=' -f2)
[ "$members_auth" = "200" ] || failed=1
[ "$members_contract" = "1" ] || failed=1
[ "$members_code" = "MEMBER_NOT_FOUND_IN_SCOPE" ] || failed=1

families_auth=$(grep '^FAMILIES_AUTH_HTTP=' "$REPORT" | cut -d'=' -f2)
families_code=$(grep '^FAMILIES_ERROR_CODE=' "$REPORT" | cut -d'=' -f2)
families_contract=$(grep '^FAMILIES_CONTRACT_OK=' "$REPORT" | cut -d'=' -f2)
[ "$families_auth" = "200" ] || failed=1
[ "$families_contract" = "1" ] || failed=1
[ "$families_code" = "FAMILY_NOT_FOUND_IN_SCOPE" ] || failed=1

followup_auth=$(grep '^FOLLOWUP_AUTH_HTTP=' "$REPORT" | cut -d'=' -f2)
followup_code=$(grep '^FOLLOWUP_ERROR_CODE=' "$REPORT" | cut -d'=' -f2)
followup_contract=$(grep '^FOLLOWUP_CONTRACT_OK=' "$REPORT" | cut -d'=' -f2)
[ "$followup_auth" = "200" ] || failed=1
[ "$followup_contract" = "1" ] || failed=1
[ "$followup_code" = "FOLLOWUP_TASK_NOT_FOUND" ] || failed=1

if [ "$failed" -ne 0 ]; then
  exit 1
fi

exit 0
