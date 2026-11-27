# HostGator Production Deploy Checklist

Target: `ekklessia.online` on HostGator shared hosting (cPanel, CloudLinux
PHP selector, nginx cache layer in front of Apache). PHP 8.5 confirmed available.

Because HostGator does not support changing a primary domain away from
`public_html`, the canonical HostGator layout is split:

```text
~/ekklesia/       private application root (app/, vendor/, writable/, modules, spark, .env)
~/public_html/    web docroot containing only the contents of public/
```

Do not upload the repository directly into `public_html`: a 2026-11-25 probe of
the Softaculous skeleton showed project files such as `composer.json`,
`composer.lock`, `spark`, and `env` reachable over HTTP. If `ekklessia.online`
is an addon/subdomain whose document root can be changed, the standard
`<project>/public` docroot is also acceptable; this checklist uses the split
layout because it works in both cases.

This checklist adapts `DEPLOYMENT_RUNBOOK.md` §4–§9 to HostGator's constraints.
Items marked **[OPERATOR]** require cPanel/SSH access; **[AGENT-verifiable]**
items can be checked from the repo or via curl after deploy.

---

## 1. cPanel pre-flight (before any upload)

| # | Check | How | Status |
|---|-------|-----|--------|
| 1.1 | PHP 8.5 selected for `ekklessia.online` | cPanel → Select PHP Version → domain dropdown | ✅ confirmed by operator |
| 1.2 | Required extensions enabled | cPanel → Select PHP Version → domain → **Extensions** tab (checkboxes — `extension=` is PHP_INI_SYSTEM, so it cannot be set in `.user.ini`; the startup php.ini is host-managed, so use the selector). Enable: `intl`, `mbstring`, `mysqli`, `gd`, `curl`, `dom`, `xml` (+`xmlreader`/`xmlwriter`/`simplexml`/`libxml`), `zip`, `openssl`, `fileinfo`. `intl` is the riskiest — confirm it exists on the plan | [OPERATOR] |
| 1.3 | SSH access enabled | cPanel → Security → SSH Access (on some HostGator plans, enable shell access via the Customer Portal first); note shell user + port (usually 22) | [OPERATOR] |
| 1.4 | MySQL database + user created | cPanel → MySQL Databases. DB/user names are prefixed with the cPanel username (e.g. `user_ekklesia`). Host is `localhost`, port 3306 | [OPERATOR] |
| 1.5 | Backup DB credentials | Either a second MySQL user on the same DB with `SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES` (mysqldump needs these; a SELECT-only user is NOT sufficient), or reuse the app user for `backup.db.*`. **Restore caveat:** `build/restore_database.sh` reads the same `backup.db.*` keys and imports with them, so a dump-only account **cannot restore** (needs `CREATE, DROP, ALTER, INSERT, INDEX, REFERENCES, CREATE VIEW, TRIGGER, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE` — see §7). `restore_database.sh --check` only pings MySQL and will not catch this. For a restore, temporarily point `backup.db.*` at the app/owner user (or leave it unset to fall back to `database.default.*`) | [OPERATOR] |
| 1.6 | Split-layout paths confirmed | Decide whether `ekklessia.online` is primary or addon. Primary domain: keep `public_html` as docroot and use the split layout. Addon/subdomain: either use the same split layout or point its docroot directly at the private app's `public/`. Never leave the project root web-accessible | [OPERATOR] |
| 1.7 | SSL certificate active + HTTPS forced | AutoSSL/Let's Encrypt issued for `ekklessia.online` + `www`. Enable HostGator's HTTPS redirect/force-HTTPS feature; direct HTTP requests must end at HTTPS, and the cached `/` → `/public` 301 from the Softaculous probe must be purged or expire | [OPERATOR] |
| 1.8 | Cron job feature available | cPanel → Advanced → Cron Jobs | [OPERATOR] |

## 2. Deploy method (no Composer on shared hosting)

HostGator shared has **no `composer` CLI**. Build artifacts locally, upload via
an encrypted, authenticated transport only: **rsync over SSH** (preferred) or
**SFTP**. Verify the server's SSH host key fingerprint on first connect against
a value obtained **out of band**. The cPanel SSH Access screen manages
account keys and does not reliably show the server fingerprint, so ask HostGator
support (via the authenticated support portal) for the host's SSH fingerprint
for this plan and record it here before the first connect:
`SSH host fingerprint: CHANGE_ME (source/date: CHANGE_ME)`. Do not accept an
unverified key on first connect. **Never use plain FTP** — it sends credentials and
release files unencrypted, exposing them to capture or tampering.

```bash
# Local build machine — from a clean repo copy on the release commit:
composer install --no-dev --optimize-autoloader --no-interaction

# Defaults: private root ~/ekklesia, docroot ~/public_html.
# Review the exact first-transfer plan, including docroot deletions:
HOSTGATOR_SSH='user@host' bash build/hostgator_sync.sh --dry-run --delete-public

# First real sync. --delete-public removes the Softaculous skeleton and any
# other docroot files that are not part of public/. Use it only when this
# docroot is dedicated to ekklessia.online; it must not be used when addon
# domains or unrelated applications live below the same public_html.
# .well-known/ (AutoSSL) and cgi-bin/ are preserved.
HOSTGATOR_SSH='user@host' bash build/hostgator_sync.sh --delete-public

# Later routine deploys, after the docroot is clean/dedicated:
HOSTGATOR_SSH='user@host' bash build/hostgator_sync.sh
```

`build/hostgator_sync.sh` performs two separate rsync transfers:

1. the repository (including the locally built production `vendor/`) to the
   private `~/ekklesia/` root, excluding `.env`, tests, runtime logs/sessions,
   backups, uploads, and local dev artifacts; and
2. only `public/` contents to `~/public_html/`.

While syncing, the script injects an absolute `SetEnv EKKLESIA_APP_ROOT` line
into the temporary `.htaccess` copy. `public/index.php` then boots the private
application root even though the web docroot is `public_html`. It also has a
`~/ekklesia` sibling fallback for manual deployments that use the default path.
The script refuses to sync a `vendor/` tree containing PHPUnit/PHPStan; use a
clean release copy rather than destroying the dependencies in a daily-use dev
checkout. If `HOSTGATOR_APP_ROOT` or `HOSTGATOR_PUBLIC_HTML` must differ from
the defaults, set both environment variables before running the script and
verify the injected `SetEnv` path after upload.

Then on the server (SSH):

```bash
cd ~/ekklesia
cp build/env-templates/production.env.template .env   # then edit every CHANGE_ME
PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'       # bare php is 8.3 here — see §3.3
"$PHP_BIN" -v                                         # MUST be 8.5 — see §3.3
"$PHP_BIN" spark config:check App
"$PHP_BIN" spark config:check Logger
"$PHP_BIN" spark config:check Session
"$PHP_BIN" spark config:check 'CodeIgniter\Shield\Config\Auth'
```

Then run the database release as **one guarded block** (runbook §5). It stops
at the first failure, reports whether the backup or the migration failed, and
never seeds, clears cache, or compiles routes after a failure:

```bash
(
  set -e
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
  "$PHP_BIN" spark directory:verify-readiness \
    || { echo 'ABORTED: province/diocese/parish hierarchy is NOT populated — see §4.1 before going live' >&2; exit 1; }
  "$PHP_BIN" spark cache:clear
  "$PHP_BIN" spark routes > /tmp/routes_after.txt
  echo 'Database release complete.'
)
```

**Very first deploy into a CONFIRMED EMPTY database only** (nothing to
restore, so no backup step) — run instead of the block above:

```bash
(
  set -e
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
  "$PHP_BIN" spark routes > /tmp/routes_after.txt
  echo 'Database bootstrap complete. NEXT REQUIRED STEP before go-live: populate province/diocese/parish (§4.1) and verify with directory:verify-readiness.'
)
```

Create `.env` values:

- `app.baseURL = 'https://ekklessia.online/'`
- `app.indexPage =` (empty — REQUIRED: `App::$indexPage` defaults to `'index.php'`;
  without this override every generated link and redirect gains a visible
  `/index.php/` prefix, as observed on the first 2026-11-25 deploy)
- `database.default.*` from cPanel MySQL credentials (host `localhost`)
- `encryption.key` + `encryption.blindIndexKey` — generate independently:
  `php -r "echo 'hex2bin:' . bin2hex(random_bytes(32)) . PHP_EOL;"` (run locally)
- `backup.db.*` per §1.5
- `email.*` SMTP per `docs/EMAIL_DELIVERABILITY.md` (A1 rotation already done)
- `families.enforceSingleFamilyMembership = 1` — safe on a fresh prod DB
  (empty `family_member`; audit query returns zero rows)
- `sms.provider = log_only` until Twilio creds exist

## 3. HostGator-specific risks (verify during deploy)

### 3.1 Split layout and cached `/public` redirect — HIGHEST RISK

The Softaculous skeleton installed on 2026-11-25 proved that PHP can execute a
stock CodeIgniter welcome page, but it also demonstrated the unsafe layout:
`/` redirected to `/public/`, project files were web-accessible, and the 301
was cached by HostGator's nginx edge (`X-Proxy-Cache: HIT`). The production
deploy must not reuse that layout.

After syncing, verify the split layout from outside the host:

```bash
curl -fsS https://ekklessia.online/health    # application health: 200
for path in /composer.json /composer.lock /spark /env /.env /app/Config/App.php; do
  curl -sS -o /dev/null -w "%{http_code} $path\\n" "https://ekklessia.online$path"
done
# None of the probe paths may return 200. 403/404/406 are acceptable edge or
# Apache denial forms; 200 means the private project root is exposed.
```

If `/` still redirects to `/public/` after the docroot is corrected, purge the
HostGator/nginx cache or ask support to clear it. Do not treat a cached 301 as
an application redirect loop. Direct `http://ekklessia.online/` and
`http://ekklessia.online/<path>` must ultimately land on HTTPS without a
`/public/` path segment.

### 3.2 HTTPS detection through the nginx proxy — HIGHEST RISK

HostGator puts an **nginx caching proxy in front of Apache**. TLS may terminate
at nginx, meaning Apache/PHP may see plain HTTP (`$_SERVER['HTTPS']` unset).

`app.forceGlobalSecureRequests = true` + `request->isSecure()` depend on either
`HTTPS=on` reaching PHP or a trusted-proxy `X-Forwarded-Proto` header
(`Config\App::$proxyIPs` is currently an empty array).

**Verify immediately after first request:**

```bash
curl -sI https://ekklessia.online/health | head -5
# Then check for a redirect LOOP on any page — that means isSecure()=false
# behind the proxy. Fix options, in order of preference:
#   a) Configure proxyIPs in app/Config/App.php with HostGator's proxy
#      IP/CIDR + 'X-Forwarded-Proto' (get proxy IPs from response headers
#      or ask HostGator support)
#   b) .htaccess: SetEnvIf X-Forwarded-Proto https HTTPS=on  (only if the
#      proxy overwrites client-supplied X-Forwarded-Proto)
```

Also confirm the cached Softaculous `/public` redirect is gone (§3.1) — the
new `.htaccess`/app config must produce HTTP→HTTPS only, without exposing the
front-controller directory in the public URL.

### 3.3 PHP CLI version for `spark` and cron

cPanel's PHP selector governs the **web** SAPI. The SSH/cron `php` binary may
resolve to a different version. Verify:

```bash
php -v          # via SSH
which php
```

If `php -v` ≠ 8.5, find the 8.5 binary (`ls /opt/cpanel/ea-php85/root/usr/bin/php`,
`ls /opt/alt/php*/usr/bin/php`, `/usr/local/bin/php*`) and use its **absolute
path in every cron entry** — the `build/*_cron.sh` wrappers call bare `php spark`
and inherit cron's minimal PATH.

**Verified on `sh00162.hostgator.com` (2026-11-25):** default SSH `php` is
ea-php83 (8.3.37) and fails the app's 8.5 gate; the correct CLI binary is
`/opt/cpanel/ea-php85/root/usr/bin/php` (8.5.13, all required extensions:
intl, mysqli, gd, curl, dom/xml, zip, openssl, fileinfo). `/opt/alt/` on this
host only ships php74 — do not use the `/opt/alt/php85` path guessed in older
revisions of this document.

**Never rely on bare `php` on this host:** bare `php` runs 8.3 and `spark`'s
version gate aborts with exit code `1` on `STDERR`. All SSH commands in §2 and
§4 therefore use `PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'` (`"$PHP_BIN"
spark …`), which works in both interactive shells and non-interactive scripts/subshells
(unlike `alias php=...`, which does not persist across SSH logins or expand in
non-interactive Bash). In crontab entries, always write the absolute path
`/opt/cpanel/ea-php85/root/usr/bin/php`.

### 3.4 Writable permissions

`fix-permissions.sh` sets `writable/` dirs to 775/664 assuming group-write.
On HostGator, PHP (lsapi/suPHP) runs as the account user, so **owner-write
(755/644) is sufficient** — run the script or `chmod -R u+rwX,g+rX,o-rwx
writable/` and ensure the deploy user owns the files. Never 777.

### 3.5 Resource limits

Shared hosting caps `max_execution_time`, `memory_limit`, and MySQL
`max_allowed_packet`/timeouts. HostGator's published shared-hosting maximums
are currently lower than the `.user.ini` targets (`memory_limit` 256M max vs
512M requested; `max_execution_time` 30s max vs 120 requested;
`max_input_time` 60s max vs 120 requested), so treat those entries as
requested ceilings, not guaranteed effective values. Migrations and
`mysqldump` run under CLI limits (often higher), but the nightly
`backup_database.sh` on a growing DB could eventually hit limits — monitor
`writable/logs/` after first scheduled runs. Batch giving-statement PDF/ZIP
exports and other long web requests must be validated under the effective 30s
cap before launch; if they exceed it, move them to CLI/cron or use VPS hosting.

Web-SAPI overrides ship in the repo at `public/.user.ini` (LSAPI/FPM reads it
per-directory; mod_php ignores it, so local dev is unaffected). It sets
`memory_limit=512M`, `max_execution_time=120`, `max_input_time=120`,
`max_input_vars=3000`, `post_max_size=40M`, `upload_max_filesize=32M`,
`opcache.validate_timestamps=1`, `opcache.revalidate_freq=2`,
`date.timezone=UTC`, `display_errors=Off`, `log_errors=On`,
`error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT`, `expose_php=Off`.
Only `PHP_INI_PERDIR`/`PHP_INI_ALL` directives are honored — OPcache sizing,
`realpath_cache_*`, `allow_url_*` are `PHP_INI_SYSTEM` and silently ignored;
host defaults apply (the cPanel editor does not expose them either).

**Verify loading:** CloudLinux mod_lsapi only reads `.user.ini` when
`lsapi_enable_user_ini On` is set by the host (default `Off`). After deploy,
check the effective values via the web SAPI (not CLI). Do **not** upload a
full `phpinfo()` page. Use a temporary probe with a random filename that
prints only the needed directives, e.g.
`<?php foreach (['memory_limit','post_max_size','opcache.validate_timestamps','date.timezone'] as $k) echo $k, '=', ini_get($k), "\n";`,
fetch it once, and **delete it immediately** (`rm ~/public_html/<random>.php`).
If values aren't applied, ask HostGator support to enable it or rely on the
cPanel editor values.

**cPanel php.ini editor** (the field list shown in cPanel for this domain)
overwrites/augments docroot php config and covers the same resource limits —
set the highest values HostGator actually permits, targeting
`max_execution_time=120`, `max_input_time=120`, `max_input_vars=3000`,
`memory_limit=512M`, `post_max_size=40M`, and `upload_max_filesize=32M` only
when the plan allows them; otherwise record the enforced lower values and treat
them as launch constraints. Leave `display_errors=Disabled`,
`zlib.output_compression=Disabled`, and leave `session.gc_maxlifetime` /
`session.save_path` blank (CI4 sets `session.gc_maxlifetime` from its session
expiration at runtime and its FileHandler uses that during PHP's session GC
in `writable/session`; blank fields keep the app in control). If the editor writes its own
`.user.ini`/`php.ini` into `public/`, it may replace the committed file —
the cPanel values then carry the resource limits; `.user.ini`'s unique
contributions are `date.timezone`, `error_reporting`, `log_errors`,
`expose_php`, `opcache.validate_timestamps`, and `opcache.revalidate_freq`
(re-add those lines if the file is overwritten — without the OPcache lines,
a host default of `validate_timestamps=0` would keep serving stale code
after rsync).

`.user.ini` changes take up to `user_ini.cache_ttl` (~300s) to apply and do
**not** affect CLI/cron — those use the php.ini from cPanel → Select PHP
Version → Options; if `php spark` commands hit memory/time limits, raise
them there.

### 3.6 Outbound connectivity

SMTP (587/TLS), Google APIs (Gmail OAuth), and Twilio (if enabled) require
outbound 443/587 — open on HostGator shared, but verify SMTP auth succeeds
with a real send during validation (runbook §7).

### 3.7 PHP version selection lives in the docroot `.htaccess` — DEPLOY HAZARD

`uapi LangPHP php_get_vhost_versions` reports the vhost (`zhv.deq.temporary.site`)
as ea-php85, but with `php_fpm: 0` that setting does **not** reach the web SAPI.
On non-FPM cPanel/lsapi hosts the per-domain PHP version is enforced only by an
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
does NOT exist on lsapi hosts — forcing it produces edge 404s for every PHP
request (static files still serve). Never append it manually.

Because rsync replaces `.htaccess` whenever its content changes, a sync could
strip cPanel's block and silently revert the site to the system default PHP
(ea-php83 → 503 version gate). `build/hostgator_sync.sh` therefore injects the
byte-identical block itself (see `HOSTGATOR_PHP_HANDLER`); verify after every
sync that the live `.htaccess` still ends with the cPanel markers. If the plan
ever enables PHP-FPM for the domain, the vhost setting takes over and
`HOSTGATOR_PHP_HANDLER=` (empty) skips injection.

## 4. First-boot bootstrap & Parish Hierarchy Population (REQUIRED before go-live)

### 4.1 Parish Hierarchy Population & Webhosting Migration Plan (NO-GO GATE)

`DatabaseSeeder` seeds `province` (Provinces I–X) and two placeholder dioceses
(`Sample Diocese One`, `Sample Diocese Two`), and seeds **zero `parish` rows**.
Going live with an empty `parish` table (or missing `province` / `diocese` parent
rows) is a **hard no-go**: registration, connect cards, parish-scoped dashboards,
and `GET /health/ready` (`components.directory`) all fail without at least one
active, non-deleted `parish` linked to a valid `diocese` → `province` chain.

Choose **one** of the following population paths before enabling public traffic:

#### Path A — Moving an already-populated local/staging system to a fresh webhosting DB (preferred for initial cutover)

Use this path **only when seeding a fresh destination database** (immediately
after `migrate --all` and `db:seed DatabaseSeeder`, **before** creating any
users, profiles, or affiliations in §4.2). Never use `mysqldump --replace`
against a live database with existing `profile`/`parish_affiliation` rows:
`REPLACE INTO` deletes and re-inserts conflicting `parish_ueid` rows while FK
checks are disabled, which can orphan existing profile/affiliation references.
For updates to an already-live database, use Path B (`directory:sync-ueid --apply`),
which updates in place by `parish_ueid` without deleting IDs (note:
`directory:sync-ueid --apply` sets `is_active = 1` on every matched parish, so
always inspect the dry-run report in `writable/reports/` — it names every
reactivated parish — and pass `--preserve-inactive` when intentionally
deactivated parishes must stay inactive).

The supported implementation is `build/parish_hierarchy_transfer.sh` (synced
to both source and destination by `build/hostgator_sync.sh`). It reads database
credentials from `.env`, keeps passwords out of process arguments, refuses to
import when `users`/`profile`/`parish` are non-empty, and runs the entire
import in one atomic transaction (any error rolls the hierarchy back to the
pre-import seeded state). Export only the canonical migration-backed hierarchy
tables in strict foreign-key order (`province` → `diocese` → `parish`) so
existing IDs, `parish_ueid`, `public_token`, addresses, and
province-to-diocese mappings are preserved identically on webhosting. The
optional legacy `diocese_alias` lookup table is deliberately **not**
transferred: no migration creates it, so a fresh destination never has it and
`directory:sync-ueid` rebuilds aliases where the table exists.

1. **Pre-export check on source DB** (confirm clean parent chain):
   ```bash
   php spark directory:verify-readiness --strict
   ```
2. **Export hierarchy tables from source DB**:
   ```bash
   bash build/parish_hierarchy_transfer.sh export parish_hierarchy_$(date +%Y%m%d).sql.gz
   ```
   The archive is created owner-only (mode 600) because parish rows include restricted tax IDs — keep it that way through transfer.
3. **Transfer over SSH/SFTP** to `~/ekklesia/writable/backups/` (never plain FTP, never into `public_html/`).
4. **Take a HostGator pre-import backup** (`sh build/backup_database.sh` + `gzip -t`), then import on HostGator inside `~/ekklesia/` (the script aborts before mutating anything if `users`, `profile`, or `parish` are non-empty):
   ```bash
   bash build/parish_hierarchy_transfer.sh import writable/backups/parish_hierarchy_YYYYMMDD.sql.gz
   ```
   The import first clears the baseline-seeded `diocese`/`province` rows inside the same transaction, so source `province.id` and `diocese.id` values are preserved 1:1 with no ID drift.
5. **Verify on HostGator** — compare destination and source counts, then confirm strict readiness and that no placeholder sample dioceses remain:
   ```bash
   PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
   bash build/parish_hierarchy_transfer.sh verify          # prints destination counts
   "$PHP_BIN" spark directory:verify-readiness --strict     # exit 0, zero warnings
   ```
6. **Keep populated across subsequent deploys**:
   - `build/hostgator_sync.sh` syncs code only and never touches MySQL or `writable/backups/`.
   - Routine deploys run `spark migrate --all`, `spark db:seed DatabaseSeeder` (idempotent — does not delete or overwrite existing `province`, `diocese`, or `parish` rows), and `spark directory:verify-readiness` (which aborts the release block if the hierarchy is ever empty).
   - Nightly `build/backup_database.sh` backs up the populated `province`, `diocese`, and `parish` tables with the rest of the database; verify row counts during restore drills.

#### Path B — Fresh webhosting install using the shipped UEID directory (`docs/UEID.json`)

`docs/UEID.json` (6,752 parishes across 108 dioceses) is synced to
`~/ekklesia/docs/UEID.json` by `build/hostgator_sync.sh`.

1. Ensure the real `province` rows and target `diocese` rows (mapped to their
   actual `province_id`) exist first — do not rely on `--default-province-id=1`
   for production unless you intentionally reconcile province assignments
   immediately afterward.
2. Dry-run first and review the generated report in `writable/reports/` (it names every parish the sync would reactivate):
   ```bash
   PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
   "$PHP_BIN" spark directory:sync-ueid
   ```
3. Apply inside a transaction and verify (pass `--preserve-inactive` when intentionally deactivated parishes must stay inactive):
   ```bash
   "$PHP_BIN" spark directory:sync-ueid --apply --preserve-inactive
   "$PHP_BIN" spark directory:verify-readiness
   ```
4. Remove unused `Sample Diocese One` / `Sample Diocese Two` rows created by
   `ProvincesAndDiocesesSeeder` once real dioceses are loaded.

#### Path C — Minimal launch (manual creation via System Admin UI)

After creating the first `systemadmin` account (§4.2), create or verify the
target `province`, create the real `diocese` under that province, create each
active `parish` under that diocese, remove placeholder sample dioceses, and run
`"$PHP_BIN" spark directory:verify-readiness --strict`.

### 4.2 First-boot account bootstrap (after migrate + seed)

Per `DEPLOYMENT_RUNBOOK.md` §7 "First-boot account bootstrap":

```bash
# Set these first (quoted shell variables, so the block runs as pasted):
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

Then install the first diocesan ordinary via **System → Diocesan Ordinaries →
Install** in the UI (requires the bishop's `users` account + diocese to exist).

## 5. Cron install

Install the approved matrix from `docs/CRON_SCHEDULE.md` via cPanel → Cron Jobs.
**Use the absolute PHP 8.5 path** (see §3.3) and absolute script paths:

cPanel adds one command per job, so set `PHP_BIN` **inline on each wrapper
job** (the `build/*.sh` wrappers read `$PHP_BIN`; don't rely on a crontab-level
environment line). Direct `php spark` jobs use the absolute binary instead:

```cron
30 1 * * *   PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash /home/user/ekklesia/build/service_metrics_refresh_cron.sh
45 1 * * *   cd /home/user/ekklesia && /opt/cpanel/ea-php85/root/usr/bin/php spark attendance:sync-diocesan-aggregates --rolling-days 35 >> writable/logs/attendance_aggregates_cron.log 2>&1
# ... full matrix in docs/CRON_SCHEDULE.md — substitute PHP binary + app root
```

After saving, confirm the wrappers picked up 8.5: run one manually as
`PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash build/clergy_reconcile_grants_cron.sh`
and check its log, then inspect `crontab -l` to see exactly what cPanel stored.

Verify server timezone first (`date` via SSH) — morning jobs assume
server-local time. Do NOT install `build/cron_sync_church_people.sh` (dead —
command removed).

After install, watch `writable/logs/*_cron.log` for the first scheduled runs.

## 6. Post-deploy validation

Run `DEPLOYMENT_RUNBOOK.md` §5/§7, substituting `PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'`
(`"$PHP_BIN" spark …`) for every bare `php spark` command, plus:

```bash
PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php'
"$PHP_BIN" spark migrate:status --all
"$PHP_BIN" spark directory:verify-readiness
export BASE_URL='https://ekklessia.online'
# + PROVINCE_ADMIN_*/DIOCESAN_ADMIN_* creds created via admin UI after bootstrap
sh build/e2e_admin_levels_smoke.sh
curl -fsS "$BASE_URL/health"           # 200
# sign in as systemadmin → GET /health/ready → 200, database+writable+directory ready
```

Go/no-go signals specific to this host:

- `/health` is 200 at the canonical root URL without `/public`
- No HTTPS redirect loop (§3.2)
- `/composer.json`, `/composer.lock`, `/spark`, `/env`, `/.env`, and `/app/Config/App.php` never return 200 (§3.1)
- `ProductionSecurityPosture` critical log absent from `writable/logs/`
- `PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php' && "$PHP_BIN" spark migrate:status --all` shows 0 pending
- `PHP_BIN='/opt/cpanel/ea-php85/root/usr/bin/php' && "$PHP_BIN" spark directory:verify-readiness` exits 0 (`province`, `diocese`, and active linked `parish` all populated)
- `GET /health/ready` as `systemadmin` returns 200 with `database`, `writable`, and `directory` all `ready`
- First cron entries produce log output within their window

## 7. Rollback

HostGator has no blue/green. In the split layout a release has two coupled
parts: the private app root (`~/ekklesia/`) and the public docroot
(`~/public_html/`). Before each deploy, retain timestamped copies or archives
of both (for example `~/release-backups/YYYYMMDD-HHMMSS-ekklesia/` and
`...-public_html/`), or rely on cPanel backup/JetBackup snapshots after
confirming they cover both locations. Treat whole-root archives as
disaster-recovery evidence, not as a routine code-rollback input: they may
contain `.env`, uploads, sessions, and other live application data.

For a routine code-only rollback, redeploy the previous reviewed commit with
`build/hostgator_sync.sh` when that release contains the script. That is the
preferred path because it replaces the matched private code root and public
docroot while excluding `.env` and writable runtime data. The first release
created with this checklist may need to roll back to a pre-split release that
predates the script; use the matched-snapshot procedure below for that case.
If a manual restore is unavoidable, restore only code
and public assets from matched snapshots while preserving `.env` and writable
application data. Never roll back only the private root or only the docroot,
because `index.php`, `.htaccess`, assets, and application code can become
mismatched. Database rollback =
`sh build/restore_database.sh <latest .sql.gz>` — requires explicit approval
per runbook §8 (destructive). **Restore credentials:** the script imports with
`backup.db.*` (falling back to `database.default.*`). If `backup.db.*` points
at a dump-only account (§1.5), the import will fail mid-restore — and
`restore_database.sh --check` only pings MySQL, so it will not warn you.
Before restoring, comment out `backup.db.*` in `.env` (so the app/owner user
is used) or point it at an account with `CREATE, DROP, ALTER, INSERT, INDEX,
REFERENCES, CREATE VIEW, TRIGGER, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE`
on the database. `LOCK TABLES` is required because mysqldump's default
`--add-locks` wraps each table's `INSERT`s in `LOCK TABLES … WRITE`; without
it the import stops after recreating a table but before loading its rows.
`CREATE ROUTINE`/`ALTER ROUTINE` cover the `--routines` objects in the dump.

---

## Open items blocking deploy — status 2026-11-25/26

1. ~~**[OPERATOR]** §1.2–1.8 cPanel checks~~ — **done**: SSH verified (BatchMode, stable);
   extensions verified on ea-php85 CLI; DB `zhvdeqte_ekklesiaonline` + user
   `zhvdeqte_ekklesia` exist with ALL PRIVILEGES; split layout deployed. **Still
   open:** force-HTTPS toggle (app-level redirect already works), cron install.
2. ~~**[OPERATOR]** SSH credentials + confirm `php -v` on server~~ — **done**; web SAPI
   required the `.htaccess` lsapi handler fix (§3.7), CLI is
   `/opt/cpanel/ea-php85/root/usr/bin/php` (§3.3)
3. ~~**[OPERATOR]** Remove/isolate the Softaculous skeleton and purge the cached `/` → `/public` redirect~~ —
   **done 2026-11-25**: `hostgator_sync.sh --delete-public` removed the skeleton;
   cached redirect gone; `/composer.json` `/spark` `/env` → 404, `/.env` → 406
4. **[AGENT+OPERATOR]** §3.2 proxy/HTTPS verification — **partially done**: no redirect
   loop observed; `http://` → single clean 307 to `https://`; `proxyIPs` not needed
   so far. Watch for edge cases during validation.
5. **[OPERATOR]** `.env` secret generation — **done except SMTP**: `.env` written
   2026-11-25 (mode 600, zero `CHANGE_ME`, independent encryption keys);
   `email.SMTPHost/User/Pass` empty pending the operator's rotated credential
6. **[JOINT]** D3 encryption-gate operator rows complete against this environment
7. **[OPERATOR]** Populate `province`, `diocese`, and `parish` tables (§4.1) and pass `"$PHP_BIN" spark directory:verify-readiness` — **required before go-live** (`DatabaseSeeder` leaves `parish` empty). **Path A cutover runs first** while `users`/`profile`/`parish` are empty; Paths B/C can run after item 8 if preferred.
8. **[OPERATOR]** First-boot bootstrap (§4.2) — shield user not yet created. Run **after item 7 if using Path A** (the cutover requires an empty `users` table), or anytime for Paths B/C.
9. **[AGENT]** e2e smoke + `/health/ready` (`database`, `writable`, `directory`) as systemadmin — blocked on items 7–8
