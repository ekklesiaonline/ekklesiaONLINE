#!/usr/bin/env sh
set -eu

# Automated MySQL backup with retention pruning.
# Reads settings from .env dotted keys when available.

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

read_env_value() {
  key="$1"
  file="$2"
  if [ ! -f "$file" ]; then
    return 1
  fi

  value=$(awk -F= -v wanted="$key" '
    function ltrim(s) { sub(/^[[:space:]]+/, "", s); return s }
    function rtrim(s) { sub(/[[:space:]]+$/, "", s); return s }
    {
      line = $0
      line = ltrim(line)
      if (line ~ /^#/ || line == "") {
        next
      }

      pos = index(line, "=")
      if (pos == 0) {
        next
      }

      k = rtrim(ltrim(substr(line, 1, pos - 1)))
      v = rtrim(ltrim(substr(line, pos + 1)))

      if (k == wanted) {
        found = v
      }
    }
    END {
      if (found != "") {
        print found
      }
    }
  ' "$file")

  if [ -z "$value" ]; then
    return 1
  fi

  case "$value" in
    \"*\")
      value=${value#\"}
      value=${value%\"}
      ;;
    \'*\')
      value=${value#\'}
      value=${value%\'}
      ;;
  esac

  printf "%s" "$value"
}

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing required command: $1"
    exit 1
  fi
}

DB_HOST="$(read_env_value 'backup.db.host' "$ENV_FILE" || read_env_value 'database.default.hostname' "$ENV_FILE" || printf 'localhost')"
DB_PORT="$(read_env_value 'backup.db.port' "$ENV_FILE" || read_env_value 'database.default.port' "$ENV_FILE" || printf '3306')"
DB_NAME="$(read_env_value 'backup.db.name' "$ENV_FILE" || read_env_value 'database.default.database' "$ENV_FILE" || printf 'ekklesiaRD')"
DB_USER="$(read_env_value 'backup.db.user' "$ENV_FILE" || read_env_value 'database.default.username' "$ENV_FILE" || printf 'root')"
DB_PASS="$(read_env_value 'backup.db.pass' "$ENV_FILE" || read_env_value 'database.default.password' "$ENV_FILE" || printf '')"
RETENTION_DAYS="$(read_env_value 'backup.retentionDays' "$ENV_FILE" || printf '30')"
OUTPUT_DIR="$(read_env_value 'backup.outputDir' "$ENV_FILE" || printf '%s' "$ROOT_DIR/writable/backups")"

case "$OUTPUT_DIR" in
  /*) ;;
  *) OUTPUT_DIR="$ROOT_DIR/$OUTPUT_DIR" ;;
esac

if ! expr "$RETENTION_DAYS" : '^[0-9][0-9]*$' >/dev/null 2>&1; then
  RETENTION_DAYS='30'
fi

require_cmd mysqldump
require_cmd gzip
require_cmd find

mkdir -p "$OUTPUT_DIR"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT_FILE="$OUTPUT_DIR/${DB_NAME}_${STAMP}.sql.gz"

if [ -z "$DB_PASS" ]; then
  mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --single-transaction --quick --routines --triggers "$DB_NAME" | gzip > "$OUT_FILE"
else
  mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" --single-transaction --quick --routines --triggers "$DB_NAME" | gzip > "$OUT_FILE"
fi

find "$OUTPUT_DIR" -type f -name "${DB_NAME}_*.sql.gz" -mtime +"$RETENTION_DAYS" -delete

echo "Backup created: $OUT_FILE"
