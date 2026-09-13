# People, Members, Membership, and Leadership Pathway Report

Date: 2026-08-01

## Executive summary

The codebase currently separates identity, relationship, and governance concerns across several layers:

- `profile` (or `profiles` in the wider schema) is the personal identity record.
- `parish_affiliation` is the parish membership/relationship record.
- `parish_people` / `church_people` is a denormalized snapshot used by list and dashboard views.
- `member_standing_summary` and `leadership_position` are governance and pathway enforcement tables.

This is a strong foundation for a secure, scoped pastoral registry, but the current implementation still mixes:

1. onboarding state (`pending_profile` / `pending_review`),
2. membership state (`visitor`, `regular_attender`, `member`, `inactive`),
3. role state (`leader`), and
4. end-state lifecycle events (`transferred`, `deceased`)

across more than one table.

The most secure and maintainable design is to keep:

- a single canonical `people` identity record,
- a separate parish relationship table for current relationship state,
- a dedicated onboarding/status history table, and
- leadership as a separate role-assignment model, not as a membership status.

## 1. How people and members are managed today

### 1.1 Canonical identity

The current persistent identity model is centered on:

- `users` for login credentials and authentication state
- `profile` for personal data and parish identity
- `auth_identities` for portal email/password identity
- `auth_groups_users` for role assignment

Evidence:

- `app/Models/ProfileModel.php` stores personal identity fields such as `first_name`, `last_name`, `phone`, `birth_date`, `baptism_date`, `confirmation_date`, `membership_status`, `is_dependent`, `portal_access`, etc.
- `Clergy/Controllers/Members.php` loads detailed member records with `ProfileModel::getProfileWithUser()` and then overlays the active parish affiliation status from `parish_affiliation`.
- `app/Models/MemberParishAffiliationModel.php` holds the relationship row that tells the app which parish a user belongs to and what relationship status they carry there.

### 1.2 People vs members split

There are two primary operational surfaces:

#### People

`Clergy/Controllers/People.php` uses `ParishPeopleModel` and a snapshot registry service:

- `People::index()` lists the parish-wide person snapshot.
- `scope` can be `all`, `members`, or `non_members`.
- `status` filters the `parish_people` row set.
- `ParishPeopleRegistryService::syncParish()` rebuilds the snapshot from `parish_affiliation`, `profile`, `users`, `member_standing_summary`, and auth identity data.

This is a read-optimized, UI-facing summary layer.

#### Members

`Clergy/Controllers/Members.php` uses `MemberParishAffiliationModel` and `ProfileModel` to build the member list and detail pages:

- the member list filters by `parish_affiliation.membership_status`
- the detail page overlays the chosen parish affiliation status onto the profile row for the current parish scope
- `member_standing_summary` is joined into the list to show `is_good_standing`

This is the operational membership layer.

### 1.3 Membership status logic in the current code

Current code shows a deliberate status mapping layer:

- `app/Services/MembershipStatusService.php` defines the active status vocabulary used for parish affiliation.
- `profile` status values currently normalize to `pending`, `visitor`, `regular_attender`, `member`, `non_active`.
- affiliation statuses normalize to `visitor`, `regular_attender`, `baptized_member`, `communicant`, `leader`, `inactive`, `transferred`, `deceased`.

This is a clue that the app currently has two status languages:

1. profile-level “person status”
2. parish-affiliation “relationship status”

The mapping is intentional, but it is also the main source of duplication and confusion.

## 2. Current membership pathway

### 2.1 Onboarding and profile completion

The onboarding flow is not stored purely in `profile`. It is gated by `user_registration_request` and by a registration gate filter:

- `app/Config/Events.php` seeds a profile row for a new authenticated user and creates a `user_registration_request` record with status `pending_profile` when needed.
- `app/Filters/RegistrationGateFilter.php` blocks logged-in users whose registration status is `pending_profile` and redirects them to `/profile/complete`.

This means the codebase already treats “pending profile completion” as a separate onboarding state, not as a membership state.

### 2.2 Member status progression

A practical reading of the current codebase is this:

- `pending` = profile completion or onboarding is incomplete
- `visitor` = first-time / non-member pastoral relationship, not yet covenant member
- `regular_attender` = recurrent attendee, not yet covenant member
- `member` = covenant member / baptized / communicant-like state
- `inactive` = lapsed member or non-current parish relationship

The code’s `MembershipStatusService::profileToAffiliation()` maps the profile statuses into parish relationship terms.

### 2.3 Good standing and discipline

The governance layer is not just status labels. It creates a computed standing record:

- `app/Models/MemberStandingSummaryModel.php` calculates `is_good_standing` per year using:
  - `users.active`
  - `profile.is_confirmed`
  - attendance counts
  - communion counts
  - annual pledge presence
  - current discipline status

This is an important design point: leadership and membership should be gated by a calculated standing record, not by a single hard-coded enum alone.

## 3. Current leadership pathway

### 3.1 Leadership is a separate role model

Leadership is modeled via `leadership_position`:

- `app/Models/LeadershipPositionModel.php`
- columns include `user_id`, `scope_type`, `parish_id`, `diocese_id`, `province_id`, `title`, `role_code`, `employment_type`, `role_category`, `active`, `appointed_by_user_id`

This is a strong sign that leadership is an assignment, not a membership status.

### 3.2 Leadership is scoped and auditable

The table supports:

- parish scope
- diocese scope
- province scope
- activation and deactivation
- appointment metadata and audit traceability

This is the right direction.

### 3.3 Good standing gates leadership

`app/Services/MembershipStatusService.php` explicitly gates leadership eligibility in the sense that `canBeGoodStanding()` only returns true for:

- `communicant`
- `leader`

In practice, the real leadership eligibility is derived from `member_standing_summary.is_good_standing` plus the individual's active leadership assignment.

## 4. Strengths in the current design

- Strong separation of auth identity from profile identity.
- Separate parish relationship model instead of storing a single parish status on the main profile row.
- Snapshot table (`parish_people` / `church_people`) for fast listing and UX.
- Computed good-standing snapshot for reporting and governance.
- Explicit onboarding gate for profile completion.
- Leadership stored in a dedicated assignment table rather than embedding leadership into a simple status field.

## 5. Main risks and inconsistencies

### 5.1 Status duplication across tables

The same person can be represented in multiple stateful layers:

- `profile.membership_status`
- `parish_affiliation.membership_status`
- `parish_people.membership_status`
- `member_standing_summary.is_good_standing`

That means the app has several “truth sources.”

### 5.2 Concept drift between business terms

The code uses overlapping concepts such as:

- `member`
- `communicant`
- `baptized_member`
- `leader`
- `inactive`
- `transferred`
- `deceased`

These are not all the same kind of thing. Some are lifecycle states, some are role assignments, and some are sacramental/disciplinary conditions.

### 5.3 `pending` is overloaded

In the current code, `pending` appears in two ways:

1. onboarding profile completion (`pending_profile` on the request table)
2. profile-level membership default state

That is workable, but it creates ambiguity when someone asks whether “pending” means “not yet approved” or “profile incomplete.”

### 5.4 Relationship status and role should not be the same column

A person cannot be safely modeled as “leader” in the same enum as “visitor / regular_attender / member / inactive.” `leader` is a role assignment, not a membership state.

## 6. Recommended secure profile design

### 6.1 Design principle

Use a normalized model with one canonical identity table and separate relationship, lifecycle, and role tables.

### 6.2 Recommended canonical schema

#### A. People identity table

Use a canonical `people` record as the single identity source:

- `id`
- `user_id` (nullable for non-portal people)
- `first_name`
- `last_name`
- `nickname`
- `birth_date`
- `baptism_date`
- `confirmation_date`
- `phone` (encrypted)
- `phone_hash` (indexable)
- `email` (if needed, minimized and encrypted where appropriate)
- `created_at`, `updated_at`
- `deleted_at` (soft delete)

This should hold identity, biography, and person-level demographics. It should not carry the parish relationship status directly.

#### B. Parish relationship table

Use a `person_parish_affiliation` table for the current parish relationship:

- `id`
- `person_id`
- `parish_id`
- `user_id` (nullable for non-portal people)
- `relationship_status`
  - `visitor`
  - `regular_attender`
  - `member`
  - `inactive`
  - `transferred`
  - `deceased`
- `is_primary`
- `start_date`
- `end_date`
- `is_member`
- `is_good_standing`
- `source`
- `created_at`, `updated_at`

This is where the parish relationship and “current parish status” belong.

#### C. Membership detail table

Use a separate `person_membership_details` table for sacramental and covenant attributes:

- `id`
- `person_id`
- `parish_id`
- `is_baptized`
- `is_confirmed`
- `is_communicant`
- `age_band` (derived: child / youth / adult, not trusted input)
- `has_pledge`
- `pledge_status`
- `is_dependent`
- `is_under_16`
- `is_over_16`
- `good_standing_override` (rare, admin-only)

This avoids overloading one status enum with multiple meanings.

#### D. Onboarding status table

Use a separate `person_onboarding` or `user_registration_request` style table with:

- `pending_profile`
- `pending_review`
- `approved`
- `dismissed`
- `banned`

This should not be a membership status. It is a case lifecycle state.

#### E. Leadership assignment table

Use `leadership_assignment` as the role table:

- `id`
- `person_id`
- `parish_id`
- `scope_type`
- `title`
- `role_code`
- `active`
- `started_at`
- `ended_at`
- `appointed_by_user_id`

Leadership must be a role/assignment record, not a membership status.

#### F. Immutable status history table

Use a `person_status_history` table that records changes over time:

- `person_id`
- `parish_id`
- `from_status`
- `to_status`
- `reason`
- `changed_by_user_id`
- `created_at`

This is the safest audit trail for pastoral transitions such as transfer, inactive, or deceased.

## 7. Recommended status model

### 7.1 Recommended membership-state enum

Use only this membership relationship enum:

- `visitor`
- `regular_attender`
- `member`
- `inactive`
- `transferred`
- `deceased`

### 7.2 Recommended onboarding enum

Keep onboarding separate:

- `pending_profile`
- `pending_review`
- `approved`
- `dismissed`
- `banned`

### 7.3 Recommended role attributes

Leadership should be derived from assignments, not status:

- `leader`
- `vestry`
- `clergy`
- `treasurer`
- `volunteer_coordinator`

These should live in the leadership assignment model, not in the relationship enum.

## 8. Recommended handling for age, communicant, and pledge

### 8.1 Age segmentation

The most secure rule is:

- compute age from `birth_date`
- derive `is_under_16` and `is_over_16` at read time, not store them as trusted state

This prevents drift and makes the rule auditable.

### 8.2 Communicant status

Use:

- `is_baptized`
- `is_confirmed`
- `is_communicant`

Derived from sacramental records and attendance data, rather than making one enum do everything.

### 8.3 Pledge status

Keep `has_pledge` or `pledge_status` in a separate record, preferably joined to the annual pledge table.

That ensures a person’s pledge status is auditable and can be measured over time.

## 9. Security recommendations

### 9.1 Protect PII

- Encrypt phone numbers at rest.
- Store only a hash for indexable lookups.
- Do not store full phone numbers in searchable `parish_people` snapshots if avoidable.

### 9.2 Separate auth from identity

- `users` remains for credentials.
- `people` remains for pastoral identity.
- `user_id` should be nullable for pastoral records that are not portal users.

### 9.3 Restrict writes

- `member.edit` and `leadership_position.manage` should gate the mutate endpoints.
- `is_good_standing` and `person_status_history` changes should be admin-reviewed and auditable.

### 9.4 Prefer status history over overwriting

Never overwrite a previous status without writing a history row. This is critical for auditability and pastoral accountability.

## 10. Final recommendation

The safest and cleanest model for this codebase is:

1. `people` = identity-only
2. `person_parish_affiliation` = parish relationship and current membership state
3. `person_membership_details` = baptized / confirmed / communicant / age / pledge data
4. `person_onboarding` = pending profile / approval workflow
5. `leadership_assignment` = leadership roles
6. `person_status_history` = immutable audit trail

In short:

- store identity in `people`
- store church relationship in `person_parish_affiliation`
- store spiritual / sacramental / covenant attributes in `person_membership_details`
- store leadership in `leadership_assignment`
- store transitions in `person_status_history`

That design is the most secure because it:

- keeps one source of truth for identity,
- separates lifecycle states from roles,
- avoids ambiguous enums,
- supports safe age-based logic,
- preserves auditability for transfers, deaths, and disciplinary changes.

## Evidence references

- `Clergy/Controllers/People.php`
- `Clergy/Controllers/Members.php`
- `app/Models/ProfileModel.php`
- `app/Models/MemberParishAffiliationModel.php`
- `app/Models/ParishPeopleModel.php`
- `app/Services/ParishPeopleRegistryService.php`
- `app/Services/MembershipStatusService.php`
- `app/Models/MemberStandingSummaryModel.php`
- `app/Models/LeadershipPositionModel.php`
- `app/Filters/RegistrationGateFilter.php`

## Bottom line

The codebase already has most of the right building blocks, but the current status model is strategically over-centralized and partially duplicated. The cleanest secure path is to move to a canonical `people` identity model with separate parish relationship, membership detail, onboarding, leadership, and status-history tables.
