# Deployment Runbook

## Purpose

This runbook provides a repeatable deployment and rollback process for ekklesiaRD.

## Prerequisites

- PHP 8.2+
- MySQL 8.0+
- Composer 2+
- Web server (Nginx/Apache) pointing to public/
- `.env` configured from `.env.example`

## 1. Pre-Deployment Checklist

- Pull latest code
- Verify dependencies:
  - `composer install --no-dev --optimize-autoloader`
- Validate configuration:
  - `php spark config:check`
- Run migrations dry check (if supported by target process)
- Run tests:
  - `./vendor/bin/phpunit --testdox`
  - `sh build/e2e_admin_levels_smoke.sh`
- Confirm backups are healthy:
  - `sh build/backup_database.sh`

## 2. Deployment Steps

1. Put app in maintenance mode at edge (load balancer or web server page)
2. Create fresh DB backup:
   - `sh build/backup_database.sh`
3. Pull release tag or commit
4. Install dependencies:
   - `composer install --no-dev --optimize-autoloader`
5. Run DB migrations:
   - `php spark migrate --all`
6. Clear caches:
   - `php spark cache:clear`
7. Verify routes compile:
   - `php spark routes > /tmp/routes_after_deploy.txt`
8. Run smoke tests:
   - `sh build/e2e_admin_levels_smoke.sh`
9. Remove maintenance mode

## 3. Rollback Steps

1. Put app in maintenance mode
2. Roll back app code to previous known-good tag
3. Restore DB backup if needed:
   - `sh build/restore_database.sh /path/to/backup.sql.gz`
4. Clear caches:
   - `php spark cache:clear`
5. Validate health endpoint and login
6. Remove maintenance mode

## 4. Monitoring and Logging Baseline

- Request monitoring filter logs slow requests and 5xx responses.
- Slow database query monitoring logs queries above the configured threshold.
- Application logs:
  - `writable/logs/log-YYYY-MM-DD.log`
- Structured request monitor logs:
  - `writable/logs/monitoring-YYYY-MM-DD.log`
- Audit trail (database table):
  - `audit_logs`
  - Events include `permission.denied`, `scope.denied`, `member.create`, `member.update`, `member.delete`, `guest.create`, `guest.update`, `guest.delete`, `annual_pledge.create`, `annual_pledge.update`, `annual_pledge.delete`, `donation.create`, `donation.delete`, `donation.export_csv`, `donation.export_pdf`, `donation.statement_pdf`, `expense.create`, `expense.update`, `expense.delete`, `expense.export_csv`, `expense.export_pdf`, `report.export_csv`, `report.export_pdf`, `attendance.mark`, `attendance.bulk_mark`, `team_member.add`, `team_member.remove`
- Recommended alerts:
  - 5xx rate spike
  - request latency > 800ms sustained
  - slow query rate spike above configured threshold
  - repeated 429 responses on critical endpoints
  - spike in `permission.denied` or `scope.denied`

## 4.1 Optional Nightly Security Check (CI)

- Workflow file:
  - `.github/workflows/nightly-security-rate-limit.yml`
- Trigger modes:
  - Scheduled nightly run (`cron`)
  - Manual run (`workflow_dispatch`)
- Purpose:
  - Verifies throttling behavior on a protected write endpoint.
  - Asserts at least one `429` and presence of `Retry-After` under burst load.
- Script executed by workflow:
  - `build/stress_rate_limit_test.sh`

## 5. Backup Operations

- Manual backup:
  - `sh build/backup_database.sh`
- Manual restore:
  - `sh build/restore_database.sh /path/to/backup.sql.gz`
- Suggested cron (daily 02:00):
  - `0 2 * * * cd /path/to/ekklesiaRD && sh build/backup_database.sh >> writable/logs/backup-cron.log 2>&1`

## 6. Environment Variables (Minimum)

Use `.env.example` as source of truth. Required sections:

- App URL and HTTPS
- Database connection
- Encryption key
- Logger threshold
- API rate limit window and cap
- API rate limit keys (`security.apiRateLimitPerWindow`, `security.apiRateLimitWindowSeconds`, `security.apiRateLimitAnonPerWindow`)
- Login rate limit keys (`security.loginRateLimitPerWindow`, `security.loginRateLimitWindowSeconds`)
- Monitoring slow request threshold
- Monitoring slow query threshold
- Structured monitoring toggles (`monitoring.enabled`, `monitoring.fileLogEnabled`)
- Audit logging toggles (`monitoring.auditLogEnabled`, `monitoring.auditDbEnabled`)
- Families membership policy toggle (`families.enforceSingleFamilyMembership`)
- Backup settings
- SMTP credentials

### `families.enforceSingleFamilyMembership`

- Purpose: when enabled, prevents adding a user to a second family.
- Default: `0` (disabled).
- Truthy values: `1`, `true`, `yes`, `on` (case-insensitive).
- Recommended production setting: `1` once legacy data has been cleaned and verified.

## 6.1 Edge Protection References

- Reverse-proxy rate limits and request-body controls:
  - `build/nginx/ekklesiard-rate-limit.conf`
- CDN/WAF and staging stress-test procedure:
  - `docs/EDGE_RATE_LIMIT_AND_WAF_PLAYBOOK.md`

## 7. Post-Deployment Validation

- Login works for:
  - province admin
  - diocesan admin
- Protected route scoping behaves correctly
- Create/update/delete flows return expected responses
- Logs are generated in both app and monitoring files
- Slow query log entries appear when threshold is intentionally exceeded in staging validation
- `audit_logs` records are being created for critical write actions
- Backup script runs successfully
