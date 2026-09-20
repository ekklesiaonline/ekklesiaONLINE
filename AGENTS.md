# ekklesiaRD — Always-On Project Rules

## Architecture

- CodeIgniter 4.7+ with Shield 1.2 for auth.
- Dependency management via Composer.
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
- Run tests with `vendor/bin/phpunit --no-coverage`; this project does not provide a `php spark test` command.

## Documentation & Bootstrapping

- Canonical documentation index: `docs/DOCUMENTATION_CANONICAL.md`.
- Fresh environment seeding: `php spark db:seed DatabaseSeeder`.
- Role-to-menu permissions are enforced by `PermissionRoleSyncSeeder`; canonical policy table is `docs/ROLE_MENU_PERMISSION_POLICY.md`.
- API/AJAX JSON contract: `docs/API_AJAX_ERROR_CONTRACT.md`.

## Terminology

- Prefer `parish` in code, routes, models, and new namespaces. `parish` is acceptable only in user-facing labels or legacy aliases (e.g., `parishes` → `parish_list` redirect).

## When In Doubt

- For view/HTML rules, see `.devin/rules/views.md`.
- For CI4/Shield coding rules, see `.devin/rules/codeigniter.md`.
- For parish-domain data integrity rules, see `.devin/rules/parish-domain.md`.
- For diagnostic playbooks, invoke the relevant skill (`/debug-no-parish`, `/debug-person-not-found`, `/sacrament-sync`, `/parish-people-snapshot`, etc.).
- For permission/scope issues, use `/parish-permissions` or `/permission-denied`.
- For audit, observability, and localization questions, use `/audit-logging` or `/localization`.
- For Sunday attendance chart rules, use `/sunday-attendance-charts`.

## Security & Onboarding Conventions

- `RegistrationGateFilter` must query registration status through `UserRegistrationRequestModel`; do not hardcode the singular table name `user_registration_request` because the deployed table is `user_registration_requests`.
- Device-verification endpoints (`/session/device-verify`, `/session/device-verify/resend`) must be rate-limited via the `loginRateLimit` filter; they are already included in `Config/Filters.php`.
- In development, device verification can be disabled with `security.disableDeviceVerificationForTesting = true` in `.env`.
- CI4's `Email` class does not support a `log` protocol; verification-code flows require SMTP or a mail catcher in test environments.

## Session 7: Final Verification Summary

- Full automated suite: `vendor/bin/phpunit --no-coverage` — 605 tests, 2,700 assertions, 6 skipped.
- Manual curl-based walkthroughs completed:
  - Registration → email activation → profile completion → pending review → admin approval (via `RegistrationApprovalService`).
  - Login → device-verification challenge (when enabled) → `/me` dashboard.
  - Magic-link login and force-password-reset flow.
  - Banned, dismissed, and deactivated accounts are blocked from authenticated routes.
  - Concurrent-session limit enforces a maximum of three tracked sessions per user.
- Fixed a regression in `RegistrationGateFilter` where a hardcoded singular onboarding table name caused banned/dismissed/pending_profile gating to be silently skipped. The filter now delegates table resolution to `UserRegistrationRequestModel`.
