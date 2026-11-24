#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# No --from/--to: the command's default window (Jan 1 -> today) is computed
# in the app timezone, the same one MonthRangeGuard validates against. Shell
# `date` uses the server timezone and can disagree near midnight.
PHP_BIN="${PHP_BIN:-php}"
"$PHP_BIN" spark attendance:sync-sunday-service-metrics
