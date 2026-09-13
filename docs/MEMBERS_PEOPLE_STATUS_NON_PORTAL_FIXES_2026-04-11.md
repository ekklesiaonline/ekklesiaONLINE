# Members/People Consistency Fixes (2026-04-11)

## Scope

This document records two production fixes applied on 2026-04-11:

1. Membership status consistency across:

- `/members/edit/{id}`
- `/members/view/{id}`
- `/members`
- `/people`

1. Non-Portal tag correctness for users who already have portal access.

---

## Fix 1: Membership Status Source-of-Truth Consistency

### Fix 1 Symptom

A user could show as `visitor` in the edit form but as `active/member` in members and people lists.

### Fix 1 Root Cause

Different pages were reading status from different sources:

- Some views used `profile.membership_status`
- Others used `member_church_affiliations.membership_status`
- People list depended on `church_people` snapshot, which updates on sync

This allowed status drift between profile-level data and church affiliation data.

### Fix 1 Resolution

Standardized behavior around church affiliation status for the operational church.

#### Controller Changes

Updated [Pastor/Controllers/Members.php](Pastor/Controllers/Members.php):

- `edit($id)` now resolves default form status from the active affiliation in operational church.
- `update($id)` now also updates active affiliation status in operational church.
- `update($id)` aligns `is_member` with status.
- `active` and `inactive` map to `is_member = 1`.
- `visitor` and `pending` map to `is_member = 0`.
- `update($id)` triggers immediate `ChurchPeopleRegistryService::syncChurch($churchId)` so listing snapshots are refreshed without waiting for cron.

#### Detail Page Alignment

Updated [Pastor/Views/Members/view.php](Pastor/Views/Members/view.php):

- Main status badge now displays operational-church affiliation status (through controller normalization).
- Status labels localized through `App.status*` keys instead of hardcoded `ucfirst(...)`.

### Fix 1 Outcome

Status now remains consistent across edit form, detail page, members list, and people list.

---

## Fix 2: Non-Portal Tag False Positives

### Fix 2 Symptom

Some portal-enabled users were still tagged as `Non-Portal` in `/members` and `/people`.

### Fix 2 Root Cause

`portal_access` profile flag could be stale (`none`) even when user already had a login identity.
UI logic relied too strictly on that flag.

### Fix 2 Resolution

Implemented effective portal-access determination using both profile flag and identity presence.

#### Sync-Layer Normalization

Updated [app/Services/ChurchPeopleRegistryService.php](app/Services/ChurchPeopleRegistryService.php):

- Normalizes `portal_access` to allowed values: `none`, `invited`, `enabled`.
- If a valid auth identity or email is present, forces effective `portal_access` to `enabled` when stale flag says `none`.

#### View-Layer Defensive Logic

Updated:

- [Pastor/Views/Members/index.php](Pastor/Views/Members/index.php)
- [Pastor/Views/People/index.php](Pastor/Views/People/index.php)

Non-Portal badge now appears only when:

- user has no identity or email
- effective portal access is `none` (or missing fallback equivalent)

If identity or email exists, Non-Portal badge is suppressed even if stale profile flag says `none`.

### Fix 2 Outcome

False Non-Portal labels are removed for portal-enabled users.

---

## Related i18n and UI Follow-Ups Included in Same Patch Window

- Localized status labels in `/people` and `/members` using `App.status*` language keys.
- Applied dark-mode-safe status badge classes (`text-bg-*`) for better contrast.

---

## Operational Validation Performed

- Syntax checks:

- [Pastor/Controllers/Members.php](Pastor/Controllers/Members.php)
- [Pastor/Views/Members/view.php](Pastor/Views/Members/view.php)
- [Pastor/Views/Members/index.php](Pastor/Views/Members/index.php)
- [Pastor/Views/People/index.php](Pastor/Views/People/index.php)
- [app/Services/ChurchPeopleRegistryService.php](app/Services/ChurchPeopleRegistryService.php)

- Immediate snapshot refresh executed:

- `php spark church-people:sync`

- Focused tests previously run and green:

- `PeopleFilterIntegrationTest`
- `MemberTagToggleIntegrationTest`

---

## Notes for Future Changes

- Treat `member_church_affiliations` as membership status authority for church-scoped views.
- Use `church_people` only as derived snapshot and ensure post-write sync when immediate consistency is required.
- For Non-Portal classification, do not rely solely on `profile.portal_access`; include identity presence as tie-breaker.
