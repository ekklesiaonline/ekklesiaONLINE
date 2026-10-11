# Deanery authorization and scoping

This document covers the deanery as a geographic scope: creation, parish membership, the regional/deanery dean office, and deanery-related routes. For the shared schema/boundary table, the migration batch-94 status note, episcopal offices, ordination verification, canonical standing and office approval, see the companion [ordinary authorization guide](ORDINARY_AUTHORIZATION.md) — it is the primary reference for those topics.

## Deanery boundaries

A deanery is a **geographic scope** (`diocese → deanery → parish`), not an administrative tier: it never acts as an admin rank or a whole-diocese shortcut, and it does not insert a level into `AdminHierarchyGuard`.

- Diocese is the **only** parent of a deanery (`deanery.diocese_id` FK). Provinces have no direct deanery access.
- Parishes without a deanery remain valid.
- Territorial containment: Deanery A never extends into Deanery B.
- `deanery_parish_membership` invariants: `parish.diocese_id` must match `deanery.diocese_id`, and a parish has at most **one effective active membership** at a time (`started_at <= ended_at`). Past membership is preserved for historical attribution but never resurrects access.
- A diocesan app admin may configure deanery membership in their assigned diocese; this does not let them verify orders or sign as bishop.

## Deanery creation and membership

`POST /diocese/deaneries/store` creates a deanery (`code`, `name`); `POST /diocese/deaneries/assign/{deaneryId}` assigns a parish with `parish_id,started_at`; `POST /diocese/deaneries/end/{membershipId}` ends a membership with `ended_at`. The diocese is always taken from the admin's authoritative scope, never from request input. `POST /diocese/congregations/{parishId}/type` classifies a congregation (`parish | cathedral | mission`) and refuses changes during an in-progress report.

## Regional/deanery dean office

The `regional_dean` / `deanery_dean` offices are deanery-scoped, priest-order entries in `LeadershipPositionService::CLERGY_OFFICES`. Once approved by the ordinary (see [office approval](ORDINARY_AUTHORIZATION.md#office-approval)) they carry `deanery.view`, `deanery.coordinate` and `pastoral.parish.view` over **their deanery and its current member congregations only** — pastoral coordination/viewing, never Rector, Priest-in-Charge or Diocesan Bishop governance powers, and no finance, account management or report sign-off. A regional dean never finalizes a report on the strength of a deanery appointment alone.

## Route gating

`Clergy/` module routes are gated by Shield only: the `session` filter plus coarse `permission:*` keys (`member.view`, `member.edit`, `member.delete`, `leadership_position.manage`, etc.). The scoped ecclesial check in `app/Services/ClergyAuthorizationService.php` (`clergy_access_grant` office/delegation matching, verified order, current standing, exact scope containment) is layered on sensitive parish-scoped routes via the `clergyScope:` filter (`app/Filters/ClergyScopeFilter.php`, alias `clergyScope:<permission>,<targetType>`). The filter resolves the parish target server-side — never from request input — and denies through the shared `logAuthorizationDenial` audit contract. It stacks on top of the coarse `permission:` filter, not instead of it.

For deanery-relevant staging: `POST /leadership/store`, `/leadership/update/{id}`, `/leadership/delete/{id}` (MemberPortal position mutations) are checked in `MemberPortal/Controllers/LeadershipPositions.php` against the position's actual scope instead of the route filter: parish positions require `pastoral.parish.manage` over that parish; **deanery/diocese positions require `clergy.appointment.manage` over the admin's assigned diocese** — so deanery office staging routes through the ordinary/delegate, matching the strict approval model in the ordinary guide.

Clergy accounts (creation, invitation, activation, scope) are administered solely via `Diocese/Controllers/ClergyAccounts.php`; the Clergy module does not manage clergy accounts. Keep that split: `Clergy/` holds parish-level pastoral operations, `Diocese/` holds diocesan clergy administration.

## Application surfaces

Routes use Shield `session` and action-specific `permission:*` filters, validate POST inputs with CI4 Validation, return the project's [API/AJAX contract](API_AJAX_ERROR_CONTRACT.md), and record redacted audit events. GET `/diocese/deaneries/manage` provides a Bootstrap page for authorized diocesan admins; `/leadership?scope=deanery` lists diocese-scoped regional appointments with a controlled office-code guide.

| Action                                            | Route                                                                                                 | Essential fields                                                                                              |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- |
| Create deanery / assign or end dated membership   | `POST /diocese/deaneries/store`, `/assign/{deaneryId}`, `/end/{membershipId}`                        | `code,name`; `parish_id,started_at`; `ended_at`. Diocese is taken from the admin's authoritative scope.      |
| Classify congregation                             | `POST /diocese/congregations/{parishId}/type`                                                        | `congregation_type=parish | cathedral | mission`; refuses a change during an in-progress report.              |
| Stage office                                      | `POST /leadership/store`                                                                             | Person-linked user, `scope_type=deanery` + `deanery_id`, controlled `role_code`/order, date. Creation does **not** approve clergy office. |
| Approve or revoke clergy office                   | `POST /diocese/clergy-review/office/{positionId}/approve | revoke`                                   | Ordinary/delegate only — see the [ordinary guide](ORDINARY_AUTHORIZATION.md#office-approval). |

## Known schema gap

The installed schema lacks a database FK on `leadership_position.deanery_id`; use a new migration if enforcing that relationship in MySQL. The disposable SQLite rehearsal `tests/ClergyMigrationRehearsalTest.php` does not certify MySQL constraints — for FK hardening or other forward migrations, rehearse against a **MySQL clone** and verify deletion/rollback behavior, per the [controlled rollout](ORDINARY_AUTHORIZATION.md#controlled-rollout).

## References

- Episcopal Diocese of Massachusetts, [deaneries and bishop-appointed priest deans](https://www.diomass.org/about-us/deaneries-and-regions); Episcopal Diocese of Virginia, [regional deans](https://episcopalvirginia.org/who-we-are/governance/regions-deans-presidents/).
- Repository: `docs/ORDINARY_AUTHORIZATION.md`, `docs/ROLE_MENU_PERMISSION_POLICY.md`, `docs/API_AJAX_ERROR_CONTRACT.md`, `app/Services/ClergyAuthorizationService.php`, `app/Services/LeadershipPositionService.php`, `app/Controllers/Diocese/Deaneries.php`, `Clergy/Config/Routes.php`, `MemberPortal/Controllers/LeadershipPositions.php`.
