#!/usr/bin/env bash
set -euo pipefail

# End-to-end smoke flow for modular integration.
# 1) Seed data
# 2) Boot local server
# 3) Exercise key module routes in sequence

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

PORT="${E2E_PORT:-8099}"
BASE_URL="http://127.0.0.1:${PORT}"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]] && kill -0 "$SERVER_PID" >/dev/null 2>&1; then
    kill "$SERVER_PID" >/dev/null 2>&1 || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

echo "[1/4] Seeding baseline data..."
php spark db:seed DatabaseSeeder >/dev/null

echo "[2/4] Starting local server on ${BASE_URL} ..."
php spark serve --host 127.0.0.1 --port "$PORT" > writable/logs/e2e_smoke_server.log 2>&1 &
SERVER_PID=$!

for i in {1..20}; do
  if curl -sS -o /dev/null "$BASE_URL/"; then
    break
  fi
  sleep 1
done

echo "[3/4] Exercising key routes..."
check_route() {
  local path="$1"
  local expected_regex="$2"

  local status
  status="$(curl -sS -o /dev/null -w "%{http_code}" "$BASE_URL$path" || true)"
  if [[ ! "$status" =~ $expected_regex ]]; then
    echo "Route check failed: $path returned HTTP $status (expected $expected_regex)"
    return 1
  fi

  echo "  OK $path -> $status"
}

# Public/login and protected module routes should return either 200 (public) or redirect/unauthorized class without crashing.
check_route "/" "^(200|302)$"
check_route "/login" "^200$"
check_route "/dashboard" "^(200|302|303|401|403)$"
check_route "/clergy/dashboard" "^(200|302|303|401|403)$"
check_route "/province/dashboard" "^(200|302|303|401|403)$"
check_route "/members" "^(200|302|303|401|403)$"
check_route "/services" "^(200|302|303|401|403)$"
check_route "/attendance/reports" "^(200|302|303|401|403)$"
check_route "/donations" "^(200|302|303|401|403)$"
check_route "/expenses" "^(200|302|303|401|403)$"
check_route "/volunteers" "^(200|302|303|401|403)$"
check_route "/chart-accounts" "^(200|302|303|401|403)$"
check_route "/liturgical-source" "^(200|302|303|401|403)$"

echo "[4/4] Smoke flow complete."
echo "Server log: writable/logs/e2e_smoke_server.log"
