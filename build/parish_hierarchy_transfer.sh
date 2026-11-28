#!/usr/bin/env bash
set -euo pipefail
# Hierarchy archives contain parish data including restricted tax IDs; never
# leave them readable by other local accounts.
umask 077

usage() {
    cat <<'EOF'
Usage:
  bash build/parish_hierarchy_transfer.sh export  <output.sql.gz> [source_db_name]
  bash build/parish_hierarchy_transfer.sh import  <input.sql.gz>
  bash build/parish_hierarchy_transfer.sh verify  [source_db_name]

Transfers the canonical ecclesiastical hierarchy tables (province, diocese,
parish) in strict foreign-key order for the HostGator split-layout cutover.
`export` reads from the given source database (or the configured .env DB);
`import` and `verify` target the database configured in .env. Only the
canonical migration-backed hierarchy tables are transferred (province,
diocese, parish): the optional legacy `diocese_alias` lookup table is not
created by any migration, so it never exists on a fresh destination and is
deliberately excluded. UEID diocese matching on the destination uses the
`diocese.alias_norm` column, which IS transferred; `diocese_alias` is only a
supplementary lookup on databases where it already exists.

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
DB_PASS=$(read_env_value 'database.default.password' "$ENV_FILE" || true)
[ "$DB_PASS" != 'CHANGE_ME' ] || die 'Missing required setting: database.default.password in .env'

# Keep credentials out of the process list.
DEFAULTS_FILE=$(mktemp "${TMPDIR:-/tmp}/ekk-hierarchy.XXXXXX")
trap 'rm -f "$DEFAULTS_FILE"' EXIT HUP INT TERM
chmod 600 "$DEFAULTS_FILE"
{
    printf '[client]\nhost=%s\nport=%s\nuser=%s\n' "$DB_HOST" "$DB_PORT" "$DB_USER"
    if [ -n "$DB_PASS" ]; then
        printf 'password=%s\n' "$DB_PASS"
    fi
} > "$DEFAULTS_FILE"

hierarchy_tables() {
    # province/diocese/parish are the only migration-backed hierarchy tables.
    # diocese_alias has no migration, so a fresh destination never has it and
    # importing its rows would fail. UEID matching relies on diocese.alias_norm
    # (transferred); diocese_alias is only a supplementary lookup where the
    # optional table already exists.
    printf 'province diocese parish'
}

table_columns() {
    local db="$1"
    local table="$2"
    mysql --defaults-extra-file="$DEFAULTS_FILE" -N -B -e \
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '${table}';" \
        "$db" 2>/dev/null | LC_ALL=C sort
}

assert_exact_columns() {
    local db="$1"
    local role="$2"
    local table="$3"
    local expected_cols="$4"
    local actual expected missing extra missing_fmt extra_fmt

    actual=$(table_columns "$db" "$table")
    if [ -z "$actual" ]; then
        die "${role} table \`${db}.${table}\` is missing or unreadable. Run php spark migrate --all first."
    fi

    expected=$(printf '%s\n' $expected_cols | LC_ALL=C sort)
    missing=$(LC_ALL=C comm -23 <(printf '%s\n' "$expected") <(printf '%s\n' "$actual"))
    extra=$(LC_ALL=C comm -13 <(printf '%s\n' "$expected") <(printf '%s\n' "$actual"))

    if [ -n "$missing" ] || [ -n "$extra" ]; then
        missing_fmt=$(printf '%s\n' "$missing" | paste -sd ',' - | sed 's/,/, /g')
        extra_fmt=$(printf '%s\n' "$extra" | paste -sd ',' - | sed 's/,/, /g')
        die "${role} table \`${db}.${table}\` does not match the canonical column set (missing: ${missing_fmt:-none}; unexpected: ${extra_fmt:-none}). Run php spark migrate --all first."
    fi
}

check_canonical_schema() {
    local db="$1"
    local role="$2"

    assert_exact_columns "$db" "$role" province \
        'id province_number name sort_order created_at updated_at'
    assert_exact_columns "$db" "$role" diocese \
        'id province_id name alias_norm created_at updated_at'
    assert_exact_columns "$db" "$role" parish \
        'id name parish_ueid is_active logo banner diocese_id street mailing_street city mailing_city state mailing_state zip mailing_zip county mailing_county country mailing_country tax_id address phone email website public_token created_at updated_at deleted_at congregation_type'
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
    check_canonical_schema "$SRC_DB" 'Source'
    HIERARCHY_TABLES=$(hierarchy_tables "$SRC_DB")
    echo "Exporting $HIERARCHY_TABLES from $SRC_DB into $OUT_FILE"
    # --complete-insert makes INSERTs immune to column-order drift.
    # --skip-add-locks / --skip-disable-keys keep the archive free of LOCK TABLES
    # and ALTER TABLE statements: both implicitly commit in MySQL, which would
    # silently break the single-transaction atomicity the import relies on.
    # --set-gtid-purged=OFF avoids SUPER/SYSTEM_VARIABLES_ADMIN errors on shared hosts.
    DUMP_EXTRA_FLAGS=()
    DUMP_HELP=$(mysqldump --help 2>/dev/null || true)
    case "$DUMP_HELP" in
        *--set-gtid-purged*) DUMP_EXTRA_FLAGS+=(--set-gtid-purged=OFF) ;;
    esac
    mysqldump --defaults-extra-file="$DEFAULTS_FILE" --single-transaction --no-create-info --complete-insert \
        --skip-triggers --skip-add-locks --skip-disable-keys ${DUMP_EXTRA_FLAGS[@]+"${DUMP_EXTRA_FLAGS[@]}"} \
        "$SRC_DB" $HIERARCHY_TABLES | gzip > "$OUT_FILE"
    [ -s "$OUT_FILE" ] || die 'Export produced an empty archive'
    chmod 600 "$OUT_FILE"
    gzip -t "$OUT_FILE"
    echo "Export complete: $OUT_FILE"
    ;;
import)
    IN_FILE="${2:-}"
    [ -n "$IN_FILE" ] || { usage >&2; die 'Pass the input .sql.gz path'; }
    [ -f "$IN_FILE" ] || die "Import archive not found: $IN_FILE"
    gzip -t "$IN_FILE" || die 'Import archive failed gzip integrity check'

    mysql --defaults-extra-file="$DEFAULTS_FILE" -e 'SELECT 1;' "$DB_NAME" >/dev/null \
        || die "Unable to connect to destination database $DB_NAME"

    for probe in users profile parish; do
        count=$(count_rows "$DB_NAME" "$probe")
        if [ "$count" = 'n/a' ]; then
            die "Required destination table \`$probe\` is missing or unreadable in $DB_NAME. Run php spark migrate --all before importing."
        fi
        if [ "$count" != '0' ]; then
            die "Destination table \`$probe\` is not empty ($count rows). Path A cutover runs only on a fresh empty database before any production users/profiles/parishes exist — see docs/HOSTGATOR_DEPLOY.md §4.1. Aborting before any mutation."
        fi
    done

    check_canonical_schema "$DB_NAME" 'Destination'

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
    check_canonical_schema "$DB_NAME" 'Destination'
    echo 'Destination schema check: canonical hierarchy columns OK'
    echo 'Destination hierarchy counts:'
    print_counts "$DB_NAME" "  destination $DB_NAME:"
    SRC_DB="${2:-}"
    if [ -n "$SRC_DB" ]; then
        check_canonical_schema "$SRC_DB" 'Source'
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
