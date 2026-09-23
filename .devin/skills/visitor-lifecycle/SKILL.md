---
name: visitor-lifecycle
description: Explain the visitor → regular_attender → member lifecycle, intake paths, directory scope, and the promote-to-member flow
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Explain or work with the visitor lifecycle in ekklesiaRD. Canonical doc: `docs/VISITOR_LIFECYCLE.md`.

## Canonical model

- `parish_affiliation.membership_status` is the only lifecycle state:
  `visitor → regular_attender → member`, exits `inactive`/`transferred`/`deceased`.
- Every new affiliation defaults to `visitor`. `member` requires an explicit
  operator choice or `MembershipPathwayService` — never write statuses directly.
- `is_member` (generated column) = on-register = `member`/`inactive`.
- Good standing (`member_standing_summary`), pathway stage, and leadership
  (`leadership_position`) are orthogonal.
- Legacy aliases normalize via `MembershipStatusService`: `'guest'` → `visitor`,
  `'active'` → `member`. Never store the alias.

## Intake (all → `visitor`)

`VisitorIntakeService::record()` dedupes by `email_hash`/`phone_hash` in the
parish, provisions `person` + `parish_affiliation(visitor)` on miss, and always
inserts `person_visit` (`first`/`return`). Entry points: staff form
`/visitors/store`, CSV `/visitors/import/process`, public card
`/connect/{token}`, registration approval, non-portal creation.

## Directory scope

`VisitorDirectoryService::scopeBuilder()` = `membership_status IN
('visitor','regular_attender') OR converted_to_member_at IS NOT NULL`.
`?lifecycle=converted` is a filter value; `&converted_window=N` bounds it to
conversions in the last N days. `visit_count` is selected per row.
The hub "converted" tile uses the same window from the system setting
`visitor.converted_window_days` (default 30, `/system/settings`).

## Managed steps

- `POST /visitors/promote-attender/{personId}` (`visitor.edit`) →
  `transitionForPerson(…, 'regular_attender')`.
- `POST /visitors/demote/{personId}` → back to `visitor`.
- Suggest-only: `visit_count >= visitor.attender_suggest_threshold`
  (ParishConfigService, default 2) shows a badge — never auto-transitions.
- `POST /visitors/promote/{personId}` → `PersonConversionService::convertToMember()`
  modes: mark member / link in-parish user / create account (needs
  `member.create`). One transaction: pathway transition + link/create +
  pending follow-ups → `skipped` + conversion markers (`followed_up=1`,
  `converted_to_member_at`, `converted_by_user_id`). Redirects to
  `/people/view/{profile_id}`.
- `POST /visitors/revert-conversion/{personId}` → `revertConversion()`.
- `VisitorDirectoryService::delete()` refuses converted rows and
  `member`/`inactive` statuses — manage those from People.

## Gotchas

- `/people/view/{id}` takes a **profile** id; `/visitors/view/{id}` takes a
  **person** id. Convert person→profile via `profile.person_id`.
- `visitorAffiliationExists($personId, $parishId)` scopes follow-up mutations
  to visitor-domain rows only.
- Event `guest_name`/`guest_email`/`guest_phone` are plus-one companions on a
  registration — not visitor lifecycle records; never conflate.
- `/guests/*` legacy GETs redirect to `/visitors/*`; POSTs alias.
