---
name: deployment-release-gate
description: Prepare, validate, review, or troubleshoot an ekklesiaONLINE deployment, release, rollback, backup, restore drill, health check, or staging smoke run. Use before declaring a release ready or changing production operations.
---

# Deployment and Release Gate

Treat `docs/DEPLOYMENT_RUNBOOK.md` as canonical. This skill is for evidence gathering and safe validation, not automatic production deployment.

## Preflight

Run from the repository root and keep secrets out of output:

```bash
php --version
composer --version
composer validate --strict
composer audit --locked --no-interaction
composer install --no-dev --optimize-autoloader --dry-run --no-interaction
vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit --no-coverage --testdox
php spark migrate:status --all
php spark directory:verify-readiness
php spark routes > writable/routes_before_deploy.txt
sh build/backup_database.sh --check
sh build/restore_database.sh --check
```

Also verify PHP 8.5 support, MySQL 8 compatibility, required extensions, `public/` as the web root, writable-directory ownership, HTTPS/trusted proxy settings, independent encryption keys, rate limits, monitoring/audit settings, SMTP configuration, backup settings, and device verification enabled in production.

### Production `.env` verification

Verify the production `.env` without printing secret values:

- [ ] `CI_ENVIRONMENT = production` — this engages `Cookie.secure`, `app.forceGlobalSecureRequests`, and the Security CSRF `redirect` defaults; under any other value they silently stay off.
- [ ] `app.baseURL` starts with `https://`.
- [ ] `security.disableDeviceVerificationForTesting` is absent or `0`/`false`.
- [ ] `app.forceGlobalSecureRequests` resolves true under the production environment.
- [ ] `database.default.*` credentials are environment-specific — no `.env.example` `CHANGE_ME` placeholders or shared dev defaults.
- [ ] `encryption.key` is set, high-entropy, and unique per environment; `encryption.blindIndexKey` is independent.
- [ ] `Config\AuthToken::$hmacEncryptionKeys` is non-empty **if** the `hmac`/`chain` authenticator is enabled (currently disabled, so the keys are inert).

Runtime tripwire: on every production boot, `App\Services\ProductionSecurityPosture` (registered on the `pre_system` event in `app/Config/Events.php`) logs a `critical` warning when `security.disableDeviceVerificationForTesting` is truthy or `app.baseURL` uses `http://`. Check `writable/logs/log-YYYY-MM-DD.log` after deploy — any such warning is a no-go signal.

Never run `php spark config:check Database`, `Encryption`, or `Email` in logs because resolved secrets may be printed.

## No-go conditions

Stop and report a no-go for any failed test, warning/risky PHPUnit result, unclassified skip, migration failure, empty `province`/`diocese`/`parish` hierarchy (`php spark directory:verify-readiness` non-zero or `/health/ready` reporting `directory: unavailable`), route compilation failure, backup preflight failure, missing rollback owner/window, placeholder environment value, or unexpected secret exposure. The eight documented SQLite skips are allowed only when they still match the runbook classifications. Leftover placeholder `Sample Diocese One/Two` rows are a warning that fails `directory:verify-readiness --strict`; remove them before go-live.

## Deployment sequence

1. Use an immutable reviewed tag/commit and record its SHA.
2. Enable edge maintenance mode.
3. Confirm public `GET /health` remains HTTP 200 and minimal.
4. Create a fresh backup; verify it is non-empty and passes `gzip -t`.
5. Install production dependencies with `--no-dev --optimize-autoloader`.
6. Record migration status, run `php spark migrate --all` once, and record status again.
7. Confirm `province`, `diocese`, and at least one active linked `parish` are populated (`php spark directory:verify-readiness --strict` — `DatabaseSeeder` does not seed `parish`; strict mode also fails on orphaned rows and leftover placeholder `Sample Diocese One/Two` rows, both no-go in production; follow `docs/HOSTGATOR_DEPLOY.md` §4.1 when moving or bootstrapping on webhosting). For the initial cutover from an already-populated source database, use `bash build/parish_hierarchy_transfer.sh export|import|verify` (Path A, runs only on a fresh empty destination before any users exist); for updates to a live database use `php spark directory:sync-ueid --apply --preserve-inactive`. Then clear cache and compile routes.
8. Run `sh build/e2e_admin_levels_smoke.sh` with staging credentials from environment variables only.
9. Verify protected `/health/ready` as a system administrator and confirm `database`, `writable`, and `directory` readiness.
10. Validate logs, audit records, SMTP staging delivery, 5xx/429/latency alerts, then disable maintenance mode.

## Rollback and restore safety

Code-only rollback may check out the previous immutable release, reinstall dependencies, clear cache, compile routes, and rerun health/smoke checks. Do not run down-migrations or restore a database automatically. A real restore is destructive and requires explicit operator approval for the confirmed isolated target; never use production as a restore-drill target.

## Evidence to retain

Record release SHA/tag, CI URL, tool/runtime versions, migration reports, route report, sanitized health/smoke output, backup filename/checksum, skip review, monitoring evidence, rollback owner, maintenance window, and sign-off. Do not push, deploy, rotate credentials, migrate, or restore unless that action is explicitly authorized.

## References

- `docs/DEPLOYMENT_RUNBOOK.md`
- `docs/HOSTGATOR_DEPLOY.md`
- `build/e2e_admin_levels_smoke.sh`
- `build/e2e_module_smoke.sh`
- `build/backup_database.sh`
- `build/restore_database.sh`
- `build/parish_hierarchy_transfer.sh`
- `AGENTS.md`
