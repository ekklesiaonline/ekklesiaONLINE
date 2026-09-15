# Go-Live TODO + Orphan Table Expansion Plan

Date: 2026-05-10
Owner: Platform + App Engineering
Status: Ready for execution

## Goal

1. Ship the currently active system safely to production.
2. Use orphan (dormant-not-wired) tables to expand functionality in controlled post-launch phases.

## Definitions

- Active tables/features: already wired into routes/controllers/models and validated by smoke tests.
- Legacy-renamed tables: old `parish_*` objects that were normalized to `parish_*`.
- Orphan tables: tables present in schema but not fully wired into user-facing modules.

---

## Phase 0 - Release Scope Lock (Do first)

- [ ] Freeze launch scope to Active + Legacy-Renamed only.
- [ ] Mark orphan-table features as "Post-Launch" in release notes and stakeholder comms.
- [ ] Confirm no new routes/views for orphan domains are merged before launch.
- [ ] Keep unit guard active for dormant route exposure.

Deliverable:

- Signed scope statement in release ticket.

---

## Phase 1 - Environment and Infrastructure Readiness

- [ ] Provision production host with PHP 8.2+, MySQL 8.0+, Composer 2+.
- [ ] Point web root to `public/`.
- [ ] Configure HTTPS at edge/web server.
- [ ] Configure process manager and restart policy.
- [ ] Configure writable directory permissions.
- [ ] Configure log rotation and retention for `writable/logs/`.

Deliverable:

- Infrastructure checklist completed and approved.

---

## Phase 2 - Secrets and Configuration Hardening

- [ ] Create production `.env` from `.env.example`.
- [ ] Set `CI_ENVIRONMENT=production`.
- [ ] Set production `app.baseURL`.
- [ ] Set strong `encryption.key` and blind-index key values.
- [ ] Configure SMTP credentials.
- [ ] Configure SMS provider (`log_only` or Twilio production keys).
- [ ] Set logger threshold appropriate for production.
- [ ] Verify rate-limit settings:
    - `security.apiRateLimitPerWindow`
    - `security.apiRateLimitWindowSeconds`
    - `security.apiRateLimitAnonPerWindow`
    - login rate-limit keys

Deliverable:

- Redacted config evidence attached to release.

---

## Phase 3 - Database Readiness and Rename Enforcement

- [ ] Take pre-deploy backup (`sh build/backup_database.sh`).
- [ ] Run migrations (`php spark migrate --all`).
- [ ] Verify migration status (`php spark migrate:status --all`).
- [ ] Confirm critical rename chain is migrated:
    - `2026_05_09_000007 StandardizeParishIdAcrossAllTables`
    - `2026_05_09_000008 NormalizeParishScopeEnumsToParish`
    - `2026_05_10_000001 StrictRenameLegacyParishSchemaObjects`
- [ ] Verify target canonical tables exist in production DB:
    - `parish`, `parish_setting`, `parish_integration`, `parish_provider_credential`, `parish_role_audit_log`, `parish_budgets`, `parish_people`, `parish_report_storage`
- [ ] Verify legacy names are absent (except archived backup schemas if intentionally retained).

Deliverable:

- DB migration and verification evidence stored with release artifacts.

---

## Phase 4 - Data Seeding and Operational Jobs

- [ ] Run required setup/seed commands (including Shield setup where required by environment policy).
- [ ] Configure cron jobs:
    - followup dispatch
    - SMS queue processing
    - service metrics refresh
    - diocesan aggregates sync
    - standing refresh
    - backup job
- [ ] Dry-run each scheduled command in production-like staging.

Deliverable:

- Cron schedule and command owner matrix approved.

---

## Phase 5 - Functional Validation (Active Scope)

- [ ] Run module smoke test.
- [ ] Run admin-level smoke test.
- [ ] Run selected integration tests for critical workflows:
    - authentication/session
    - members/families
    - guests/followup
    - donations/expenses/reports
- [ ] Run performance baseline script and save outputs.

Deliverable:

- Test report bundle with pass/fail and known risks.

---

## Phase 6 - Security and Access Validation

- [ ] Validate role/permission boundaries with real role accounts.
- [ ] Validate CSRF behavior for native fetch endpoints.
- [ ] Validate API and login throttling behavior.
- [ ] Validate secure headers and HTTPS redirect behavior.
- [ ] Validate audit logging for critical write actions.

Deliverable:

- Security verification sign-off.

---

## Phase 7 - Launch Day Runbook

- [ ] Enable maintenance mode at edge.
- [ ] Take final backup.
- [ ] Deploy release tag.
- [ ] Install dependencies (`composer install --no-dev --optimize-autoloader`).
- [ ] Run migrations.
- [ ] Clear cache.
- [ ] Run smoke checks.
- [ ] Disable maintenance mode.
- [ ] Watch logs and error rates for first 2 hours.

Deliverable:

- Launch timeline with timestamps and owner initials.

---

## Phase 8 - Post-Launch Stabilization (First 14 Days)

- [ ] Daily review of 5xx rates and slow query logs.
- [ ] Daily review of permission/scope denied spikes.
- [ ] Validate followup queue and SMS delivery health.
- [ ] Validate backup/restore drill (at least once).
- [ ] Triage and patch hot issues within agreed SLA.

Deliverable:

- Stabilization report and go/no-go for expansion phase.

---

## Orphan Table Expansion Plan (Use orphan tables to expand)

### Expansion Rule

For each orphan domain, do not enable UI/routes until all are complete:

- [ ] Model + validation rules
- [ ] Service layer
- [ ] Controller + permissions + tenancy scope
- [ ] Routes
- [ ] Views/API contracts
- [ ] Migration verification and indexes
- [ ] Integration tests + smoke coverage
- [ ] Monitoring and rollback path

### Tranche 1 (Highest business value, low-medium complexity)

Target outcome: richer people records and intake workflows.

Tables:

- `person`, `user_person`, `person_address`, `person_contact`, `person_setting`
- `portal_access_request`, `pre_registration`

Steps:

- [ ] Build person profile module (read/write + search).
- [ ] Add member-to-person bridge synchronization.
- [ ] Add intake pipeline from pre-registration to member profile.
- [ ] Add admin queue for portal access requests.
- [ ] Add unit/integration tests and route smoke checks.

### Tranche 2 (Ministry/sacramental/governance functions)

Target outcome: formation/governance records for clergy and parish leadership.

Tables:

- `sacramental_record`, `sacramental_participant`, `electoral_roll_entry`
- `membership_stage_history`, `ministry`, `ministry_role`, `governance_term`, `parish_user_role`, `parish_role_catalog`

Steps:

- [ ] Implement sacramental records UI and reporting.
- [ ] Implement governance term tracking and ministry role assignments.
- [ ] Enforce permissions by role and parish scope.
- [ ] Add exports and audit trail coverage.

### Tranche 3 (Facility and scheduling expansion)

Target outcome: facility booking and resource scheduling.

Tables:

- `facility`, `facility_resource`, `facility_reservation`, `facility_reservation_slot`

Steps:

- [ ] Build facility/resource CRUD.
- [ ] Build reservation calendar workflow.
- [ ] Add conflict detection and approval states.
- [ ] Add reminders/notifications integration.

### Tranche 4 (Advanced finance/ledger)

Target outcome: accounting-grade close and reconciliation workflows.

Tables:

- `accounting_period`, `bank_account`, `bank_transaction`, `bank_reconciliation`, `bank_reconciliation_item`, `bank_transaction_journal_link`
- `journal_entry`, `journal_entry_line`, `journal_entry_balance`
- `vendor`, `vendor_bill`, `vendor_bill_line`, `vendor_payment`, `vendor_payment_allocation`, `period_close_batch`

Steps:

- [ ] Implement period management and close flow.
- [ ] Implement vendor AP lifecycle.
- [ ] Implement bank reconciliation workflow.
- [ ] Implement journal posting and balance checks.
- [ ] Add finance controls and approvals.

### Tranche 5 (Campaign/notification/webhook platform)

Target outcome: communication automation and delivery telemetry.

Tables:

- `communication_template`, `communication_campaign`, `communication_recipient`, `communication_suppression`, `campaign_delivery_event`
- `notification`, `notification_recipient`, `webhook_delivery_attempt`

Steps:

- [ ] Build campaign composer and audience selection.
- [ ] Build suppression/compliance controls.
- [ ] Add delivery event tracking and dashboards.
- [ ] Add webhook retry policies and dead-letter process.

---

## Legacy Cleanup After Stability Window (N+2)

- [ ] Remove legacy fallback probes for old table names where safe.
- [ ] Remove obsolete migration helper paths no longer needed.
- [ ] Update docs to canonical naming only.
- [ ] Re-run full regression and route ownership tests.

Deliverable:

- Legacy cleanup completion report.

---

## Final Exit Criteria (System ready + expansion enabled)

- [ ] Active scope stable in production for agreed window.
- [ ] Legacy rename enforcement verified with evidence.
- [ ] Orphan expansion tranche 1 approved with owner, scope, and dates.
- [ ] Backlog for tranches 2-5 prioritized and scheduled.
