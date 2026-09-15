---
description: Church hierarchy and data integrity rules for ekklesiaRD
trigger: always_on
---

# Church Domain & Data Integrity

## Hierarchy & Scopes

- Dashboard routing hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is canonical for landing-path resolution.
- `BaseController::canAccessMyDashboard()` gates `/me`. Parish roles always qualify; elevated admins qualify only with a `parish_affiliation` row.
- Personal dashboard is served by `MemberPortal\Controllers\MyDashboard` + `App\Services\MemberDashboardDataService`.

## People Counts

- Parish-level people counts must be based on `parish_affiliation.person_id` (the canonical `person` row), not `parish_affiliation.linked_user_id` and not `profile`.
- `countDistinctMembers()` and `countDistinctPeople()` must `JOIN person` and `COUNT(DISTINCT parish_affiliation.person_id)`.
- `total_guests` = legacy guest table + `parish_affiliation` rows where `affiliation_type = 'guest'`.

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

## Registration Approvals

- Use `RegistrationApprovalService` for all approve/dismiss/ban/delete actions.
- Lower-tier admins cannot act on requests from higher-tier admin groups; `AdminHierarchyGuard` enforces this.
- Higher-tier groups (`systemadmin`, `provinceadmin`, `dioceseadmin`) are auto-approved and bypass parish `pending_review`.
- Use `lang('Dashboard.approveLabel')`, `lang('Dashboard.dismissLabel')`, `lang('Dashboard.confirmApproveRegistration')`, `lang('Dashboard.confirmDismissRegistration')`. Do not use `lang('App.approve')` / `lang('App.dismiss')`.

## Parish People Snapshot

- After manual changes to `person`, `profile`, `user_person`, or `parish_affiliation`, re-sync:

  ```bash
  php spark parish-people:sync
  # or for one parish:
  php spark parish-people:integrity --repair --parish=<parish_id>
  ```

## Sunday Attendance Graphics

- Parish admin Sunday-attendance charts must use raw `service_metrics` values joined to `services`.
- Do not bucket/average or apply derived ASA/ASC calculations.
- Preserve separate service points when multiple Sunday services share a date.
