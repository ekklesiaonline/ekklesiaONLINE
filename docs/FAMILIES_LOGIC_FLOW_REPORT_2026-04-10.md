# Families Logic, Data Model, and CRUD Flow Report (2026-04-10)

## Scope

This report documents the families feature in ekklesiaRD, including data model, request flow, CRUD behavior, parish scoping, security controls, and implementation files.

## Architecture Summary

The families feature is built as a parish-scoped household module with two core tables.

- families stores family metadata for a parish.
- family_members stores user-to-family membership links.

The lifecycle is intentionally split.

1. Family metadata CRUD: create, read, update, delete family records.
2. Membership CRUD: add/remove users from a family.

## Live Schema Snapshot

Validated on the live database during this review.

- families rows: 4
- family_members rows: 10
- users linked to multiple families: 0

### families table

Purpose: canonical family record.

Fields:

- id
- parish_id
- family_name
- head_of_household_id
- address
- created_at
- updated_at

Foreign keys and behavior:

- parish_id -> parishes.id (ON DELETE CASCADE, ON UPDATE CASCADE)
- head_of_household_id -> users.id (ON DELETE CASCADE, ON UPDATE SET NULL in current live DDL)

### family_members table

Purpose: relationship links between users and families.

Fields:

- id
- family_id
- user_id
- relationship
- created_at

Foreign keys and behavior:

- family_id -> families.id (ON DELETE CASCADE, ON UPDATE CASCADE)
- user_id -> users.id (ON DELETE CASCADE, ON UPDATE CASCADE)

Indexes:

- family_id_user_id index exists.
- There is no unique key on (family_id, user_id).
- There is no unique key enforcing one-family-per-user.

## Business Flow

## A) List families

Route:

- GET /families

Flow:

1. Permission guard requires families.view.
2. Parish scope guard requires operational parish context.
3. FamilyModel returns parish-scoped families with member counts.
4. Controller renders families index view.

## B) Create family

Routes:

- GET /families/create
- POST /families/store

GET flow:

1. Permission guard requires families.manage.
2. Parish scope guard enforced.
3. MemberParishAffiliationModel loads parish members for head-of-household select.
4. Controller renders create form.

POST flow:

1. Permission guard requires families.manage.
2. Parish scope guard enforced.
3. Validation requires family_name with min length 3.
4. Insert payload is built using operational parish_id and posted form fields.
5. On success: audit log family.create and redirect success.
6. On failure: redirect back with errors/input.

Important behavior:

- Creating a family does not auto-create family_members rows.
- Head of household is optional.

## C) View family details

Route:

- GET /families/view/{id}

Flow:

1. Permission guard requires families.view.
2. Parish scope guard enforced.
3. FamilyModel aggregate fetches family, head name, and member list.
4. Controller verifies family.parish_id matches operational parish.
5. Controller renders detail page.

## D) Update family

Routes:

- GET /families/edit/{id}
- POST /families/update/{id}

GET flow:

1. Permission guard requires families.manage.
2. Parish scope guard enforced.
3. Controller verifies family exists and is in parish scope.
4. Member list is loaded for head-of-household selection.
5. Edit view renders form.

POST flow:

1. Permission and scope checks.
2. Validate family ownership in parish scope.
3. Validate family_name required + min length 3.
4. Update family_name, head_of_household_id, address.
5. On success: audit log family.update with old/new values and redirect.
6. On failure: redirect with errors/input.

## E) Delete family

Route:

- POST /families/delete/{id}

Flow:

1. Permission guard requires families.manage.
2. Parish scope guard enforced.
3. Controller verifies family exists in current parish scope.
4. Delete family row.
5. Return JSON success/error payload.
6. On success: audit log family.delete.

Cascade effect:

- family_members rows for that family are removed by FK cascade.

## F) Add family member

Route:

- POST /families/addMember

Flow:

1. Permission guard requires families.manage.
2. Parish scope guard enforced.
3. Controller reads family_id, user_id, relationship.
4. Controller verifies family in current parish scope.
5. Controller verifies user affiliation with same parish using MemberParishAffiliationModel::isUserAffiliatedWithParish(..., activeOnly=true).
6. Insert row into family_members.
7. Return JSON success/error.
8. On success: audit log family_member.add.

## G) Remove family member

Route:

- POST /families/removeMember/{familyMemberId}

Flow:

1. Permission guard requires families.manage.
2. Parish scope guard enforced.
3. Controller loads relation row from family_members.
4. Controller loads parent family and validates parish scope.
5. Delete relation row.
6. Return JSON success/error.
7. On success: audit log family_member.remove.

## UI Flow and AJAX Contract

Families pages are server-rendered views.

- index.php supports delete via fetch POST and JSON contract handling.
- view.php supports add/remove member via fetch POST and JSON contract handling.
- create.php and edit.php use standard form POST + CSRF.

Error payloads are normalized by BaseController JSON helpers and include contract-style fields used by front-end handlers.

## Security, Permissions, and Rate Limits

Permissions used:

- families.view
- families.manage

Security and boundary controls:

- Route-level permission filters in MemberPortal route group.
- Controller-level requirePermission and requireParish checks.
- In-scope ownership checks by comparing family.parish_id with operational parish context.
- addMember checks member-parish affiliation before insert.
- CSRF tokens/headers are included for form and AJAX writes.
- API rate limit includes families write endpoints.

## Known Characteristics and Gaps

1. Duplicate relation possibility.

The database has an index on (family_id, user_id), but not unique. Duplicate membership rows are technically possible.

1. Multi-family assignment possibility.

No DB or controller constraint prevents the same user from joining multiple families.

1. Head-of-household consistency.

There is no enforced rule that head_of_household_id must also exist in family_members for that family.

1. No transaction wrapping in add/remove member.

Controller performs check-then-write operations without explicit transaction boundaries.

## Files Associated with Families Functionality

### Core module

- MemberPortal/Controllers/Families.php
- MemberPortal/Config/Routes.php
- MemberPortal/Views/Families/index.php
- MemberPortal/Views/Families/create.php
- MemberPortal/Views/Families/edit.php
- MemberPortal/Views/Families/view.php

### Data model and schema

- app/Database/Migrations/2025_01_01_000003_CreateFamilies.php
- app/Database/Migrations/2025_01_01_000004_CreateFamilyMembers.php
- app/Models/FamilyModel.php
- app/Models/FamilyMemberModel.php

### Supporting models used by family flows

- app/Models/MemberParishAffiliationModel.php
- app/Models/ProfileModel.php

### Cross-cutting framework pieces

- app/Controllers/BaseController.php
- app/Config/AuthGroups.php
- app/Config/Filters.php
- app/Filters/ApiRateLimitFilter.php
- app/Views/partials/sidebar.php

### Language resources

- app/Language/en/Families.php
- app/Language/es/Families.php
- app/Language/fr/Families.php

### Tests and operations

- tests/unit/CriticalWorkflowContractCoverageTest.php
- tests/unit/ModuleRoutesSmokeTest.php
- tests/unit/SidebarRoleVisibilityTest.php
- build/e2e_members_families_followup_smoke.sh

### Seed support touching family tables

- app/Database/Seeds/ModuleDemoDataSeeder.php

## End-to-End Create Family Flow (Condensed)

1. User opens /families/create.
2. Controller loads parish-scoped member options for optional head selection.
3. User submits family_name, optional head_of_household_id, optional address.
4. Controller validates and inserts into families.
5. Audit event family.create is recorded.
6. User is redirected to families list.
7. Family membership is managed later on detail page via addMember/removeMember endpoints.

## Conclusion

The families module is well-structured and consistently parish-scoped, with clear separation between family metadata and membership links. Permission guards, scope checks, CSRF handling, and JSON error contract usage are present. The main hardening opportunities are uniqueness and consistency constraints for family membership and head-of-household linkage.
