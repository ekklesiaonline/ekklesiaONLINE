#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Applies reconciliation of expired/revoked clergy office and delegation
# grants: removes their synced Shield permissions automatically.
# Recommended cron: hourly (or at least daily), e.g.
#   15 * * * * /path/to/ekklesiaRD/build/clergy_reconcile_grants_cron.sh
php spark clergy:reconcile-grants --apply >> writable/logs/clergy_reconcile_grants_cron.log 2>&1
