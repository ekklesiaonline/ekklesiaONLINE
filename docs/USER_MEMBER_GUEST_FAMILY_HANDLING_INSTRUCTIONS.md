# User, Member, Guest, Child, Family, and Access Handling Instructions

## Purpose

This guide defines the canonical handling rules for people records in ekklesiaRD, including:

- Users (authentication identity in Shield)
- Members (parish-affiliated people)
- Guests (first-time or non-member visitors)
- Children (member subtype, usually family-linked)
- Families (household grouping)
- Non-access / non-portal members (no login access)

Use this as an operational and implementation reference for controllers, services, UI flows, seeders, and tests.

## Core Data Model Responsibilities

### 1. users (Shield account)

- Represents authentication identity owner.
- Do not treat `users` as the full profile.
- Keep credentials/auth state here and in Shield auth tables.

### 2. profile (extended person record)

- Source of truth for personal/member metadata.
- Holds parish scope linkage (`parish_id`) and membership status.
- Includes portal access flags (`portal_access`, `portal_enabled_at`) when present.

### 3. member_parish_affiliations (membership scope)

- Source of truth for parish affiliation state and active/home linkage.
- Required for scope-aware membership queries.

### 4. families + family_members

- `families`: household metadata.
- `family_members`: user-to-family relation rows.
- Family membership management must be parish-scoped.

### 5. guests

- Visitor records are separate from member accounts.
- Do not force guest into `users` unless a conversion workflow is explicit.

## Person Type Definitions

### User

A system account that can authenticate. A user may be:

- Full portal member
- Non-portal member record with no login enabled yet
- Admin/staff account

### Member

A parish-affiliated person with profile + affiliation and normally family linkage where applicable.

### Guest

A non-member visitor tracked in guest/follow-up workflows.

### Child

A member subtype represented as a normal user/profile record with family relation and age/category semantics.

### Non-access / Non-portal member

A valid member in parish records who does not have active login credentials.

## Canonical Workflows

## A. Create Family

1. Validate parish scope and `families.manage` permission.
2. Insert into `families` using operational `parish_id`.
3. Do not auto-create `family_members` rows.
4. Audit event: `family.create`.

## B. Add Existing Member to Family

1. Validate family belongs to operational parish.
2. Validate user has active affiliation in same parish.
3. Insert relation row in `family_members`.
4. Audit event: `family_member.add`.

## C. Create Non-portal Member (No Login Access)

1. Validate family and parish scope.
2. Insert `users` row (no auth identity).
3. Insert `profile` row with member metadata.
4. Set `portal_access = 'none'` (or equivalent canonical value).
5. Insert active `member_parish_affiliations` row.
6. Insert `family_members` relation.
7. Audit event for member creation and family linking.

Result: person is a valid member in records, not able to log in.

## D. Enable Portal Access for Existing Non-portal Member

1. Validate family scope and member linkage.
2. Validate active parish affiliation.
3. Ensure no existing `email_password` identity for that user.
4. Ensure email uniqueness among identity records.
5. Insert Shield identity (`auth_identities`).
6. Ensure member group assignment (`auth_groups_users`).
7. Update profile access fields (`portal_access = 'enabled'`, `portal_enabled_at`).
8. Audit event: portal enablement.

## E. Guest Handling

1. Create guest in `guests` with visit context.
2. Track follow-up in `follow_up_tasks`.
3. Do not auto-convert to member/user unless explicitly actioned.
4. If converted:
   - create user/profile/affiliation
   - optionally associate to family
   - mark guest lifecycle state (followed up/converted) in guest workflows.

## F. Child Handling

- Child records are first-class member records (user/profile/affiliation/family_member).
- Prefer creating children through family-aware flow.
- Portal access is optional and often disabled by default.
- Maintain relationship semantics in `family_members.relationship`.

## Access and Permission Rules

- Always enforce permission + parish scope at route and controller/service level.
- Super-admin/system scopes may bypass parish restriction only where explicitly allowed.
- Families and member write operations must require `families.manage` (or stronger role permission).
- Read routes use `families.view` minimum.

## Status and Access Conventions

Use these consistently:

- Membership lifecycle: `active`, `inactive`, `visitor`, `pending`.
- Portal access lifecycle:
  - `none`: no login enabled
  - `enabled`: login enabled
- Affiliation active state must align with effective member visibility in parish-scoped lists.

## Data Integrity Rules (Required)

- Do not create auth identity for non-portal members.
- Do not enable portal without validated scope + family linkage + active affiliation.
- Do not allow cross-parish family/member operations.
- Do not write plaintext passwords to logs.
- Wrap multi-step create/enable workflows in DB transactions.

## Recommended Hardening Rules (Backlog if not fully enforced)

- Add unique key for (`family_id`, `user_id`) in `family_members`.
- Enforce one-home-family policy if required by business rules.
- Validate `head_of_household_id` is also a `family_members` relation when policy requires strict consistency.

## API/JSON Contract Guidance

For AJAX/JSON endpoints handling people/families:

- Return deterministic envelopes:
  - `success`, `code`, `message`, `data`, `errors`, `meta`
- Include request correlation in `meta.request_id`.
- Use stable error taxonomy codes (validation, scope, not found, conflict, internal error).

## Testing Guidance

For database integration tests involving users/members/families:

- Seed minimal required auth/profile/affiliation/family tables.
- Make fixture builders resilient to SQLite schema drift (prefixed/unprefixed table names, missing columns).
- Prefer `ensureColumnExists` defensive schema normalization in test bootstrap where mixed suite order can vary schema shape.

## Quick Decision Matrix

- Need login now?
  - Yes: create/enable portal identity flow.
  - No: create non-portal member flow.
- Visitor with no membership yet?
  - Use guest flow.
- Household relationship needed?
  - Use family + family_members flow.
- Cross-parish request?
  - Deny unless super-admin permitted path.

## Ownership

- Families module owns family + household flows.
- Profile and affiliation models own person scope and membership state.
- Shield tables/services own authentication identity and group access.

Update this document whenever lifecycle statuses, schema contracts, or access policies change.
