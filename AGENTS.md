# Project Notes

## Dashboard Architecture (Hierarchy-First)

- `BaseController::resolveDashboardScope()` is the single source of truth for landing path resolution. It maps every user group to a `[tier, scope_id, role, route]` tuple following the church hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveAuthenticatedLandingPath()` delegates to `resolveDashboardScope()`.
- `BaseController::canAccessMyDashboard()` gates the `/me` personal dashboard. Parish-level roles and members always qualify. Elevated admins (system/province/diocese) qualify only if they have a `parish_affiliation` row.
- The old `/dashboard` URL (MemberPortal\Controllers\Dashboard::index) is now a **deprecated redirect shim** that calls `resolveDashboardScope()` and redirects. It will be removed in a future release.
- The old `/treasurer/dashboard` URL is also a deprecated redirect shim to `/parish/finance`.
- New parish-tier dashboards live in the `Parish/` module (namespace `Parish`):
  - `/parish/dashboard` — parish admin (KPIs, people, attendance)
  - `/parish/finance` — treasurer (parish-scoped, replaces diocese-scoped Treasurer dashboard)
  - `/parish/governance` — vestry roles (meetings, motions, votes)
  - `/parish/secretary` — secretary (minutes, reports)
  - `/parish/worship` — clergy (services, attendance, follow-up)
  - `/parish/volunteers` — volunteer coordinator (teams, schedules)
- The personal `/me` dashboard is served by `MemberPortal\Controllers\MyDashboard` and `App\Services\MemberDashboardDataService`. It surfaces 8 sections: identity, membership, sacraments, leadership, family, pledges, giving, attendance, onboarding.
- New Shield groups: `vestry_member`, `warden`, `clerk`, `secretary`. These are parish-tier roles with governance-focused permissions.
- Vestry governance tables: `vestry_meeting`, `vestry_motion`, `vestry_vote` (migration `2026_08_24_000100_CreateVestryTables`). The `Clergy\Controllers\Vestry` controller handles CRUD + voting at `/vestry/*`.
- `ParishDashboardDataService` extracts the parish-KPI logic that was inline in `MemberPortal\Controllers\Dashboard::index()` into a testable service.

## Database / Schema

- `user_scope_assignment` is **unused legacy** and is dropped by the `DropUserScopeAssignmentTable` migration. Primary admin state lives in dedicated tables:
  - `system_admin_primary` (singleton, `id = 1`)
  - `province_admin_primary` (per-province)
  - `diocese_admin_primary` (per-diocese)
  - `parish_admin_primary` (per-parish)

## Dashboard people counts vs. `/people` directory

- Dashboard tile counts (`total_people`, `total_members`, `active_members`, `inactive_members`) come from `PeopleMetricsService` and `MemberParishAffiliationModel`.
- The `/people` list queries the `person` table through `PeopleDirectoryService`.
- **Rule of thumb:** any parish-level people count must be based on `parish_affiliation.person_id` (the canonical `person` row), not `parish_affiliation.linked_user_id` and not `profile`.
- `countDistinctMembers()` and `countDistinctPeople()` should `JOIN person` and `COUNT(DISTINCT mca.person_id)` to stay in sync with the directory.
- `total_guests` remains `legacy guest table` + `parish_affiliation` rows where `affiliation_type = 'guest'`.

## "No parish assigned" on login

- `BaseController::getOperationalParishId()` resolves a regular user's parish from `BaseController::getHomeparishId()`, which uses `profile.parish_id`.
- If a logged-in user sees *"No parish assigned"*, the account is missing one or more of:
  - `profile.parish_id` (and `diocese_id`/`province_id`)
  - A `person` row for that user
  - A `user_person` mapping
  - A `parish_affiliation` row (`linked_user_id`, `parish_id`, `person_id`)
  - A `parish_admin_primary` row for parish admins
- Fix order: create `person` → `user_person` → update `profile` (`person_id`, `parish_id`) → insert `parish_affiliation` → insert `parish_admin_primary` if the user is the primary parish admin.

## "Person not found" on `/people/view/{id}`

- `/people/view/{id}` is `Clergy\Controllers\People::view()`. It looks up the `parish_people` snapshot by `parish_id` and `profile_id = {id}`.
- "Person not found" means either:
  - The `parish_people` snapshot is stale (no row for that `profile_id` in the current parish).
  - The `profile.person_id` is missing or wrong.
- After any manual change to `person`, `profile`, `user_person`, or `parish_affiliation`, re-sync the snapshot:
  ```bash
  php spark parish-people:sync
  # or for one parish
  php spark parish-people:integrity --repair --parish=<parish_id>
  ```
- `People::view()` must use `profile.person_id` for downstream services (`PersonMembershipDetailsService`, `PersonOnboardingService`, `PersonStatusHistoryService`), not the route `id`. The route `id` is `profile_id`; the canonical person key is `person_id`.

## Sacramental records vs membership details (data integrity)

- `sacramental_record` is the **canonical source of truth** for baptism/confirmation status. `person_membership_details.is_baptized`/`is_confirmed` flags and `profile.baptism_date`/`confirmation_date`/`is_confirmed` are **derived** from it.
- `App\Services\SacramentFlagSyncService` recomputes the derived flags + profile dates from sacrament rows and upserts both stores. It is called by `People::addSacrament()`, `People::updateSacrament()`, and `People::deleteSacrament()` after every sacrament CRUD operation.
- `ParishPeopleRegistryService::syncParish()` consults `sacramental_record` first in the COALESCE chain for `is_baptized`/`is_confirmed`, falling back to `person_membership_details` flags, then to `profile` dates.
- `is_communicant` is **attendance-based** (`member_standing_summary.communion_count >= 3`), NOT derived from sacramental records. A `eucharist` sacrament record does not set `is_communicant`.
- `sacrament_type` comparisons must use `LOWER()` — the dropdown emits lowercase but the column is free-text.
- Run `php spark sacraments:reconcile` (with `--dry-run` / `--parish=<id>`) to re-sync flags + profile dates from records and detect discrepancies.
- The backfill migration `2026_08_25_000000_BackfillSacramentalRecordsFromProfile` creates sacrament rows from existing `profile.baptism_date`/`confirmation_date` where no record exists.
- Discrepancy warning banners are shown on `/people/view/{n}` and `/me` when `SacramentFlagSyncService::discrepanciesForPerson()` returns mismatches.

## Pending registration approvals

- Pending user registrations are surfaced in two places:
  - Dashboard alert banner (for parish admins) linking to `people#pendingRegistrationsCard`.
  - The `pendingRegistrationsCard` table at the bottom of `/people`, rendered by `Clergy\Views\People\index.php`.
- The approval card posts to `POST /members/registrations/approve/{id}` and `POST /members/registrations/dismiss/{id}` (routed to `Diocesan\Controllers\RegistrationRequests`).
- Bulk approval uses `POST /members/registrations/bulk-approve` with `request_ids[]` (handled by `RegistrationRequests::bulkApprove()`). It falls back to each request's `requested_role` when no override is supplied.
- Do **not** use `lang('App.approve')` / `lang('App.dismiss')` (those keys do not exist). Use `lang('Dashboard.approveLabel')`, `lang('Dashboard.dismissLabel')`, `lang('Dashboard.confirmApproveRegistration')`, and `lang('Dashboard.confirmDismissRegistration')`.

## CodeIgniter DataCaster cast type format

- Cast types in models must use the nullable prefix form (`?int`, `?string`), never the suffix form (`int?`, `string?`).
- If you see `InvalidArgumentException: No such handler for "...". Invalid type: ...?`, correct the `protected array $casts` entry.

## Role hierarchy and parish onboarding

- `systemadmin`, `provinceadmin`, and `dioceseadmin` are auto-approved when they complete a parish-join profile; they are not subject to parish-level `pending_review`.
- `BaseController::canBypassPendingReviewGate()` is the single gate for the `pending_review` hold page in `PublicHome` and `Dashboard`.
- A lower-tier admin cannot approve, dismiss, ban, or delete a registration request whose user has a higher-tier admin group; `RegistrationApprovalService` enforces this through `AdminHierarchyGuard`.
- `RegistrationApprovalService` preserves existing higher-tier Shield groups when it assigns a lower role (e.g., a `dioceseadmin` who joins as `member` keeps `dioceseadmin` and gains `member`).
- All approval/dismiss/ban/delete actions should go through `RegistrationApprovalService` rather than duplicating the logic.
- The pending-registration card in `Clergy/Views/People/index.php` shows a top toolbar with individual Approve, Dismiss, Ban, Delete (with `member.delete` permission), and Escalate buttons that act on selected rows and post to `members/registrations/bulk-action`.
- `BaseController::isElevatedAdmin()` is the single source of truth for top-tier admins and is used by `canBypassPendingReviewGate()`, `Profile::completeSubmit`, and `Profile::joinParishSubmit`.
- `BaseController::getCurrentRegistrationRequest()` returns the most recent request (`orderBy id DESC`) so auto-approval always acts on the row the user just submitted.
- `UserRegistrationRequestModel::getPendingByParish()`, `getPendingByDiocese()`, and `countPendingByDiocese()` exclude `systemadmin`, `provinceadmin`, and `dioceseadmin` users so a higher-tier admin request never appears in a parish/diocesan admin's pending queue, even if auto-approval fails.
