#!/usr/bin/env sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

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

[ "$#" -eq 1 ] || { echo "Usage: $0 --check | /path/to/backup.sql[.gz]" >&2; exit 1; }
[ -f "$ENV_FILE" ] || { echo "Missing $ENV_FILE" >&2; exit 1; }
MODE="$1"

DB_HOST=$(required_value 'backup.db.host' 'database.default.hostname')
DB_PORT=$(required_value 'backup.db.port' 'database.default.port')
DB_NAME=$(required_value 'backup.db.name' 'database.default.database')
DB_USER=$(required_value 'backup.db.user' 'database.default.username')
DB_PASS=$(required_value 'backup.db.pass' 'database.default.password')

require_cmd mysqladmin
DEFAULTS_FILE=$(mktemp "${TMPDIR:-/tmp}/ekk-mysql.XXXXXX")
trap 'rm -f "$DEFAULTS_FILE"' EXIT HUP INT TERM
chmod 600 "$DEFAULTS_FILE"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$DEFAULTS_FILE"
mysqladmin --defaults-extra-file="$DEFAULTS_FILE" ping --silent >/dev/null

if [ "$MODE" = "--check" ]; then
  echo 'Restore preflight passed.'
  exit 0
fi

BACKUP_FILE="$MODE"
[ -f "$BACKUP_FILE" ] || { echo "Backup file not found: $BACKUP_FILE" >&2; exit 1; }
case "$BACKUP_FILE" in *.sql|*.sql.gz) ;; *) echo 'Backup file must end in .sql or .sql.gz' >&2; exit 1 ;; esac
require_cmd mysql

echo "Restoring backup into configured database $DB_NAME"
case "$BACKUP_FILE" in
  *.sql.gz)
    require_cmd gzip
    gzip -t "$BACKUP_FILE"
    gzip -dc "$BACKUP_FILE" | mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME"
    ;;
  *) mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME" < "$BACKUP_FILE" ;;
esac

echo 'Restore complete.'
