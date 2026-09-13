#!/bin/sh
set -eu

# Lightweight helper for recurring performance governance review.
# Requires MYSQL_* environment variables when database commands are used.

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_DATABASE="${MYSQL_DATABASE:-ekklesiaRD}"
WINDOW_DAYS="${WINDOW_DAYS:-7}"

REPORT_DIR="${REPORT_DIR:-build/logs/performance}"
TS="$(date +%Y%m%d_%H%M%S)"
REPORT_FILE="$REPORT_DIR/perf_review_${TS}.md"

mkdir -p "$REPORT_DIR"

{
  echo "# Performance Review Snapshot"
  echo
  echo "Generated: $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
  echo "Window (days): $WINDOW_DAYS"
  echo
  echo "## Manual Inputs Required"
  echo
  echo "1. Attach top slow-query lines from DB logs for the window."
  echo "2. Attach EXPLAIN plans for top 10 expensive queries."
  echo "3. Record observed P95 endpoints and compare against SLA."
  echo
  echo "## SQL Snippets"
  echo
  echo "Use these snippets with mysql client when performance_schema is enabled:"
  echo
  echo "\`\`\`sql"
  echo "SELECT DIGEST_TEXT, COUNT_STAR, AVG_TIMER_WAIT/1000000000 AS avg_ms"
  echo "FROM performance_schema.events_statements_summary_by_digest"
  echo "ORDER BY AVG_TIMER_WAIT DESC"
  echo "LIMIT 10;"
  echo "\`\`\`"
  echo
  echo "## Outcome"
  echo
  echo "- [ ] SLA pass/fail documented"
  echo "- [ ] Regressions triaged"
  echo "- [ ] Follow-up owners assigned"
} > "$REPORT_FILE"

echo "Generated $REPORT_FILE"

# Optional best-effort quick DB connectivity check.
if command -v mysql >/dev/null 2>&1; then
  if [ -n "$MYSQL_PASSWORD" ]; then
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" "-p$MYSQL_PASSWORD" -D "$MYSQL_DATABASE" -e "SELECT 1" >/dev/null 2>&1 && \
      echo "MySQL connectivity: OK" || echo "MySQL connectivity: FAILED (non-blocking)"
  else
    mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -D "$MYSQL_DATABASE" -e "SELECT 1" >/dev/null 2>&1 && \
      echo "MySQL connectivity: OK" || echo "MySQL connectivity: FAILED (non-blocking)"
  fi
else
  echo "mysql client not found; skipping connectivity check"
fi
