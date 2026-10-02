# Post-migration schema and Shield role audit

**Observed:** 2026-10-02 21:12:29 UTC (17:12 EDT), after the user's migration run. This is a **read-only** check of the CodeIgniter application's configured local MySQL database. The previous [pre-migration audit](live-migration-role-audit-2026-10-02.md) is retained as historical evidence. Neither this audit nor its test run applied a migration, seeded permissions, or changed user roles.

## Verification result

**All three previously pending migrations are recorded as applied in CI4 batch 94** at 21:09:12–21:09:13 UTC: `RenameRoleGroupsPhase3`, `CreateClergyDeaneryFoundation`, and `StageClergyReviewCandidates`. The ledger grew from **158 to 161** rows. `php spark migrate:status` shows **no remaining pending migration**. This verifies the configured database's ledger and visible schema; it is not a retrospective test of rollback or historical migration code checksums. [CI4 migration status reference](https://codeigniter4.github.io/userguide/dbmgmt/migration.html); [post-run JSON snapshot](../.work/clergy-role-plan/live_auth_migration_audit_post_2026-10-02.json).

| Live schema check | Post-run observation |
| --- | --- |
| New tables | **9/9 present:** deanery, dated parish membership, ordination, canonical status, transition candidates, scoped grants, permission provenance, report revisions, report signatures. |
| Additive columns | **17/17 present** on `leadership_position`, `parish`, and `parish_report`; no expected target field was missing. |
| Deanery office scope | `leadership_position.scope_type` is now `ENUM('parish','deanery','diocese','province')`. The `(deanery_id,active)` index exists in that order. |
| Referential metadata | **12 FK relationships** exist across the nine new tables, matching the relationships defined by the foundation migration. The one staged candidate's person and parish references also resolve. |
| Authorization/review data | **1 pending legacy candidate**; **0** deaneries, deanery memberships, verified ordination rows, canonical standing rows, access grants, managed-permission provenance rows, approved leadership positions, report revisions, or report signatures. |

The candidate is from legacy `leadership_position.id=5`: user **64**, person **17**, claimed order **priest**, no controlled office code, parish **1415**. The primary user-person link matches person 17. Migration staged this as **unverified evidence** (`review_status=pending`); it did **not** turn the claim into ordination or a grant. [Candidate staging migration](../app/Database/Migrations/2026-10-02-185843_StageClergyReviewCandidates.php).

**Database-enforcement follow-up:** The foundation added `leadership_position.deanery_id` and its index, but **no FK from that existing table's `deanery_id` to `deanery.id`**. The new `permission_role_sync_managed.permission_user_id` likewise has no FK to `auth_permissions_users.id`; its table is currently empty. The [schema specification](clergy-deanery-schema-permissions.md) calls for a deanery FK. Application services check scope/provenance, but direct SQL writes would not have those two referential protections. Treat an additive FK/constraint migration—after confirming existing data and desired deletion behavior—as a separate hardening task. No new FK was applied by this audit. [Foundation migration](../app/Database/Migrations/2026-10-02-185843_CreateClergyDeaneryFoundation.php); [post-run FK metadata](../.work/clergy-role-plan/live_auth_migration_audit_post_2026-10-02.json).

## User and permission state: unchanged by migration

The pre/post read-only snapshots show **identical group memberships, relevant direct grants, and group-less-account status**. There are **26 active user records**, **23** assigned to a Shield group, **24** group-membership rows, and **239** direct permission rows. No duplicate/orphan group or direct-permission rows were found.

| Shield group | Accounts | User IDs |
| --- | ---: | --- |
| `systemadmin` | 5 | 4, 26, 27, 35, 37 |
| `provinceadmin` | 3 | 24, 32, 34 |
| `dioceseadmin` | 3 | 23, 30, 33 |
| `parishadmin` | 3 | 5, 41, 70 |
| `member` | 10 | 4, 56, 57, 58, 60, 62, 64, 67, 68, 69 |
| `clergy` and every other configured group | 0 | — |

User **4** belongs to both `member` and `systemadmin`, explaining the one extra membership row. The three diocesan-admin profiles and primary appointments still agree (**23→104, 30→107, 33→1**). The active group-less users **42, 63 and 65** still each have a profile but no observed login identity, direct permission, or registration-request row; do not assign a group by inference. [Current AuthGroups matrix](../app/Config/AuthGroups.php); [post-run snapshot](../.work/clergy-role-plan/live_auth_migration_audit_post_2026-10-02.json).

**Legacy direct signing keys remain:** `governance.diocese.approve` has **6** direct rows (three diocesan admins and three system admins); `governance.report.finalize_clergy` has **3** (system admins); `governance.report.approve_warden` and `governance.report.certify_clerk` have **4 each** (one parish admin and three system admins). These **17 direct rows** did not change. The new group matrix no longer gives technical admin groups these ecclesial signatures, but direct Shield grants are separate from group grants. The new provenance table contains **zero** rows, so it cannot establish how these earlier direct grants were created. **Do not remove them automatically or mistake them for a documented ecclesial office.** Separately scoped authorization checks are required at every sensitive endpoint. [Role-sync seeder](../app/Database/Seeds/PermissionRoleSyncSeeder.php); [scoped authorization service](../app/Services/ClergyAuthorizationService.php).

## Follow-up gates

1. **Review the 17 untracked direct signing grants** against the intended diocesan/lay officers and existing routes. Keep a reason-coded decision for each grant before changing it; the new provenance mechanism cannot retroactively prove origin. Four additional legacy direct permission names are outside the current catalog (`budget.manage`, `member.import`, `member.tag.update`, `volunteer.remind`) and also warrant usage review.
2. **Review the one pending clergy transition candidate** with independent ordination/standing and a controlled office. Establish the first verified diocesan ordinary via the audited two-person bootstrap in the [authorization runbook](../docs/CLERGY_DEANERY_AUTHORIZATION.md); no such verified authority currently exists in the new tables.
3. **Plan FK hardening** for `leadership_position.deanery_id` and, if appropriate, managed-permission provenance, using a new forward migration rather than editing already-applied migration files. Review nullable/delete semantics and rehearse against MySQL first.
4. **Validate workflow activation per diocese**, not by assuming that schema installation activated an office or report-signature policy. The evidence/grant and approved-office counts are zero. No deanery was auto-created.

**Tests:** `vendor/bin/phpunit --no-coverage --do-not-cache-result` passed after the migration: **846 tests, 3,699 assertions, 6 skipped**. This avoids the machine's 128 MB PHP CLI result-cache limit; it does not replace a live authenticated end-to-end review. [Verification snapshot](../.work/clergy-role-plan/live_auth_migration_audit_post_2026-10-02.json) and [repeatable read-only audit script](../.work/clergy-role-plan/scripts/live_auth_migration_audit.php).
