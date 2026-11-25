# HostGator Production Deploy Checklist

Target: `ekklessia.online` on HostGator shared hosting (cPanel/hPanel, CloudLinux
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
Items marked **[OPERATOR]** require hPanel/SSH access; **[AGENT-verifiable]**
items can be checked from the repo or via curl after deploy.

---

## 1. hPanel pre-flight (before any upload)

| # | Check | How | Status |
|---|-------|-----|--------|
| 1.1 | PHP 8.5 selected for `ekklessia.online` | hPanel → Select PHP Version → domain dropdown | ✅ confirmed by operator |
| 1.2 | Required extensions enabled | hPanel → Select PHP Version → domain → **Extensions** tab (checkboxes — `extension=` is PHP_INI_SYSTEM, so it cannot be set in `.user.ini`; the startup php.ini is host-managed, so use the selector). Enable: `intl`, `mbstring`, `mysqli`, `gd`, `curl`, `dom`, `xml` (+`xmlreader`/`xmlwriter`/`simplexml`/`libxml`), `zip`, `openssl`, `fileinfo`. `intl` is the riskiest — confirm it exists on the plan | [OPERATOR] |
| 1.3 | SSH access enabled | hPanel → Advanced → SSH Access → enable; note shell user + port (usually 22) | [OPERATOR] |
| 1.4 | MySQL database + user created | hPanel → MySQL Databases. DB/user names are prefixed with the cPanel username (e.g. `user_ekklesia`). Host is `localhost`, port 3306 | [OPERATOR] |
| 1.5 | Backup DB credentials | Either a second MySQL user on the same DB with `SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES` (mysqldump needs these; a SELECT-only user is NOT sufficient), or reuse the app user for `backup.db.*`. **Restore caveat:** `build/restore_database.sh` reads the same `backup.db.*` keys and imports with them, so a dump-only account **cannot restore** (needs `CREATE, DROP, ALTER, INSERT, INDEX, REFERENCES, CREATE VIEW, TRIGGER, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE` — see §7). `restore_database.sh --check` only pings MySQL and will not catch this. For a restore, temporarily point `backup.db.*` at the app/owner user (or leave it unset to fall back to `database.default.*`) | [OPERATOR] |
| 1.6 | Split-layout paths confirmed | Decide whether `ekklessia.online` is primary or addon. Primary domain: keep `public_html` as docroot and use the split layout. Addon/subdomain: either use the same split layout or point its docroot directly at the private app's `public/`. Never leave the project root web-accessible | [OPERATOR] |
| 1.7 | SSL certificate active + HTTPS forced | AutoSSL/Let's Encrypt issued for `ekklessia.online` + `www`. Enable HostGator's HTTPS redirect/force-HTTPS feature; direct HTTP requests must end at HTTPS, and the cached `/` → `/public` 301 from the Softaculous probe must be purged or expire | [OPERATOR] |
| 1.8 | Cron job feature available | hPanel → Advanced → Cron Jobs | [OPERATOR] |

## 2. Deploy method (no Composer on shared hosting)

HostGator shared has **no `composer` CLI**. Build artifacts locally, upload via
an encrypted, authenticated transport only: **rsync over SSH** (preferred) or
**SFTP**. Verify the server's SSH host key fingerprint on first connect against
a value obtained **out of band**. The hPanel/cPanel SSH Access screen manages
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
# Review the transfer first:
HOSTGATOR_SSH='user@host' bash build/hostgator_sync.sh --dry-run

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
php -v                                                # MUST be 8.5 — see §3.3
php spark config:check App
php spark config:check Logger
php spark config:check Session
php spark config:check 'CodeIgniter\Shield\Config\Auth'
```

Then run the database release as **one guarded block** (runbook §5). It stops
at the first failure, reports whether the backup or the migration failed, and
never seeds, clears cache, or compiles routes after a failure:

```bash
(
  set -e
  sh build/backup_database.sh --check                 # needs backup.db.* filled
  sh build/restore_database.sh --check
  php spark migrate:status --all

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
  MIGRATE_OUT="$(php spark migrate --all 2>&1)" && MIGRATE_RC=0 || MIGRATE_RC=$?
  printf '%s\n' "$MIGRATE_OUT"
  if [ "$MIGRATE_RC" -ne 0 ] \
    || ! printf '%s\n' "$MIGRATE_OUT" | grep -q 'Migrations complete.' \
    || printf '%s\n' "$MIGRATE_OUT" | grep -q 'Exception'; then
    echo "ABORTED: migration FAILED after backup — restore from $BACKUP_FILE if needed; not seeded" >&2
    exit 1
  fi

  # Same post-migration check as CI: no Exception and no unapplied row
  # (an empty "---" migrated-on cell) in migrate:status.
  STATUS_OUT="$(php spark migrate:status --all 2>&1)" && STATUS_RC=0 || STATUS_RC=$?
  printf '%s\n' "$STATUS_OUT"
  if [ "$STATUS_RC" -ne 0 ] || printf '%s\n' "$STATUS_OUT" | grep -q -E 'Exception|\| *---  *\|'; then
    echo "ABORTED: migration status check failed or migrations pending — restore from $BACKUP_FILE if needed; not seeded" >&2
    exit 1
  fi

  SEED_OUT="$(php spark db:seed DatabaseSeeder 2>&1)" && SEED_RC=0 || SEED_RC=$?
  printf '%s\n' "$SEED_OUT"
  if [ "$SEED_RC" -ne 0 ] || printf '%s\n' "$SEED_OUT" | grep -q 'Exception'; then
    echo "ABORTED: seeding FAILED after migration — restore from $BACKUP_FILE if needed" >&2
    exit 1
  fi
  php spark cache:clear
  php spark routes > /tmp/routes_after.txt
  echo 'Database release complete.'
)
```

**Very first deploy into a CONFIRMED EMPTY database only** (nothing to
restore, so no backup step) — run instead of the block above:

```bash
(
  set -e
  MIGRATE_OUT="$(php spark migrate --all 2>&1)" && MIGRATE_RC=0 || MIGRATE_RC=$?
  printf '%s\n' "$MIGRATE_OUT"
  if [ "$MIGRATE_RC" -ne 0 ] \
    || ! printf '%s\n' "$MIGRATE_OUT" | grep -q 'Migrations complete.' \
    || printf '%s\n' "$MIGRATE_OUT" | grep -q 'Exception'; then
    echo 'ABORTED: migration FAILED — not seeded' >&2
    exit 1
  fi

  STATUS_OUT="$(php spark migrate:status --all 2>&1)" && STATUS_RC=0 || STATUS_RC=$?
  printf '%s\n' "$STATUS_OUT"
  if [ "$STATUS_RC" -ne 0 ] || printf '%s\n' "$STATUS_OUT" | grep -q -E 'Exception|\| *---  *\|'; then
    echo 'ABORTED: migration status check failed or migrations pending — not seeded' >&2
    exit 1
  fi

  SEED_OUT="$(php spark db:seed DatabaseSeeder 2>&1)" && SEED_RC=0 || SEED_RC=$?
  printf '%s\n' "$SEED_OUT"
  if [ "$SEED_RC" -ne 0 ] || printf '%s\n' "$SEED_OUT" | grep -q 'Exception'; then
    echo 'ABORTED: seeding FAILED — bootstrap incomplete' >&2
    exit 1
  fi
  php spark cache:clear
  php spark routes > /tmp/routes_after.txt
  echo 'Database bootstrap complete.'
)
```

Create `.env` values:

- `app.baseURL = 'https://ekklessia.online/'`
- `database.default.*` from hPanel MySQL credentials (host `localhost`)
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

hPanel's PHP selector governs the **web** SAPI. The SSH/cron `php` binary may
resolve to a different version. Verify:

```bash
php -v          # via SSH
which php
```

If `php -v` ≠ 8.5, find the 8.5 binary (`ls /opt/alt/php*/usr/bin/php`,
`/usr/local/bin/php*`) and use its **absolute path in every cron entry** —
the `build/*_cron.sh` wrappers call bare `php spark` and inherit cron's
minimal PATH.

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
host defaults apply (the hPanel editor does not expose them either).

**Verify loading:** CloudLinux mod_lsapi only reads `.user.ini` when
`lsapi_enable_user_ini On` is set by the host (default `Off`). After deploy,
check the effective values via the web SAPI (not CLI). Do **not** upload a
full `phpinfo()` page. Use a temporary probe with a random filename that
prints only the needed directives, e.g.
`<?php foreach (['memory_limit','post_max_size','opcache.validate_timestamps','date.timezone'] as $k) echo $k, '=', ini_get($k), "\n";`,
fetch it once, and **delete it immediately** (`rm ~/public_html/<random>.php`).
If values aren't applied, ask HostGator support to enable it or rely on the
hPanel editor values.

**hPanel php.ini editor** (the field list shown in hPanel for this domain)
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
the hPanel values then carry the resource limits; `.user.ini`'s unique
contributions are `date.timezone`, `error_reporting`, `log_errors`,
`expose_php`, `opcache.validate_timestamps`, and `opcache.revalidate_freq`
(re-add those lines if the file is overwritten — without the OPcache lines,
a host default of `validate_timestamps=0` would keep serving stale code
after rsync).

`.user.ini` changes take up to `user_ini.cache_ttl` (~300s) to apply and do
**not** affect CLI/cron — those use the php.ini from hPanel → Select PHP
Version → Options; if `php spark` commands hit memory/time limits, raise
them there.

### 3.6 Outbound connectivity

SMTP (587/TLS), Google APIs (Gmail OAuth), and Twilio (if enabled) require
outbound 443/587 — open on HostGator shared, but verify SMTP auth succeeds
with a real send during validation (runbook §7).

## 4. First-boot bootstrap (after migrate + seed)

Per `DEPLOYMENT_RUNBOOK.md` §7 "First-boot account bootstrap":

```bash
# Set these first (quoted shell variables, so the block runs as pasted):
ADMIN_USER='CHANGE_ME'
ADMIN_EMAIL='CHANGE_ME@example.org'

php spark shield:user create -n "$ADMIN_USER" -e "$ADMIN_EMAIL" -g systemadmin
php spark shield:user activate -e "$ADMIN_EMAIL"    # users.active defaults to 0
# Note the numeric id printed by `create` (or look it up), then:
ADMIN_USER_ID='CHANGE_ME'
php spark user:check "$ADMIN_USER_ID"
php spark db:seed PermissionRoleSyncSeeder          # after users/groups exist
```

Then install the first diocesan ordinary via **System → Diocesan Ordinaries →
Install** in the UI (requires the bishop's `users` account + diocese to exist).

## 5. Cron install

Install the approved matrix from `docs/CRON_SCHEDULE.md` via hPanel → Cron Jobs.
**Use the absolute PHP 8.5 path** (see §3.3) and absolute script paths:

hPanel adds one command per job, so set `PHP_BIN` **inline on each wrapper
job** (the `build/*.sh` wrappers read `$PHP_BIN`; don't rely on a crontab-level
environment line). Direct `php spark` jobs use the absolute binary instead:

```cron
30 1 * * *   PHP_BIN=/opt/alt/php85/usr/bin/php bash /home/user/ekklesia/build/service_metrics_refresh_cron.sh
45 1 * * *   cd /home/user/ekklesia && /opt/alt/php85/usr/bin/php spark attendance:sync-diocesan-aggregates --rolling-days 35 >> writable/logs/attendance_aggregates_cron.log 2>&1
# ... full matrix in docs/CRON_SCHEDULE.md — substitute PHP binary + app root
```

After saving, confirm the wrappers picked up 8.5: run one manually as
`PHP_BIN=/opt/alt/php85/usr/bin/php bash build/clergy_reconcile_grants_cron.sh`
and check its log, then inspect `crontab -l` to see exactly what hPanel stored.

Verify server timezone first (`date` via SSH) — morning jobs assume
server-local time. Do NOT install `build/cron_sync_church_people.sh` (dead —
command removed).

After install, watch `writable/logs/*_cron.log` for the first scheduled runs.

## 6. Post-deploy validation

Runbook §5/§7 verbatim, plus:

```bash
export BASE_URL='https://ekklessia.online'
# + PROVINCE_ADMIN_*/DIOCESAN_ADMIN_* creds created via admin UI after bootstrap
sh build/e2e_admin_levels_smoke.sh
curl -fsS "$BASE_URL/health"           # 200
# sign in as systemadmin → GET /health/ready → 200, database+writable ready
```

Go/no-go signals specific to this host:

- `/health` is 200 at the canonical root URL without `/public`
- No HTTPS redirect loop (§3.2)
- `/composer.json`, `/composer.lock`, `/spark`, `/env`, `/.env`, and `/app/Config/App.php` never return 200 (§3.1)
- `ProductionSecurityPosture` critical log absent from `writable/logs/`
- `php spark migrate:status --all` shows 0 pending
- First cron entries produce log output within their window

## 7. Rollback

HostGator has no blue/green. In the split layout a release has two coupled
parts: the private app root (`~/ekklesia/`) and the public docroot
(`~/public_html/`). Keep timestamped copies or archives of both before each
deploy (for example `~/release-backups/YYYYMMDD-HHMMSS-ekklesia/` and
`...-public_html/`), or rely on hPanel backup/JetBackup snapshots after
confirming they cover both locations. A code-only rollback restores both parts
from the same release — never roll back only the private root or only the
docroot, because `index.php`, `.htaccess`, assets, and application code can
become mismatched. Redeploying the previous reviewed commit with
`build/hostgator_sync.sh` is also acceptable. Database rollback =
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

## Open items blocking deploy

1. **[OPERATOR]** §1.2–1.8 hPanel checks (extensions, SSH, MySQL, split-layout paths, SSL, cron)
2. **[OPERATOR]** SSH credentials + confirm `php -v` on server
3. **[OPERATOR]** Remove/isolate the Softaculous skeleton and purge the cached `/` → `/public` redirect
4. **[AGENT+OPERATOR]** §3.1 split-layout exposure probes and §3.2 proxy/HTTPS verification — `proxyIPs` may still need HostGator-specific configuration after the first request
5. **[OPERATOR]** `.env` secret generation (encryption keys, SMTP already rotated)
6. **[JOINT]** D3 encryption-gate operator rows complete against this environment
