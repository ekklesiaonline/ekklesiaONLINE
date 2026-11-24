#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Recommended cron: daily 01:30 server-local, e.g.
#   30 1 * * * /path/to/ekklesiaONLINE/build/service_metrics_refresh_cron.sh
php spark service-metrics:refresh --rolling-days 35 --rebuild-period-buckets >> writable/logs/service_metrics_refresh_cron.log 2>&1
