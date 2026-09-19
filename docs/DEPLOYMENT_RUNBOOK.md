# Deployment Runbook

## Purpose

This is the canonical go/no-go, deployment, validation, and rollback procedure for ekklesiaRD.

## 1. Supported Production Platform

- PHP 8.2–8.5.
- MySQL 8.0+.
- Composer 2.10.3+ with verification keys configured on the deployment host.
- PHP extensions: `intl`, `mbstring`, `mysqli`/`mysqlnd`, `gd`, `curl`, `dom`, `xml`, and `zip`.
- Nginx or Apache document root set to `public/`.
- The web-service account must own or have group write access to required `writable/` subdirectories. Do not use world-writable permissions.
- `.env` must be created from `.env.example`; never deploy the example placeholders.

CI uses SQLite for the broad PHP 8.2–8.5 regression matrix. The dedicated MySQL 8 job is authoritative for production migration compatibility.

## 2. Required Environment Contract

Validate required values without printing secret values. The deployment must configure:

- `CI_ENVIRONMENT = production`.
- HTTPS `app.baseURL`, global secure requests, and trusted reverse-proxy IPs when TLS terminates upstream.
- MySQL connection values.
- Independent `encryption.key` and `encryption.blindIndexKey` values; retain prior keys only during an intentional rotation.
- Production logger threshold.
- API and login rate-limit windows and caps.
- Request/query monitoring and audit logging toggles.
- `families.enforceSingleFamilyMembership`; enable it only after legacy duplicate memberships have been remediated.
- Session concurrency and administrative MFA controls.
- SMTP sender and credentials.
- Backup connection, retention, and output directory.

`security.disableDeviceVerificationForTesting` must remain `0` in production.

Run explicit configuration checks:

```bash
php spark config:check App
php spark config:check Logger
php spark config:check Session
php spark config:check 'CodeIgniter\Shield\Config\Auth'
```

Do not run `config:check` for `Database`, `Encryption`, or `Email` in deployment logs because the command prints resolved secret values. Validate those settings with the non-printing database preflight scripts and secret-manager presence checks.

## 3. Go/No-Go Release Gate

A release is **no-go** if any item fails:

1. The release is an immutable reviewed tag or commit.
2. All PHP 8.2–8.5 SQLite jobs pass.
3. The MySQL 8 migration job passes from an empty database.
4. Composer validation, production install dry-run, audit, and PHPStan pass.
5. PHPUnit has no failures, warnings, or risky tests.
6. Review the JUnit/TestDox artifacts. Any unclassified skip blocks release.
7. Migration status, backup preflight, route compilation, liveness, readiness, and role smoke checks pass.
8. The rollback owner and maintenance window are confirmed.

### Approved skip classifications

The current SQLite suite has six classified skips:

- Four `ModuleCrudControllerPathsTest` cases target legacy plural-table fixtures (`parishes`) that are not part of the canonical singular-table SQLite schema. Production behavior is covered by canonical model/integration tests and the MySQL migration job.
- `ModuleDemoDataSeederTest` targets an optional development fixture that is not shipped.
- `ServicesLiturgicalDaysLocalizationTest` requires `liturgical_calendar_entry`, which is validated by the MySQL migration job rather than the reduced SQLite fixture.

Any additional skip is unclassified and blocks release. Schema/order failures and caught controller exceptions must not be converted into skips.

## 4. Pre-Deployment

```bash
php --version
composer --version
composer validate --strict
composer audit --locked --no-interaction
composer install --no-dev --optimize-autoloader --dry-run --no-interaction
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --testdox
php spark migrate:status --all
php spark routes > /tmp/routes_before_deploy.txt
sh build/backup_database.sh --check
sh build/restore_database.sh --check
```

Verify all `.env.example` placeholders have been replaced without logging their resulting values.

Export staging/production smoke inputs through the secret manager or deployment shell. Do not put credentials in command history or repository files:

```bash
export BASE_URL='https://application.example'
export PROVINCE_ADMIN_EMAIL='...'
export PROVINCE_ADMIN_PASSWORD='...'
export DIOCESAN_ADMIN_EMAIL='...'
export DIOCESAN_ADMIN_PASSWORD='...'
```

## 5. Deployment

1. Enable maintenance mode at the load balancer or web server edge.
2. Confirm unauthenticated liveness remains available:

   ```bash
   curl --fail --silent --show-error "$BASE_URL/health"
   ```

3. Create and validate a fresh backup:

   ```bash
   BACKUP_OUTPUT=$(sh build/backup_database.sh)
   printf '%s\n' "$BACKUP_OUTPUT"
   # Locate the reported file, then verify it is non-empty and valid gzip.
   test -s /path/to/reported-backup.sql.gz
   gzip -t /path/to/reported-backup.sql.gz
   ```

4. Check out the approved immutable release.
5. Install production dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader --no-interaction
   ```

6. Record migration state, apply migrations exactly once, and record it again:

   ```bash
   php spark migrate:status --all > /tmp/migrations_before.txt
   php spark migrate --all
   php spark migrate:status --all > /tmp/migrations_after.txt
   ```

7. Clear caches and compile routes:

   ```bash
   php spark cache:clear
   php spark routes > /tmp/routes_after_deploy.txt
   ```

8. Run admin-level smoke assertions:

   ```bash
   sh build/e2e_admin_levels_smoke.sh
   ```

9. Sign in as a system administrator and request `GET /health/ready`. It must return HTTP 200 with `database` and `writable` marked `ready`.
10. Disable edge maintenance mode.

## 6. Health Endpoints

### `GET /health`

- Public liveness probe for load balancers.
- Returns HTTP 200 and minimal JSON.
- Does not query the database or disclose versions, paths, hostnames, configuration, or exceptions.
- Remains available during maintenance mode.

### `GET /health/ready`

- Requires a Shield session and `system.admin` permission.
- Checks database connectivity and required writable runtime directories.
- Returns HTTP 200 when ready or HTTP 503 with stable error code `HEALTH_READINESS_FAILED`.
- Never returns raw exception or SQL text.

## 7. Post-Deployment Validation

- Public liveness returns 200 through the external load balancer.
- Protected readiness returns 200 for a system administrator.
- Province and diocesan administrator login and scope assertions pass.
- Critical create/update/delete AJAX flows return their documented contracts.
- Application and structured monitoring logs are written.
- `audit_logs` receives critical write events.
- SMTP delivery is verified with a non-sensitive staging message.
- Slow-request/query alerts and 5xx/429 alerts are active.
- The fresh backup exists, is non-empty, and passes `gzip -t`.

Retain the release SHA/tag, CI run URL, PHP/Composer/MySQL versions, migration reports, route report, sanitized health/smoke reports, backup filename/checksum, skip review, and operator sign-off.

## 8. Backups and Restore Drills

Manual backup and non-destructive checks:

```bash
sh build/backup_database.sh --check
sh build/backup_database.sh
sh build/restore_database.sh --check
```

Schedule backups only after the command succeeds under the service account. Example:

```cron
0 2 * * * cd /path/to/ekklesiaRD && sh build/backup_database.sh >> writable/logs/backup-cron.log 2>&1
```

At a scheduled interval, restore the latest backup into an isolated database, verify expected tables and `php spark migrate:status --all`, then destroy the isolated environment through the approved infrastructure process. Never use a production database as a restore-drill target.

A real restore is destructive and requires explicit approval for the selected target:

```bash
sh build/restore_database.sh /path/to/backup.sql.gz
```

## 9. Rollback

1. Enable edge maintenance mode.
2. Determine whether rollback is code-only or requires data/schema restoration.
3. For code-only rollback, check out the previous known-good immutable release, reinstall production dependencies, clear caches, and compile routes.
4. Do not run down-migrations or restore a database automatically. A restore can discard writes accepted after deployment and requires explicit operator approval.
5. If approved, restore the selected verified backup into the confirmed target.
6. Re-run liveness, protected readiness, migration status, login, and admin-level smoke checks.
7. Disable maintenance mode only after all checks pass.
8. Record the incident, selected backup, data-loss window assessment, and operator approvals.

## 10. Nightly Rate-Limit Check

`.github/workflows/nightly-security-rate-limit.yml` runs on schedule and manually against a protected non-production `staging` environment. Configure these secrets:

- `STAGING_BASE_URL`
- `STAGING_RATE_LIMIT_EMAIL`
- `STAGING_RATE_LIMIT_PASSWORD`

The workflow runs `build/stress_rate_limit_test.sh`, requires at least one HTTP 429 and a `Retry-After` header, and uploads only the sanitized report. It must never target production.

## 11. Edge Protection and Monitoring

- Reverse-proxy rate limits and body controls: `build/nginx/ekklesiard-rate-limit.conf`.
- CDN/WAF procedure: `docs/EDGE_RATE_LIMIT_AND_WAF_PLAYBOOK.md`.
- Application logs: `writable/logs/log-YYYY-MM-DD.log`.
- Structured monitoring logs: `writable/logs/monitoring-YYYY-MM-DD.log`.
- Recommended alerts: 5xx spikes, sustained latency above 800 ms, slow-query spikes, repeated 429s, and spikes in `permission.denied` or `scope.denied` audit events.
