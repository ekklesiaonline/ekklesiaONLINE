# HostGator Production Deploy Checklist

Target: `ekklessia.online` on HostGator shared hosting (cPanel/hPanel, CloudLinux
PHP selector, nginx cache layer in front of Apache). PHP 8.5 confirmed available.

This checklist adapts `DEPLOYMENT_RUNBOOK.md` §4–§9 to HostGator's constraints.
Items marked **[OPERATOR]** require hPanel/SSH access; **[AGENT-verifiable]**
items can be checked from the repo or via curl after deploy.

---

## 1. hPanel pre-flight (before any upload)

| # | Check | How | Status |
|---|-------|-----|--------|
| 1.1 | PHP 8.5 selected for `ekklessia.online` | hPanel → Select PHP Version → domain dropdown | ✅ confirmed by operator |
| 1.2 | Required extensions enabled | Same screen → Options/Extensions: `intl`, `mbstring`, `mysqli` (or `mysqlnd`), `gd`, `curl`, `dom`/`xml`, `zip`, `openssl` | [OPERATOR] |
| 1.3 | SSH access enabled | hPanel → Advanced → SSH Access → enable; note shell user + port (usually 22) | [OPERATOR] |
| 1.4 | MySQL database + user created | hPanel → MySQL Databases. DB/user names are prefixed with the cPanel username (e.g. `user_ekklesia`). Host is `localhost`, port 3306 | [OPERATOR] |
| 1.5 | Backup DB credentials | Either a second MySQL user on the same DB with `SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES` (mysqldump needs these; a SELECT-only user is NOT sufficient), or reuse the app user for `backup.db.*`. **Restore caveat:** `build/restore_database.sh` reads the same `backup.db.*` keys and imports with them, so a dump-only account **cannot restore** (needs `CREATE, DROP, ALTER, INSERT, INDEX, REFERENCES, CREATE VIEW, TRIGGER`, etc.). `restore_database.sh --check` only pings MySQL and will not catch this. For a restore, temporarily point `backup.db.*` at the app/owner user (or leave it unset to fall back to `database.default.*`) | [OPERATOR] |
| 1.6 | Document root points at `public/` | hPanel → Domains → `ekklessia.online` document root. App layout is `<root>/public/index.php` — the domain docroot MUST be the app's `public/` directory, not the project root | [OPERATOR] |
| 1.7 | SSL certificate active | AutoSSL/Let's Encrypt issued for `ekklessia.online` + `www`. Currently HTTPS→HTTP redirect observed — must end as HTTP→HTTPS only | [OPERATOR] |
| 1.8 | Cron job feature available | hPanel → Advanced → Cron Jobs | [OPERATOR] |

## 2. Deploy method (no Composer on shared hosting)

HostGator shared has **no `composer` CLI**. Build artifacts locally, upload via
SSH/rsync (preferred) or FTP.

```bash
# Local build machine — from repo root, on the release commit:
composer install --no-dev --optimize-autoloader --no-interaction

# Upload everything EXCEPT:
#   .git/, tests/, .env, writable/{cache,logs,session,uploads,debugbar}/*,
#   node_modules/
# Keep build/env-templates/ — the server-side `cp` below needs
# production.env.template (it contains only CHANGE_ME placeholders).
rsync -avz \
  --exclude='.git/' --exclude='tests/' --exclude='.env' \
  --exclude='node_modules/' \
  --exclude='writable/cache/*' --exclude='writable/logs/*' \
  --exclude='writable/session/*' --exclude='writable/uploads/*' \
  --exclude='writable/debugbar/*' \
  ./ user@host:/home/user/ekklesia/
```

Then on the server (SSH):

```bash
cd ~/ekklesia
cp build/env-templates/production.env.template .env   # then edit every CHANGE_ME
php -v                                                # MUST be 8.5 — see §3.2
php spark config:check App
php spark config:check Logger
php spark config:check Session
php spark config:check 'CodeIgniter\Shield\Config\Auth'
sh build/backup_database.sh --check                   # needs backup.db.* filled
sh build/restore_database.sh --check
php spark migrate:status --all
# Fresh, verified restore point BEFORE migrating (runbook §5). Skippable only
# on the very first deploy into an empty database.
sh build/backup_database.sh
LATEST_BACKUP="$(ls -t writable/backups/*.sql.gz | head -1)"
gzip -t "$LATEST_BACKUP" && ls -l "$LATEST_BACKUP"   # must exist, non-empty, pass gzip -t
php spark migrate --all
php spark db:seed DatabaseSeeder
php spark cache:clear
php spark routes > /tmp/routes_after.txt
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

### 3.1 HTTPS detection through the nginx proxy — HIGHEST RISK

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

Also confirm the current skeleton's HTTPS→HTTP redirect is gone — the new
`.htaccess`/app config must produce HTTP→HTTPS only.

### 3.2 PHP CLI version for `spark` and cron

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

### 3.3 Writable permissions

`fix-permissions.sh` sets `writable/` dirs to 775/664 assuming group-write.
On HostGator, PHP (lsapi/suPHP) runs as the account user, so **owner-write
(755/644) is sufficient** — run the script or `chmod -R u+rwX,g+rX,o-rwx
writable/` and ensure the deploy user owns the files. Never 777.

### 3.4 Resource limits

Shared hosting caps `max_execution_time`, `memory_limit`, and MySQL
`max_allowed_packet`/timeouts. Migrations and `mysqldump` run under CLI limits
(often higher), but the nightly `backup_database.sh` on a growing DB could
eventually hit limits — monitor `writable/logs/` after first scheduled runs.
If needed, `.user.ini` in `public/` can raise `memory_limit`/`max_execution_time`
for the web SAPI only.

### 3.5 Outbound connectivity

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
**Use the absolute PHP 8.5 path** (see §3.2) and absolute script paths:

```cron
30 1 * * *   cd /home/user/ekklesia && /opt/alt/php85/usr/bin/php spark service-metrics:refresh --rolling-days 35 --rebuild-period-buckets >> writable/logs/service_metrics_refresh_cron.log 2>&1
# ... full matrix in docs/CRON_SCHEDULE.md — substitute PHP binary + ROOT_DIR
```

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

- No HTTPS redirect loop (§3.1)
- `ProductionSecurityPosture` critical log absent from `writable/logs/`
- `php spark migrate:status --all` shows 0 pending
- First cron entries produce log output within their window

## 7. Rollback

HostGator has no blue/green. Keep the previous release as a sibling directory
(`~/ekklesia-prev/`) or rely on hPanel backup/JetBackup snapshots. Docroot swap
= update the domain's document root or rename dirs. Database rollback =
`sh build/restore_database.sh <latest .sql.gz>` — requires explicit approval
per runbook §8 (destructive).

---

## Open items blocking deploy

1. **[OPERATOR]** §1.2–1.8 hPanel checks (extensions, SSH, MySQL, docroot, SSL, cron)
2. **[OPERATOR]** SSH credentials + confirm `php -v` on server
3. **[AGENT+OPERATOR]** §3.1 proxy/HTTPS verification — may need a code change
   (`proxyIPs` config) discovered only after first request
4. **[OPERATOR]** `.env` secret generation (encryption keys, SMTP already rotated)
5. **[JOINT]** D3 encryption-gate operator rows complete against this environment
