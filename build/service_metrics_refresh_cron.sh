#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="/Users/juanrosariodelacruz/Sites/ekklesiaRD"
cd "$ROOT_DIR"

php spark service-metrics:refresh --rolling-days 35 --rebuild-period-buckets >> writable/logs/service_metrics_refresh_cron.log 2>&1
