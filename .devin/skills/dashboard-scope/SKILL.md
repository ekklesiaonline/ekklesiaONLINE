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

New parish-tier dashboards live under `Parish/`:
- `/parish/dashboard`
- `/parish/finance`
- `/parish/governance`
- `/parish/secretary`
- `/parish/worship`
- `/parish/volunteers`

Old `/dashboard` and `/treasurer/dashboard` are deprecated redirect shims.

When answering, cite the actual resolution code and map the user’s role to the correct route.
