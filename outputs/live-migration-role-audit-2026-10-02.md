# Live MySQL migration and user-role integrity audit

> **Historical pre-migration snapshot.** The user subsequently applied the migrations in batch 94. For the current state, see the [post-migration audit](post-migration-role-audit-2026-10-02.md).

**Observed:** 2026-10-02 21:06:13 UTC (17:06 EDT). **Environment:** the CodeIgniter application's configured local MySQL connection. **Method:** CI4 `php spark migrate:status`, `INFORMATION_SCHEMA` metadata and aggregate/ID-only Shield queries inside a `START TRANSACTION READ ONLY` session; no names, email addresses, credential material or freeform notes were read. The preserved [pre-migration audit snapshot](../.work/clergy-role-plan/live_auth_migration_audit_pre_2026-10-02.json) records the raw counts and IDs. **No migration, seed, group edit, permission edit or live-schema change was executed by this audit.**

## Verdict

**The live schema is in a clean pre-migration state, not an activated clergy/deanery state.** The foundation's eight required baseline tables and its checked existing report columns are present. None of its nine target tables or 17 additive target columns exists. Its two clergy migrations and an earlier group-rename migration are pending, while the `migrations` ledger holds 158 applied rows. That is a satisfactory *preflight shape check*, **not** proof that the migration will succeed on MySQL; the one disposable SQLite rehearsal passed (1 test, 13 assertions), but a MySQL clone rehearsal and backup remain required. The migration explicitly extends the current three-value `leadership_position.scope_type` ENUM with `deanery` when running on MySQL; it does not omit this step. [Foundation migration](../app/Database/Migrations/2026-10-02-185843_CreateClergyDeaneryFoundation.php); [rehearsal test](../tests/ClergyMigrationRehearsalTest.php).

**Do not equate existing direct Shield signing keys with verified ecclesial authority.** Seventeen legacy direct rows across four report/diocesan signing keys are currently attached to technical-admin accounts. The new group matrix no longer grants those keys to those groups, but the provenance table that would distinguish managed grants from older/manual rows does not yet exist. The new checked endpoints require additional reviewed office/diocese evidence; do not assume that every other current/future consumer of `user->can()` does so. Review the rows deliberately rather than mass-revoking or re-seeding blindly. [Current group matrix](../app/Config/AuthGroups.php); [provenance-aware seeder](../app/Database/Seeds/PermissionRoleSyncSeeder.php); [scoped authorization service](../app/Services/ClergyAuthorizationService.php).

## Migration ledger and schema preflight

| Pending migration (App namespace) | Live status | Function | Audit observation |
| --- | --- | --- | --- |
| `2026_09_30_000001_RenameRoleGroupsPhase3` | Not run | Renames `treasurer`→`finance`, `secretary`/`clerk`→`vestry_secretary`. | **Zero** current memberships in those three legacy groups, so its `up()` currently has no matching membership to rename. Its `down()` cannot restore whether an account originally belonged to `clerk` or `secretary`; it also renames any subsequently assigned `vestry_secretary` to `secretary`. Treat rollback as **lossy**, despite the migration's non-destructive comment. |
| `2026-10-02-185843_CreateClergyDeaneryFoundation` | Not run | Adds deanery, ordination, status, grants, provenance, report-signature tables and additive columns. | Eight required baseline tables and required report fields exist; **zero of nine** new tables and **zero of 17** new columns exist. Live referenced `id` columns checked are `INT UNSIGNED`. The existing leadership scope is `ENUM('parish','diocese','province')`; this migration includes the MySQL `ALTER` to add `deanery`. |
| `2026-10-02-185843_StageClergyReviewCandidates` | Not run | Copies legacy role/position *claims* into a review queue. | Zero users currently have the `clergy` Shield group; **one** existing leadership row qualifies as a clergy-claimed position. The migration stages evidence only—no verified order, group, or app permission. |

CI4 lists both same-timestamp clergy classes individually as pending. [CodeIgniter migration documentation](https://codeigniter4.github.io/userguide/dbmgmt/migration.html) describes the status ledger and migration order; the direct live status output was checked as part of this audit. The new migration files are currently **untracked in Git** in this working copy; a deployment sourced only from tracked commits would not include them. The previous draft in `outputs/` is not in CI4's migration discovery path.

**Execution hazard:** The foundation issues multiple `CREATE TABLE`/`ALTER TABLE` statements. MySQL [implicitly commits DDL](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html); a mid-migration error could leave a partial schema even if CI4 has not recorded the migration as complete. The foundation's next-run preflight is designed to stop on existing target tables/columns, not to repair such a partial install automatically. Clone rehearsal, a backup, and a reviewed forward-fix/restore path are prerequisites. The SQLite test establishes logic/fixtures, **not MySQL DDL compatibility**. See [foundation migration `up()`/`down()`](../app/Database/Migrations/2026-10-02-185843_CreateClergyDeaneryFoundation.php) and [legacy candidate migration](../app/Database/Migrations/2026-10-02-185843_StageClergyReviewCandidates.php).

## Current Shield group assignments

**26** `users` rows are active; **23** distinct users have a Shield group; there are **24** group-membership rows because user ID **4** has both `member` and `systemadmin`. No duplicate or orphan membership rows, unknown group names, or cross-tier admin-group combinations were found. IDs are database identifiers, not names.

| Shield group | Members | User IDs |
| --- | ---: | --- |
| `systemadmin` | 5 | 4, 26, 27, 35, 37 |
| `provinceadmin` | 3 | 24, 32, 34 |
| `dioceseadmin` | 3 | 23, 30, 33 |
| `parishadmin` | 3 | 5, 41, 70 |
| `member` | 10 | 4, 56, 57, 58, 60, 62, 64, 67, 68, 69 |
| All other configured groups (including `clergy`, `finance`, `warden`, `vestry_secretary` and the mission groups) | 0 | — |

All three diocesan admins have one profile and matching explicit primary assignment: user **23 → diocese 104**, **30 → 107**, **33 → 1**. No absent or conflicting diocesan assignment was detected. The remaining **three active users without any group—42, 63 and 65—each have a profile but have no `auth_identities` row, no direct permission, and no `user_registration_requests` row**. They presently lack the observed login identity; review whether they are intentional legacy/person records rather than assigning a group automatically. [Shield tables](../app/Database/canonical_schema_tables.sql); [preserved audit snapshot](../.work/clergy-role-plan/live_auth_migration_audit_pre_2026-10-02.json).

## Direct permissions requiring review

There are **239** direct `auth_permissions_users` rows, with **no duplicate user/permission pair or orphan user reference**. The following rows have the highest policy relevance; they are direct grants, not inferred effective permissions. Group permissions can also provide capabilities (for example, `dioceseadmin` has `leadership_position.manage` in the current matrix even though these admins have no matching direct row).

| Direct permission | Rows | Current user IDs and groups | Observation |
| --- | ---: | --- | --- |
| `governance.diocese.approve` | 6 | 23, 30, 33 (`dioceseadmin`); 4, 26, 27 (`systemadmin`) | No longer in either admin group's current matrix. |
| `governance.report.finalize_clergy` | 3 | 4, 26, 27 (`systemadmin`) | Technical admin rank alone does not establish priestly office. |
| `governance.report.approve_warden` | 4 | 5 (`parishadmin`); 4, 26, 27 (`systemadmin`) | Lay signatory office must be independently reviewed. |
| `governance.report.certify_clerk` | 4 | 5 (`parishadmin`); 4, 26, 27 (`systemadmin`) | Same separation applies to clerk/secretary certification. |
| `leadership_position.manage` | 1 | 5 (`parishadmin`) | Diocesan admins receive this from their group matrix; direct rows are not the full effective-permission picture. |

There are currently **no direct rows** for `clergy.ordination.verify`, `clergy.appointment.manage`, `deanery.view`, `deanery.manage`, or `deanery.coordinate`. Four other direct permission names occur once each but are absent from today's permission catalog: `budget.manage`, `member.import`, `member.tag.update`, `volunteer.remind`. Determine whether any legacy code still checks them before removal. **Do not automatically revoke or relabel existing direct rows**: their creation provenance is unknown, and the [sync seeder](../app/Database/Seeds/PermissionRoleSyncSeeder.php) intentionally preserves untracked rows.

## Legacy clergy transition candidate

The only qualifying `leadership_position` is **position ID 5, user ID 64, person ID 17**. It is active and parish-scoped to **parish 1415**, with stored `clergy_order=priest` but **no `role_code`**. User 64 has the `member` Shield group, not the `clergy` group. Their single primary `user_person` link matches person 17, and parish 1415 exists, so the expected *candidate staging link* is coherent. This is a **self/legacy claim, not verified ordination, canonical standing or an approved parish office**. A reviewer must obtain independent evidence and assign the actual controlled office before granting authority. [Staging migration](../app/Database/Migrations/2026-10-02-185843_StageClergyReviewCandidates.php); [review workflow](../docs/CLERGY_DEANERY_AUTHORIZATION.md).

## Recommended next steps (none executed)

1. Preserve a verified backup and run all **three pending migrations** on a MySQL clone that mirrors the live schema and representative permission rows. Verify the `deanery` ENUM, FKs/indexes, legacy candidate count and *absence of new verified powers*; test rollback refusal after reviewed data/signatures exist. Do not use `migrate:refresh` on the live database.
2. Decide, with the authorized diocesan/technical owners, how to reconcile the 17 legacy direct signing rows. Identify intended signatories and documentary office evidence. Do not execute `PermissionRoleSyncSeeder` as a substitute for that review; its new provenance table cannot retrospectively prove who created existing grants.
3. Confirm the group-less accounts are intentional records and review the four unrecognized permission names. Avoid group assignment or deletion by inference.
4. Only after clone validation and a deployment/rollback plan, schedule the live migration and separately bootstrap the first verified diocesan ordinary. Verify per-diocese policies remain disabled until local signatory rules are approved. **No authorization to run live migration is implied by this audit.**

### Evidence and limits

- [Repeatable read-only audit script](../.work/clergy-role-plan/scripts/live_auth_migration_audit.php) and [preserved pre-migration JSON snapshot](../.work/clergy-role-plan/live_auth_migration_audit_pre_2026-10-02.json). Snapshot holds numeric user IDs/group keys but no contact or credential data. It is a **point-in-time** observation, not the current migration status.
- [Role policy](../app/Config/AuthGroups.php), [group-sync seeder](../app/Database/Seeds/PermissionRoleSyncSeeder.php), [three pending migrations](../app/Database/Migrations/), and [canonical authorization runbook](../docs/CLERGY_DEANERY_AUTHORIZATION.md).
- `php spark migrate:status` and the targeted [SQLite rehearsal](../tests/ClergyMigrationRehearsalTest.php) succeeded. **No MySQL clone migration was executed**, so this report does not certify apply/rollback success on MySQL or the historical equivalence of applied migration file contents (the CI4 ledger records status, not a code checksum).
