#!/usr/bin/env bash
set -euo pipefail

usage() {
    cat <<'EOF'
Usage:
  HOSTGATOR_SSH=user@host bash build/hostgator_sync.sh [--dry-run] [--delete-public]

Environment:
  HOSTGATOR_SSH          Required SSH target, for example cpaneluser.example.com@example.com
  HOSTGATOR_SSH_PORT     SSH port (default: 22)
  HOSTGATOR_APP_ROOT     Private app root relative to the SSH home directory (default: ekklesia)
  HOSTGATOR_PUBLIC_HTML  Web docroot relative to the SSH home directory (default: public_html)

Run this first from the repository root:
  composer install --no-dev --optimize-autoloader --no-interaction

The private sync uses --delete while protecting .env and writable runtime data.
--delete-public removes files in the docroot that are not part of public/; use it
only when that docroot is dedicated to this domain. .well-known/ and cgi-bin/
are preserved even when --delete-public is used.
EOF
}

die() {
    echo "ERROR: $*" >&2
    exit 1
}

DRY_RUN=0
DELETE_PUBLIC=0
for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=1 ;;
        --delete-public) DELETE_PUBLIC=1 ;;
        -h|--help) usage; exit 0 ;;
        *) usage >&2; die "Unknown argument: $arg" ;;
    esac
done

ROOT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT_DIR"

SSH_TARGET="${HOSTGATOR_SSH:-}"
SSH_PORT="${HOSTGATOR_SSH_PORT:-22}"
APP_ROOT="${HOSTGATOR_APP_ROOT:-ekklesia}"
PUBLIC_HTML="${HOSTGATOR_PUBLIC_HTML:-public_html}"
APP_ROOT="${APP_ROOT%/}"
PUBLIC_HTML="${PUBLIC_HTML%/}"

[ -n "$SSH_TARGET" ] || { usage >&2; die 'Set HOSTGATOR_SSH=user@host'; }

validate_relative_path() {
    local name="$1"
    local value="$2"

    [[ "$value" =~ ^[A-Za-z0-9._/-]+$ ]] || die "$name contains unsupported characters: $value"
    [[ "$value" != /* ]] || die "$name must be relative to the SSH home directory: $value"
    [[ "$value" != *..* ]] || die "$name must not contain '..': $value"
    [[ "$value" != '.' && -n "$value" ]] || die "$name must not be empty"
}

validate_relative_path HOSTGATOR_APP_ROOT "$APP_ROOT"
validate_relative_path HOSTGATOR_PUBLIC_HTML "$PUBLIC_HTML"
[[ "$SSH_PORT" =~ ^[0-9]+$ ]] || die 'HOSTGATOR_SSH_PORT must be numeric'

command -v rsync >/dev/null 2>&1 || die 'rsync is required on the local machine'
command -v ssh >/dev/null 2>&1 || die 'ssh is required on the local machine'
[ -f vendor/autoload.php ] || die 'vendor/autoload.php is missing; run composer install --no-dev --optimize-autoloader first'

if [ -d vendor/phpunit ] || [ -d vendor/phpstan ]; then
    die 'vendor/ contains development packages; run composer install --no-dev --optimize-autoloader before syncing production'
fi

RSYNC_BASE=(rsync -az --human-readable --itemize-changes -e "ssh -p $SSH_PORT")
if [ "$DRY_RUN" -eq 1 ]; then
    RSYNC_BASE+=(--dry-run)
fi

PRIVATE_EXCLUDES=(
    --include='.env.example'
    --exclude='.env'
    --exclude='.env.*'
    --exclude='.git/'
    --exclude='.github/'
    --exclude='.devin/'
    --exclude='.agents/'
    --exclude='node_modules/'
    --exclude='tests/'
    --exclude='writable/cache/*'
    --exclude='writable/logs/*'
    --exclude='writable/session/*'
    --exclude='writable/uploads/*'
    --exclude='writable/debugbar/*'
    --exclude='writable/backups/*'
    --exclude='writable/restore_points/*'
    --exclude='writable/reports/*'
    --exclude='writable/parochial-report/*'
    --exclude='writable/mail_queue/*'
    --exclude='writable/*.db'
    --exclude='writable/*.sqlite'
    --exclude='writable/*.sqlite3'
    --exclude='build/logs/'
    --exclude='build/.phpunit.cache/'
)

PUBLIC_DELETE=()
if [ "$DELETE_PUBLIC" -eq 1 ]; then
    PUBLIC_DELETE=(--delete)
fi

REMOTE_HOME="$(ssh -p "$SSH_PORT" "$SSH_TARGET" 'printf %s "$HOME"')"
[[ "$REMOTE_HOME" =~ ^/[A-Za-z0-9._/-]+$ ]] || die 'Unable to determine a safe remote HOME path'
REMOTE_APP_ROOT="$REMOTE_HOME/$APP_ROOT"

PUBLIC_STAGE="$(mktemp -d "${TMPDIR:-/tmp}/ekk-hostgator-public.XXXXXX")"
trap 'rm -rf "$PUBLIC_STAGE"' EXIT HUP INT TERM
cp -a public/. "$PUBLIC_STAGE/"
cat >> "$PUBLIC_STAGE/.htaccess" <<EOF

<IfModule mod_env.c>
    SetEnv EKKLESIA_APP_ROOT $REMOTE_APP_ROOT
</IfModule>
EOF

if [ "$DRY_RUN" -eq 0 ]; then
    ssh -p "$SSH_PORT" "$SSH_TARGET" "mkdir -p -- $APP_ROOT $PUBLIC_HTML"
else
    echo "DRY RUN: would ensure remote directories $APP_ROOT/ and $PUBLIC_HTML/"
fi

echo "Syncing private app root to $SSH_TARGET:$APP_ROOT/"
"${RSYNC_BASE[@]}" --delete "${PRIVATE_EXCLUDES[@]}" ./ "$SSH_TARGET:$APP_ROOT/"

echo "Syncing public docroot to $SSH_TARGET:$PUBLIC_HTML/"
"${RSYNC_BASE[@]}" "${PUBLIC_DELETE[@]}" \
    --exclude='.well-known/' \
    --exclude='cgi-bin/' \
    "$PUBLIC_STAGE/" "$SSH_TARGET:$PUBLIC_HTML/"

if [ "$DRY_RUN" -eq 0 ]; then
    ssh -p "$SSH_PORT" "$SSH_TARGET" "test -f $APP_ROOT/app/Config/Paths.php && test -f $PUBLIC_HTML/index.php"
    echo 'Split-layout sync complete. Next: create/review the private .env, then run the guarded database release block from docs/HOSTGATOR_DEPLOY.md.'
else
    echo 'Dry run complete; no files were changed.'
fi
