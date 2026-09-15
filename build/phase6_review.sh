#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$ROOT_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_DIR="$ROOT_DIR/writable/logs"
REPORT_FILE="$LOG_DIR/phase6-review-$TIMESTAMP.log"

mkdir -p "$LOG_DIR"

BASE_URL="${BASE_URL:-}"
LOGIN_EMAIL="${LOGIN_EMAIL:-}"
LOGIN_PASSWORD="${LOGIN_PASSWORD:-}"
REPEAT="${REPEAT:-3}"
PARISH_ID="${PARISH_ID:-}"
START_DATE="${START_DATE:-}"
END_DATE="${END_DATE:-}"
ENABLE_SLOW_LOG="${ENABLE_SLOW_LOG:-1}"
SLOW_LOG_THRESHOLD_MS="${SLOW_LOG_THRESHOLD_MS:-200}"

tee_report() {
  printf '%s\n' "$1" | tee -a "$REPORT_FILE"
}

run_and_capture() {
  label="$1"
  shift

  tee_report ""
  tee_report "== $label =="

  set +e
  output=$("$@" 2>&1)
  exit_code=$?
  set -e

  printf '%s\n' "$output" | tee -a "$REPORT_FILE"
  tee_report "EXIT_CODE=$exit_code"

  return "$exit_code"
}

benchmark_args=""
if [ -n "$PARISH_ID" ]; then
  benchmark_args="$benchmark_args --parish $PARISH_ID"
fi
if [ -n "$START_DATE" ]; then
  benchmark_args="$benchmark_args --start-date $START_DATE"
fi
if [ -n "$END_DATE" ]; then
  benchmark_args="$benchmark_args --end-date $END_DATE"
fi
if [ "$ENABLE_SLOW_LOG" = "1" ]; then
  benchmark_args="$benchmark_args --enable-slow-log --slow-log-threshold-ms $SLOW_LOG_THRESHOLD_MS"
fi

tee_report "Phase 6 Optimization Review"
tee_report "Timestamp: $TIMESTAMP"
tee_report "Workspace: $ROOT_DIR"
tee_report "Report: $REPORT_FILE"

audit_status=0
benchmark_status=0
http_status=0

set +e
run_and_capture "Query Audit" php spark optimize:query-audit --strict
audit_status=$?

run_and_capture "Performance Benchmark" sh -c "php spark optimize:benchmark$benchmark_args"
benchmark_status=$?

if [ -n "$BASE_URL" ] && [ -n "$LOGIN_EMAIL" ] && [ -n "$LOGIN_PASSWORD" ]; then
  run_and_capture \
    "Authenticated HTTP Baseline" \
    env BASE_URL="$BASE_URL" LOGIN_EMAIL="$LOGIN_EMAIL" LOGIN_PASSWORD="$LOGIN_PASSWORD" REPEAT="$REPEAT" sh build/performance_baseline.sh
  http_status=$?
else
  tee_report ""
  tee_report "== Authenticated HTTP Baseline =="
  tee_report "SKIPPED: BASE_URL, LOGIN_EMAIL, and LOGIN_PASSWORD were not all provided."
fi
set -e

overall_status=0
if [ "$audit_status" -ne 0 ] || [ "$benchmark_status" -ne 0 ] || [ "$http_status" -ne 0 ]; then
  overall_status=1
fi

tee_report ""
tee_report "== Summary =="
tee_report "QUERY_AUDIT_EXIT=$audit_status"
tee_report "BENCHMARK_EXIT=$benchmark_status"
tee_report "HTTP_BASELINE_EXIT=$http_status"

if [ "$overall_status" -eq 0 ]; then
  tee_report "PHASE6_STATUS=GREEN"
else
  tee_report "PHASE6_STATUS=ATTENTION"
fi

tee_report ""
tee_report "Saved review snapshot to: $REPORT_FILE"

exit "$overall_status"
