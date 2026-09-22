# Membership Status Simplification Plan

**Branch:** `refactor/membership-status-simplification`
**Status:** In progress — Phase 0/1
**Date:** 2026-09-21

## Problem

The "guest → leader" progression is modeled across overlapping dimensions that are
individually defensible but over-denormalized in storage. `parish_affiliation`
carries **six** overlapping status fields:

| Column | Role | Redundant with |
| --- | --- | --- |
| `membership_status` | Canonical lifecycle (visitor / regular_attender / member / inactive / transferred / deceased) | — source of truth |
| `affiliation_type` | High-level bucket (guest / attendee / member / clergy / staff) | Derivable from `membership_status` for 3 of 5 values |
| `canonical_status` | Copy of `membership_status`; only ever SELECTed, never read for logic | `membership_status` |
| `is_member` | Stored flag meant to equal `membership_status='member'` | `membership_status` |
| `is_good_standing` | Selected in `PeopleDirectoryService`; nothing writes it | `member_standing_summary.is_good_standing` |
| `pathway_stage` | Denormalized 1–3 pathway stage, single writer (`PathwayStageService`) | Partially derivable; kept as a cache |

Plus `profile.membership_status` — a second source of truth with a *different*
enum (`non_active` exists there, `pending` was removed) mirrored by ~8 writers
and read in ~3 places.

## Known drift symptoms (found while mapping)

- `ParishStaffService::ensureStaffProfile()` / `createStaffProfile()` write
  `profile.membership_status = 'active'` — not in the enum; MySQL silently
  stores `''` (same bug class as the registration-approval fix).
- `MemberParishAffiliationModel::countDistinctMembers()` filters
  `is_member=1 AND membership_status IN ('member','inactive')` — transitions to
  `inactive` set `is_member=0`, so the `inactive` branch is dead code.
- `MemberAffiliationService::deactivateActiveAffiliationsForUser()` sets
  `is_member=0, end_date=now` while leaving `membership_status='member'` —
  manufactures contradictory rows by design.
- `Worship/Controllers/ParochialReport.php` reads
  `p.membership_status IN ('visitor','regular_attender','pending')` — `pending`
  is no longer in the profile enum.
- `pathway_stage` is `ENUM(...)` in `canonical_schema_tables.sql` but `tinyint`
  in `canonical_schema.sql` — the schema dumps disagree.
- `member_standing_summary` is keyed by `user_id`, so non-portal persons can
  never reach good standing except via admin override.

## Principle

`parish_affiliation.membership_status` is the only stored status. Everything
else is either (a) a generated column, (b) computed by a service at read time,
or (c) deleted. All mutations go through two choke points:
`MembershipPathwayService` (status transitions) and `MemberAffiliationService`
(attach/detach/primary).

## Phase 0 — Baseline drift audit (no behavior change) — DONE

1. `php spark people:audit-affiliation-drift` (app/Commands/AuditAffiliationDrift.php)
   reports rows violating each invariant:
   - non-canonical `membership_status`
   - `is_member != isOnRegister(membership_status)`
   - `affiliation_type != affiliationTypeForStatus(membership_status)`
     (excluding `clergy`/`staff`)
   - `canonical_status != membership_status`
   - `end_date` set on non-terminal status
   - `profile.membership_status` invalid enum value / disagrees with primary affiliation
2. **Resolved domain decision — `is_member` means "on the parish register"**
   (`status IN ('member','inactive')`), not `status='member'`. Evidence:
   `MemberTagToggleIntegrationTest` locks `(inactive, is_member=1)` as a valid
   state, `countDistinctMembers` counts `is_member=1 AND status IN
   ('member','inactive')`, and canonically inactive members remain on the
   register until transferred/deceased. `MembershipStatusService` now exposes
   `isOnRegister()` (register flag) alongside `isMember()` (active member,
   `status='member'`, unchanged — locked by `MembershipStatusServiceTest`).

   **Intentional behavior change:** pathway transitions to `inactive` now keep
   `is_member=1` — inactive members remain on the parish register and in
   `countDistinctMembers`, instead of silently dropping out. This fixes the
   dead `inactive` branch in `countDistinctMembers`.

   **Reader audit (all ~30 `is_member=1` readers reviewed — confirmed safe):**
   - Register/count readers (`countDistinctMembers`, `ParishModel` and
     `DiocesesModel` `member_count`, `getParishStatusCounts`,
     `ProfileModel` status counts, `ParishDeletionService`,
     `SystemMissionOutcomes`): now correctly include inactive — this was the
     intent all along; the `inactive` bucket in status counts was previously
     unreachable.
   - Directory/scope readers (`getParishMembers*`, `PeopleDirectoryService`
     `members`/`non_members` scopes, `isUserAffiliatedWithParish`,
     `MemberAccessService`, `DiocesanScopeFilter`/`ProvinceScopeFilter`,
     `BaseController` scope resolution, `syncProfileParishMirror`,
     `setHomeParish`): inactive members stay affiliated, retain portal access
     and parish attribution — pastorally correct (a lapsed member is still a
     member of record).
   - Active-only readers (`isRecordableMember`, `AttendanceController`,
     `PeopleMetricsService`, `ProfileModel` `active_members`): already
     double-guard with `AND membership_status='member'` — unaffected.
   - Visible change: member directories now show inactive members (with an
     Inactive badge) instead of hiding them. If a strictly-active list is
     needed, add an `active_members` scope filtering `membership_status='member'`.

## Phase 1 — Fix the write paths (bugfixes, no schema change) — DONE

1. `attachToParish()` `$isMember` param **removed** — `is_member` is always
   derived via `MembershipStatusService::fieldsForStatus($status)`, the single
   derivation point for `membership_status`/`affiliation_type`/
   `canonical_status`/`is_member`. Also fixed: the `$isHome` param was silently
   ignored — it now calls `setHomeParish()` for on-register statuses.
2. `deactivateActiveAffiliationsForUser()` and `detachFromParish()` now write
   the consistent off-register state (`fieldsForStatus('visitor')` +
   `end_date`) instead of `is_member=0` with a stale `member` status. (Matches
   the locked `setMemberTag` revoke policy: off-register → `visitor`.)
   Soft-delete was rejected: unique keys on `(linked_user_id, parish_id)` and
   `(person_id, parish_id)` have no `deleted_at` component, so a soft-deleted
   row would block re-attach.
3. `ParishStaffService`: `'membership_status' => 'active'` → `'member'`
   (two sites — `'active'` is not in the profile ENUM; MySQL stored `''`).
4. `PersonConversionService`, `GuestDirectoryService::revertConversion`,
   `PersonProvisioningService` now use `fieldsForStatus()` instead of setting
   the tuple by hand; `NonPortalMemberProvisioningService` no longer passes
   redundant `affiliation_type`/`is_member` overrides.
   `MembershipPathwayService::applyTransition` also uses `fieldsForStatus()`,
   which additionally fixes `canonical_status` going stale on transitions.
5. `tests/database/MembershipInvariantTest.php` locks the invariants for every
   write path. Full suite: 661 tests, 2955 assertions, 6 skipped.

## Phase 2 — Make derived columns structural

1. Migration: `is_member` → `GENERATED ALWAYS AS (membership_status IN
   ('member','inactive')) STORED` — the resolved on-register semantics.
   Optionally rename to `is_on_register` in the same migration so the column
   name matches the meaning (repoint the ~30 readers). Same treatment for
   `affiliation_type`'s derivable buckets; `clergy`/`staff` can't be generated:
   - **Option A (recommended):** narrow the enum to `guest|attendee|member` and
     expose clergy/staff via the `leadership_position` EXISTS pattern already
     used for `is_leader` in `PeopleDirectoryService`.
   - **Option B:** keep the column stored, written only by staff/leadership
     flows, with an invariant test.
2. Drop `canonical_status` — only ever SELECTed; writers just copy
   `membership_status`. Stop writing → backfill → drop column.
3. Drop `parish_affiliation.is_good_standing` — unwritten; readers already
   join `member_standing_summary`.
4. `pathway_stage`: keep (real cache, single writer) but reconcile the schema
   dumps — tinyint vs ENUM.
5. Legacy `member_parish_affiliations` view aliases `is_member` AS `active` —
   under register semantics that alias reports inactive members as `active=1`.
   Review view consumers when touching the column.

## Phase 3 — Retire `profile.membership_status`

1. Repoint the three real readers to the primary affiliation:
   `ProfileModel` status counts (~line 438), `ParochialReport` (~line 584 —
   also fixes dead `'pending'`), `FamilyModel` (~line 76).
2. Delete the ~8 writers: `RegistrationApprovalService`,
   `ProvinceDioceseAdmins`, `DioceseParishAdmins`, `Events.php`,
   `AdminCreationService`, `PersonProvisioningService`, `ParishStaffService`,
   `GuestIntakeService`.
3. If legacy SQL still needs it, keep it temporarily as a write-only mirror fed
   by `PersonProfileSyncService` — then drop the column and the
   `profileStatuses`/`profileToAffiliation`/`affiliationToProfile` maps in
   `MembershipStatusService` (~halves the service).

## Phase 4 — Enforce the choke points

1. Architecture test failing if app code writes status columns on
   `parish_affiliation` outside `MembershipPathwayService` /
   `MemberAffiliationService` (same style as `ModuleCrudControllerPathsTest`).
2. Document the invariant table in this file and
   `.devin/rules/parish-domain.md`.

## Phase 5 — Schema hygiene (independent)

1. Make migrations the only schema truth; regenerate or delete the four
   divergent SQL dumps.
2. Consider re-keying `member_standing_summary` by `person_id` instead of
   `user_id`.

## Sequencing

Phases 0→1→2 are the core fix (kills the bug class). Phase 3 is the largest
refactor but pure deletion once readers are repointed. Phases 4–5 are
enforcement/cleanup. Each phase is independently shippable; verify with
`vendor/bin/phpunit --no-coverage`.
