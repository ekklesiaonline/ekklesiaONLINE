---
name: project-architecture
description: Explain or change ekklesiaRD architecture, module ownership, routing, scopes, and canonical documentation. Use when tracing a feature, deciding where code belongs, reviewing a modularization change, or explaining the project to a contributor.
---

# Project Architecture

Use this skill before changing a cross-module feature or explaining where a new controller, service, model, route, view, migration, or test belongs.

## Source precedence

1. `AGENTS.md` and `.agents/rules/*.md` (or `.devin/rules/*.md`) are always-on repository instructions.
2. `docs/DOCUMENTATION_CANONICAL.md` identifies authoritative project documents.
3. Canonical domain documents in `docs/` outrank dated reports and implementation snapshots.
4. The current code and tests are the final authority for behavior; verify paths and symbols before citing them.

## Architecture map

- Framework: CodeIgniter 4.7+, PHP 8.2+, Shield 1.2, MySQL 8, Bootstrap 5.
- Organizational hierarchy: **System → Province → Diocese → Parish → Personal**.
- Shared application code lives under `app/`.
- Role modules use PSR-4 roots: `Province/`, `Diocese/`, `Clergy/`, `Parish/`, `Worship/`, `Finance/`, `Volunteer/`, and `MemberPortal/`.
- Module routes are loaded from each module's `Config/Routes.php` by `app/Config/Routes.php`.
- Domain operations belong in services/models; controllers should coordinate authorization, validation, scope, and response rendering.

## Routing and scope workflow

1. Find the route in `app/Config/Routes.php` or the owning module route file.
2. Identify session, permission, scope, CSRF, rate-limit, registration, and device-verification filters.
3. Trace the controller to its service/model calls and view or JSON response.
4. For landing paths, use `BaseController::resolveDashboardScope()` and `resolveAuthenticatedLandingPath()`; do not invent role routing.
5. For parish data, scope by `parish_id` unless an explicit elevated-admin path grants cross-scope access.
6. Check `docs/ROLE_MENU_PERMISSION_POLICY.md` before changing sidebar visibility or permissions.
7. Check modularization ownership tests before moving or deleting legacy controllers/views.

## Sample routing test: parish treasurer

Use this as a compact architecture smoke test when reviewing a role-based landing change:

1. `BaseController::resolveDashboardScope()` sees the `treasurer` group after higher-tier groups and returns tier `parish`, the operational parish scope, and `/parish/finance`.
2. `Parish/Config/Routes.php` maps `GET /parish/finance` to `Parish\\Controllers\\FinanceDashboard::index` with session and `permission:finance.view` protection.
3. `Parish/Controllers/FinanceDashboard.php` resolves `getOperationalParishId()` and applies that `parish_id` to donation and expense queries.
4. `Finance/Config/Routes.php` still exposes `GET /treasurer/dashboard` only as a compatibility entry point; `Finance\\Controllers\\Dashboard` redirects to `/parish/finance`.
5. Confirm the route table with `php spark routes`, then run the focused tests:

   ```bash
   vendor/bin/phpunit --no-coverage \
     tests/unit/DashboardScopeResolverTest.php \
     tests/unit/HomeRedirectMatrixTest.php \
     tests/unit/SidebarLandingPathAlignmentTest.php \
     tests/unit/ModuleOwnershipClosureTest.php
   ```

Expected evidence is the canonical landing path, the owning module/controller, the required filters, and the parish-scope boundary. Do not treat the deprecated route as the current feature owner.

## Naming and compatibility

- Prefer `parish` in new code, routes, models, and namespaces; preserve legacy aliases only where the route contract requires them.
- Old `/dashboard` and `/treasurer/dashboard` paths are compatibility shims, not new ownership locations.
- Use `php spark` generators for new CI4 boilerplate.
- Do not delete a legacy file until the module route is live, ownership is tested, and no runtime dependency remains.

## Evidence-based answers

When explaining a feature, report the module, route, controller, service, model, view, permission, and scope boundary. Cite verified paths and line ranges; if a document is historical or a gap remains, say so explicitly. Never infer a table, route, or class that is not present in the checkout.

## Related sources

- `README.md`
- `AGENTS.md`
- `.agents/rules/codeigniter-shield.md`
- `.agents/rules/parish-domain.md`
- `docs/DOCUMENTATION_CANONICAL.md`
- `docs/MODULARIZATION_CUTOVER_EXECUTION_PLAN.md`
- `docs/ROLE_MENU_PERMISSION_POLICY.md`
