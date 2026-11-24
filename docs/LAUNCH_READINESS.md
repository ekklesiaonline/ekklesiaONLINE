# ekklesiaONLINE — Launch Readiness

**Date:** 2026-11-21
**Status:** In progress — Phase A (A1 done; A2/A3 operator items open). **2026-11-23:** operator elected to fully decommission the existing staging deployment (GitHub `staging` environment + hosting) and re-provision a fresh staging from `build/env-templates/` before launch validation. A2/A3 staging items now apply to the *replacement* staging.
**Canonical gate:** `docs/DEPLOYMENT_RUNBOOK.md` §3 (go/no-go) and §12 (Sessions 1–8)
**Execution plan for:** `docs/GO_LIVE_TODO_AND_ORPHAN_TABLE_EXPANSION_2026-05-10.md` (its phases map onto the runbook sessions below)

## How to use this plan

- Steps execute in order **A → E**. Each step has substeps, a verification gate, and a ready-to-run prompt.
- A step is marked `[x]` **only after** its verification criteria pass and evidence is recorded in the [Evidence register](#5-evidence-register).
- After completing and verifying a step, **stop and ask the operator to confirm moving to the next step**.
- On any contradiction, doubt, or multiple valid options: **stop, present the options with an explanation, recommend one with rationale, and wait for a decision.** Never guess on security, data, credential, or production-affecting choices.
- Steps tagged **USER/OPS** require credentials, provider consoles, or GitHub admin access. The agent prepares everything possible (templates, commands, checklists) and hands off exact steps.
- **Never print secrets.** Verify `.env` changes by key presence/shape only. `.env` is untracked; only `.env.example` is committed.
- Prompts marked `AGENT` are self-contained instructions for a coding agent session in this repo. Prompts marked `OPS` are manual runbook procedures.

---

## 1. Findings summary (2026-11-21 audit)

### Verified green baseline

| Check | Result |
|---|---|
| PHPUnit (PHP 8.5.11, PHPUnit 13.4.7, locked toolchain) | 1,349 tests, 6,305 assertions, **0 failures**, 8 skipped |
| PHPStan (`vendor/bin/phpstan analyse`) | No errors |
| `composer audit --locked` | No advisories |
| `php spark routes` | Compiles; ~1,270 route definitions across 8 modules + `app/` |
| `php spark migrate:status --all` | All applied except 3 newest (B1) |
| Local app (`ekklesiaonline.local`) | `GET /health` → 200; `GET /` → 200 |
| `.env` hygiene | Gitignored; only `.env.example` tracked; `app.CSPEnabled` defaults `true` |
| Module security audits | All 7 remediated and merged (PRs #50–#65); boundary-filter coverage verified in `app/Config/Filters.php` |

### Open blockers (must clear before release)

1. **Exposed SMTP credential not yet rotated** — runbook Session 1, still open. Security-critical.
2. **No staging or production environment exists** — `.env` files, hosting, HTTPS, and the GitHub `staging` environment are all unprovisioned (Sessions 3–4).
3. **Backup/restore never drilled** — preflight currently fails locally on empty `database.default.password`; no isolated restore evidence (Session 7).
4. **Unclassified test skips** — 8 skips vs. the runbook's documented 6; the 2 `VolunteerScheduleSchemaIntegrityTest` cases require MySQLi and no CI job runs PHPUnit against MySQL.
5. **Pending migrations** — `AddEventRegistrationUniqueIndex`, `AddTeamMemberUniqueIndex`, `DropVolunteerScheduleLegacyDateColumns` committed but unapplied locally; `ConvertSqlsrvValueColumn` must run on prod after a verified backup.

### Test-hygiene gaps vs. the release gate

- Gate requires "no failures, **warnings**, or risky tests": PHPUnit 13 reports **19 PHP deprecations** (incl. 2 in app code: `str_getcsv()` missing `$escape` in `BankStatementParserService`, `finfo_close()` in `MemberPortal/Controllers/PublicAssets.php`), **9 PHPUnit deprecations** (`setAccessible()` calls in tests), **19 PHPUnit notices** (mocks without expectations), **8 skips**.
- `phpunit.xml.dist` still references the PHPUnit 10.5 schema and `failOnWarning` (not a PHPUnit 13 flag), so warnings do not currently gate.

### Operational gaps

- **Cron schedule undefined** — 11+ scheduled commands need an approved crontab (only the backup example exists in the runbook).
- **SMS provider is `log_only`** — Twilio keys empty; SMS follow-up channel will not deliver in production.
- **First-admin bootstrap undocumented** — README references an "approved bootstrap process" without naming it.
- **`PermissionRoleSyncSeeder` is not in the `DatabaseSeeder` chain** — its intended post-bootstrap timing needs documenting/verifying.
- **Encryption-at-rest gate** (`docs/ENCRYPTION_AT_REST_RELEASE_GATE.md`) is a blank template — all 10 items need evidence.
- **Edge monitoring/alerting** (5xx/429/latency, `permission.denied` spikes) not yet configured.
- Local `.env` is dev-oriented: `CI_ENVIRONMENT=development`, `disableDeviceVerificationForTesting=true`, `MFA_REQUIRED_FOR_ADMIN_OPS=false`, `families.enforceSingleFamilyMembership` unset.

### Post-launch backlog (by design — not blockers)

Dormant-table tranches per `docs/DORMANT_NOT_WIRED_DEFER_PLAN_2026-05-10.md` and `docs/GO_LIVE_TODO_AND_ORPHAN_TABLE_EXPANSION_2026-05-10.md`: sacramental-records UI, electoral roll, ministry/governance terms, facilities/reservations, advanced finance (AP/AR, bank rec, period close), campaign/notification platform. `.github/ekklesiaONLINE_overview.md` "Pending Modules" table is stale (May 2026).

---

## 2. Implementation plan

### Phase A — Credentials & environments

#### A1 — Rotate the exposed SMTP credential (Session 1) — USER/OPS

- [x] Revoke the exposed SMTP/app password at the email provider.
- [x] Generate a replacement credential.
- [x] Update untracked local `.env` (`email.SMTPUser`, `email.SMTPPass`, `email.SMTPHost`, `email.SMTPPort`, `email.SMTPCrypto`). *(note: local `.env` SMTP values still hash-identical to the Oct 17 backup as of 2026-11-21 — update if local mail testing is needed)*
- [x] Update staging and production secret managers where applicable.
- [x] Confirm the revoked credential no longer authenticates.
- [x] Send a non-sensitive test email with the replacement credential.
- [x] Verify `writable/logs/` contains no SMTP password. *(agent-verified 2026-11-21)*
- [x] Confirm no credential is committed (`git ls-files | grep -c '^\.env$'` → 0). *(agent-verified)*

**Verification:** test email delivered; revoked credential rejected; no secret in logs or git. — **Operator-attested 2026-11-21; agent-verified the repo-side checks (logs clean, `.env` untracked).**

> **OPS prompt:** Follow `docs/DEPLOYMENT_RUNBOOK.md` §12 Session 1 exactly. Do not run `php spark config:check Email` (prints resolved secrets). After completion, record evidence (date, provider ticket/ref, test-email result) in the Evidence register and ask to move to A2.

#### A2 — Reconcile environment configuration (Session 3) — JOINT

- [x] Agent: diff local `.env` keys vs `.env.example` (names only, never values) — done 2026-11-21; 35 example keys absent locally (all have code defaults; `encryption.blindIndexKey`, `backup.db.*`, monitoring/rate-limit keys must be set explicitly in deployed envs). Findings in `build/env-templates/README.md`.
- [x] Agent: draft staging/prod env templates — done 2026-11-21: `build/env-templates/staging.env.template`, `production.env.template`, `README.md`. Verified: exact key parity with `.env.example` (no missing/extra keys). Also added commented `email.googleClientId`/`googleClientSecret` to `.env.example` (keys existed in `app/Config/Email.php` but were undocumented).
- [x] Agent: local config sanity — `php spark config:check App|Logger|Session|'CodeIgniter\Shield\Config\Auth'` all pass (2026-11-21).
- [ ] Operator: `families.enforceSingleFamilyMembership` — **decision recorded 2026-11-22: enable `=1`** on deployed envs. Duplicate audit so far: local dev DB only, 0 rows (dev DB has 0 `family`/`family_member` rows — not representative). **Still required:** run audit on the **production** DB (`SELECT user_id, COUNT(DISTINCT family_id) AS family_count FROM family_member GROUP BY user_id HAVING family_count > 1;`) to find legacy duplicates for remediation — the flag only gates *new* writes and does not clean existing rows. (Staging-DB audit is moot — staging is being decommissioned 2026-11-23.) Set `families.enforceSingleFamilyMembership = 1` in each deployed `.env` after audit+remediation.
- [ ] Operator: decommission existing staging (decided 2026-11-23 — full deletion incl. hosting; staging will be re-provisioned fresh before launch validation). Then provision new staging `.env` from `build/env-templates/staging.env.template` — `CI_ENVIRONMENT=production`, `https://` `app.baseURL`, `app.forceGlobalSecureRequests=true`, trusted proxy IPs, MySQL creds, independent `encryption.key`/`encryption.blindIndexKey` (`hex2bin:` 32-byte), SMTP, `backup.db.*`, monitoring/rate-limit values.
- [ ] Operator: fill production `.env` from `build/env-templates/production.env.template` with production-specific secrets; confirm zero `CHANGE_ME` remain.
- [ ] Confirm `security.disableDeviceVerificationForTesting` absent or `0`/`false` in both deployed envs.
- [ ] Confirm writable directories owned by service account, group-writable, not world-writable on deployed hosts.

**Verification:** `php spark config:check App|Logger|Session|'CodeIgniter\Shield\Config\Auth'` pass on staging; no `CHANGE_ME` in deployed `.env`; `ProductionSecurityPosture` logs no critical warning on boot.

> **AGENT prompt:** Produce (a) a sanitized `.env.staging.template` / `.env.production.template` pair under `build/env-templates/` (or as a docs appendix) listing every required key with comments, and (b) the duplicate-membership audit query for the family-policy decision. Do not write real secrets. Hand templates to the operator; ask to move to A3 when deployed envs are confirmed.

#### A3 — Configure protected GitHub staging environment (Session 4) — USER/OPS

**2026-11-23 decommission decision:** the existing `staging` environment and its secrets will be deleted along with the staging hosting; a fresh `staging` environment + secrets will be created when the replacement staging is provisioned. Items below describe the *previous* environment's verified state — re-verify all of them on the new environment.

**Decommission complete 2026-11-23:** GitHub `staging` environment + secrets deleted (verified via public API) and old staging hosting cleaned up by operator. HostGator confirmed to offer **PHP 8.5** — `ekklessia.online` is a viable deployment target. Fresh `staging` environment + secrets will be created when the replacement staging is provisioned.

**Deployment target confirmed 2026-11-23:** HostGator shared hosting, domain `ekklessia.online` (NS `hgns1/2.hostgator.com`, IP `129.121.81.160`), **PHP 8.5 available** (operator-confirmed via hPanel). Currently serves a bare CodeIgniter 4.7.4 skeleton at `/public/` — placeholder pending deployment. HTTPS exists but does not enforce (redirects to HTTP) — must be fixed at deploy time (`app.forceGlobalSecureRequests`, HSTS, HostGator SSL/TLS settings).

Verified via GitHub public API 2026-11-21 (repo is public; secrets/workflow logs need auth):

- [x] ~~GitHub environment `staging` exists~~ — **deleted 2026-11-23** (verified via public API); will be recreated with replacement staging (branch policy `main` only, no required reviewers).
- [x] ~~No required reviewers on the environment~~ — moot; re-verify on recreation.
- [x] Secrets functionally proven: 15 scheduled nightly runs, 14 success — the auth probe requires a valid login, so `STAGING_BASE_URL` + `STAGING_RATE_LIMIT_EMAIL` + `STAGING_RATE_LIMIT_PASSWORD` are all configured and working. (Secret *names* not listable without auth.)
- [x] `STAGING_BASE_URL` reaches the app bypassing edge rate limits (probes observe application 429 + `Retry-After`, not edge 503).
- [x] Scheduled nightly runs decommissioned — `.github/workflows/nightly-security-rate-limit.yml` deleted; staging rate-limit verification retained via manual `build/stress_rate_limit_test.sh`.
- [x] Previous failure tracking issue (#79) noted; automated schedule and nightly issue creation removed.
- [ ] Confirm staging-secret modification is restricted (needs admin).
- [ ] Review artifact sanitization if reports are archived manually (script sanitizes by design).

**Verification:** all Session 4 completion boxes checked, with the date and sanitized output of a manual `build/stress_rate_limit_test.sh` run against staging as evidence.

> **OPS prompt:** Follow `docs/DEPLOYMENT_RUNBOOK.md` §10 + §12 Session 4. Run `sh build/stress_rate_limit_test.sh` manually against staging (`BASE_URL`, `LOGIN_EMAIL`, `LOGIN_PASSWORD`); no scheduled run or automated failure issue is required. Secret-restriction check requires GitHub repo admin. Record the run date and sanitized status-code output in the Evidence register; ask to move to B1.

### Phase B — Database & recovery

#### B1 — Apply pending migrations locally — AGENT — ✅ DONE 2026-11-24

- [x] Run `php spark migrate --all` — all 3 applied cleanly (`AddEventRegistrationUniqueIndex`, `AddTeamMemberUniqueIndex`, `DropVolunteerScheduleLegacyDateColumns`).
- [x] Run `php spark migrate:status --all` → zero `| --- |` pending rows.
- [x] Run `php spark routes` → exit 0, 729 lines compiled (`/tmp/routes_after_migrate.txt`).
- [x] Smoke: `curl -sf http://ekklesiaonline.local/health` → 200; `/` → 200.

> **AGENT prompt:** In `/Users/juanrosariodelacruz/Sites/ekklesiaONLINE`, run `php spark migrate --all`, then `php spark migrate:status --all` and confirm no pending rows remain (the three expected: `2026_11_17_000002 AddEventRegistrationUniqueIndex`, `2026_11_18_000001 AddTeamMemberUniqueIndex`, `2026_11_18_000002 DropVolunteerScheduleLegacyDateColumns`). Re-run `php spark routes` and `curl -sf http://ekklesiaonline.local/health`. If any migration errors, STOP and report — do not force. On success, mark B1 done and ask to move to B2.

#### B2 — Backup/restore validation (Session 7) — JOINT — ✅ DONE locally 2026-11-24

- [x] Configure `backup.db.*` — done: dedicated least-privilege `ekklesia_backup@localhost` MySQL user (SELECT/SHOW VIEW/TRIGGER/EVENT/LOCK TABLES on `ekklesiaRD`, ALL on scratch DB, PROCESS/RELOAD/FLUSH_TABLES/SET_ANY_DEFINER global — all required by mysqldump/restore); credentials in untracked local `.env`. Both `--check` preflights pass.
- [x] Backup created — `writable/backups/ekklesiaRD_20261123_204350.sql.gz` (710K, non-empty, `gzip -t` OK, SHA-256 `ccc8baac99d56750018a856153ffda67eeef7271ee992512bf56a9095b5dec67`, 187 tables + 2 views, `backup.retentionDays=30`). *(Local dev DB — staging is decommissioned; repeat on staging/prod at deploy.)*
- [x] Separate empty MySQL DB — `ekklesiaRD_restore_drill` created empty (0 tables), neither staging nor production.
- [x] Explicit operator approval obtained before restore (2026-11-24).
- [x] Restore verified — `Restore complete.`; 189 objects (187 tables + 2 views); `migrate:status --all` → 0 pending; row counts match source exactly (users=27, auth_identities=24, auth_groups_users=25, profile=34, parish_affiliation=19).
- [x] Isolated environment removed — scratch DB dropped; `.env` reverted (`backup.db.name = ekklesiaRD`); preflights re-verified.

**Issues found & fixed during drill:**
- `build/backup_database.sh` patched — conditional `--skip-masking-policies` (MySQL 8.4+/9 mysqldump fails on masking-policy catalog for least-privilege users) and `--set-gtid-purged=OFF` (prevents `SET @@GLOBAL.GTID_PURGED` in dumps, which needs global admin on restore). Both flags added only when `mysqldump --help` advertises them — no-ops on MySQL 8.0/MariaDB.
- Two dev-only views (`vw_current_clergy_appointment`, `vw_open_vendor_bills` — manual artifacts, not in repo migrations) referenced dead schema `ekklesiaonline`; recreated with portable unqualified table refs and `DEFINER=ekklesia_backup@localhost` (non-system definer so `SET_ANY_DEFINER` suffices — no `SYSTEM_USER` needed).
- **Production caveat:** if prod ever has views/triggers/routines with `DEFINER=root@…`, restores need `SET_ANY_DEFINER` + `SYSTEM_USER` or definer stripping. Canonical migrations create none — fresh prod deploy unaffected.

**Verification:** both `--check` preflights pass; backup integrity verified; isolated restore verified; production untouched.

> **JOINT prompt:** Agent: diagnose why `backup.db.pass`/`database.default.password` resolves empty locally (local `.env` has empty MySQL root password — decide whether to set `backup.db.*` explicitly), run both `--check` scripts, and produce the exact isolated-restore command sequence for operator approval. **STOP for explicit approval before any restore.** Operator: approve target, run restore, verify. Record evidence; ask to move to C1.

### Phase C — Test-gate hygiene

#### C1 — Resolve unclassified test skips + CI MySQL coverage — AGENT

- [x] Add a PHPUnit step to the `mysql` job in `.github/workflows/ci.yml` that runs `tests/database/VolunteerScheduleSchemaIntegrityTest.php` against the migrated MySQL 8 service via scoped `database.tests.*` env overrides (`DBDriver=MySQLi`, `DBPrefix` empty, `database=ekklesia_ci`, service creds). Do **not** put these overrides in `phpunit.xml.dist` (the SQLite job must keep its fixture). **Implementation note:** overrides were appended to the job's generated `.env` (not per-step `env:`) — equivalent effect, single source, and verified that CI4 parses empty values (`DBPrefix = ''` → `''`).
- [x] Verify locally that the env-override approach works (optional: temporary local MySQL `tests` group) or reason through CI env propagation; the CI run is the real verification. **Done:** local MySQL fixture `ekklesia_ci_local` + env overrides → `OK (2 tests, 7 assertions)`; CI4 `BaseConfig`/`DotEnv` source path traced for `database.tests.*` propagation incl. empty-value handling.
- [x] Update `docs/DEPLOYMENT_RUNBOOK.md` §3 "Approved skip classifications": reclassify the suite as **eight** skips — add the two `VolunteerScheduleSchemaIntegrityTest` cases as "requires MySQLi; executed by the CI MySQL job PHPUnit step". Update every "exactly six"/"six classified skips" reference (incl. §12 Session 5 completion text).
- [x] Re-run `vendor/bin/phpunit --no-coverage` → still 0 failures; skips remain 8 on SQLite (now all classified). **Result:** 1,349 tests, 6,305 assertions, 8 skipped, 0 failures; `phpstan analyse` clean.

**Verification:** CI mysql job shows the 2 tests executed (not skipped) and passing — *pending first post-merge CI run*; equivalent local MySQL verification passed 2026-11-24; runbook skip count = observed skip count (8 = 8).

> **AGENT prompt:** In `ekklesiaonline/ekklesiaONLINE`: (1) edit `.github/workflows/ci.yml` — in the `mysql` job after "Compile routes", add a step `Run MySQL-fixture PHPUnit tests` executing `vendor/bin/phpunit --no-coverage tests/database/VolunteerScheduleSchemaIntegrityTest.php` with env `database.tests.hostname=127.0.0.1`, `database.tests.database=ekklesia_ci`, `database.tests.username=ekklesia_ci`, `database.tests.password=ekklesia_ci`, `database.tests.DBDriver=MySQLi`, `database.tests.DBPrefix=` (empty), `database.tests.port=3306`. Confirm CI4 env override semantics for the `tests` group first (`app/Config/Database.php` `$tests` + `ENVIRONMENT=testing` default-group switch); if `DBPrefix` cannot be emptied via env, add a `phpunit.mysql.xml` config instead — choose the simpler working option and explain. (2) Update `docs/DEPLOYMENT_RUNBOOK.md` skip classifications and counts. (3) Run `vendor/bin/phpunit --no-coverage` locally (SQLite) to confirm no regressions, and `vendor/bin/phpstan analyse`. Do NOT push. Mark C1 done only after CI evidence or an equivalent local MySQL verification; ask to move to C2.

#### C2 — PHP 8.5 deprecation cleanup + phpunit config — AGENT

- [x] `app/Services/BankStatementParserService.php` (~lines 177, 199): pass `escape: ''` explicitly to `str_getcsv()` (PHP 8.5 deprecation; matches the future default).
- [x] `MemberPortal/Controllers/PublicAssets.php` (~line 526): remove the deprecated `finfo_close($finfo)` call (finfo objects free automatically).
- [x] Tests: remove no-op `->setAccessible(true)` calls on `ReflectionMethod`/`ReflectionProperty` (deprecated since PHP 8.5, no-op since 8.1) in: `tests/EmailSettingsSecurityTest.php`, `tests/ProvinceSecurityRegressionTest.php`, `tests/app/Services/ProductionSecurityPostureTest.php`, `tests/database/PermissionRoleSyncSeederTest.php`, `tests/unit/DioceseDashboardControllerTest.php`, `tests/unit/FinanceAuditParishContextContractTest.php`, `tests/unit/GmailApiEmailTest.php`, `tests/unit/ParishAuditLoggingTest.php`, `tests/unit/ParishDashboardDataServiceTest.php`, `tests/unit/ProvinceDashboardControllerTest.php`, `tests/unit/RegistrationNotificationServiceTest.php`, `tests/unit/SettingsScopeAndCastTest.php`, `tests/unit/VisitorConversionPrefillTest.php`.
- [x] `ProductionSecurityPostureTest`: replace static `ReflectionHelper::setPrivateProperty(...)` calls with instance calls (`$this->setPrivateProperty(...)`) per the deprecation notice.
- [x] PHPUnit notices (19): mocks created without expectations in `LoginRateLimitFilterTest`, `MonitoringBehaviorTest`, `ParishAuditLoggingTest`, `RegistrationNotificationServiceTest`, `ScopedEmailActivatorTest`, and others — apply `#[AllowMockObjectsWithoutExpectations]` where mocking without expectations is intentional (PHPUnit's documented opt-out), or convert to stubs where trivial. **Done:** `createMock`→`createStub` in 6 files (stubs allow `method()->willReturn*` without expects and emit no notice); `#[AllowMockObjectsWithoutExpectations]` on `MagicLinkSecurityTest` (class) and `RegistrationNotificationServiceTest::testNotifyAdminsPendingReviewPreservesFromHeaderPerRecipient` (partial `getMockBuilder` doubles can't be stubs).
- [x] PHPUnit deprecations (9): `with()` without `expects()` — `LocalePrecedenceTest` helper → stub + unconditional `willReturn` (SUT calls `getHeaderLine` 0–1× depending on profile path); `MonitoringBehaviorTest` → `expects($this->once())` (SUT calls `getDuration(6)` exactly once). `any()` invoked count — `ParishAuditLoggingTest` ×4 → `createStub`; the `with()` payload callbacks were dead config (`invokeAudit` only resolves the service, `log()` is never invoked) and were removed.
- [x] `phpunit.xml.dist`: update `xsi:noNamespaceSchemaLocation` to the PHPUnit 13 schema (`13.4`); replace `failOnWarning="true"` with the PHPUnit 13 granular flags (`failOnPhpunitDeprecation`, `failOnPhpunitNotice`) **only after** the suite is clean — enabling them on a dirty suite breaks CI. **Done:** schema → `13.4`; added `failOnDeprecation`, `failOnNotice`, `failOnPhpunitDeprecation`, `failOnPhpunitNotice`, `failOnPhpunitWarning` (kept `failOnRisky`, `failOnWarning`; verified full config against PHPUnit 13.4 XSD — all elements/attributes valid).
- [x] Re-run `vendor/bin/phpstan analyse` and `vendor/bin/phpunit --no-coverage --display-deprecations --display-phpunit-deprecations --display-phpunit-notices`; target: 0 failures, 0 deprecations, 0 PHPUnit deprecations/notices, 8 classified skips. **Result:** `Tests: 1349, Assertions: 6303, Skipped: 8` — zero issues of every category; PHPStan clean.

**Verification:** clean PHPUnit summary; PHPStan clean; parser and asset-delivery tests still pass (`BankStatementImportIntegrationTest`, `ProfilePicturePrivateDeliveryIntegrationTest`, `LayoutAndAssetExtractionTest`, `UploadCatchAllGateIntegrationTest` — 27 tests green).

> **AGENT prompt:** Execute the C2 checklist above in `ekklesiaonline/ekklesiaONLINE`. Rules: minimal diffs; no behavior change beyond the deprecation fixes; `escape: ''` must keep `BankStatementImportIntegrationTest` green; do not suppress notices globally — fix per-file. Run `php -l` on touched files, PHPStan, and the full suite. Commit nothing; report the before/after issue counts. Mark C2 done on a clean run; ask to move to D1.

### Phase D — Operations documentation

#### D1 — Cron schedule matrix — AGENT drafts, USER installs

- [ ] Agent: produce the approved crontab block covering: `followup:send`, `sms:process`, `volunteers:remind`, `service-metrics:refresh`, `standing:refresh`, `attendance:sync-diocesan-aggregates`, `attendance:sync-sunday-service-metrics`, `events:publish-due`, `finance:sync-journal-history`, `pledge:sync-fulfillment`, `clergy:reconcile-grants`, nightly backup — with frequency, command, log path, and owner per row.
- [ ] Reconcile with existing documented schedules: `service-metrics:refresh` daily 01:30 (`docs/SERVICE_METRICS_REFRESH_SCHEDULING_2026-04-10.md`), `standing:refresh` Sunday 23:00 (`docs/STANDING_SUMMARY_SCHEDULING_TODO_2026-04-10.md`), `clergy:reconcile-grants` hourly-or-daily (`docs/ORDINARY_AUTHORIZATION.md`), backup daily 02:00 (runbook §8).
- [ ] Operator: approve matrix; install on production host; verify first runs in logs.

> **AGENT prompt:** Draft `docs/CRON_SCHEDULE.md` (or a runbook §12 appendix) with a table: job, command (use `build/*_cron.sh` wrappers where they exist — note `build/service_metrics_refresh_cron.sh` has a hardcoded local path that must be templated for production), suggested frequency with rationale, log file, and owner. Cross-check each command's `$name`/`$usage` in `app/Commands/`. Present the draft for operator approval before marking D1 done; ask to move to D2.

#### D2 — First-admin bootstrap + PermissionRoleSyncSeeder documentation — AGENT

- [ ] Verify actual CLI syntax (`php spark shield:user --help`, related commands) before writing.
- [ ] Document: create first system admin (`shield:user create` + activate + `systemadmin` group), `AdminCreationService` alternative for admin-created accounts, `ClergyReviewService::installOrdinary()` for the first bishop, and when `php spark db:seed PermissionRoleSyncSeeder` runs (post-bootstrap user/group provisioning; it is intentionally not in `DatabaseSeeder`).
- [ ] Add the section to `docs/DEPLOYMENT_RUNBOOK.md` (post-deploy bootstrap) or README "Bootstrap Accounts".

> **AGENT prompt:** Verify command syntax against `php spark list`/`shield:user` help and `app/Services/AdminCreationService.php`, `ClergyReviewService::installOrdinary()`, then write the bootstrap section. Mark D2 done on review; ask to move to D3.

#### D3 — Encryption-at-rest release gate evidence — JOINT

- [ ] Agent: draft evidence narratives for gate items answerable from code (1 data inventory, 2 crypto design, 6 blind-index query strategy, 7 decrypt access boundaries, 8 perf/error paths).
- [ ] Operator: supply items 3 (KMS/secret manager), 4 (key versioning), 5 (rotation drill), 9 (backup/restore with encryption), 10 (monitoring) evidence.
- [ ] Complete Approval Matrix; any `Fail` on items 1–5 or 9 = release blocker; >2 Conditional Pass = blocker.

> **JOINT prompt:** Agent fills the draftable rows of `docs/ENCRYPTION_AT_REST_RELEASE_GATE.md` with code-verified evidence links; operator completes infra/secret-manager rows. Ask to move to E1 when the gate shows Pass/Conditional Pass within limits.

### Phase E — Release gate & launch

#### E1 — Record Session 2 review approval — USER

- [ ] Reviewer sign-off: health endpoints, CI design, ops scripts, skip classifications, `.env.example` contain no unintended business-flow changes or secrets.

#### E2 — Run full CI release gate (Session 5) — JOINT

- [ ] Green CI on `main`: `composer validate --strict`, `composer audit --locked`, production `--no-dev` dry-run, PHPStan, PHPUnit (only classified skips), MySQL 8 migration job artifacts reviewed.
- [ ] Composer ≥ 2.10.3 confirmed (local: 2.10.3 ✓).
- [ ] TestDox/JUnit artifacts reviewed; no unclassified skips.

#### E3 — Final review + launch-day execution (Session 8 + go-live Phase 7) — JOINT

- [ ] `git status`, review every modified file, `git diff --check`, secret scan of tracked changes; `.env`/runtime/backup artifacts untracked.
- [ ] Re-run composer validate/audit, PHPStan, PHPUnit, `php spark routes`, shell syntax checks (`fail=0; for f in build/*.sh; do sh -n "$f" || { echo "FAIL: $f"; fail=1; }; done; [ "$fail" -eq 0 ]`), workflow YAML validation.
- [ ] In approved release window: verified backup → `php spark migrate --all` (incl. `ConvertSqlsrvValueColumn` on prod) → `php spark cache:clear` → route compile → `sh build/e2e_admin_levels_smoke.sh` → `GET /health/ready` as systemadmin → disable maintenance mode → watch logs/5xx for 2h.
- [ ] Record rollback owner + maintenance window; commit per git rules (push to `main` immediately per `AGENTS.md`); execute launch-day runbook.

> **OPS prompt:** Follow `docs/DEPLOYMENT_RUNBOOK.md` §4–§9 verbatim. No-go on any failed item. Evidence to retain per runbook §5/§9.

---

## 3. Execution rules (summary)

1. Order is A → E; do not skip ahead without operator approval.
2. Verify before checking: a box is `[x]` only with evidence in §5.
3. After each verified step, ask: "Step X complete and verified — move to step Y?"
4. On contradictions/options: stop, explain, recommend, wait.
5. USER/OPS steps: agent prepares, human executes, agent verifies evidence.

## 4. Decision points already identified

| # | Step | Question | Options | Recommendation |
|---|---|---|---|---|
| 1 | A2 | Enable `families.enforceSingleFamilyMembership`? | Enable after duplicate audit / keep `0` | **Decided 2026-11-22: enable `=1`**, but per `docs/DEPLOYMENT_RUNBOOK.md` §1/Session 3, only **after** the duplicate audit and remediation on each target DB (staging, prod). The local dev audit is not representative |
| 2 | B2 | Isolated restore target | New empty MySQL DB on staging host / local scratch instance | Staging-host scratch DB — matches runbook intent, no prod adjacency |
| 3 | C1 | How to run MySQL-fixture tests in CI | Scoped `database.tests.*` env override in mysql job / separate `phpunit.mysql.xml` / classify-only | Try env override first (simplest); fall back to dedicated config if `DBPrefix` override fails |
| 4 | C2 | Mock-without-expectation notices | `#[AllowMockObjectsWithoutExpectations]` per file / convert to stubs | Attribute where intent is a pure mock; stub only where trivial — least invasive |
| 5 | E3 | Release mechanism | Tag/commit on `main` per runbook (no release branches per SECURITY.md) | Immutable reviewed commit SHA on `main` |

## 5. Evidence register

| Step | Evidence | Date | Verified by |
|---|---|---|---|
| A1 | Operator attestation (provider revocation, new credential, test email); agent-verified: logs clean of SMTP password, `.env` untracked | 2026-11-21 | Operator + agent (repo-side checks) |
| A2 | Env-template key parity verified; `config:check` App/Logger/Session/Shield-Auth pass; family-flag decision = enable `=1`; duplicate audit 0 rows on **local dev DB only** — staging/prod audit still pending | 2026-11-21/22 | Agent (repo checks) + operator (audit, decision) |
| A3 | `staging` env exists, main-only branch policy, no required reviewers; 3 secrets functionally proven by 14/15 nightly passes; failure-issue tracking verified (#79); **run 45018067784 failed 2026-11-22 — under investigation** | 2026-11-21 | Agent via GitHub public API |
| B1 | `php spark migrate --all` applied 3/3 cleanly; `migrate:status` 0 pending; `spark routes` exit 0 (729 lines); `/health` + `/` → 200 | 2026-11-24 | Agent |
| B2 | Local drill complete: `ekklesia_backup` user + `backup.db.*` configured; preflights pass; backup `ekklesiaRD_20261123_204350.sql.gz` (SHA-256 `ccc8baac…`); isolated restore into `ekklesiaRD_restore_drill` verified (189 objects, 0 pending migrations, row counts match); scratch dropped, `.env` reverted; `backup_database.sh` patched for MySQL 9 (masking-policies, gtid-purged) | 2026-11-24 | Agent + operator approval |
| C1 | `ci.yml` mysql job: `database.tests.*`→MySQLi overrides in generated `.env` + PHPUnit step after route compile; test fixture fixed (`team_member.person_id` FK); runbook reclassified to 8 skips; local SQLite suite 1,349 tests/6,305 assertions/8 skips/0 failures; PHPStan clean; local MySQLi run of target file OK (2 tests, 7 assertions). CI run pending first push. | 2026-11-24 | Agent |
| C2 | PHP 8.5 deprecations eliminated: `str_getcsv(escape: '')` ×2, `finfo_close` removed, 13 `setAccessible` calls removed, `ReflectionHelper` static→instance ×2; PHPUnit 13 cleanup: 6 files `createMock`→`createStub`, 2 `#[AllowMockObjectsWithoutExpectations]`, `with()`-without-`expects()` fixed (stub/`once()`), dead `any()`+`with()` config removed; `phpunit.xml.dist` schema→13.4 + granular fail flags. Suite: 1,349 tests, 6,303 assertions, **0 failures/0 deprecations/0 PHPUnit issues**, 8 classified skips; PHPStan clean | 2026-11-24 | Agent |
| D1 | | | |
| D2 | | | |
| D3 | | | |
| E1 | | | |
| E2 | | | |
| E3 | | | |
