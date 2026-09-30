# Membership Status Simplification Plan

**Branch:** `refactor/membership-status-simplification`
**Status:** Complete — all 5 phases shipped
**Date:** 2026-09-21

## Problem

The "guest → leader" progression is modeled across overlapping dimensions that are
individually defensible but over-denormalized in storage. `parish_affiliation`
carries **six** overlapping status fields:

| Column             | Role                                                                                                                   | Redundant with                                                     |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------ |
| `membership_status`| Canonical lifecycle (visitor / regular_attender / member / inactive / transferred / deceased)                          | — source of truth                                                  |
| `affiliation_type` | High-level bucket (guest / attendee / member / clergy / staff)                                                         | Derivable from `membership_status` for 3 of 5 values               |
| `canonical_status` | Copy of `membership_status`; only ever SELECTed, never read for logic                                                  | `membership_status`                                                |
| `is_member`        | Stored flag meant to equal `membership_status='member'`                                                                | `membership_status`                                                |
| `is_good_standing` | Selected in `PeopleDirectoryService`; nothing writes it                                                                | `member_standing_summary.is_good_standing`                         |
| `pathway_stage`    | Denormalized 1–3 pathway stage, single writer (`PathwayStageService`)                                                  | Partially derivable; kept as a cache                               |

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

**DONE** — `2026-09-23-000000_SimplifyParishAffiliationStatusColumns` plus the
reader/test sweep:

1. `is_member` → `GENERATED ALWAYS AS (membership_status IN
   ('member','inactive')) STORED` — the resolved on-register semantics. All
   write payloads stripped; `fieldsForStatus()` no longer returns it. SQLite
   test schema mirrors it as a VIRTUAL generated column via
   `CanonicalTestSchema::ensureGeneratedIsMember()` (rebuilds plain columns
   forge-created test tables leave behind). The column was NOT renamed to
   `is_on_register` — deferred; the generated expression now guarantees the
   meaning regardless of name.
2. `affiliation_type` → **Option A implemented**: enum narrowed to
   `guest|attendee|member`. The migration counts clergy/staff rows first and
   skips the narrowing (with a warning log) if any exist — reclassify those
   through `leadership_position` and re-run. No app code read or wrote the
   clergy/staff buckets.
3. `canonical_status` dropped — only ever SELECTed; writers just copied
   `membership_status`. Removed from `allowedFields`, `fieldsForStatus()`,
   `PeopleDirectoryService` selects, test payloads, and schema dumps.
   `AuditAffiliationDrift` skips the check when the column is absent.
4. `parish_affiliation.is_good_standing` dropped — unwritten; the one real
   reader (`Diocesan/Dashboard` good_standing_members) now joins
   `member_standing_summary` on `user_id` + current year.
5. `pathway_stage` dumps reconciled — TINYINT per the migration, ENUM dump
   entries corrected.
6. `vw_parish_active_affiliation` dropped (it selected the removed columns);
   `member_parish_affiliations` view now exposes `active` as
   `membership_status='member'` instead of aliasing `is_member`, so inactive
   members no longer report `active=1` to legacy consumers.
7. `MemberTagToggleIntegrationTest` rewritten: the contradictory
   `guest+is_member=1` / `member+is_member=0` rows it manufactured can no
   longer exist. Grant on a non-member row is now correctly rejected (the
   `setMemberTag` guard's unreachable-drift path collapsed); revoke still
   demotes to `visitor`.

Full suite after Phase 2: 664 tests, 2957 assertions, 6 skipped.

## Phase 3 — Retire `profile.membership_status` — DONE

The column turned out to serve two incompatible purposes: a stale mirror of
the primary affiliation status, and an (invalid) onboarding state — `pending`
was removed from the enum in `2026-08-01-174951`, so the member-portal write
of `'pending'` was silently coerced to `''`.

What landed:

1. `MemberParishAffiliationModel::statusForUser($userId, $parishId = null)` is
   the canonical resolver — primary affiliation status, or the status at a
   given parish; `null` when the user has no live affiliation.
2. Profile arrays are decorated at read time where views still expect a
   `membership_status` key: `BaseController::getProfile()`,
   `MemberPortal\Profile::resolveCurrentUserProfileState()`,
   `MemberDashboardDataService::resolveIdentity()`, and the Clergy members
   edit/view paths. The key is derived, never stored.
3. All query readers repointed to `parish_affiliation.membership_status`:
   `ProfileModel` status counts, `FamilyModel` (member status at the family
   parish), `MemberMergeService` duplicate detection, `ParochialReport`
   active participants, `PeopleDirectoryService` (vestigial
   `profile_membership_status` select removed).
4. All ~15 profile writers deleted — inserts/updates in
   `RegistrationApprovalService`, `AdminCreationService`,
   `ParishStaffService`, `Events.php`, `PersonConversionService`,
   `NonPortalMemberProvisioningService`, `PersonProvisioningService`,
   `MemberPortal\Profile`, `Clergy\Members`, `ProvinceDioceseAdmins`,
   `DioceseParishAdmins`. Registration pending/review stays in
   `user_registration_requests.status` — that was always the gate.
5. `MembershipStatusService` profile maps removed; the `active`,
   `non_active`, `pending` aliases folded into
   `normalizeAffiliationStatus` for import/back-compat only.
6. `EventVisibilityService` stale `['active','inactive']` fallback fixed to
   canonical register statuses; member-portal/clergy views switched from
   `active`/`pending` vocabulary to canonical status names and outline
   badges.
7. Migration `2026-09-24-000000_DropProfileMembershipStatus` drops the
   column plus `idx_membership_status` / `idx_parish_status` /
   `idx_church_status` (both legacy composite names handled); `down()`
   restores the enum and repopulates it from the primary affiliation.
8. Schema dumps and `CanonicalTestSchema` updated; ~30 test files swept of
   profile-status DDL/inserts.

Verified live: `profile.membership_status` and its indexes are gone on the
dev DB; drift audit clean (14 rows, 0 issues).

Full suite after Phase 3: 664 tests, 2954 assertions, 6 skipped.

## Phase 4 — Enforce the choke points — DONE

1. Stray status writers routed through the choke points:
   - `PersonProvisioningService` no longer inserts the affiliation row
     directly — it calls the new
     `MemberParishAffiliationModel::provisionForPerson()` (person-first
     creation path that `attachToParish()` can't cover, since a portal user
     may not exist yet) and aborts the transaction if it fails.
   - `PersonConversionService::convertToMember` now calls
     `MembershipPathwayService::transition()` for the guest→member change
     instead of a raw `fieldsForStatus('member')` update. The pathway runs
     first inside the outer transaction so its internal
     `resetTransStatus()` cannot mask a failure elsewhere, and its own
     `transStart`/`transComplete` nest under `transBegin` via transaction
     depth. The manual `person_status_history` insert was removed — the
     pathway records it. Remaining writes (`linked_user_id`,
     `converted_to_member_at`, `converted_by_user_id`, `followed_up`) are
     non-status metadata and stay direct.
   - `GuestDirectoryService::revertConversion` likewise transitions
     member→visitor through the pathway; the manual history insert was
     removed for the same reason.
2. `tests/unit/ParishAffiliationWriteScopeTest.php` scans all app + module
   PHP (migrations, seeders, dumps, views, lang excluded) and fails if any
   file outside the allowlist — `MemberParishAffiliationModel`,
   `MembershipPathwayService`, `MemberAffiliationService`,
   `MembershipStatusService` — calls `fieldsForStatus()`, `->set()`s a
   status column, or pairs a `membership_status`/`affiliation_type`/`is_member`
   array key with a `parish_affiliation` write in the same statement or the
   same file. Audit `new_values` payloads and non-status metadata updates
   (`linked_user_id`, `converted_*`, `followed_up`, `deleted_at`) do not
   trigger it.
3. The invariant is documented in `.devin/rules/parish-domain.md`
   (status columns are never written directly).

Full suite after Phase 4: 665 tests, 2955 assertions, 6 skipped.

## Phase 5 — Schema hygiene (independent) — DONE

1. Migrations are now the only schema truth. Three dead dumps deleted —
   `canonical_schema.sql`, `canonical_schema_tables_ordered.sql`,
   `localhost.sql` (nothing read them; `localhost.sql.bak` is untracked
   local debris, left alone). `canonical_schema_tables.sql` is **not** a
   snapshot — it is the input `2026_05_12_000001_CreateCanonicalSchema`
   executes on fresh installs, verified in step with the migrated dev DB.
   `tests/unit/SchemaHygieneTest.php` fails if any other `*.sql` appears
   under `app/Database/` or if the baseline migration stops reading the
   canonical file. `docs/MIGRATIONS_AND_SEEDERS.md` now names the correct
   file and documents the policy.
2. `member_standing_summary` re-key (`user_id` → `person_id`): **deferred.**
   Analysis: the table is `UNIQUE(user_id, year)` FK→`users`, and its
   three data sources (`attendance.user_id`, `annual_pledge.user_id`,
   `member_discipline.user_id`) are all user-keyed too. Re-keying the
   summary alone would orphan the aggregates — it requires re-keying the
   whole standing pipeline first, which is its own epic outside this
   plan's scope. Documented here so the decision is on record.

## Phase 6 — Collapse the guest bucket; single `visitor` default — DONE

1. **All creation paths default to `visitor`.** Registration approval
   (`MemberAffiliationService::ensurePrimaryAffiliationForUser`), non-portal
   provisioning (children and unflagged adults; the absent-`is_member`
   fallback flipped from a latent `member` default to `visitor`), and
   `attachToParish()`'s signature default all land at `visitor`. `member` is
   reached only by an explicit operator choice or a pathway promotion.
2. **`parish_affiliation.affiliation_type` dropped** —
   `2026-09-25-000000_DropParishAffiliationType`. Phase 2 narrowed the enum;
   with "guest = visitor" the column was pure derivation (`visitor`→guest,
   `regular_attender`→attendee, else member). Every reader was repointed to
   `membership_status = 'visitor'`; `affiliationTypeForStatus()` and the
   `fieldsForStatus()` payload key removed; the write-scope test's guarded
   column list updated.
3. **Jargon unified on "visitor."** English lang values relabeled
   Guest→Visitor (es/fr already said visitante/visiteur). Code-level
   names — `Guests.*` lang keys, `guest.*` permissions, `/guests` routes,
   `GuestDirectoryService` — kept as-is: the guest directory is the
   follow-up workflow *for* visitors, and renaming the feature surface is
   cosmetic churn with no schema benefit.

## Sequencing

Phases 0→1→2 are the core fix (kills the bug class). Phase 3 is the largest
refactor but pure deletion once readers are repointed. Phases 4–5 are
enforcement/cleanup. Each phase is independently shippable; verify with
`vendor/bin/phpunit --no-coverage`.
