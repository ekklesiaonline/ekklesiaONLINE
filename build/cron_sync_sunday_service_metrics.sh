#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

FROM_DATE="$(date +%Y-01-01)"
TO_DATE="$(date +%F)"

PHP_BIN="${PHP_BIN:-php}"
"$PHP_BIN" spark attendance:sync-sunday-service-metrics --from "$FROM_DATE" --to "$TO_DATE"
