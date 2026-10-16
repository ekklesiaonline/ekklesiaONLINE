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

Explain how ekklesiaONLINE resolves dashboards and scopes.

Key sources:
- `BaseController::resolveDashboardScope()`
- `BaseController::resolveAuthenticatedLandingPath()`
- `BaseController::canAccessMyDashboard()`
- `BaseController::getOperationalParishId()` / `getHomeparishId()`
- `BaseController::isElevatedAdmin()`

Use this hierarchy: System → Province → Diocese → Parish → Personal.

Role-to-route mapping (first match wins, highest tier first):

| Group                                                                | Dashboard route       |
| -------------------------------------------------------------------- | --------------------- |
| `systemadmin`                                                        | `/system/dashboard`   |
| `provinceadmin`                                                      | `/province/dashboard` |
| `dioceseadmin`                                                       | `/diocese/dashboard`  |
| `parishadmin`                                                        | `/parish/dashboard`   |
| `finance`                                                            | `/parish/finance`     |
| `vestry_secretary`                                                   | `/parish/secretary`   |
| `warden`, `vestry_member`                                            | `/parish/governance`  |
| `clergy`, `worship_coordinator`                                      | `/parish/worship`     |
| `volunteer_coordinator`                                              | `/parish/volunteers`  |
| `followup_coordinator`                                               | `/parish/followup`    |
| `followup_volunteer`, `worship_volunteer`, `member`, `dashboard.view` | `/me`                 |

Old `/dashboard` and `/treasurer/dashboard` are deprecated redirect shims.

When answering, cite the actual resolution code and map the user’s role to the correct route.
