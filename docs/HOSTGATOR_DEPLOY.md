# HostGator Production Deploy Checklist

Target: `ekklessia.online` on HostGator shared hosting (`sh00162.hostgator.com`,
cPanel, CloudLinux PHP selector, nginx cache layer in front of Apache). PHP
8.5.13 confirmed available.

Because HostGator does not support changing a primary domain away from
`public_html`, the canonical HostGator layout is split:

```text
~/ekklesia/       private application root (app/, vendor/, writable/, modules, spark, .env)
~/public_html/    web docroot containing only the contents of public/
```

> **Note:** On `sh00162.hostgator.com`, `$HOME` resolves to `/home1/<user>`
> (e.g. `/home1/zhvdeqte`). Use `$HOME` or the exact `/home1/<user>` path in
> `.htaccess` and cron entries — never assume `/home/<user>`.

Do not upload the repository directly into `public_html`: a 2026-11-25 probe of
the Softaculous skeleton showed project files such as `composer.json`,
`composer.lock`, `spark`, and `env` reachable over HTTP. If `ekklessia.online`
is an addon/subdomain whose document root can be changed, the standard
`<project>/public` docroot is also acceptable; this checklist uses the split
layout because it works in both cases.

This checklist adapts `DEPLOYMENT_RUNBOOK.md` §4–§9 to HostGator's constraints.
Items marked **[OPERATOR]** require cPanel/SSH access; **[AGENT-verifiable]**
items can be checked from the repo or via curl/SSH after deploy.

---

## 1. cPanel pre-flight (before any upload)

| # | Check | How | Status |
|---|---|---|---|
| 1.1 | PHP 8.5 selected for `ekklessia.online` | cPanel → Select PHP Version → domain dropdown + docroot `.htaccess` handler (§3.7) | ✅ confirmed (`8.5.13` CLI + `ea-php85___lsphp` web) |
| 1.2 | Required extensions enabled | cPanel → Select PHP Version → domain → **Extensions** tab (`extension=` is `PHP_INI_SYSTEM`, so it cannot be set in `.user.ini`). Required: `intl`, `mbstring`, `mysqli`, `gd`, `curl`, `dom`, `xml` (+`xmlreader`/`xmlwriter`/`simplexml`/`libxml`), `zip`, `openssl`, `fileinfo` | ✅ confirmed on `ea-php85` |
| 1.3 | SSH access enabled | cPanel → Security → SSH Access (`BatchMode`, port `22`) | ✅ confirmed |
| 1.4 | MySQL database + user created | cPanel → MySQL Databases. Host is `localhost`, port `3306` | ✅ confirmed (`zhvdeqte_ekklesiaonline` / `zhvdeqte_ekklesia`) |
| 1.5 | Backup DB credentials | Either a second MySQL user on the same DB with `SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES` (mysqldump needs these; a SELECT-only user is NOT sufficient), or reuse the app user for `backup.db.*`. **Restore caveat:** `build/restore_database.sh` reads `backup.db.*` and imports with it, so a dump-only account **cannot restore** (needs `CREATE, DROP, ALTER, INSERT, INDEX, REFERENCES, CREATE VIEW, TRIGGER, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE` — see §7). `restore_database.sh --check` only pings MySQL and will not catch this | ✅ configured (`backup.db.*` verified; nightly backups running) |
| 1.6 | Split-layout paths confirmed | Primary domain keeps `~/public_html` as docroot; private app lives in `~/ekklesia`. Never leave the project root web-accessible | ✅ confirmed |
| 1.7 | SSL certificate active + HTTPS forced | AutoSSL/Let's Encrypt issued for `ekklessia.online` + `www`. Enable HostGator's cPanel **Force HTTPS Redirect** toggle; direct HTTP requests must end at HTTPS cleanly without `/public` | ⏳ AutoSSL + app 307 active; cPanel Force HTTPS toggle pending [OPERATOR] |
| 1.8 | Cron job feature available | cPanel → Advanced → Cron Jobs | ✅ confirmed (12 jobs installed 2026-11-25) |

---

## 2. Deploy method (no Composer on shared hosting)

### 2.1 Transport, host-key verification, and clean release build

HostGator shared has **no `composer` CLI**. Build artifacts locally from an
isolated `git worktree` (so production `--no-dev` builds never clobber PHPUnit or
PHPStan in your daily dev checkout), then upload via **rsync over SSH**
(preferred) or **SFTP**.

Verify the server's SSH host key fingerprint on first connect against an
out-of-band value from HostGator support. For `sh00162.hostgator.com`
(`ekklessia.online`), the verified ed25519 host key recorded in operator
`known_hosts` on `2026-11-25` is:

```text
ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIIpKs+fy3XQ4Mxrt0/LfWH8j1XPhjRbNufGSiL0WBTO
```

Do not accept an unverified host key on first connect or on a host replacement.
**Never use plain FTP** — it sends credentials and release files unencrypted.

```bash
# 1. Build production vendor/ from a clean detached worktree on the release tag/SHA:
RELEASE_REF="$(git rev-parse --short HEAD)"
BUILD_DIR="$(mktemp -d "${TMPDIR:-/tmp}/ekk-release-${RELEASE_REF}.XXXXXX")"
git worktree add --detach "$BUILD_DIR" "$RELEASE_REF"

(
  cd "$BUILD_DIR"
  composer validate --strict
  composer install --no-dev --optimize-autoloader --no-interaction

  # Preview exact sync plan (optional):
  HOSTGATOR_SSH='zhvdeqte@sh00162.hostgator.com' bash build/hostgator_sync.sh --dry-run

  # First real sync on a dedicated docroot used --delete-public to remove the
  # Softaculous skeleton (.well-known/, cgi-bin/, and php.ini are preserved):
  # HOSTGATOR_SSH='zhvdeqte@sh00162.hostgator.com' bash build/hostgator_sync.sh --delete-public

  # Routine production syncs (preserves .env, writable/, .well-known/, cgi-bin/, php.ini):
  HOSTGATOR_SSH='zhvdeqte@sh00162.hostgator.com' bash build/hostgator_sync.sh
)

# 2. Clean up the temporary release worktree:
git worktree remove --force "$BUILD_DIR"
```

`build/hostgator_sync.sh` performs two separate rsync transfers:

1. the repository (including the locally built production `vendor/`) to the
   private `~/ekklesia/` root, excluding `.env`, tests, runtime logs/sessions,
   backups, reports, uploads, and local dev artifacts; and
2. only `public/` contents to `~/public_html/` (preserving `.well-known/`,
   `cgi-bin/`, and cPanel MultiPHP INI Editor's `php.ini`).

While syncing, `hostgator_sync.sh` injects:

- `SetEnv EKKLESIA_APP_ROOT <remote_home>/ekklesia` so `public/index.php` boots
  the private application root; and
- the cPanel MultiPHP `ea-php85___lsphp` `AddHandler` block (§3.7) so rsync
  never reverts the web SAPI to ea-php83.

The script refuses to sync a `vendor/` tree containing PHPUnit or PHPStan. If
`HOSTGATOR_APP_ROOT` or `HOSTGATOR_PUBLIC_HTML` differ from the defaults, export
both before running the script and verify the injected `SetEnv` path after upload.

### 2.2 Pre-sync code snapshot (routine deploys)

Before syncing a new release over an existing HostGator install, capture a
timestamped snapshot of the current code root (excluding `.env` and `writable/`)
and public docroot so code-only rollback (§7) can restore an exact matched pair:

```bash
ssh zhvdeqte@sh00162.hostgator.com '
  STAMP=$(date +%Y%m%d-%H%M%S)
  mkdir -p ~/release-backups
  tar --exclude=".env" --exclude="writable" -czf ~/release-backups/ekklesia-${STAMP}.tar.gz -C ~ ekklesia
  tar -czf ~/release-backups/public_html-${STAMP}.tar.gz -C ~ public_html
  chmod 600 ~/release-backups/*-${STAMP}.tar.gz
  ls -lh ~/release-backups/*-${STAMP}.tar.gz
'
```

Prune old `~/release-backups/*.tar.gz` archives periodically to stay within shared
hosting disk and inode quotas.

### 2.3 Maintenance mode during deploy

For routine deploys against a live site, enable maintenance mode before syncing
code and migrating, and disable it only after post-deploy checks pass:

- **Application maintenance mode (preferred for DB migrations & smoke checks):**
  - Via UI: sign in as `systemadmin` → **System → Settings** → set
    `maintenance.enabled = 1` (or toggle via DB + `spark cache:clear` below).
  - Via SSH before/after migrations:
    ```bash
    PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
    cd ~/ekklesia
    # Enable / disable maintenance.enabled in system_settings, then flush cache:
    "$PHP_BIN" spark cache:clear
    ```
  - `App\Filters\MaintenanceMode` exempts `GET /health` (stays HTTP 200),
    `login`, `logout`, `auth/a/*`, `auth/set-password`, `assets/*`, and
    `system/settings`, and allows signed-in `system.admin` users through while
    returning HTTP 503 (`errors/maintenance` or JSON for AJAX) to everyone else.
- **Hard edge fallback (optional during large file syncs):** temporarily place a
  `503` rewrite guard at the top of `~/public_html/.htaccess` (excluding
  `^health$`) if you must block PHP entrypoints completely while files are in
  flight, then remove it after `hostgator_sync.sh` finishes.

### 2.4 Environment setup and guarded database release

On first server setup (SSH):

```bash
cd ~/ekklesia
cp build/env-templates/production.env.template .env   # then edit every CHANGE_ME
chmod 600 .env
PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'       # bare php is 8.3 here — see §3.3
"$PHP_BIN" -v                                         # MUST be 8.5 — see §3.3
"$PHP_BIN" spark config:check App
"$PHP_BIN" spark config:check Logger
"$PHP_BIN" spark config:check Session
"$PHP_BIN" spark config:check 'CodeIgniter\Shield\Config\Auth'
```

Never run `config:check` for `Database`, `Encryption`, or `Email` (they print
resolved secrets).

Required `.env` values on `ekklessia.online`:

- `CI_ENVIRONMENT = production`
- `app.baseURL = 'https://ekklessia.online/'`
- `app.indexPage =` (empty — **required**: `App::$indexPage` defaults to
  `'index.php'`; without this override every generated link and redirect gains a
  visible `/index.php/` prefix)
- `app.forceGlobalSecureRequests = true`
- `security.disableDeviceVerificationForTesting = 0`
- `database.default.*` from cPanel MySQL credentials (`hostname = localhost`)
- `encryption.key` + `encryption.blindIndexKey` — generate independently:
  `php -r "echo 'hex2bin:' . bin2hex(random_bytes(32)) . PHP_EOL;"`
- `backup.db.*` per §1.5
- `email.*` SMTP per `docs/EMAIL_DELIVERABILITY.md`
- `families.enforceSingleFamilyMembership = 1` — safe on a fresh prod DB
  (empty `family_member`; audit query returns zero rows)
- `sms.provider = log_only` until Twilio credentials exist

Then run the database release as **one guarded block** (runbook §5). It stops
at the first failure, reports whether the backup or the migration failed, and
never seeds, clears cache, or compiles routes after a failure. Route output is
written to `writable/reports/` (never shared-host `/tmp`):

```bash
(
  set -e
  cd ~/ekklesia
  mkdir -p writable/reports
  PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php85/root/usr/bin/php}"
  sh build/backup_database.sh --check                 # needs backup.db.* filled
  sh build/restore_database.sh --check
  "$PHP_BIN" spark migrate:status --all

  # Fresh, verified restore point BEFORE migrating. Validates the exact
  # archive the script reports (honours backup.outputDir).
  BACKUP_OUT="$(sh build/backup_database.sh)" \
    || { echo 'ABORTED: backup failed — nothing migrated or seeded' >&2; exit 1; }
  BACKUP_FILE="$(printf '%s\n' "$BACKUP_OUT" | sed -n 's/^Backup created: //p')"
  { [ -n "$BACKUP_FILE" ] && [ -s "$BACKUP_FILE" ] && gzip -t "$BACKUP_FILE"; } \
    || { echo 'ABORTED: backup verification failed — nothing migrated or seeded' >&2; exit 1; }
  echo "Verified backup: $BACKUP_FILE"

  # `spark migrate` and `db:seed` can print an exception and still exit 0, so
  # output is checked the same way the CI mysql job does (.github/workflows/ci.yml).
  MIGRATE_OUT="$("$PHP_BIN" spark migrate --all 2>&1)" && MIGRATE_RC=0 || MIGRATE_RC=$?
  printf '%s\n' "$MIGRATE_OUT"
  if [ "$MIGRATE_RC" -ne 0 ] \
    || ! printf '%s\n' "$MIGRATE_OUT" | grep -q 'Migrations complete.' \
    || printf '%s\n' "$MIGRATE_OUT" | grep -q 'Exception'; then
    echo "ABORTED: migration FAILED after backup — restore from $BACKUP_FILE if needed; not seeded" >&2
    exit 1
  fi

  # Same post-migration check as CI: no Exception and no unapplied row
  # (an empty "---" migrated-on cell) in migrate:status.
  STATUS_OUT="$("$PHP_BIN" spark migrate:status --all 2>&1)" && STATUS_RC=0 || STATUS_RC=$?
  printf '%s\n' "$STATUS_OUT"
  if [ "$STATUS_RC" -ne 0 ] || printf '%s\n' "$STATUS_OUT" | grep -q -E 'Exception|\| *---  *\|'; then
    echo "ABORTED: migration status check failed or migrations pending — restore from $BACKUP_FILE if needed; not seeded" >&2
    exit 1
  fi

  SEED_OUT="$("$PHP_BIN" spark db:seed DatabaseSeeder 2>&1)" && SEED_RC=0 || SEED_RC=$?
  printf '%s\n' "$SEED_OUT"
  if [ "$SEED_RC" -ne 0 ] || printf '%s\n' "$SEED_OUT" | grep -q 'Exception'; then
    echo "ABORTED: seeding FAILED after migration — restore from $BACKUP_FILE if needed" >&2
    exit 1
  fi

  # If users already exist and role-permission mappings may have changed:
  # "$PHP_BIN" spark db:seed PermissionRoleSyncSeeder

  "$PHP_BIN" spark directory:verify-readiness --strict \
    || { echo 'ABORTED: province/diocese/parish hierarchy is NOT clean — populate it and remove orphaned/placeholder rows before release (see §4.1)' >&2; exit 1; }
  "$PHP_BIN" spark cache:clear
  "$PHP_BIN" spark routes > writable/reports/routes_after.txt
  echo 'Database release complete.'
)
```

**Very first deploy into a CONFIRMED EMPTY database only** (nothing to
restore, so no backup step) — run instead of the block above:

```bash
(
  set -e
  cd ~/ekklesia
  mkdir -p writable/reports
  PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php85/root/usr/bin/php}"
  MIGRATE_OUT="$("$PHP_BIN" spark migrate --all 2>&1)" && MIGRATE_RC=0 || MIGRATE_RC=$?
  printf '%s\n' "$MIGRATE_OUT"
  if [ "$MIGRATE_RC" -ne 0 ] \
    || ! printf '%s\n' "$MIGRATE_OUT" | grep -q 'Migrations complete.' \
    || printf '%s\n' "$MIGRATE_OUT" | grep -q 'Exception'; then
    echo 'ABORTED: migration FAILED — not seeded' >&2
    exit 1
  fi

  STATUS_OUT="$("$PHP_BIN" spark migrate:status --all 2>&1)" && STATUS_RC=0 || STATUS_RC=$?
  printf '%s\n' "$STATUS_OUT"
  if [ "$STATUS_RC" -ne 0 ] || printf '%s\n' "$STATUS_OUT" | grep -q -E 'Exception|\| *---  *\|'; then
    echo 'ABORTED: migration status check failed or migrations pending — not seeded' >&2
    exit 1
  fi

  SEED_OUT="$("$PHP_BIN" spark db:seed DatabaseSeeder 2>&1)" && SEED_RC=0 || SEED_RC=$?
  printf '%s\n' "$SEED_OUT"
  if [ "$SEED_RC" -ne 0 ] || printf '%s\n' "$SEED_OUT" | grep -q 'Exception'; then
    echo 'ABORTED: seeding FAILED — bootstrap incomplete' >&2
    exit 1
  fi
  "$PHP_BIN" spark cache:clear
  "$PHP_BIN" spark routes > writable/reports/routes_after.txt
  echo 'Database bootstrap complete. NEXT REQUIRED STEP before go-live: populate province/diocese/parish (§4.1) and verify with directory:verify-readiness --strict.'
)
```

---

## 3. HostGator-specific risks (verify during deploy)

### 3.1 Split layout, sensitive docroot files, and cached `/public` redirect — HIGHEST RISK

The Softaculous skeleton installed on 2026-11-25 proved that PHP can execute a
stock CodeIgniter welcome page, but it also demonstrated the unsafe layout:
`/` redirected to `/public/`, project files were web-accessible, and the 301
was cached by HostGator's nginx edge (`X-Proxy-Cache: HIT`). The production
deploy must not reuse that layout.

After syncing, verify the split layout from outside the host:

```bash
curl -fsS https://ekklessia.online/health    # application health: 200

for path in /composer.json /composer.lock /spark /env /.env /app/Config/App.php /.htaccess; do
  curl -sS -o /dev/null -w "%{http_code} $path\n" "https://ekklessia.online$path"
done
# None of these probe paths may return 200. 403/404/406 are acceptable edge or
# Apache denial forms; 200 means the private project root or config is exposed.

for ini_path in /.user.ini /php.ini; do
  echo "=== $ini_path ==="
  curl -sS -i "https://ekklessia.online$ini_path" | head -n 25
done
# HostGator's nginx/cPanel layer has been observed returning HTTP 200 with a
# rendered "403 Error" HTML page for /.user.ini and /php.ini. Confirm the body
# is a 403 error page (or 403/404 status) and NEVER contains INI directives
# (such as memory_limit= or display_errors=).
```

`public/.htaccess` also ships a `<FilesMatch>` rule denying `.user.ini`,
`php.ini`, `.env`, `.bak`, `.sql`, `.gz`, and `.log` at the Apache layer as
defense-in-depth.

If `/` still redirects to `/public/` after the docroot is corrected, purge the
HostGator/nginx cache or ask support to clear it. Do not treat a cached 301 as
an application redirect loop. Direct `http://ekklessia.online/` and
`http://ekklessia.online/<path>` must ultimately land on HTTPS without a
`/public/` path segment.

### 3.2 HTTPS detection, `www.` canonicalization, and real client IP behind nginx — HIGHEST RISK

HostGator puts an **nginx caching proxy in front of Apache**. TLS may terminate
at nginx, or Apache may receive `HTTPS=on` depending on the vhost configuration.
Observe **two separate invariants** after every deploy:

1. **HTTPS & `www.` canonicalization (no redirect loop, no split origins):**
   `public/.htaccess` redirects both `http://www.` and `https://www.` to the
   canonical apex domain (`https://ekklessia.online/`), and
   `app.forceGlobalSecureRequests = true` redirects plain `http://` to `https://`.
   Verify all four entrypoints:
   ```bash
   curl -sI http://ekklessia.online/ | head -n 8        # expects 301/307 -> https://ekklessia.online/
   curl -sI https://ekklessia.online/health | head -n 8 # expects 200 OK (no loop)
   curl -sI https://www.ekklessia.online/ | head -n 8   # expects 301 -> https://ekklessia.online/
   curl -sI -L http://www.ekklessia.online/ | grep -E '^(HTTP/|Location:)'
   # Must converge on 200 OK at https://ekklessia.online/ without /public/ or /index.php/
   ```
   If an HTTPS redirect loop appears on `https://ekklessia.online/`, it means
   `isSecure() = false` behind the proxy. Fix options, in order of preference:
   - a) Configure `App::$proxyIPs` in `app/Config/App.php` with HostGator's
     proxy IP/CIDR + `'X-Forwarded-Proto'` (or `'X-Forwarded-For'`).
   - b) `.htaccess`: `SetEnvIf X-Forwarded-Proto https HTTPS=on` (only if the
     proxy overwrites client-supplied `X-Forwarded-Proto`).

2. **Real client IP preservation (CRITICAL — do NOT skip just because HTTPS has no loop):**
   Even when HTTPS works without a redirect loop, `Config\App::$proxyIPs` is
   currently `[]`. If Apache receives the nginx proxy's internal/edge IP in
   `REMOTE_ADDR` without `mod_remoteip` or `$proxyIPs` configured, **every user
   shares the same IP address** in:
   - `loginRateLimit` / `apiRateLimit` (one locked-out user locks out everyone),
   - `audit_logs` and `monitoring-*.log` forensics, and
   - `SessionTrackingFilter` / device verification IP checks.

   **Verify after login/request:** inspect `writable/logs/monitoring-*.log` (or
   `audit_logs` / `auth_logins.ip_address`) for a request from your known public
   IP. If the logged IP is a HostGator server/proxy IP instead of your client IP,
   inspect the headers reaching PHP (`REMOTE_ADDR`, `HTTP_X_FORWARDED_FOR`,
   `HTTP_X_REAL_IP`) via a temporary minimal probe (deleted immediately after
   one request) and configure `Config\App::$proxyIPs` before opening public traffic.

### 3.3 PHP CLI version for `spark` and cron

cPanel's PHP selector governs the **web** SAPI. The SSH/cron `php` binary may
resolve to a different version.

**Verified on `sh00162.hostgator.com` (2026-11-25 & 2026-11-28):** default SSH
`/usr/local/bin/php` is ea-php83 (`8.3.37`) and fails `spark`'s PHP 8.5 gate;
the correct CLI binary is:

```text
/opt/cpanel/ea-php85/root/usr/bin/php   (8.5.13, all required extensions enabled)
```

`/opt/alt/` on this host only ships php74 — do not use `/opt/alt/php85`.

**Never rely on bare `php` on this host:** bare `php` runs 8.3 and `spark`'s
version gate aborts with exit code `1` on `STDERR`. All SSH commands in §2, §4,
and §6 therefore use `PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'`
(`"$PHP_BIN" spark …`), which works in both interactive shells and
non-interactive scripts/subshells. In crontab entries, always write the absolute
path `/opt/cpanel/ea-php85/root/usr/bin/php`.

### 3.4 Writable permissions

`fix-permissions.sh` sets `writable/cache`, `writable/logs`, `writable/session`,
`writable/uploads`, `writable/debugbar`, and `writable/reports` to `775/664` and
keeps `writable/backups` strictly owner-only (`700/600`) because backups and
hierarchy exports contain PII and restricted tax IDs.

On HostGator, PHP (`lsapi`) runs as the account user (`zhvdeqte`), so
owner-write is sufficient and `writable/backups` must **never** be group- or
world-readable. Ensure all required `Health::writableReady()` directories exist
(`cache`, `logs`, `session`, `uploads`, `backups`) and enforce permissions:

```bash
cd ~/ekklesia
mkdir -p writable/cache writable/logs writable/session writable/uploads writable/backups writable/reports
chmod -R u+rwX,g+rX,o-rwx writable/
chmod 700 writable/backups
find writable/backups -type f -exec chmod 600 {} +
```

Never use `777`.

### 3.5 Resource limits and PHP ini directives

Shared hosting caps `max_execution_time`, `memory_limit`, and MySQL
`max_allowed_packet`/timeouts. HostGator's published shared-hosting maximums are
often documented lower (`memory_limit` 256M, `max_execution_time` 30s), though on
`sh00162.hostgator.com` cPanel MultiPHP INI Editor currently writes
`~/public_html/php.ini` with:

```ini
display_errors = Off
max_execution_time = 120
max_input_time = 120
max_input_vars = 3000
memory_limit = 512M
post_max_size = 64M
session.gc_maxlifetime = 1440
session.save_path = "/var/cpanel/php/sessions/ea-php85"
upload_max_filesize = 64M
zlib.output_compression = Off
```

Web-SAPI overrides also ship in `public/.user.ini` (setting `memory_limit=512M`,
`max_execution_time=120`, `max_input_time=120`, `max_input_vars=3000`,
`post_max_size=40M`, `upload_max_filesize=32M`, `opcache.validate_timestamps=1`,
`opcache.revalidate_freq=2`, `date.timezone=UTC`, `display_errors=Off`,
`log_errors=On`, `error_reporting=E_ALL & ~E_DEPRECATED`, `expose_php=Off`).
`build/hostgator_sync.sh` preserves cPanel's `~/public_html/php.ini` while
syncing `public/.user.ini`.

Only `PHP_INI_PERDIR`/`PHP_INI_ALL` directives are honored in `.user.ini` —
OPcache sizing, `realpath_cache_*`, and `allow_url_*` are `PHP_INI_SYSTEM` and
silently ignored.

**Verify effective web-SAPI limits:** CloudLinux `mod_lsapi` only reads
`.user.ini` when `lsapi_enable_user_ini On` is set by the host (default `Off`).
Check effective values via the **web SAPI** (not CLI). Do **not** upload a full
`phpinfo()` page. Use a temporary probe with a random filename that prints only
the needed directives, fetch it once, and **delete it immediately**:

```php
<?php foreach (['memory_limit','max_execution_time','post_max_size','upload_max_filesize','opcache.validate_timestamps','date.timezone','expose_php'] as $k) echo $k, '=', ini_get($k), "\n";
```

Delete immediately (`rm ~/public_html/<random>.php`) after inspecting. Also test
long web requests (batch giving-statement PDF/ZIP exports) under the effective
execution time limit before launch.

In cPanel MultiPHP INI Editor, keep `display_errors=Disabled`,
`zlib.output_compression=Disabled`, and leave `session.gc_maxlifetime` /
`session.save_path` untouched/blank where editable (CI4's FileHandler manages
`writable/session/` directly using its own session expiration). Changes to
`.user.ini` take up to `user_ini.cache_ttl` (~300s) to apply and do **not**
affect CLI/cron (which use cPanel → Select PHP Version → Options).

### 3.6 Outbound connectivity & live SMTP verification

SMTP (587/TLS), Google APIs (Gmail OAuth), and Twilio (if enabled) require
outbound 443/587. Verify SMTP auth and real inbox delivery (including SPF, DKIM,
and DMARC alignment per `docs/EMAIL_DELIVERABILITY.md`) before creating or
logging in as `systemadmin`, because admin logins require email 2FA and device
verification (§4.2).

### 3.7 PHP version selection lives in the docroot `.htaccess` — DEPLOY HAZARD

`uapi LangPHP php_get_vhost_versions` reports the vhost as `ea-php85`, but with
`php_fpm: 0` that setting does **not** reach the web SAPI. On non-FPM
cPanel/lsapi hosts the per-domain PHP version is enforced only by the
`AddHandler` block that cPanel writes into the docroot `.htaccess`:

```apache
# php -- BEGIN cPanel-generated handler, do not edit
# Set the "ea-php85" package as the default "PHP" programming language.
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php85___lsphp .php .php8 .phtml
</IfModule>
# php -- END cPanel-generated handler, do not edit
```

The plain handler name (`application/x-httpd-ea-php85`, without `___lsphp`)
does **not** exist on lsapi hosts — forcing it produces edge 404s for every PHP
request (static files still serve). Never append it manually.

Because rsync replaces `.htaccess` whenever its content changes, `build/hostgator_sync.sh`
appends the cPanel `AddHandler` block automatically (`HOSTGATOR_PHP_HANDLER`).
Verify after every sync that `~/public_html/.htaccess` still ends with the
cPanel markers (`tail -n 10 ~/public_html/.htaccess`). Note: cPanel's UI may use
curly quotes in the comment line (`“ea-php85”`), while `hostgator_sync.sh` uses
ASCII quotes (`"ea-php85"`) — Apache treats comments identically, and both
preserve the exact `AddHandler` line. If PHP-FPM is ever enabled for the domain,
set `HOSTGATOR_PHP_HANDLER=''` so the handler block is skipped.

---

## 4. First-boot bootstrap & Parish Hierarchy Population (REQUIRED before go-live)

### 4.1 Parish Hierarchy Population & Webhosting Migration Plan (NO-GO GATE)

`DatabaseSeeder` seeds `province` (Provinces I–X when `province` is empty) and
two placeholder dioceses (`Sample Diocese One`, `Sample Diocese Two` when
`diocese` is empty), and seeds **zero `parish` rows**. Going live with an empty
`parish` table (or missing `province` / `diocese` parent rows) is a **hard
no-go**: registration, connect cards, parish-scoped dashboards, and
`GET /health/ready` (`components.directory`) all fail without at least one
active, non-deleted `parish` linked to a valid `diocese` → `province` chain.

> **Prerequisite:** Confirm `~/ekklesia` has been synced with the release that
> includes `app/Commands/VerifyDirectoryReadiness.php` and
> `build/parish_hierarchy_transfer.sh` (PRs `#88`–`#90`+) before running Path A
> or `directory:verify-readiness`.

Choose **one** of the following population paths before enabling public traffic:

#### Path A — Moving an already-populated local/staging system to a fresh webhosting DB (preferred for initial cutover)

Use this path **only when seeding a fresh destination database** (immediately
after `migrate --all` and `db:seed DatabaseSeeder`, **before** creating any
users, profiles, deaneries, or affiliations in §4.2). Never use
`mysqldump --replace` against a live database with existing
`profile`/`parish_affiliation` rows: `REPLACE INTO` deletes and re-inserts
conflicting `parish_ueid` rows while FK checks are disabled, which can orphan
existing profile/affiliation references. For updates to an already-live database,
use Path B (`directory:sync-ueid --apply`), which updates in place by
`parish_ueid` without deleting IDs (always inspect the dry-run report in
`writable/reports/` and pass `--preserve-inactive` when intentionally
deactivated parishes must stay inactive).

The supported implementation is `build/parish_hierarchy_transfer.sh` (synced
to both source and destination by `build/hostgator_sync.sh`). It reads database
credentials from `.env`, keeps passwords out of process arguments, validates
canonical column parity on `province`, `diocese`, and `parish`, exports with
`--complete-insert`, refuses to import when `users`/`profile`/`parish`/`deanery`
are non-empty, and runs the entire import in one atomic transaction (any error
rolls the hierarchy back to the pre-import seeded state). Export only the
canonical migration-backed hierarchy tables in strict foreign-key order
(`province` → `diocese` → `parish`, with `ON DELETE RESTRICT ON UPDATE CASCADE`
parent-child FK rules) so existing IDs, `parish_ueid`, `public_token`,
addresses, and province-to-diocese mappings are preserved identically on
webhosting. The optional legacy `diocese_alias` lookup table is deliberately
**not** transferred: no migration creates it, so a fresh destination never has
it. UEID diocese matching relies on the `diocese.alias_norm` column, which
**is** transferred; `diocese_alias` is only a supplementary lookup on databases
where it already exists (and `directory:sync-ueid` only writes alias rows for
dioceses it creates itself).

1. **Pre-export check on source DB** (confirm 0 pending migrations and clean parent chain):
   ```bash
   php spark migrate:status --all
   php spark directory:verify-readiness --strict
   ```
2. **Export hierarchy tables from source DB** into `writable/backups/`:
   ```bash
   bash build/parish_hierarchy_transfer.sh export writable/backups/parish_hierarchy_$(date +%Y%m%d).sql.gz
   ```
   The archive is created owner-only (mode `600`) because parish rows include
   restricted tax IDs — keep it mode `600` through transfer.
3. **Transfer over SSH/SFTP** (`scp` or `sftp`, never plain FTP, never into `public_html/`):
   ```bash
   scp -p writable/backups/parish_hierarchy_YYYYMMDD.sql.gz zhvdeqte@sh00162.hostgator.com:~/ekklesia/writable/backups/
   ```
4. **Take a HostGator pre-import backup** (`sh build/backup_database.sh` +
   `gzip -t`), then import on HostGator inside `~/ekklesia/` (the script aborts
   before mutating anything if `users`, `profile`, `parish`, or `deanery` are
   non-empty):
   ```bash
   cd ~/ekklesia
   sh build/backup_database.sh
   bash build/parish_hierarchy_transfer.sh import writable/backups/parish_hierarchy_YYYYMMDD.sql.gz
   ```
   The import first clears the baseline-seeded `diocese`/`province` rows inside
   the same transaction, so source `province.id` and `diocese.id` values are
   preserved 1:1 with no ID drift.
5. **Verify on HostGator** — confirm destination counts match the source counts
   and strict readiness passes with zero warnings:
   ```bash
   PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
   bash build/parish_hierarchy_transfer.sh verify          # prints destination counts
   "$PHP_BIN" spark directory:verify-readiness --strict     # exit 0, zero warnings
   ```
6. **Keep populated across subsequent deploys**:
   - `build/hostgator_sync.sh` syncs code only and never touches MySQL or `writable/backups/`.
   - Routine deploys run `spark migrate --all`, `spark db:seed DatabaseSeeder`
     (idempotent — `ProvincesAndDiocesesSeeder` skips seeding `province` and
     `diocese` once populated, so it neither recreates sample dioceses nor
     injects `Province X` after a 9-province Path A cutover), and
     `spark directory:verify-readiness --strict`.
   - Nightly `build/backup_database.sh` backs up the populated `province`,
     `diocese`, and `parish` tables with the rest of the database; verify row
     counts during restore drills.

#### Path B — Fresh webhosting install using the shipped UEID directory (`docs/UEID.json`)

`docs/UEID.json` (6,751 parishes across 106 dioceses) is synced to
`~/ekklesia/docs/UEID.json` by `build/hostgator_sync.sh`.

1. Ensure the real `province` rows and target `diocese` rows (mapped to their
   actual `province_id`) exist first — do not rely on `--default-province-id=1`
   for production unless you intentionally reconcile province assignments
   immediately afterward.
2. Dry-run first and review the generated report in `writable/reports/` (it
   names every parish the sync would reactivate):
   ```bash
   PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
   "$PHP_BIN" spark directory:sync-ueid
   ```
3. Apply inside a transaction and verify (pass `--preserve-inactive` when
   intentionally deactivated parishes must stay inactive):
   ```bash
   "$PHP_BIN" spark directory:sync-ueid --apply --preserve-inactive
   "$PHP_BIN" spark directory:verify-readiness --strict
   ```
4. Remove unused `Sample Diocese One` / `Sample Diocese Two` rows created by
   `ProvincesAndDiocesesSeeder` once real dioceses are loaded, then re-run
   `"$PHP_BIN" spark directory:verify-readiness --strict`.

#### Path C — Minimal launch (manual creation via System Admin UI)

After creating the first `systemadmin` account (§4.2), create or verify the
target `province`, create the real `diocese` under that province, create each
active `parish` under that diocese, remove placeholder sample dioceses, and run
`"$PHP_BIN" spark directory:verify-readiness --strict`.

### 4.2 First-boot account bootstrap (after migrate + seed + Path A cutover)

Per `DEPLOYMENT_RUNBOOK.md` §7 "First-boot account bootstrap":

> **Prerequisite:** Verify outbound SMTP (§2 / §3.6) before signing in via the
> web UI, because `security.login_2fa_scope = admins` and `deviceVerification`
> require email OTP/verification codes for `systemadmin` web logins.

```bash
cd ~/ekklesia
PHP_BIN="${PHP_BIN:-/opt/cpanel/ea-php85/root/usr/bin/php}"
ADMIN_USER='CHANGE_ME'
ADMIN_EMAIL='CHANGE_ME@example.org'

"$PHP_BIN" spark shield:user create -n "$ADMIN_USER" -e "$ADMIN_EMAIL" -g systemadmin
"$PHP_BIN" spark shield:user activate -e "$ADMIN_EMAIL"    # users.active defaults to 0
# Note the numeric id printed by `create` (or look it up), then:
ADMIN_USER_ID='CHANGE_ME'
"$PHP_BIN" spark user:check "$ADMIN_USER_ID"
"$PHP_BIN" spark db:seed PermissionRoleSyncSeeder          # after users/groups exist
```

Then sign in at `https://ekklessia.online/login`, complete the email 2FA /
device verification challenge, confirm the system dashboard loads, and install
the first diocesan ordinary via **System → Diocesan Ordinaries → Install** in
the UI (requires the bishop's `users` account + diocese to exist and the
documented two-person record review).

---

## 5. Cron install

Install the approved matrix from `docs/CRON_SCHEDULE.md` via cPanel → Cron Jobs.
**Use the absolute PHP 8.5 path** (`/opt/cpanel/ea-php85/root/usr/bin/php`,
see §3.3) and absolute app root (`echo "$HOME/ekklesia"` — on `sh00162.hostgator.com`,
`/home1/zhvdeqte/ekklesia`).

cPanel adds one command per job, so set `PHP_BIN` **inline on each wrapper
job** (the `build/*.sh` wrappers read `$PHP_BIN`; don't rely on a crontab-level
environment line). Direct `php spark` jobs use the absolute binary instead:

```cron
30 1 * * *   PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash /home1/zhvdeqte/ekklesia/build/service_metrics_refresh_cron.sh
45 1 * * *   cd /home1/zhvdeqte/ekklesia && /opt/cpanel/ea-php85/root/usr/bin/php spark attendance:sync-diocesan-aggregates --rolling-days 35 >> writable/logs/attendance_aggregates_cron.log 2>&1
# ... full matrix in docs/CRON_SCHEDULE.md — substitute exact $HOME/ekklesia path
```

**Observed server timezone (`sh00162.hostgator.com`):** `CST` (US Central), so
morning jobs (`07:30` / `08:00`) already align with parish-local business hours.
Do **not** install `build/cron_sync_church_people.sh` (dead — command removed).

**Log rotation on shared hosting:** High-frequency cron logs (`events_publish_cron.log`,
`sms_process_cron.log`, `clergy_reconcile_grants_cron.log`) append continuously
and are not rotated by CI4. Periodically trim or rotate `writable/logs/*_cron.log`
(e.g., via a monthly cron or logrotate-equivalent truncation keeping the tail) so
disk quota is never exhausted.

---

## 6. Post-deploy validation

Run `DEPLOYMENT_RUNBOOK.md` §5/§7, substituting `PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'`
(`"$PHP_BIN" spark …`) for every bare `php spark` command:

```bash
cd ~/ekklesia
PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
"$PHP_BIN" spark migrate:status --all
"$PHP_BIN" spark directory:verify-readiness --strict

# Public HTTP/HTTPS & directory registration checks:
BASE_URL='https://ekklessia.online'
curl -fsS "$BASE_URL/health"                                   # 200 JSON
curl -fsS "$BASE_URL/register" | grep -q '"parish_ueid":'      # populated parish list

# Security posture tripwire (must return zero matches):
grep -i 'ProductionSecurityPosture' writable/logs/log-*.log || true
```

**Production smoke test caveat (`build/e2e_admin_levels_smoke.sh`):**
`build/e2e_admin_levels_smoke.sh` uses headless `curl` login (`POST /login`)
without email OTP entry, so it is designed for **staging** (where 2FA/device
verification may use a mail catcher or staging config). On **production**, do
**not** disable `security.login_2fa_scope` or `deviceVerification` to force the
script to pass — instead:

1. Run `sh build/e2e_admin_levels_smoke.sh` against staging before cutover.
2. On production (`https://ekklessia.online`), verify `/register` contains
   `"parish_ueid":` via `curl`, then sign in interactively in a browser as
   `systemadmin`, a `provinceadmin`, and a `dioceseadmin` to verify 2FA/device
   verification, `GET /health/ready` (`database`, `writable`, `directory` all
   `ready`), and scoped dashboard/route boundaries.
3. Confirm SMTP delivery with a real test message, verify `www.` redirects to
   the apex HTTPS URL (§3.2), and confirm real client IPs appear in
   `writable/logs/monitoring-*.log` / `audit_logs`.

Go/no-go signals specific to this host:

- `/health` is 200 at `https://ekklessia.online/health` without `/public` or `/index.php/`
- No HTTPS redirect loop and `https://www.ekklessia.online/` redirects 301 → `https://ekklessia.online/` (§3.2)
- Real client IP (not nginx proxy IP) is recorded by `RequestMonitoringFilter` / audit logs (§3.2)
- `/composer.json`, `/composer.lock`, `/spark`, `/env`, `/.env`, `/app/Config/App.php`, `/.htaccess` never return 200, and `/.user.ini` / `/php.ini` never leak INI directives (§3.1)
- `ProductionSecurityPosture` critical log absent from `writable/logs/`
- `"$PHP_BIN" spark migrate:status --all` shows 0 pending
- `"$PHP_BIN" spark directory:verify-readiness --strict` exits 0 (`province`, `diocese`, and active linked `parish` all populated; zero orphaned rows and zero placeholder sample dioceses)
- `GET /health/ready` as `systemadmin` returns 200 with `database`, `writable`, and `directory` all `ready`
- Live SMTP test email succeeds and passes SPF/DKIM/DMARC
- Cron entries produce clean log output within their window (no PHP 8.3 version-gate errors)

---

## 7. Rollback & Off-Host Backup Safety

HostGator has no blue/green. In the split layout a release has two coupled
parts: the private app root (`~/ekklesia/`) and the public docroot
(`~/public_html/`). Before each deploy, retain matched snapshots in
`~/release-backups/` (§2.2). Treat whole-root archives as disaster-recovery
evidence only (never extract `.env` or stale `writable/` data over live data).

For a **routine code-only rollback**:

1. Enable maintenance mode (§2.3).
2. Check out the previous reviewed commit in a clean worktree, build
   `vendor/` with `composer install --no-dev --optimize-autoloader --no-interaction`,
   and re-sync with `build/hostgator_sync.sh` (or restore matched code-only
   snapshots of `~/ekklesia` and `~/public_html` while preserving `.env` and
   `writable/`).
3. Verify `~/public_html/.htaccess` still ends with the `ea-php85___lsphp` handler (§3.7).
4. Run `"$PHP_BIN" spark cache:clear`, verify `/health` and `/health/ready`,
   then disable maintenance mode.

For a **database rollback** (destructive — requires explicit operator approval
per `DEPLOYMENT_RUNBOOK.md` §9):

```bash
cd ~/ekklesia
PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php' sh build/restore_database.sh writable/backups/<latest>.sql.gz
```

**Restore credentials caveat:** `build/restore_database.sh` imports with
`backup.db.*` (falling back to `database.default.*`). If `backup.db.*` points
at a dump-only account (§1.5), the import will fail mid-restore — and
`restore_database.sh --check` only pings MySQL, so it will not warn you.
Before restoring, comment out `backup.db.*` in `.env` (so the app/owner user
is used) or point it at an account with `CREATE, DROP, ALTER, INSERT, INDEX,
REFERENCES, CREATE VIEW, TRIGGER, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE`
on the database (`LOCK TABLES` is required by `mysqldump`'s default `--add-locks`;
`CREATE ROUTINE`/`ALTER ROUTINE` cover `--routines`).

**Off-host backup replication:** `build/backup_database.sh` writes to
`~/ekklesia/writable/backups/` on the same HostGator disk. Before go-live,
confirm either automated off-host pull of `writable/backups/*.sql.gz` over SSH/SFTP
or HostGator cPanel/JetBackup off-server retention covering both MySQL and
`~/ekklesia/.env` + `writable/uploads/`.

---

## Open items blocking go-live — status 2026-11-28

1. ~~**[OPERATOR]** §1.2–1.6, §1.8 cPanel checks~~ — **done**: SSH (`BatchMode`),
   `ea-php85` (8.5.13) + all extensions, MySQL DB `zhvdeqte_ekklesiaonline` +
   user `zhvdeqte_ekklesia`, split layout, and 12 cron jobs on `CST` verified.
   **Still open:** enable cPanel **Force HTTPS Redirect** toggle (§1.7).
2. ~~**[OPERATOR]** Remove/isolate Softaculous skeleton & pin PHP 8.5 web handler~~ —
   **done 2026-11-25**.
3. **[AGENT+OPERATOR]** Sync updated release (`PRs #88–#90` + deploy fixes) to HostGator —
   **open (blocking Path A & readiness gate)**: live `~/ekklesia` is still at the
   `2026-11-25` deploy and lacks `directory:verify-readiness`,
   `parish_hierarchy_transfer.sh`, and the `www.` HTTPS redirect in `.htaccess`.
4. **[AGENT+OPERATOR]** §3.2 `www.` redirect & real client IP verification —
   **open**: `https://www.ekklessia.online/` currently serves `200 OK` without
   redirecting to apex until `.htaccess` fix is synced; real client IP logging
   (`Config\App::$proxyIPs`) must be verified once an admin logs in.
5. **[OPERATOR]** `.env` secrets & SMTP live test — `.env` is populated (mode
   `600`, zero `CHANGE_ME`, `email.SMTP*` filled); **still open:** send and verify
   a real staging/test email end-to-end (§3.6).
6. **[JOINT]** D3 encryption-gate operator rows complete against this environment.
7. **[OPERATOR+AGENT]** Populate `province`, `diocese`, and `parish` tables (§4.1) —
   **hard NO-GO blocker**: verified live DB on `2026-11-28` has `province=10`,
   `diocese=2` (`Sample Diocese One/Two`), `parish=0`, `users=0`, `profile=0`.
   Because `users`/`profile`/`parish` are empty and local DB passes
   `directory:verify-readiness --strict` (`9` provinces, `109` dioceses, `6,751`
   active linked parishes), run **Path A** immediately after syncing item 3
   (**before** creating any users in item 8).
8. **[OPERATOR]** First-boot `systemadmin` bootstrap + `PermissionRoleSyncSeeder`
   + install first diocesan ordinary (§4.2) — run **after Path A cutover (item 7)**
   and **after confirming SMTP works (item 5)**.
9. **[AGENT+OPERATOR]** Post-deploy validation (§6): `directory:verify-readiness --strict`,
   `GET /health/ready` as `systemadmin` (`database`, `writable`, `directory` all
   `ready`), interactive 2FA login checks across admin levels, `ProductionSecurityPosture`
   log check, and off-host backup pull/verification (§7) — blocked on items 3–8.
