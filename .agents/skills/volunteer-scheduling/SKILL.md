---
name: volunteer-scheduling
description: Explain volunteer opportunities, team assignments, scheduling shifts, status tracking, and automated reminder CLI cron
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

# Volunteer Scheduling & Management

Help with volunteer coordination, scheduling shifts, team rosters, and automated reminders.

## Key Sources

- Controllers:
  - `Volunteer\Controllers\Volunteers` (Opportunities, teams, schedules, assignments)
  - `Volunteer\Controllers\Dashboard` (Compatibility redirect to `/parish/volunteers`)
  - `Parish\Controllers\VolunteerDashboard` (Parish coordinator dashboard)
- Models:
  - `App\Models\VolunteerScheduleModel`
  - `App\Models\VolunteerTeamModel`
  - `App\Models\TeamModel`
  - `App\Models\TeamMemberModel`
- Commands:
  - `php spark volunteers:remind [--days=2]` (`App\Commands\VolunteerRemind`)
- Routes:
  - `Volunteer/Config/Routes.php` (`/volunteers/*`)
  - `Parish/Config/Routes.php` (`/parish/volunteers`)

## Roles & Dashboards

- Shield group: `volunteer_coordinator` lands on `/parish/volunteers`.
- Plain volunteers (`followup_volunteer`, `worship_volunteer`, or members) manage their own assignments via `/me`.
- Route permissions: `volunteer.view` (view rosters/schedules) and `volunteer.manage` (create/assign/modify shifts).

## Scheduled Reminders & Cron

- Shift reminder emails are sent via `php spark volunteers:remind --days=2`.
- Queries pending shifts starting in N days (default 2 days) and dispatches reminder notifications using `EmailScopeService`.
