#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
MODE="${1:-backup}"

read_env_value() {
  key="$1"
  file="$2"
  [ -f "$file" ] || return 1
  value=$(awk -v wanted="$key" '
    /^[[:space:]]*#/ || /^[[:space:]]*$/ { next }
    {
      pos = index($0, "=")
      if (pos == 0) next
      k = substr($0, 1, pos - 1)
      v = substr($0, pos + 1)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", k)
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", v)
      if (k == wanted) found = v
    }
    END { if (found != "") print found }
  ' "$file")
  [ -n "$value" ] || return 1
  case "$value" in
    \"*\") value=${value#\"}; value=${value%\"} ;;
    \'*\') value=${value#\'}; value=${value%\'} ;;
  esac
  printf '%s' "$value"
}

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing required command: $1" >&2; exit 1; }
}

required_value() {
  key="$1"
  fallback="$2"
  value=$(read_env_value "$key" "$ENV_FILE" || read_env_value "$fallback" "$ENV_FILE" || true)
  case "$value" in ''|CHANGE_ME) echo "Missing required setting: $key (or $fallback)" >&2; exit 1 ;; esac
  printf '%s' "$value"
}

[ -f "$ENV_FILE" ] || { echo "Missing $ENV_FILE" >&2; exit 1; }
[ "$MODE" = "backup" ] || [ "$MODE" = "--check" ] || { echo "Usage: $0 [--check]" >&2; exit 1; }

DB_HOST=$(required_value 'backup.db.host' 'database.default.hostname')
DB_PORT=$(required_value 'backup.db.port' 'database.default.port')
DB_NAME=$(required_value 'backup.db.name' 'database.default.database')
DB_USER=$(required_value 'backup.db.user' 'database.default.username')
DB_PASS=$(required_value 'backup.db.pass' 'database.default.password')
RETENTION_DAYS=$(read_env_value 'backup.retentionDays' "$ENV_FILE" || printf '30')
OUTPUT_DIR=$(read_env_value 'backup.outputDir' "$ENV_FILE" || printf '%s' "$ROOT_DIR/writable/backups")

expr "$RETENTION_DAYS" : '^[0-9][0-9]*$' >/dev/null 2>&1 || { echo 'backup.retentionDays must be a non-negative integer' >&2; exit 1; }
case "$OUTPUT_DIR" in /*) ;; *) OUTPUT_DIR="$ROOT_DIR/$OUTPUT_DIR" ;; esac
mkdir -p "$OUTPUT_DIR"
[ -w "$OUTPUT_DIR" ] || { echo 'Backup output directory is not writable' >&2; exit 1; }

require_cmd mysqladmin
DEFAULTS_FILE=$(mktemp "${TMPDIR:-/tmp}/ekk-mysql.XXXXXX")
TMP_GZ=''
STATUS_FILE=''
trap 'rm -f "$DEFAULTS_FILE" ${TMP_GZ:+"$TMP_GZ"} ${STATUS_FILE:+"$STATUS_FILE"}' EXIT HUP INT TERM
chmod 600 "$DEFAULTS_FILE"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$DEFAULTS_FILE"
mysqladmin --defaults-extra-file="$DEFAULTS_FILE" ping --silent >/dev/null

if [ "$MODE" = "--check" ]; then
  echo 'Backup preflight passed.'
  exit 0
fi

require_cmd mysqldump
require_cmd gzip
require_cmd find
STAMP=$(date +%Y%m%d_%H%M%S)
OUT_FILE="$OUTPUT_DIR/${DB_NAME}_${STAMP}.sql.gz"
# Stream straight into a temporary compressed archive (no uncompressed copy
# on disk). POSIX sh has no pipefail, so mysqldump's success is recorded as
# a positive marker in a status file (a missing/empty marker = failure);
# gzip's failure is the pipeline status (caught by set -e).
TMP_GZ=$(mktemp "$OUTPUT_DIR/.ekk-dump.XXXXXX")
STATUS_FILE=$(mktemp "${TMPDIR:-/tmp}/ekk-dump-status.XXXXXX")
chmod 600 "$TMP_GZ"
{ mysqldump --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --quick --routines --triggers "$DB_NAME" && printf '%s\n' ok > "$STATUS_FILE"; } | gzip > "$TMP_GZ"
if [ "$(cat "$STATUS_FILE" 2>/dev/null)" != 'ok' ]; then
  echo 'mysqldump failed; no backup written' >&2
  exit 1
fi
gzip -t "$TMP_GZ"
[ "$(gzip -dc "$TMP_GZ" | head -c 1 | wc -c)" -gt 0 ] || { echo 'mysqldump produced no output' >&2; exit 1; }
mv "$TMP_GZ" "$OUT_FILE"
TMP_GZ=''
find "$OUTPUT_DIR" -type f -name "${DB_NAME}_*.sql.gz" -mtime +"$RETENTION_DAYS" -delete
echo "Backup created: $OUT_FILE"
