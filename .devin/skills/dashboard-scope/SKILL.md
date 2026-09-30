---
name: dashboard-scope
description: Explain dashboard routing, scope resolution, and role-based landing paths
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
---

Explain how ekklesiaRD resolves dashboards and scopes.

Key sources:
- `BaseController::resolveDashboardScope()`
- `BaseController::resolveAuthenticatedLandingPath()`
- `BaseController::canAccessMyDashboard()`
- `BaseController::getOperationalParishId()` / `getHomeparishId()`
- `BaseController::isElevatedAdmin()`

Use this hierarchy: System → Province → Diocese → Parish → Personal.

Role-to-route mapping (first match wins, highest tier first):

| Group                               | Dashboard route       |
| ----------------------------------- | ---------------       |
| `systemadmin`                       | `/system/dashboard`   |
| `provinceadmin`                     | `/province/dashboard` |
| `dioceseadmin`                      | `/diocese/dashboard`  |
| `parishadmin`                       | `/parish/dashboard`   |
| `treasurer`                         | `/parish/finance`     |
| `secretary`                         | `/parish/secretary`   |
| `warden`, `clerk`, `vestry_member`  | `/parish/governance`  |
| `clergy`                            | `/parish/worship`     |
| `volunteer_coordinator`             | `/parish/volunteers`  |
| `member` or `dashboard.view`        | `/me`                 |

Old `/dashboard` and `/treasurer/dashboard` are deprecated redirect shims.

When answering, cite the actual resolution code and map the user’s role to the correct route.
