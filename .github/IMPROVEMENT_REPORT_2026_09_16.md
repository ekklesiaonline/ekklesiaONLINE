# ekklesiaRD – Status Comparison & Improvement Report

Date: 2026-09-16
Baseline: [ACTUAL_STATUS_REPORT_2026_07_28.md](./ACTUAL_STATUS_REPORT_2026_07_28.md)

## Executive Summary

Since the 2026-07-28 report, the codebase went through a full severity-ordered hardening
program (Critical → High → Medium → Low) plus a test-infrastructure repair pass. The
suite is now green: **508 tests, 2,332 assertions, 0 errors, 0 failures, 8 skipped** —
up from 310 tests with 11 failures at the report date.

The system has moved from "feature-rich but not production-stable" to "feature-rich,
hardened, and verified on SQLite." The main remaining gap is no longer test failures —
it is unfinished product backlog (finance extensions, small groups, bulk messaging) and
second-order hardening (MySQL verification, CI enforcement, PHPStan depth, a11y coverage).

## Then vs Now

| Area | 2026-07-28 report | 2026-09-16 actual |
| --- | --- | --- |
| PHPUnit | 310 tests, 11 failures | 508 tests, 0 errors, 0 failures |
| View-layer XSS | Unaddressed | Flash/toast output escaped in all 7 module layouts; unsafe `old()` call sites guarded by a static test |
| CSRF | Mutating GET delete routes exposed | Converted to POST with `csrf_field()`; device-verify resend protected |
| Uploads | Partial validation | Extension whitelist, non-executable storage, private delivery checks |
| Cross-parish isolation | Vestry/event lookups unscoped | Parish-scoped lookups; cross-event attachment injection blocked; 403 on out-of-scope access |
| Permission matrix | Suspected phantom names (`parishes.*` vs `parish.*`) | Audited; regression tests pin canonical names |
| Transactions | Multi-table writes unguarded | Boundaries added/confirmed across provisioning, merge, cleanup, admin flows |
| Identity lookup | `find($personId)` anti-pattern on `profile` | Fixed to `person_id` lookups; regression tests for divergent IDs |
| Tenant scope | `BaseTenantModel::doFind()` bypass; ambiguous `[]` sentinel | Scope restored; explicit unrestricted representation |
| Contact hashing | Raw SHA-256 | Keyed HMAC via `DataEncryption::blindIndex()` with compatibility path |
| Config settings | Cross-diocese/province leakage | Scoped `where()` applied |
| Schema resolution | `getColumnsForTable()` copy-pasted in 5 services | Single `SchemaResolver` |
| Legacy fallbacks | Uninventoried | `profile`/`guest`/`parish_people` fallbacks inventoried (M7); preserved pending migration evidence |
| Static analysis | None | PHPStan 2.2 level 0 configured, green with 14-item baseline |
| Accessibility | Not addressed | Started on Parish Finance (scope attrs, aria-labels) — proof of concept only |
| Test infra | Shared in-memory DB caused cross-test schema pollution | Diagnosed and fixed; `fieldExists` guards added in `PersonCleanupService`/`PersonMergeService`; `transException(true)` surfaces real transaction errors |

## Report's Recommended Priority Order — Disposition

1. **Stabilize failing integration tests** — Done. Suite is green.
2. **Resolve schema/column mismatches and provisioning errors** — Done for test fixtures;
   the canonical schema still only runs under MySQL (see open items).
3. **Finish highest-risk backlog items** — Not started (see below).
4. **Harden permission and role behavior** — Done via C8/H7 audits and boundary tests.
5. **Close remaining governance/pathway features** — Not started.

## Still Missing / Partial (confirmed against the codebase today)

- **Bank reconciliation** — `bank_account`, `bank_transaction`, `bank_reconciliation`
  tables exist in the canonical schema dump, but no controllers/models wire them.
- **Small groups module** — schema references only; no application-layer module.
- **Bulk messaging** — no general-purpose campaign/messaging module; existing messaging
  is follow-up-oriented.
- **Membership pathway tracking** — documented plan; no stage-tracking implementation.
- **Leadership appointment gate** — no hard good-standing enforcement at insertion.
- **Guest-to-member conversion pre-fill** — conversion flow exists; create-member form
  still not pre-filled.
- **PwnedValidator** — intentionally not enabled (external service ruled out);
  `NothingPersonalValidator` and composition rules are active instead.

## New Findings Not in the Original Report

1. **CI runs no tests.** `.github/workflows/php.yml` installs dependencies but the
   PHPUnit step is commented out. A green local suite is not enforced anywhere.
2. **MySQL verification gate never ran.** The megaplan required running data-integrity
   changes against a disposable MySQL schema; all verification so far is SQLite-only.
   Transaction semantics, ENUM handling, and FK behavior differ between the drivers.
3. **Test fixtures remain fragile.** Fixes added `ensureColumnExists` fallbacks, but the
   shared `:memory:` DB means any new test that creates a table with a non-canonical
   shape can still poison later tests. A shared canonical SQLite schema (or per-test
   schema isolation) would remove this class of bug permanently.
4. **PHPStan is shallow.** Level 0 with a 14-entry baseline of PHP 8.4 implicit-nullable
   deprecations. Raising the level will surface real type issues.
5. **Pre-existing PSR-4 mismatch.** `app/Models/MemberChurchAffiliationModel.php`
   declares `MemberParishAffiliationModel` — skipped by Composer autoload and excluded
   from PHPStan; the class is effectively unreachable via PSR-4.
6. **Accessibility coverage is ~1 view deep.** ~895 icon usages exist across views;
   only `Parish/Views/Finance/index.php` received scope/aria attributes.

## Recommended Improvement Order

### Tier 1 — Verification gaps (low effort, high confidence gain)

1. **Uncomment the PHPUnit step in `.github/workflows/php.yml`** and add
   `vendor/bin/phpstan analyse`. Optionally add `sh build/e2e_module_smoke.sh` and
   `sh build/targeted_forms_smoke.sh` as a separate job.
2. **Run the suite once against disposable MySQL** (separate schema). Validates
   transaction boundaries (H1/H2/H6), the canonical schema migration, and the
   contact-hash backfill under production SQL semantics.
3. **Add a canonical SQLite schema bootstrap for tests** — load a SQLite-translated
   dump once per process instead of per-test `CREATE TABLE IF NOT EXISTS` fragments,
   eliminating schema-pollution flakiness.

**Status (implemented 2026-09-16):** ✅ all three done.

- `.github/workflows/php.yml` runs PHPUnit + PHPStan on push.
- Disposable-MySQL verification ran against `ekklesia_test` and surfaced **9 real
  defects**, now fixed: stale `table_names`/`listFieldNames` cache after the
  canonical dump import (`resetDataCache()` added), duplicate `setting_key` index,
  a broken `AFTER` chain + duplicate index in `AddOrganizerColumnsToEvents`,
  unguarded adds in `AddNationalProvinceScopeToEventCalendar` and
  `AddParishAffiliationPersonParishUnique`, duplicated table entries in
  `AddLeaderFieldsToParishPeopleAndSyncAll`/`AddPersonMembershipDetails...`,
  hardcoded FK names in the two `DropGuestId*` migrations, and non-idempotent
  `PermissionsAndRolesSeeder` inserts. Fresh install now runs 88 migrations +
  both seeders cleanly (154 tables).
- Canonical test schema: `Tests\Support\Database\CanonicalTestSchema` holds the
  union column set for shared tables; `tests/bootstrap.php` applies it before the
  suite and `Tests\Support\CanonicalSchemaExtension` (PHPUnit `Prepared`
  subscriber) repairs it after every `setUp()`. The two drop-and-recreate
  polluters (`SacramentFlagSyncServiceTest`, `SacramentFlagSyncIntegrationTest`)
  were converted to `CanonicalTestSchema::reset()`. Suite: 511 tests, 0 errors,
  0 failures.

### Tier 2 — Finish the hardening tail

1. **Raise PHPStan to level 3–5** incrementally; burn down the baseline; fix the
   `MemberChurchAffiliationModel` filename/class mismatch.
2. **Extend the M1 fat-controller extraction** beyond the single proof-of-concept
   controller (`db_connect()` still appears in Treasurer/Worship/Clergy controllers).
3. **Systematic a11y pass** on the remaining views (icon-only buttons, table
   `scope`/`caption`, `alt` text) — mechanical, wave-per-module.
4. **i18n completion** — `L5` covered selected strings; a sweep for remaining hardcoded
   English in views is still open.

**Status (implemented 2026-09-16):**

- ✅ PHPStan raised to **level 3**, green with no baseline growth. Fixes along the
  way: `PersonStatusHistoryModel::getTableName()` accessor (protected-property
  access), `VestryVoteModel::tallyVotes()` rewritten on an explicit builder, a
  stale `sunday_attendance_trend` docblock key removed from
  `ParishDashboardDataService::assemble()`, `WRITEPATH`/CI4 constants added to
  the PHPStan stub. Level 4 produces 42 narrowing/`alwaysTrue` findings — a
  reasonable next burn-down target.
- ✅ `MemberChurchAffiliationModel` PSR-4 mismatch fixed (proper alias class;
  PHPStan exclusion removed).
- 🔄 Fat-controller extraction — `Clergy\Controllers\People` (2,396 → ~2,000
  lines) is fully service-mediated on its write paths:
  - `LeadershipPositionService` — scope resolution + position CRUD + registry sync
  - `DonationService` — fund/income-account validation, parish-scoped lookup,
    persistence, receipt issuance; also adopted by `Treasurer\Controllers\Donations`
  - `AnnualPledgeService` — validation rules, parish-scoped lookup, pledge CRUD
  - `SacramentalRecordService` — record/participant rules, parish scoping,
    duplicate checks, `SacramentFlagSyncService` invocation
  - `FamilyService` — family rules, membership checks, transactional
    create-family-with-head
- ✅ `SystemAdminUsers` — `SystemAdminUserService` owns hierarchy reads,
  primary-admin ops, cascade deletes, permission sync, and directory queries
  (47 → 6 raw calls; the rest are transaction handles and trait-helper args).
- ✅ `Diocesan\Parishes` — `ParishStaffService` (staff add/promote/demote/
  role removal honoring `allowedAssignableRoles`) and `ParishDeletionService`
  (impact counts + FK-safe delete order); 30 → 7 raw calls; province-admin
  write blocks preserved.
- ✅ `MemberPortal\Profile` — `HierarchyService` (province/diocese/parish
  option lists, parent-child checks, `parish_people` affiliations),
  `RegistrationNotificationService` (scoped admin recipients, pending-review
  email, registration audit), `SchemaResolver::filterPayloadByTableColumns`;
  22 → 2 raw calls; elevated-admin auto-approval untouched.
- ✅ `MemberPortal\AuditLogs` — `AuditLogQueryService` (scoped parish
  resolution, role-dependent action filtering, statistics, option lists,
  actor autocomplete); 18 → 0 raw calls; dead helpers removed.
- ✅ `Clergy\Members` — `MemberAffiliationService` (attach/detach/home-parish
  transactions + profile mirror + person deactivation) and
  `ProvinceStaffDirectoryService` (province/diocesan staff lists);
  16 → 0 raw calls.
- ✅ `Clergy\Guests` — `GuestFollowupService` (progress badges, next pending
  action, status counts, timeline with orphaned `call_task` merge, dashboard
  aggregates); 16 → 0 raw calls.
- ✅ a11y sweep — `scope="col"` on 442 `<thead>` header cells across 73 views,
  `scope="row"` on the key/value row labels in `MemberPortal/Views/Families/view.php`;
  accessible names added to all 20 icon-only controls found by a
  quoted-attribute-aware detector (localized `aria-label`s plus a new
  `ChartAccounts.actionToggle` key in en/es/fr); 747 decorative icons marked
  `aria-hidden="true"` across 123 views. Two detector-based regression guards
  added to `AccessibilityAndStaticAnalysisTest` (icon-only controls must expose
  a name; `<thead>` `<th>` must declare scope).
- ⬜ i18n sweep still open.

### Tier 3 — Product backlog (from the original report, still valid)

1. **Leadership appointment gate** — enforce good-standing at insert; smallest and
   lowest-risk of the backlog items.
2. **Guest-to-member conversion pre-fill** — wire flash data into the create form.
3. **Membership pathway tracking** — stage model + transitions.
4. **Bank reconciliation** — largest item; schema exists, needs full module.
5. **Small groups** and **bulk messaging** — new modules; design before building.

### Tier 4 — Legacy retirement (evidence-gated)

 1. **Retire `profile`/`guest`/`parish_people` fallback paths** only after the M7
    inventory is verified against production data and dual-write coverage is confirmed.
    Premature removal was explicitly out of scope for the completed waves.

## Bottom Line

- Production readiness is materially higher than the 2026-07-28 report: security
  criticals are closed, data-integrity boundaries exist, and the suite is green.
- The dominant residual risks are **verification coverage** (CI doesn't run tests;
  MySQL semantics unverified) and **unfinished backlog features**, not instability.
- Suggested next action: Tier 1 items 1–3, then re-run this report's checklist.
