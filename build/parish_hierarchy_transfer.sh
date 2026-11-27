#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  bash build/parish_hierarchy_transfer.sh export  <output.sql.gz> [source_db_name]
  bash build/parish_hierarchy_transfer.sh import  <input.sql.gz>
  bash build/parish_hierarchy_transfer.sh verify  [source_db_name]

Transfers the canonical ecclesiastical hierarchy tables (province, diocese,
parish) in strict foreign-key order for the HostGator split-layout cutover.
`export` reads from the given source database (or the configured .env DB);
`import` and `verify` target the database configured in .env. Diocesan aliases
(`diocese_alias`) are included automatically when present in the source.

EXPORT — run on the source system while its .env points at the populated DB
(or pass the DB name explicitly):
  php spark directory:verify-readiness --strict
  bash build/parish_hierarchy_transfer.sh export parish_hierarchy_$(date +%Y%m%d).sql.gz
Then transfer the archive over SSH/SFTP into ~/ekklesia/writable/backups/.

IMPORT — run on HostGator inside ~/ekklesia/, after `php spark migrate --all`
and `php spark db:seed DatabaseSeeder`, and BEFORE creating any production
users/profiles/parishes (docs/HOSTGATOR_DEPLOY.md §4.1 Path A):
  bash build/parish_hierarchy_transfer.sh import writable/backups/parish_hierarchy_YYYYMMDD.sql.gz
The import is atomic (single transaction): baseline-seeded diocese/province
rows are cleared first so source IDs are preserved 1:1, and any import error
rolls the hierarchy back to its pre-import seeded state.

VERIFY — compare live hierarchy counts against the source DB (or print
counts only when no source DB is given):
  bash build/parish_hierarchy_transfer.sh verify <source_db_name>
EOF
}

die() {
    echo "ERROR: $*" >&2
    exit 1
}

MODE="${1:-}"
case "$MODE" in
    export|import|verify) ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die 'Pass export, import, or verify' ;;
esac

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
[ -f "$ENV_FILE" ] || die "Missing $ENV_FILE"

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

required_value() {
    key="$1"
    value=$(read_env_value "$key" "$ENV_FILE" || true)
    case "$value" in ''|CHANGE_ME) die "Missing required setting: $key in .env" ;; esac
    printf '%s' "$value"
}

command -v mysql >/dev/null 2>&1 || die 'mysql client is required'
command -v gzip >/dev/null 2>&1 || die 'gzip is required'
command -v mysqldump >/dev/null 2>&1 || [ "$MODE" != 'export' ] || die 'mysqldump is required'

DB_HOST=$(required_value 'database.default.hostname')
DB_PORT=$(read_env_value 'database.default.port' "$ENV_FILE" || printf '3306')
DB_NAME=$(required_value 'database.default.database')
DB_USER=$(required_value 'database.default.username')
DB_PASS=$(required_value 'database.default.password')

# Keep credentials out of the process list.
DEFAULTS_FILE=$(mktemp "${TMPDIR:-/tmp}/ekk-hierarchy.XXXXXX")
trap 'rm -f "$DEFAULTS_FILE"' EXIT HUP INT TERM
chmod 600 "$DEFAULTS_FILE"
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" > "$DEFAULTS_FILE"

table_exists() {
    local db="$1"
    local table="$2"
    local result
    result=$(mysql --defaults-extra-file="$DEFAULTS_FILE" -N -B -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '${table}';" "$db" 2>/dev/null) \
        || result='0'
    [ "$result" = '1' ]
}

hierarchy_tables() {
    local db="$1"
    local tables='province diocese'
    if table_exists "$db" 'diocese_alias'; then
        tables="$tables diocese_alias"
    fi
    printf '%s parish' "$tables"
}

count_rows() {
    local db="$1"
    local table="$2"
    mysql --defaults-extra-file="$DEFAULTS_FILE" -N -B -e "SELECT COUNT(*) FROM \`${table}\`;" "$db" 2>/dev/null || printf 'n/a'
}

print_counts() {
    local db="$1"
    local label="$2"
    echo "$label"
    for t in province diocese parish; do
        printf '  %-10s %s\n' "$t" "$(count_rows "$db" "$t")"
    done
}

case "$MODE" in
export)
    OUT_FILE="${2:-}"
    [ -n "$OUT_FILE" ] || { usage >&2; die 'Pass the output .sql.gz path'; }
    SRC_DB="${3:-$DB_NAME}"
    HIERARCHY_TABLES=$(hierarchy_tables "$SRC_DB")
    echo "Exporting $HIERARCHY_TABLES from $SRC_DB into $OUT_FILE"
    mysqldump --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --no-create-info \
        --skip-triggers "$SRC_DB" $HIERARCHY_TABLES | gzip > "$OUT_FILE"
    [ -s "$OUT_FILE" ] || die 'Export produced an empty archive'
    gzip -t "$OUT_FILE"
    echo "Export complete: $OUT_FILE"
    ;;
import)
    IN_FILE="${2:-}"
    [ -n "$IN_FILE" ] || { usage >&2; die 'Pass the input .sql.gz path'; }
    [ -f "$IN_FILE" ] || die "Import archive not found: $IN_FILE"
    gzip -t "$IN_FILE" || die 'Import archive failed gzip integrity check'

    for probe in users profile parish; do
        count=$(count_rows "$DB_NAME" "$probe")
        if [ "$count" != 'n/a' ] && [ "$count" != '0' ]; then
            die "Destination table \`$probe\` is not empty ($count rows). Path A cutover runs only on a fresh empty database before any production users/profiles/parishes exist — see docs/HOSTGATOR_DEPLOY.md §4.1. Aborting before any mutation."
        fi
    done

    echo "Importing hierarchy from $IN_FILE into $DB_NAME (single transaction)"
    {
        printf 'SET autocommit = 0;\n'
        printf 'START TRANSACTION;\n'
        printf 'DELETE FROM parish;\n'
        printf 'DELETE FROM diocese;\n'
        printf 'DELETE FROM province;\n'
        gzip -dc "$IN_FILE"
        printf 'COMMIT;\n'
    } | mysql --defaults-extra-file="$DEFAULTS_FILE" "$DB_NAME"

    echo 'Import complete. Verifying hierarchy counts:'
    print_counts "$DB_NAME" "  destination $DB_NAME:"
    echo 'Next: run php spark directory:verify-readiness --strict'
    ;;
verify)
    echo 'Destination hierarchy counts:'
    print_counts "$DB_NAME" "  destination $DB_NAME:"
    SRC_DB="${2:-}"
    if [ -n "$SRC_DB" ]; then
        echo "Source hierarchy counts (same MySQL host, database $SRC_DB):"
        print_counts "$SRC_DB" "  source $SRC_DB:"
        mismatch=0
        for t in province diocese parish; do
            dst=$(count_rows "$DB_NAME" "$t")
            src=$(count_rows "$SRC_DB" "$t")
            if [ "$dst" != "$src" ]; then
                echo "MISMATCH: $t destination=$dst source=$src" >&2
                mismatch=1
            fi
        done
        [ "$mismatch" -eq 0 ] || die 'Hierarchy counts differ between source and destination'
        echo 'Counts match between source and destination.'
    fi
    ;;
esac
