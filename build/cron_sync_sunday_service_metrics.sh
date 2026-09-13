#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

FROM_DATE="$(date +%Y-01-01)"
TO_DATE="$(date +%F)"

php spark attendance:sync-sunday-service-metrics --from "$FROM_DATE" --to "$TO_DATE"
