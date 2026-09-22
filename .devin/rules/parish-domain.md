---
description: Parish hierarchy and data integrity rules for ekklesiaRD
trigger: always_on
---

# Parish Domain & Data Integrity

## Hierarchy & Scopes

- Dashboard routing hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is canonical for landing-path resolution.
- `BaseController::canAccessMyDashboard()` gates `/me`. Parish roles always qualify; elevated admins qualify only with a `parish_affiliation` row.
- Personal dashboard is served by `MemberPortal\Controllers\MyDashboard` + `App\Services\MemberDashboardDataService`.

## Membership Status Invariants

- `parish_affiliation.membership_status` is the only stored lifecycle status. `is_member` is a generated column (`status IN ('member','inactive')`); `affiliation_type`, `canonical_status`, and `is_good_standing` no longer exist — never write any of them. Visitors are `membership_status='visitor'` (the `/visitors` directory + follow-up feature); legacy `'guest'` input normalizes to `'visitor'` via `MembershipStatusService`. Event-registration `guest_*` fields are plus-one companions, not lifecycle records — never conflate them.
- `is_member` means "on the parish register": `status IN ('member','inactive')` (`MembershipStatusService::isOnRegister()`). `isMember()` is the stricter "active member" check (`status='member'`). Do not conflate them.
- Off-register removal (detach, member delete, tag revoke) writes `visitor` + `end_date` — never a stale `member` status.
- New affiliations default to `visitor`: registration approval, non-portal person creation (children, unflagged), and `attachToParish()`'s signature default all start at `visitor`. `member` is only ever set by an explicit operator choice (member checkbox) or a `MembershipPathwayService` promotion.
- Good standing lives on `member_standing_summary.is_good_standing` (keyed by `user_id` + `year`), not on the affiliation row.
- Clergy/staff are expressed through `leadership_position`, not lifecycle statuses.
- Audit existing data with `php spark people:audit-affiliation-drift`.
- Full plan: `docs/MEMBERSHIP_STATUS_SIMPLIFICATION_PLAN.md`.

## People Counts

- Parish-level people counts must be based on `parish_affiliation.person_id` (the canonical `person` row), not `parish_affiliation.linked_user_id` and not `profile`.
- `countDistinctMembers()` and `countDistinctPeople()` must `JOIN person` and `COUNT(DISTINCT parish_affiliation.person_id)`.
- `total_visitors` = `parish_affiliation` rows where `membership_status IN ('visitor','regular_attender')` (the legacy `guest` table was dropped).
- Visitor management is `/visitors/*` (`Clergy\Controllers\Visitors`); the visitor→member promotion is `POST /visitors/promote/{personId}` → `PersonConversionService::convertToMember()`. See `docs/VISITOR_LIFECYCLE.md`.

## Person Identity

- The canonical person key is `person.id`.
- `profile.person_id` links a profile to the canonical person.
- `/people/view/{id}` route `id` is `profile_id`; downstream services must use `profile.person_id`.

## Sacramental Records

- `sacramental_record` is the canonical source of truth for baptism/confirmation.
- `person_membership_details.is_baptized` / `is_confirmed` and `profile.baptism_date` / `confirmation_date` / `is_confirmed` are derived.
- After every sacrament CRUD operation, call `SacramentFlagSyncService`.
- Sacrament type comparisons must use `LOWER()` because the dropdown emits lowercase but the column is free-text.
- `is_communicant` is attendance-based (`member_standing_summary.communion_count >= 3`), not derived from sacramental records.

## Elevated Admin Scope Restrictions

- Province admins have **read-only** access to parish data and are **blocked** from staff management (`staff`, `addStaff`, `promoteMember`, `removeStaffRole`, `demoteStaff`).
- Tax ID editability:
  - System Admin: all parishes.
  - Diocesan Admin: parishes within their diocese.
  - Parish Admin: their own parish only.
- Error key for province admin staff block: `Diocesan.provinceAdminStaffManagementDenied`.

## Registration Approvals

- Use `RegistrationApprovalService` for all approve/dismiss/ban/delete actions.
- Lower-tier admins cannot act on requests from higher-tier admin groups; `AdminHierarchyGuard` enforces this.
- Higher-tier groups (`systemadmin`, `provinceadmin`, `dioceseadmin`) are auto-approved and bypass parish `pending_review`.
- Use `lang('Dashboard.approveLabel')`, `lang('Dashboard.dismissLabel')`, `lang('Dashboard.confirmApproveRegistration')`, `lang('Dashboard.confirmDismissRegistration')`. Do not use `lang('App.approve')` / `lang('App.dismiss')`.

## Parish People Snapshot

- See the `/parish-people-snapshot` skill for re-sync commands after manual changes to `person`, `profile`, `user_person`, or `parish_affiliation`.

## Sunday Attendance Graphics

- See the `/sunday-attendance-charts` skill for chart-building guidance.

## Bootstrapping & Permissions

- Fresh environment seeding: `php spark db:seed DatabaseSeeder`.
- Sync role-to-menu permissions: `php spark db:seed PermissionRoleSyncSeeder`.
- Canonical policy table: `docs/ROLE_MENU_PERMISSION_POLICY.md`.
