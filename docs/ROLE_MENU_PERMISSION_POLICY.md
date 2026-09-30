# Role-to-Menu Permission Policy (Canonical)

This table defines the baseline least-privilege policy used by the canonical sidebar and `PermissionRoleSyncSeeder`.

## Scope

- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
- Canonical menu renderer: `app/Views/partials/sidebar.php`
- Baseline roles: `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, `finance`, `vestry_secretary`, `volunteer_coordinator`, `followup_coordinator`, `followup_volunteer`, `worship_coordinator`, `worship_volunteer`, `member`

## Policy Table

| Menu / Capability | Sidebar Gate | Baseline Roles |
| --- | --- | --- |
| Province Dashboard | `province.admin` | `provinceadmin` |
| Diocesan Dashboard + diocesan links | `diocese.admin` | `dioceseadmin` |
| Dashboard access fallback | `dashboard.view` | all baseline roles |
| Members / Staff | `member.view` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, `finance`, `worship_coordinator` |
| Members edit actions | `member.edit` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy` |
| Families | `family.view` | `parishadmin`, `clergy` |
| Small Groups | `groups.view` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy` |
| Small group manage / roster | `groups.manage`, `groups.assign` | `parishadmin`, `clergy` |
| Bulk Messages | `messaging.view` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy` |
| Bulk send / templates | `messaging.send` | `parishadmin`, `clergy` |
| Services | `service.view` | `dioceseadmin`, `parishadmin`, `clergy`, `volunteer_coordinator`, `worship_coordinator`, `worship_volunteer` |
| Attendance reports | `attendance.view` | `parishadmin`, `clergy`, `worship_coordinator`, `worship_volunteer` |
| Visitors (directory, intake, import) | `visitor.view`, `visitor.create` | `parishadmin`, `clergy`, `followup_coordinator` |
| Visitor edits, promote/demote, member promotion | `visitor.edit` | `parishadmin`, `clergy`, `followup_coordinator` |
| Visitor deletion | `visitor.delete` | `parishadmin`, `clergy` |
| Follow-up sequences, tasks, dispatch | `followup.manage` | `parishadmin`, `clergy`, `followup_coordinator` |
| Follow-up read-only (queue, timeline) | `followup.view` | `parishadmin`, `clergy`, `followup_coordinator`, `followup_volunteer` |
| Follow-up global sequences/templates (`/system/followup-workflow/*`) | `system.admin` | `systemadmin` |
| Follow-up diocesan sequences/templates (`/diocese/followup-workflow/*`) | `diocese.admin` | `dioceseadmin` |
| Finance section visibility | `donation.view` OR `expense.view` OR `finance.view` OR `finance.manage` | `dioceseadmin`, `parishadmin`, `finance` |
| Pledges (inside Finance) | `finance.manage` | `dioceseadmin`, `parishadmin`, `finance` |
| Volunteers | `volunteer.manage` | `parishadmin`, `volunteer_coordinator` |
| Volunteer schedules | `volunteer.schedule` | `parishadmin`, `volunteer_coordinator` |
| Events | `event.view` | `parishadmin`, `clergy`, `volunteer_coordinator`, `worship_coordinator` |
| Worship planning | `worship.view` | `parishadmin`, `clergy`, `volunteer_coordinator`, `worship_coordinator`, `worship_volunteer` |
| Reports | `report.view` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, `finance`, `vestry_secretary` |
| Parishes management | `parishes.edit` | `provinceadmin`, `dioceseadmin` |

## Enforcement Notes

- Seeder creates missing permissions from this policy catalog.
- Seeder grants policy permissions to all users currently assigned to each role group.
- Seeder also removes managed menu permissions that are outside policy for grouped users (strict sync for managed permissions only).
- Non-policy custom permissions are not touched.

## Run

`php spark db:seed PermissionRoleSyncSeeder`

Policy owner: Platform/AuthZ.
