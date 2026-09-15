# People and Members Refactor (2026-04-11)

## Summary

This refactor separates the domain of **People** from **Members**:

- **People** = every parish-affiliated user profile (portal and non-portal).
- **Members** = people explicitly tagged as parish members (`is_member = 1`).
- Attendance registration is restricted to active parish members only.

## Data Model Changes

### 1) Member Tag at Affiliation Level

Migration: `app/Database/Migrations/2026_04_11_000003_AddIsMemberToMemberParishAffiliations.php`

Added to `member_parish_affiliations`:

- `is_member TINYINT(1) NOT NULL DEFAULT 1`

Backfill:

- rows with `membership_status IN ('visitor','pending')` are set to `is_member = 0`

Index:

- `idx_mca_parish_member_active (parish_id, is_member, active)`

### 2) Parish People Registry Table

Migration: `app/Database/Migrations/2026_04_11_000004_CreateParishPeople.php`

Created table: `parish_people`

- parish-scoped registry snapshot for users/profile/affiliation status and portal/member badges
- unique key on `(parish_id, user_id)`

Primary fields:

- identity: `parish_id`, `user_id`, `profile_id`
- display/profile: `first_name`, `last_name`, `email`, `phone`
- classification: `portal_access`, `membership_status`, `is_member`, `is_good_standing`
- sync metadata: `source`, `synced_at`, `created_at`, `updated_at`

## Backend / Domain Logic

### 1) Affiliation Model Enhanced

File: `app/Models/MemberParishAffiliationModel.php`

Added capabilities:

- stores and validates `is_member`
- member-only filtering by default in member-focused queries
- explicit methods:

  - `setMemberTag(int $userId, int $parishId, bool $isMember)`
  - `isRecordableMember(int $userId, int $parishId)`

### 2) Non-Portal Provisioning Classification

File: `app/Services/NonPortalMemberProvisioningService.php`

New non-portal creates now attach as:

- `membership_status = 'active'`
- `is_member = 0`

Result:

- they are visible in People by default
- they do not become Members unless explicitly tagged

### 3) People Registry Sync Service

File: `app/Services/ParishPeopleRegistryService.php`

Purpose:

- refresh `parish_people` from shield users + profile + affiliations + standing summary.
- used by People MVC to keep view state current.

## New MVC: People

### Controller

File: `Pastor/Controllers/People.php`

Implemented:

- `index()` list/filter/paginate all people from `parish_people`
- `toggleMemberTag($userId)` marks or removes member tag on active affiliation

### View

File: `Pastor/Views/People/index.php`

Features:

- scope filter: all / members / non-members
- status filter and pagination
- shows tags:

  - Member vs Person
  - Good Standing
  - Non-Portal (only meaningful for member record display)

- action to mark/unmark member tag

### Routing

File: `Pastor/Config/Routes.php`

Added:

- `GET /people` -> People list
- `POST /people/toggle-member-tag/{userId}` -> toggle member tag

### Navigation

File: `app/Views/partials/sidebar.php`

Added nav item:

- **People** (separate from Members)

Language keys added in:

- `app/Language/en/App.php`
- `app/Language/es/App.php`
- `app/Language/fr/App.php`
- `app/Language/en/People.php`
- `app/Language/es/People.php`
- `app/Language/fr/People.php`

## Members UI Updates

File: `Pastor/Views/Members/index.php`

Added classification badges per member row:

- Good Standing badge
- Non-Portal badge (hidden for portal-enabled users)

## Attendance Enforcement

### Worship Module

File: `Worship/Controllers/Attendance.php`

Changed check to require:

- active affiliation
- `is_member = 1`
- `membership_status = 'active'`

### Global Attendance Controller

File: `app/Controllers/AttendanceController.php`

Search and recordability now enforce member-tag rules:

- search list includes only active tagged members
- record validation uses `MemberParishAffiliationModel::isRecordableMember()`

## Metrics Alignment

File: `app/Libraries/PeopleMetricsService.php`

Adjusted semantics:

- `total_people` now reflects parish-affiliated users only (portal + non-portal users)
- members counts remain member-tag driven
- good standing counts restricted to tagged active members

## Rollout Steps

1. Run migrations:

- `php spark migrate --all`

1. Run on-demand sync when needed:

- `php spark parish-people:sync`
- `php spark parish-people:sync --parish 9`

1. Configure background schedule (cron):

- use `build/cron_sync_parish_people.sh` from your scheduler (recommended every 5-10 minutes).

1. Validate flows:

- non-portal create appears in People
- mark as member from People
- marked active member appears/selectable for attendance

## Notes

- Existing `members` permissions are reused for the People module.
- Province-admin staff flow is unchanged.
- This implementation keeps guests (`guests` table) separate from user-based People records.
