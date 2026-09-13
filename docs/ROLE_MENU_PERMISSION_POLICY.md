# Role-to-Menu Permission Policy (Canonical)

This table defines the baseline least-privilege policy used by the canonical sidebar and `PermissionRoleSyncSeeder`.

## Scope

- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
- Canonical menu renderer: `app/Views/partials/sidebar.php`
- Baseline roles: `provinceadmin`, `diocesanadmin`, `churchadmin`, `pastor`, `treasurer`, `volunteer_coordinator`, `member`

## Policy Table

| Menu / Capability | Sidebar Gate | Baseline Roles |
| --- | --- | --- |
| Province Dashboard | `admin.province` | `provinceadmin` |
| Diocesan Dashboard + diocesan links | `admin.diocese` | `diocesanadmin` |
| Dashboard access fallback | `dashboard.view` | all baseline roles |
| Members / Staff | `members.view` | `provinceadmin`, `diocesanadmin`, `churchadmin`, `pastor`, `treasurer` |
| Members edit actions | `members.edit` | `provinceadmin`, `diocesanadmin`, `churchadmin`, `pastor` |
| Families | `families.view` | `churchadmin`, `pastor` |
| Services | `services.view` | `diocesanadmin`, `churchadmin`, `pastor`, `volunteer_coordinator` |
| Attendance reports | `attendance.view` | `churchadmin`, `pastor` |
| Guests | `guests.view` | `churchadmin`, `pastor` |
| Guest conversions / edit | `guests.edit` | `churchadmin`, `pastor` |
| Follow-up tasks | `followup.tasks` | `churchadmin`, `pastor` |
| Finance section visibility | `donations.view` OR `expenses.view` OR `finance.view` OR `finance.manage` | `diocesanadmin`, `churchadmin`, `treasurer` |
| Pledges (inside Finance) | `finance.manage` | `diocesanadmin`, `churchadmin`, `treasurer` |
| Volunteers | `volunteers.manage` | `churchadmin`, `volunteer_coordinator` |
| Volunteer schedules | `volunteers.schedule` | `churchadmin`, `volunteer_coordinator` |
| Events | `events.view` | `churchadmin`, `pastor`, `volunteer_coordinator` |
| Worship planning | `worship.view` | `churchadmin`, `pastor`, `volunteer_coordinator` |
| Reports | `reports.view` | `provinceadmin`, `diocesanadmin`, `churchadmin`, `pastor`, `treasurer` |
| Churches management | `churches.edit` | `provinceadmin`, `diocesanadmin` |

## Enforcement Notes

- Seeder creates missing permissions from this policy catalog.
- Seeder grants policy permissions to all users currently assigned to each role group.
- Seeder also removes managed menu permissions that are outside policy for grouped users (strict sync for managed permissions only).
- Non-policy custom permissions are not touched.

## Run

`php spark db:seed PermissionRoleSyncSeeder`

Policy owner: Platform/AuthZ.
