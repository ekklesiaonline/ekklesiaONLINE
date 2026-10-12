# Role-to-Menu Permission Policy (Canonical)

This table defines the baseline least-privilege policy used by the canonical sidebar and `PermissionRoleSyncSeeder`.

## Scope

- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
- Canonical menu renderer: `app/Views/partials/sidebar.php`
- Baseline roles: `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, `finance`, `warden`, `vestry_secretary`, `mission_governance_signer`, `mission_approver`, `volunteer_coordinator`, `followup_coordinator`, `followup_volunteer`, `worship_coordinator`, `worship_volunteer`, `member`

## Policy Table

| Menu / Capability | Sidebar Gate | Baseline Roles |
| --- | --- | --- |
| Province Dashboard | `province.admin` | `provinceadmin` |
| Diocesan Dashboard + diocesan links | `diocese.admin` | `dioceseadmin` |
| Dashboard access fallback | `dashboard.view` | all baseline roles |
| Members / Staff | `member.view` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, `finance`, `worship_coordinator` |
| Members edit actions | `member.edit` | `dioceseadmin`, `parishadmin` and separately assigned operational groups; no province-wide clergy office grant |
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
| Chart of Accounts (Diocesan) | `diocese.admin` | `dioceseadmin` |
| Chart of Accounts (Parish) | `parishadmin` OR `finance.manage` (nested: `finance.view`) | `parishadmin`, `finance` |
| Finance section visibility | `donation.view` OR `expense.view` OR `finance.view` OR `finance.manage` | `parishadmin`, `finance` and separately authorized operational actors |
| Pledges (inside Finance) | `finance.manage` | `parishadmin`, `finance` |
| Volunteers | `volunteer.manage` | `parishadmin`, `volunteer_coordinator` |
| Volunteer schedules | `volunteer.schedule` | `parishadmin`, `volunteer_coordinator` |
| Events | `event.view` | `parishadmin`, `clergy`, `volunteer_coordinator`, `worship_coordinator` |
| Worship planning | `worship.view` | `parishadmin`, `clergy`, `volunteer_coordinator`, `worship_coordinator`, `worship_volunteer` |
| Reports | `report.view` | `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, `finance`, `vestry_secretary` |
| Parishes management | `parishes.edit` | `dioceseadmin` within the assigned diocese |

## Clergy and deanery authorization (additive to the menu baseline)

The Shield admin priorities (`systemadmin=4`, `provinceadmin=3`, `dioceseadmin=2`, `parishadmin=1`, others `0`) govern **account-target mutation**, not ordination or ecclesial jurisdiction. Bishop, priest, deacon, rector, cathedral dean, and regional dean are **not** Shield groups. A `clergy` group does not prove orders. Deanery is a diocese-owned scope, not an admin tier; never expand a deanery or diocese office to its province or all of The Episcopal Church. [Episcopal Church rector](https://www.episcopalchurch.org/glossary/rector/) and [ordinary](https://www.episcopalchurch.org/glossary/ordinary-of-a-diocese/) definitions support the office distinctions. See the [full implementation decisions](../outputs/clergy-ordination-office-authorization-plan.md).

| Action | Coarse Shield permission | Separate required source and exact target |
| --- | --- | --- |
| Deanery structure and dated membership | `diocese.admin` + `deanery.manage` | Diocesan app admin; selected deanery and parish must both belong to the admin's actual diocese. |
| Appointment creation and edits | `leadership_position.manage` | Parish admin: own parish; diocesan app admin: assigned diocese and its descendant parishes/deaneries. No implied province access. Clergy offices start **unapproved**. |
| Ordination verification and clergy-office review | `clergy.ordination.verify` / `clergy.appointment.manage` | Current ordinary or distinct, documented, active diocesan delegate; no self-review. Legacy `clergy` group and candidate rows do not grant either. |
| Cathedral dean | Office grant; e.g. `governance.report.finalize_clergy` | Verified priest, current approved `cathedral_dean` parish office, congregation explicitly typed `cathedral`, and that cathedral's report. No diocese-admin rights. |
| Regional/deanery dean | `deanery.coordinate`, `pastoral.parish.view` | Verified priest and approved office in one deanery; only current member parishes. No finance, parish manage, report finalization, or diocesan approval. |
| Rector / priest-in-charge / interim / vicar | `pastoral.parish.view`, `.manage`, `governance.report.finalize_clergy` | Verified priest and current approved office for the report's parish; unrelated deanery or diocesan status is insufficient. |
| Diocesan ordinary | `governance.diocese.approve`, scoped reviewer permissions | Verified bishop with the one current approved `diocesan_bishop` office for the report's diocese. A suffragan/coadjutor/assistant requires a separate dated delegation to approve. |
| Warden, clerk or local mission signer | `governance.report.approve_warden` or `.certify_clerk` | Distinct, reviewed lay appointment in the same parish/mission and the required signer group; neither a technical admin group nor a blank role code suffices. |
| Report submission/signature | `report.parochial` and the stage-specific key above | Explicit diocesan policy enablement; ordered, distinct actors; immutable version and recorded office/delegation + diocese; financial-summary finalization is not an ecclesial signature. |

Source-of-truth services: `HierarchyService` and `DeaneryService` (containment); `LeadershipPositionService` (office validation and retirement); `ClergyAuthorizationService` (action + active scoped source); `ClergyReviewService` (documented verification/delegation); `ParishReportApprovalService` (ordered sign-off and revisions). The bishop's approval grants no finance CRUD or `dioceseadmin` membership. A verified order without a currently approved office grants no access. [Episcopal Diocese of Massachusetts](https://www.diomass.org/about-us/deaneries-and-regions) describes deaneries as groups of congregations; this app checks **current** recorded membership.

## Enforcement Notes

- Seeder creates missing permissions from this policy catalog.
- Seeder grants policy permissions to all users currently assigned to each role group.
- After the clergy/deanery foundation migration creates `permission_role_sync_managed`, the seeder prunes only direct permissions it can prove it created, and preserves independent office/delegation and unrelated manual sources. Previously existing direct permissions with no provenance are **not** silently removed; they require a separate reviewed audit.
- If the provenance table is not yet installed, sync remains additive. Role-filter permissions alone are never sufficient for ecclesial report or office actions.

## Run

`php spark db:seed PermissionRoleSyncSeeder`

Policy owner: Platform/AuthZ.
