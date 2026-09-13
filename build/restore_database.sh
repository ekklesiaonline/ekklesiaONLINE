#!/usr/bin/env sh
set -eu

# Restore MySQL database from a .sql or .sql.gz file.
# Usage: ./build/restore_database.sh /path/to/backup.sql.gz

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 /path/to/backup.sql[.gz]"
  exit 1
fi

BACKUP_FILE="$1"
if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup file not found: $BACKUP_FILE"
  exit 1
fi

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

require_cmd mysql
if [ "${BACKUP_FILE##*.}" = "gz" ]; then
  require_cmd gzip
fi

echo "Restoring $BACKUP_FILE into database $DB_NAME on $DB_HOST:$DB_PORT"

if [ -z "$DB_PASS" ]; then
  if [ "${BACKUP_FILE##*.}" = "gz" ]; then
    gzip -dc "$BACKUP_FILE" | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME"
  else
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" < "$BACKUP_FILE"
  fi
else
  if [ "${BACKUP_FILE##*.}" = "gz" ]; then
    gzip -dc "$BACKUP_FILE" | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"
  else
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"
  fi
fi

echo "Restore complete."
