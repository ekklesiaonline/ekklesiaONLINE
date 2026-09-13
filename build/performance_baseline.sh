#!/usr/bin/env sh
set -eu

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
LOGIN_EMAIL="${LOGIN_EMAIL:-admin@ekklesiaRD.org}"
LOGIN_PASSWORD="${LOGIN_PASSWORD:-password@123}"
REPEAT="${REPEAT:-3}"

TMP_DIR="${TMPDIR:-/tmp}/ekk_performance_baseline.$$"
COOKIE_FILE="$TMP_DIR/cookies.txt"
LOGIN_HTML="$TMP_DIR/login.html"
LOGIN_RESP="$TMP_DIR/login_response.txt"
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

benchmark_endpoint() {
  label="$1"
  path="$2"
  slug=$(echo "$label" | tr '[:upper:]' '[:lower:]' | tr ' /' '__')
  result_file="$TMP_DIR/$slug.tsv"

  i=1
  while [ "$i" -le "$REPEAT" ]; do
    curl -sS -o /dev/null -w '%{http_code}\t%{time_total}\n' \
      --connect-timeout 5 --max-time 30 \
      -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL$path" >> "$result_file"
    i=$((i + 1))
  done

  avg_ms=$(awk 'BEGIN { total = 0; count = 0 } { total += $2 * 1000; count++ } END { if (count == 0) print "0.00"; else printf "%.2f", total / count }' "$result_file")
  min_ms=$(awk 'NR == 1 { min = $2 * 1000 } { value = $2 * 1000; if (value < min) min = value } END { if (NR == 0) print "0.00"; else printf "%.2f", min }' "$result_file")
  max_ms=$(awk 'NR == 1 { max = $2 * 1000 } { value = $2 * 1000; if (value > max) max = value } END { if (NR == 0) print "0.00"; else printf "%.2f", max }' "$result_file")
  codes=$(awk '{ print $1 }' "$result_file" | sort | uniq | tr '\n' ',' | sed 's/,$//')

  echo "$label|$codes|$avg_ms|$min_ms|$max_ms" >> "$REPORT"
}

benchmark_endpoint_with_query() {
  label="$1"
  path_with_query="$2"
  slug=$(echo "$label" | tr '[:upper:]' '[:lower:]' | tr ' /' '__')
  result_file="$TMP_DIR/$slug.tsv"

  i=1
  while [ "$i" -le "$REPEAT" ]; do
    curl -sS -o /dev/null -w '%{http_code}\t%{time_total}\n' \
      --connect-timeout 5 --max-time 30 \
      -b "$COOKIE_FILE" -c "$COOKIE_FILE" "$BASE_URL$path_with_query" >> "$result_file"
    i=$((i + 1))
  done

  avg_ms=$(awk 'BEGIN { total = 0; count = 0 } { total += $2 * 1000; count++ } END { if (count == 0) print "0.00"; else printf "%.2f", total / count }' "$result_file")
  min_ms=$(awk 'NR == 1 { min = $2 * 1000 } { value = $2 * 1000; if (value < min) min = value } END { if (NR == 0) print "0.00"; else printf "%.2f", min }' "$result_file")
  max_ms=$(awk 'NR == 1 { max = $2 * 1000 } { value = $2 * 1000; if (value > max) max = value } END { if (NR == 0) print "0.00"; else printf "%.2f", max }' "$result_file")
  codes=$(awk '{ print $1 }' "$result_file" | sort | uniq | tr '\n' ',' | sed 's/,$//')

  echo "$label|$codes|$avg_ms|$min_ms|$max_ms" >> "$REPORT"
}

fetch_login_page
parse_login_csrf
login

login_http=$(grep -Eo 'HTTP/[0-9.]+ (302|303|200|401|403)' "$LOGIN_RESP" | head -n1 || true)
echo "LOGIN_HTTP=${login_http:-missing}"

start_date=$(date +%Y-01-01)
end_date=$(date +%Y-%m-%d)

: > "$REPORT"
benchmark_endpoint "Dashboard" "/dashboard"
benchmark_endpoint "Pastor Dashboard" "/pastor/dashboard"
benchmark_endpoint "Province Dashboard" "/province/dashboard"
benchmark_endpoint_with_query "Members" "/members?status=active&per_page=50"
benchmark_endpoint_with_query "Services" "/services?per_page=50"
benchmark_endpoint_with_query "Volunteers" "/volunteers?per_page=50"
benchmark_endpoint_with_query "Donations" "/donations?per_page=50"
benchmark_endpoint_with_query "Expenses" "/expenses?per_page=50"
benchmark_endpoint_with_query "Reports" "/reports?start_date=$start_date&end_date=$end_date"

echo "ENDPOINT|HTTP_CODES|AVG_MS|MIN_MS|MAX_MS"
cat "$REPORT"