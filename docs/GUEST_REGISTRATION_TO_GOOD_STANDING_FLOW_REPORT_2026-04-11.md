# Guest Registration to Good Standing Flow Report (2026-04-11)

## Objective

Document the end-to-end operational flow from guest intake through guest conversion and into church member good-standing status, including:

- Controller and route entry points
- Table-level writes and reads per phase
- Status transitions
- Good-standing computation inputs and outputs
- Verification queries for production/staging audits

## Scope and Assumptions

- Scope is church-level operational flow (pastor/church-admin/member-portal interfaces).
- Good standing source of truth is member_standing_summary.
- Church scoping for standing is enforced through member_church_affiliations joins (member_standing_summary is user/year scoped, not church scoped).
- Two conversion branches are supported:
  - Branch A: link guest to an existing user/member account.
  - Branch B: move guest into member account creation flow (prefilled handoff).

## Entry Points (Routes and Controllers)

### Guest Intake and Follow-up

- GET /guests, GET /guests/create, POST /guests/store, GET /guests/view/{id}, POST /guests/update/{id}
  - Pastor/Controllers/Guests::index/create/store/view/update
- POST /followup/store, POST /followup/update-status/{id}
  - Pastor/Controllers/FollowUp::store/updateStatus

### Guest Conversion

- GET /guests/convertToMember/{id}
  - Pastor/Controllers/Guests::convertToMember
  - Marks follow-up and hands off to member create form.
- GET /guest-conversions, POST /guest-conversions/convert/{guestId}, POST /guest-conversions/revert/{guestId}
  - MemberPortal/Controllers/GuestConversions::index/convert/revert

### Member Good Standing

- POST /standing/refresh
  - MemberPortal/Controllers/Standing::refresh
  - App/Commands/RefreshGoodStanding (CLI equivalent: standing:refresh)
- GET /standing, GET /standing/api/summary
  - MemberPortal/Controllers/Standing::index/summaryApi
- Dashboard KPI read path
  - App/Libraries/PeopleMetricsService::getChurchSummary

## End-to-End Flow

```mermaid
sequenceDiagram
    autonumber
    actor Staff as Pastor/Church Admin
    participant G as GuestsController
    participant GC as GuestConversionsController
    participant M as MembersController
    participant DB as Database
    participant S as StandingSummaryModel
    participant KPI as PeopleMetricsService

    Staff->>G: POST /guests/store
    G->>DB: INSERT guests
    G->>DB: INSERT follow_up_tasks (initial contact)

    Staff->>G: POST /followup/update-status/{task}
    G->>DB: UPDATE follow_up_tasks

    alt Branch A: Link to existing user
        Staff->>GC: POST /guest-conversions/convert/{guest}
        GC->>DB: UPSERT member_church_affiliations (attach if missing)
        GC->>DB: UPDATE guests.converted_* fields
    else Branch B: Create new member account
        Staff->>G: GET /guests/convertToMember/{guest}
        G->>DB: UPDATE guests.followed_up = 1
        G-->>M: Redirect /members/create with guest_data
        Staff->>M: POST /members/store
        M->>DB: INSERT users/auth_identities (Shield)
        M->>DB: INSERT auth_groups_users
        M->>DB: INSERT profile
        M->>DB: INSERT member_church_affiliations
    end

    Staff->>S: standing:refresh (web or CLI)
    S->>DB: DELETE member_standing_summary for year
    S->>DB: INSERT recomputed member_standing_summary rows

    KPI->>DB: JOIN member_standing_summary + member_church_affiliations
    KPI-->>Staff: good_standing_members KPI
```

## Phase-by-Phase Table Involvement

| Phase | Primary Writes | Primary Reads | Core Keys / Filters |
| --- | --- | --- | --- |
| Guest intake | guests, follow_up_tasks | churches, users (assignee), profile (assignee names) | guests.church_id, follow_up_tasks.guest_id |
| Guest follow-up | follow_up_tasks (status/notes), guests.followed_up (optional via guest edit) | guests, follow_up_tasks, profile | follow_up_tasks.status, guests.followed_up |
| Conversion A (existing user link) | guests.converted_to_member_at, guests.converted_user_id, guests.converted_by_user_id; member_church_affiliations (attach/reactivate) | guests, member_church_affiliations | guests.id, guests.church_id, mca.user_id+church_id |
| Conversion B (new account) | users, auth_identities, auth_groups_users, profile, member_church_affiliations | churches (for hierarchy), existing identities (uniqueness checks) | users.id, profile.user_id, mca.user_id+church_id |
| Standing recompute | member_standing_summary (year snapshot rebuild) | users, attendance, services, annual_pledges, member_discipline | mss.user_id+year, services.date range, pledges year |
| Standing display/API | none | member_standing_summary joined with member_church_affiliations (+church/diocese/province scope joins) | mss.year, mss.is_good_standing, mca.active, mca.membership_status |

## Status and State Transitions

### Guest Lifecycle (guests)

- Initial record on intake:
  - followed_up = 0
  - converted_to_member_at = NULL
  - converted_user_id = NULL
- Follow-up progression:
  - follow_up_tasks.status transitions pending -> completed
  - guests.followed_up can be set to 1
- Conversion completion:
  - converted_to_member_at set to timestamp
  - converted_user_id set to users.id
  - converted_by_user_id set to actor users.id

### Member Affiliation Lifecycle (member_church_affiliations)

- attachToChurch guarantees one affiliation per user/church key.
- Conversion to member in church scope requires active affiliation:
  - active = 1
  - membership_status typically active
  - is_home set according to path (new member creation sets true in current flow)

### Good Standing Lifecycle (member_standing_summary)

Row identity:

- Unique per (user_id, year)

Computed flags:

- is_active_member from users.active
- attendance_count and communion_count from attendance joined to services within year window
- has_pledged from annual_pledges for same year (excluding cancelled)
- is_under_discipline from member_discipline overlap with year window
- is_good_standing computed as all conditions true

## Good Standing Rule (Current Runtime Logic)

A user is in good standing for year Y when:

- user is active
- attendance_count >= 3
- communion_count >= 3
- has_pledged = true for Y
- is_under_discipline = false during Y window

Formally:

- is_good_standing = is_active_member AND attendance_count >= 3 AND communion_count >= 3 AND has_pledged AND NOT is_under_discipline

## Data Model Notes (Important)

- member_standing_summary has no church_id.
- Church-level good-standing outputs are derived by joining summary rows to member_church_affiliations for active church membership.
- Conversion writes do not directly write member_standing_summary; summary is refreshed by:
  - standing:refresh yearly recompute, and
  - per-user sync when users.active changes (UserModel hook to updateSingleUserStanding).

## Verification SQL Appendix

### A. Guest Intake and Conversion Integrity

```sql
-- 1) Guests converted without linked user (should be zero)
SELECT id, church_id, converted_to_member_at, converted_user_id
FROM guests
WHERE converted_to_member_at IS NOT NULL
  AND converted_user_id IS NULL;
```

```sql
-- 2) Converted users without active church affiliation in guest church (should be zero)
SELECT g.id AS guest_id, g.church_id, g.converted_user_id
FROM guests g
LEFT JOIN member_church_affiliations mca
  ON mca.user_id = g.converted_user_id
 AND mca.church_id = g.church_id
 AND mca.active = 1
WHERE g.converted_to_member_at IS NOT NULL
  AND g.converted_user_id IS NOT NULL
  AND mca.id IS NULL;
```

```sql
-- 3) Unfollowed guests with all tasks completed (operational cleanup candidates)
SELECT g.id, g.first_name, g.last_name, g.followed_up
FROM guests g
WHERE g.followed_up = 0
  AND NOT EXISTS (
      SELECT 1
      FROM follow_up_tasks ft
      WHERE ft.guest_id = g.id
        AND ft.status = 'pending'
  );
```

### B. Good Standing Integrity

```sql
-- 4) Duplicate summary rows (must be zero)
SELECT user_id, year, COUNT(*) AS cnt
FROM member_standing_summary
GROUP BY user_id, year
HAVING COUNT(*) > 1;
```

```sql
-- 5) Church-scoped good standing count (authoritative pattern)
-- Replace :church_id and :year
SELECT COUNT(*) AS good_standing_count
FROM member_standing_summary mss
JOIN member_church_affiliations mca
  ON mca.user_id = mss.user_id
 AND mca.church_id = :church_id
 AND mca.active = 1
 AND mca.membership_status = 'active'
WHERE mss.year = :year
  AND mss.is_good_standing = 1;
```

```sql
-- 6) Members marked good standing while user inactive (should be zero)
SELECT mss.user_id, mss.year, mss.is_good_standing, u.active
FROM member_standing_summary mss
JOIN users u ON u.id = mss.user_id
WHERE mss.year = :year
  AND mss.is_good_standing = 1
  AND u.active <> 1;
```

## Operational Risks and Controls

- Risk: guest converted_user_id points to a user not affiliated to guest church.
  - Control: conversion path enforces attachToChurch when needed.
- Risk: standing KPI appears stale after membership/pledge/attendance changes.
  - Control: run standing:refresh for target year as part of reconciliation window.
- Risk: affiliation status drifts to inactive while guest remains marked converted.
  - Control: periodic query #2 and admin remediation workflow.

## Files Reviewed

- Pastor/Config/Routes.php
- MemberPortal/Config/Routes.php
- Pastor/Controllers/Guests.php
- MemberPortal/Controllers/GuestConversions.php
- Pastor/Controllers/Members.php
- MemberPortal/Controllers/Standing.php
- app/Libraries/PeopleMetricsService.php
- app/Models/GuestModel.php
- app/Models/MemberChurchAffiliationModel.php
- app/Models/MemberStandingSummaryModel.php
- app/Models/UserModel.php
- app/Commands/RefreshGoodStanding.php
- app/Database/Migrations/2025_01_01_000007_CreateGuests.php
- app/Database/Migrations/2026_03_26_000001_AddGuestConversionFields.php
- app/Database/Migrations/2025_01_01_000008_CreateFollowUpTasks.php
- app/Database/Migrations/2025_01_01_000002_CreateProfiles.php
- app/Database/Migrations/2026_03_18_000005_CreateMemberChurchAffiliations.php
- app/Database/Migrations/2025_01_01_000005_CreateServices.php
- app/Database/Migrations/2025_01_01_000006_CreateAttendance.php
- app/Database/Migrations/2026_03_26_000002_CreateAnnualPledges.php
- app/Database/Migrations/2026_04_10_000001_CreateMemberDiscipline.php
- app/Database/Migrations/2026_04_10_000002_CreateMemberStandingSummary.php
