# Role-to-Menu Permission Policy (Canonical)

This table defines the baseline least-privilege policy used by the canonical sidebar and `PermissionRoleSyncSeeder`.

## Scope

- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
- Canonical menu renderer: `app/Views/partials/sidebar.php`
- Baseline roles: `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor`, `treasurer`, `volunteer_coordinator`, `member`

## Policy Table

| Menu / Capability | Sidebar Gate | Baseline Roles |
| --- | --- | --- |
| Province Dashboard | `admin.province` | `provinceadmin` |
| Diocesan Dashboard + diocesan links | `admin.diocese` | `diocesanadmin` |
| Dashboard access fallback | `dashboard.view` | all baseline roles |
| Members / Staff | `members.view` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor`, `treasurer` |
| Members edit actions | `members.edit` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor` |
| Families | `families.view` | `parishadmin`, `pastor` |
| Services | `services.view` | `diocesanadmin`, `parishadmin`, `pastor`, `volunteer_coordinator` |
| Attendance reports | `attendance.view` | `parishadmin`, `pastor` |
| Guests | `guests.view` | `parishadmin`, `pastor` |
| Guest conversions / edit | `guests.edit` | `parishadmin`, `pastor` |
| Follow-up tasks | `followup.tasks` | `parishadmin`, `pastor` |
| Finance section visibility | `donations.view` OR `expenses.view` OR `finance.view` OR `finance.manage` | `diocesanadmin`, `parishadmin`, `treasurer` |
| Pledges (inside Finance) | `finance.manage` | `diocesanadmin`, `parishadmin`, `treasurer` |
| Volunteers | `volunteers.manage` | `parishadmin`, `volunteer_coordinator` |
| Volunteer schedules | `volunteers.schedule` | `parishadmin`, `volunteer_coordinator` |
| Events | `events.view` | `parishadmin`, `pastor`, `volunteer_coordinator` |
| Worship planning | `worship.view` | `parishadmin`, `pastor`, `volunteer_coordinator` |
| Reports | `reports.view` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor`, `treasurer` |
| Parishes management | `parishes.edit` | `provinceadmin`, `diocesanadmin` |

## Enforcement Notes

- Seeder creates missing permissions from this policy catalog.
- Seeder grants policy permissions to all users currently assigned to each role group.
- Seeder also removes managed menu permissions that are outside policy for grouped users (strict sync for managed permissions only).
- Non-policy custom permissions are not touched.

## Run

`php spark db:seed PermissionRoleSyncSeeder`

Policy owner: Platform/AuthZ.
