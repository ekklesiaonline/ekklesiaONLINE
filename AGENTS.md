# ekklesiaRD — Always-On Project Rules

## Architecture

- CodeIgniter 4.7+ with Shield 1.4+ (v1.4.1) for auth.
- Dependency management via Composer.
- Backend: PHP 8.5+, MySQL 8.0. Frontend: Bootstrap 5, jQuery/vanilla JS, Chart.js.
- Hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is the single source of truth for landing-path resolution.
- `BaseController::isElevatedAdmin()` is the single source of truth for top-tier admins.
- Deanery is a geographic scope (`diocese → deanery → parish`), not an administrative tier; never create Shield groups for holy orders or ecclesiastical offices (`bishop`, `priest`, `deacon`, `rector`, `dean`).
- New parish-tier dashboards live in the `Parish/` namespace under `/parish/*`.
- Old `/dashboard` and `/treasurer/dashboard` URLs are deprecated redirect shims.

## Code & Domain

- Use CI4’s `Validation` class and Shield helpers (`auth()->user()`).
- Validate requests; scope queries by `parish_id` unless the user is elevated.
- Model casts must use nullable prefix form: `?int`, `?string` (never `int?`, `string?`).
- Use `php spark` for code generation, not manual scaffolding.
- Resolve email sender identity and SMTP only through `EmailScopeService`; feed `Email::setFrom()` only from `senderConfig()` output (it sanitizes `from_name` of RFC 5322 specials that break Exchange header parsing).
- Credential-bearing mail (activation, magic link, OTP, device verification, password-change notices) must go through `EmailScopeService::credentialMailer($scope)` — System transport, System-aligned From mailbox, tenant display name/Reply-To.
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

- For view/HTML rules, see `.agents/rules/views.md` (or `.devin/rules/views.md`).
- For CI4/Shield coding rules, see `.agents/rules/codeigniter-shield.md` (or `.devin/rules/codeigniter.md`).
- For domain invariants, see `.agents/rules/membership-domain.md` (membership status, person identity, counts), `.agents/rules/sacraments.md` (sacramental records), `.agents/rules/hierarchy-authz.md` (scopes, elevated admins, registration approvals, clergy/deanery governance), and `.agents/rules/parish-finance.md` (donations, funds, stewardship) — or the `.devin/rules/` mirrors.
- For diagnostic playbooks, invoke the relevant skill (`/debug-no-parish`, `/debug-person-not-found`, `/sacrament-sync`, `/data-integrity`, `/email-deliverability`, etc.).
- For the visitor → regular_attender → member lifecycle and promotion flow, see `docs/VISITOR_LIFECYCLE.md` or `/visitor-lifecycle`; for follow-up sequences/dispatch, see `docs/FOLLOWUP_WORKFLOW_SYSTEM.md` or `/visitor-followup`.
- For permission/scope issues, use `/parish-permissions` or `/permission-denied`.
- For audit, observability, and localization questions, use `/audit-logging` or `/localization`.
- For Sunday attendance chart rules, use `/sunday-attendance-charts`.
- For clergy ordination, canonical status, deaneries, and scoped authorization planning, use `/episcopal-clergy-authz-planning`.

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

## Session 8: From-Header Sanitization

- Root cause of Exchange `550 5.1.20` ("Multiple From addresses are not allowed without a Sender address") bounces: a scoped `email.from_name` containing both a comma and a non-ASCII character (e.g. `Iglesia Episcopal La Transfiguración, Bani RD`). `Email::setFrom()` Q-encodes non-ASCII names via `iconv_mime_encode()`, leaving the comma literal inside the encoded word, so Exchange parses `From:` as two mailboxes.
- Fix: `EmailScopeService::senderConfig()` now sanitizes `from_name` through `sanitizeFromName()` (strips `,;:@<>"()[]\` runs to a single space). All send paths consume `senderConfig()`, so they are covered.
- Verified live: test emails accepted by both iCloud and Outlook.com after the fix.
- Full suite: 640 tests, 2,828 assertions, 6 skipped.
