#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Applies reconciliation of expired/revoked clergy office and delegation
# grants: removes their synced Shield permissions automatically.
# Recommended cron: hourly (or at least daily), e.g.
#   15 * * * * /path/to/ekklesiaONLINE/build/clergy_reconcile_grants_cron.sh
PHP_BIN="${PHP_BIN:-php}"
"$PHP_BIN" spark clergy:reconcile-grants --apply >> writable/logs/clergy_reconcile_grants_cron.log 2>&1
