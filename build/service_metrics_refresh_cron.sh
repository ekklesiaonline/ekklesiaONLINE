#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Recommended cron: daily 01:30 server-local, e.g.
#   30 1 * * * /path/to/ekklesiaONLINE/build/service_metrics_refresh_cron.sh
# Start at the 1st of the previous month so month-keyed buckets are never
# rebuilt from a partial month (a mid-month --rolling-days start truncates them).
FROM_DATE="$(date -d "$(date +%Y-%m-01) -1 month" +%Y-%m-01)"
PHP_BIN="${PHP_BIN:-php}"
"$PHP_BIN" spark service-metrics:refresh --from "$FROM_DATE" --to "$(date +%Y-%m-%d)" --rebuild-period-buckets >> writable/logs/service_metrics_refresh_cron.log 2>&1
