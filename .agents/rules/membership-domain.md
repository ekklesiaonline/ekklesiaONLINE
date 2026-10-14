---
description: Membership status, person identity, and parish affiliation invariants
trigger: always_on
---

# Membership Domain & Data Integrity

## Membership Status Invariants

- `parish_affiliation.membership_status` is the only stored lifecycle status. `is_member` is a generated column (`status IN ('member','inactive')`); `affiliation_type`, `canonical_status`, and `is_good_standing` no longer exist — never write any of them. Visitors are `membership_status='visitor'` (the `/visitors` directory + follow-up feature); legacy `'guest'` input normalizes to `'visitor'` via `MembershipStatusService`. Event-registration `guest_*` fields are plus-one companions, not lifecycle records — never conflate them.
- `is_member` means "on the parish register": `status IN ('member','inactive')` (`MembershipStatusService::isOnRegister()`). `isMember()` is the stricter "active member" check (`status='member'`). Do not conflate them.
- Off-register removal (detach, member delete, tag revoke) writes `visitor` + `end_date` — never a stale `member` status.
- New affiliations default to `visitor`: registration approval, non-portal person creation (children, unflagged), and `attachToParish()`'s signature default all start at `visitor`. `member` is only ever set by an explicit operator choice (member checkbox) or a `MembershipPathwayService` promotion.
- Good standing lives on `member_standing_summary.is_good_standing` (keyed by `user_id` + `year`), not on the affiliation row.
- Clergy/staff are expressed through `leadership_position`, not lifecycle statuses.
- Leadership eligibility is enforced by `LeadershipPositionService::eligibilityFor()` on every create and on reactivation (active 0→1) — never gate on `users.id` existence alone, because non-portal people still get a `users` row. Portal access is required for ALL positions including clergy: `users.active=1` + `auth_identities(type='email_password')` + `profile.portal_access='enabled'` (an `invited` profile is not enough). Lay positions additionally require a recorded baptism plus the standing block (confirmation, current-year pledge, communicant-in-good-standing or `good_standing_override`); clergy are exempt from the standing block only. `good_standing_override` never bypasses portal access or baptism.
- `revokePortalAccess()` refuses to strip portal access while the user holds active leadership positions — end the positions first.
- Audit existing violations with `php spark leadership:audit-eligibility` (report-only; remediation is manual).
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

## Canonical Person Diagnostics

- Use `php spark person:diagnose` and `php spark people:audit-affiliation-drift` to verify canonical identity integrity across `person`, `profile`, `user_person`, and `parish_affiliation`.

## Sunday Attendance Graphics

- See the `/sunday-attendance-charts` skill for chart-building guidance.
