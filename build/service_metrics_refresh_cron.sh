#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Recommended cron: daily 01:30 server-local, e.g.
#   30 1 * * * PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash /path/to/ekklesiaONLINE/build/service_metrics_refresh_cron.sh
# PHP_BIN must point at a PHP 8.5 binary: bare `php` on HostGator is 8.3, and
# spark's version gate then exits 0 without refreshing anything.
# --rolling-days is resolved inside the command in the app timezone and its
# start is snapped to the 1st of the month, so buckets are never rebuilt from
# a partial month and the end date always matches the guard's "today".
PHP_BIN="${PHP_BIN:-php}"
"$PHP_BIN" spark service-metrics:refresh --rolling-days 35 --rebuild-period-buckets >> writable/logs/service_metrics_refresh_cron.log 2>&1
