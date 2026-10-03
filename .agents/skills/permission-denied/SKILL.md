---
name: permission-denied
description: Diagnose 403/permission-denied issues and audit logging
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

Diagnose why a request is being denied with a 403 or permission error.

Key sources:
- `BaseController::checkPermission()`
- `BaseController::requirePermission()` — logs `permission.denied` events, rate-limited to 10/hour per user.
- Shield group/permission assignments (`auth_groups_users`, `auth_permissions_users`, etc.)
- Route filters (`session`, `permission:*`)

Checklist:
1. Confirm the route filter and required permission from `app/Config/Routes.php` or module route files.
2. Check the user's Shield groups and permissions.
3. Check parish/scope access (`requireParish`, `getOperationalParishId`).
4. Review `permission.denied` audit logs if auditing is enabled.
5. Look for province-admin read-only blocks or hierarchy guards (`AdminHierarchyGuard`).

When answering:
1. Identify the exact permission/scope check that failed.
2. Show how to verify the user's groups and permissions.
3. Suggest the fix or escalation path.
4. Cite specific files and line ranges.
