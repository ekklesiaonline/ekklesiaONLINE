# Deployment Runbook

## Purpose

This is the canonical go/no-go, deployment, validation, and rollback procedure for ekklesiaONLINE.

## 1. Supported Production Platform

- PHP 8.2–8.5.
- MySQL 8.0+ (MariaDB is supported; the optional `idx_profile_birth_month_day` functional index is skipped there because MariaDB has no functional key parts).
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
- `ServicesLiturgicalDaysLocalizationTest` requires `liturgical_calendar_entries` (created by `CreateCanonicalSchema` on MySQL/MariaDB only), which is validated by the MySQL migration job rather than the reduced SQLite fixture.

Any additional skip is unclassified and blocks release. Schema/order failures and caught controller exceptions must not be converted into skips.

## 4. Pre-Deployment

```bash
php --version
composer --version
composer validate --strict
composer audit --locked --no-interaction
composer install --no-dev --optimize-autoloader --dry-run --no-interaction
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --no-coverage --testdox
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
0 2 * * * cd /path/to/ekklesiaONLINE && sh build/backup_database.sh >> writable/logs/backup-cron.log 2>&1
```

At a scheduled interval, restore the latest backup into an isolated database, verify expected tables and `php spark migrate:status --all`, then destroy the isolated environment through the approved infrastructure process. Never use a production database as a restore-drill target.

`build/restore_database.sh` restores into `backup.db.name` (falling back to `database.default.database`) from `.env`; it has no separate target option. For a drill, take the backup first, then point `backup.db.*` at the empty isolated database before restoring, and point `database.default.*` at it only while running `migrate:status --all` against the restored copy. Restore back to the original `.env` afterwards.

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

- Reverse-proxy rate limits and body controls: `build/nginx/ekklesiaONLINE-rate-limit.conf`.
- CDN/WAF procedure: `docs/EDGE_RATE_LIMIT_AND_WAF_PLAYBOOK.md`.
- Application logs: `writable/logs/log-YYYY-MM-DD.log`.
- Structured monitoring logs: `writable/logs/monitoring-YYYY-MM-DD.log`.
- Recommended alerts: 5xx spikes, sustained latency above 800 ms, slow-query spikes, repeated 429s, and spikes in `permission.denied` or `scope.denied` audit events.

## 12. Deployment Readiness Completion Checklist

Complete these sessions in order. Mark an item complete only when its evidence has been reviewed. The current release status is **conditional no-go** until every session completion criterion is checked.

### Session 1 — Rotate the exposed SMTP credential

- [ ] Revoke the exposed SMTP/app password at the configured email provider.
- [ ] Generate a replacement SMTP credential.
- [ ] Update the untracked local `.env` values for `email.SMTPUser`, `email.SMTPPass`, `email.SMTPHost`, `email.SMTPPort`, and `email.SMTPCrypto`.
- [ ] Update the staging and production secret managers where applicable.
- [ ] Confirm the revoked credential no longer authenticates.
- [ ] Send a non-sensitive test email with the replacement credential.
- [ ] Verify application logs contain no SMTP password.
- [ ] Confirm no credential was committed.

Do not run `php spark config:check Email`; it prints resolved secrets.

**Completion:** [ ] Old password revoked; [ ] replacement stored only in untracked environment files or secret managers; [ ] test email succeeds; [ ] no credential committed.

### Session 2 — Review deployment-readiness changes

- [x] Review `app/Controllers/Health.php` and confirm public liveness is minimal and does not query the database.
- [x] Confirm protected readiness requires `system.admin`, returns HTTP 503 on component failure, and does not expose SQL, paths, versions, or exceptions.
- [x] Review health routes and filters: `/health` bypasses maintenance/authentication while secure headers and request monitoring remain; `/health/ready` retains session and permission filters.
- [x] Review the PHP 8.2–8.5 matrix, Composer pin, audit, production-install dry-run, MySQL migration job, and test artifacts.
- [x] Review backup, restore, role smoke, and rate-limit scripts; database passwords are not command arguments and smoke credentials come from the environment.
- [x] Review the six classified skips and confirm broad exceptions are not converted into skips.
- [x] Review `.env.example`, this runbook, and README credential removal.
- [x] Confirm no secrets or reusable passwords are present in tracked files.
- [ ] Record reviewer approval that no unintended business-flow changes were found and the health, operations, and CI designs are accepted.

**Completion:** [ ] Review approval is recorded.

### Session 3 — Reconcile environment configuration

- [ ] Compare local `.env` with `.env.example` without printing secret values.
- [ ] Replace `family.enforceSingleFamilyMembership` with `families.enforceSingleFamilyMembership` and record the local policy decision.
- [ ] Confirm local development environment, base URL, database, independent encryption keys, monitoring, and rate limits.
- [ ] Configure staging with `CI_ENVIRONMENT = production`, HTTPS URL, trusted proxies, MySQL, independent encryption keys, SMTP, monitoring, audit, and backup values.
- [ ] Confirm staging keeps `security.disableDeviceVerificationForTesting = 0`.
- [ ] Repeat the environment review with production-specific secrets and confirm no `CHANGE_ME` values remain.
- [ ] Confirm production `.env` is untracked and writable directories have service-account ownership/group access without world-writable permissions.
- [ ] Audit and remediate multiple family memberships where necessary.
- [ ] Decide and document whether `families.enforceSingleFamilyMembership = 1`.

**Known blockers:** local `.env` remains development-oriented; deployment environment evidence and the family-policy decision have not been recorded.

**Completion:** [ ] Local works; [ ] staging complete; [ ] production complete or securely staged; [ ] no deployment placeholders; [ ] family policy documented.

### Session 4 — Configure protected GitHub staging

- [ ] Create a protected GitHub environment named `staging`.
- [ ] Restrict staging-secret modification and add required reviewers where appropriate.
- [ ] Add `STAGING_BASE_URL`, `STAGING_RATE_LIMIT_EMAIL`, and `STAGING_RATE_LIMIT_PASSWORD`.
- [ ] Confirm the test account is non-production and has only required permissions.
- [ ] Manually run the nightly rate-limit workflow against staging.
- [ ] Confirm validation and login succeed, at least one HTTP 429 and a `Retry-After` header are observed, and concurrent runs are prevented.
- [ ] Confirm the artifact is sanitized and excludes cookies, credentials, and temporary response bodies.

**Completion:** [ ] Protected environment exists; [ ] all secrets configured; [ ] manual run succeeds; [ ] sanitized artifact reviewed; [ ] production not targeted.

### Session 5 — Run the complete CI release gate

- [x] Local `composer validate --strict` passes.
- [x] Local dependency install and production `--no-dev` dry-run pass.
- [x] Local `composer audit --locked --no-interaction` reports no advisories.
- [x] Local PHPStan reports no errors.
- [x] Local PHPUnit passes with 588 tests, 2,626 assertions, and exactly six classified skips.
- [ ] Upgrade/confirm Composer 2.10.3 or newer; the assessed local version was 2.10.1.
- [ ] Confirm CI PHP 8.2, 8.3, 8.4, and 8.5 jobs pass without failures, warnings, or risky tests.
- [ ] Download and review TestDox and JUnit artifacts; block on any skip beyond the four legacy plural-table, optional development-seeder, and liturgical-calendar fixture skips.
- [ ] Confirm the MySQL 8 service is healthy, migrations apply to an empty database, route compilation succeeds, and uploaded evidence is reviewed.

**Completion:** [ ] Quality job passes; [ ] all four PHP jobs pass; [ ] MySQL job passes; [ ] exactly six skips; [ ] all artifacts reviewed.

### Session 6 — Validate staging application flows

- [ ] Confirm unauthenticated `GET /health` returns minimal HTTP 200 JSON without versions, paths, or configuration values.
- [ ] Enable maintenance mode, confirm `/health` still returns HTTP 200, and disable maintenance mode afterward.
- [ ] Confirm `/health/ready` rejects anonymous, normal-member, and non-`system.admin` parish-admin requests.
- [ ] Confirm system-admin readiness returns HTTP 200 with `database` and `writable` marked `ready`.
- [ ] Export staging smoke credentials securely and run `sh build/e2e_admin_levels_smoke.sh`.
- [ ] Confirm province- and diocesan-admin assertions pass and investigate unexpected 200, 302, 403, 404, or 500 responses.
- [ ] Confirm application and structured monitoring logs are written and critical writes create redacted audit records.
- [ ] Confirm credentials do not appear in logs and 5xx, latency, and 429 monitoring is active.

**Completion:** [ ] Liveness passes; [ ] readiness authorization passes; [ ] components ready; [ ] role smoke passes; [ ] logging and monitoring operational.

### Session 7 — Validate backups and isolated restoration

- [ ] Run `sh build/backup_database.sh --check` and confirm connectivity and writable output.
- [ ] Run `sh build/restore_database.sh --check` and confirm prerequisites.
- [ ] Create a staging backup and record its reported filename.
- [ ] Confirm the backup is non-empty, passes `gzip -t`, has a recorded checksum, and uses the intended retention settings.
- [ ] Create a separate empty MySQL database and confirm it is neither staging nor production.
- [ ] Configure isolated restore credentials.
- [ ] Obtain explicit confirmation for the selected isolated target before running the restore.
- [ ] Restore and verify core tables, migration status, representative row counts, authentication, and encrypted-data expectations where practical.
- [ ] Record results and remove the isolated environment through the approved infrastructure process.

**Known blocker:** backup and restore preflights currently lack `backup.db.pass` or `database.default.password`.

**Completion:** [ ] Both preflights pass; [ ] backup integrity passes; [ ] isolated restore succeeds; [ ] schema/migrations verified; [ ] production untouched.

### Session 8 — Final review and commit

- [ ] Confirm Sessions 1–7 are complete.
- [ ] Run `git status`, review every modified/new file, run `git diff --check`, and search tracked changes for credentials.
- [ ] Confirm `.env`, runtime files, backup artifacts, vendor-generated cache files, and unrelated changes are not tracked.
- [ ] Resolve the dirty dompdf generated font-cache files by using a clean lockfile-based Composer install for release evidence.
- [ ] Re-run Composer validation/audit, PHPStan, PHPUnit without coverage, route compilation, shell syntax checks, and workflow YAML validation.
- [ ] Confirm CI and staging checks remain green.
- [ ] Create a verified backup, then apply the pending `CodeIgniter\\Settings\\ConvertSqlsrvValueColumn` migration with `php spark migrate --all` in the approved release window.
- [ ] Confirm rollback owner and maintenance window.
- [ ] Review the proposed subject: `Harden deployment readiness and release validation`.
- [ ] Stage only intended files, create the commit, verify it contains no secrets, and record its SHA.
- [ ] Do not push or deploy without explicit approval.

**Completion:** [ ] Preceding sessions complete; [ ] repository contains only intended changes; [ ] verification passes; [ ] commit created; [ ] no unapproved push/deployment.

### Current decision

- [x] Application-level local automated checks passed during the readiness assessment.
- [x] No deployment, migration, destructive restore, push, or production change was performed during the assessment.
- [ ] Production release approved.

The release remains **no-go** until the SMTP credential is rotated, deployment environments are reconciled, Composer 2.10.3+ and clean vendor installation are confirmed, CI/MySQL/staging evidence is reviewed, backup and isolated restore validation succeed, the pending vendor migration is applied after a verified backup, and the rollback owner and maintenance window are recorded.
