# ekklesiaRD — Always-On Project Rules

## Architecture

- CodeIgniter 4.7+ with Shield 1.2 for auth.
- Backend: PHP 8.2+, MySQL 8.0. Frontend: Bootstrap 5, jQuery/vanilla JS, Chart.js.
- Hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is the single source of truth for landing-path resolution.
- `BaseController::isElevatedAdmin()` is the single source of truth for top-tier admins.
- New parish-tier dashboards live in the `Parish/` namespace under `/parish/*`.
- Old `/dashboard` and `/treasurer/dashboard` URLs are deprecated redirect shims.

## Code & Domain

- Use CI4’s `Validation` class and Shield helpers (`auth()->user()`).
- Validate requests; scope queries by `parish_id` unless the user is elevated.
- Model casts must use nullable prefix form: `?int`, `?string` (never `int?`, `string?`).
- Use `php spark` for code generation, not manual scaffolding.
- Write tests with CIUnitTestCase.

## Documentation & Bootstrapping

- Canonical documentation index: `docs/DOCUMENTATION_CANONICAL.md`.
- Fresh environment seeding: `php spark db:seed DatabaseSeeder`.
- Role-to-menu permissions are enforced by `PermissionRoleSyncSeeder`; canonical policy table is `docs/ROLE_MENU_PERMISSION_POLICY.md`.
- API/AJAX JSON contract: `docs/API_AJAX_ERROR_CONTRACT.md`.

## Terminology

- Prefer `parish` in code, routes, models, and new namespaces. `church` is acceptable only in user-facing labels or legacy aliases (e.g., `churches` → `parish_list` redirect).

## When In Doubt

- For view/HTML rules, see `.devin/rules/views.md`.
- For CI4/Shield coding rules, see `.devin/rules/codeigniter.md`.
- For church-domain data integrity rules, see `.devin/rules/church-domain.md`.
- For diagnostic playbooks, invoke the relevant skill (`/debug-no-parish`, `/debug-person-not-found`, `/sacrament-sync`, etc.).
- For permission/scope issues, use `/church-permissions` or `/permission-denied`.
- For audit, observability, and localization questions, use `/audit-logging` or `/localization`.
