# Role-to-Menu Permission Policy (Canonical)

This table defines the baseline least-privilege policy used by the canonical sidebar and `PermissionRoleSyncSeeder`.

## Scope

- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
- Canonical menu renderer: `app/Views/partials/sidebar.php`
- Baseline roles: `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor`, `treasurer`, `volunteer_coordinator`, `member`

## Policy Table

| Menu / Capability | Sidebar Gate | Baseline Roles |
| --- | --- | --- |
| Province Dashboard | `province.admin` | `provinceadmin` |
| Diocesan Dashboard + diocesan links | `diocese.admin` | `diocesanadmin` |
| Dashboard access fallback | `dashboard.view` | all baseline roles |
| Members / Staff | `member.view` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor`, `treasurer` |
| Members edit actions | `member.edit` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor` |
| Families | `family.view` | `parishadmin`, `pastor` |
| Small Groups | `groups.view` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `clergy` |
| Small group manage / roster | `groups.manage`, `groups.assign` | `parishadmin`, `clergy` |
| Bulk Messages | `messaging.view` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `clergy` |
| Bulk send / templates | `messaging.send` | `parishadmin`, `clergy` |
| Services | `service.view` | `diocesanadmin`, `parishadmin`, `pastor`, `volunteer_coordinator` |
| Attendance reports | `attendance.view` | `parishadmin`, `pastor` |
| Visitors (directory, intake, import) | `visitor.view`, `visitor.create` | `parishadmin`, `pastor` |
| Visitor edits, promote/demote, member promotion | `visitor.edit` | `parishadmin`, `pastor` |
| Visitor deletion | `visitor.delete` | `parishadmin`, `pastor` |
| Follow-up sequences, tasks, dispatch | `followup.manage` | `parishadmin`, `pastor` |
| Follow-up read-only (queue, timeline) | `followup.view` | `parishadmin`, `pastor` |
| Finance section visibility | `donation.view` OR `expense.view` OR `finance.view` OR `finance.manage` | `diocesanadmin`, `parishadmin`, `treasurer` |
| Pledges (inside Finance) | `finance.manage` | `diocesanadmin`, `parishadmin`, `treasurer` |
| Volunteers | `volunteer.manage` | `parishadmin`, `volunteer_coordinator` |
| Volunteer schedules | `volunteer.schedule` | `parishadmin`, `volunteer_coordinator` |
| Events | `event.view` | `parishadmin`, `pastor`, `volunteer_coordinator` |
| Worship planning | `worship.view` | `parishadmin`, `pastor`, `volunteer_coordinator` |
| Reports | `report.view` | `provinceadmin`, `diocesanadmin`, `parishadmin`, `pastor`, `treasurer` |
| Parishes management | `parishes.edit` | `provinceadmin`, `diocesanadmin` |

## Enforcement Notes

- Seeder creates missing permissions from this policy catalog.
- Seeder grants policy permissions to all users currently assigned to each role group.
- Seeder also removes managed menu permissions that are outside policy for grouped users (strict sync for managed permissions only).
- Non-policy custom permissions are not touched.

## Run

`php spark db:seed PermissionRoleSyncSeeder`

Policy owner: Platform/AuthZ.
