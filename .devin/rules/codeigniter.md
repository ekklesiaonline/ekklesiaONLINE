---
description: CodeIgniter 4 + Shield coding conventions for ekklesiaRD
trigger: always_on
---

# CI4 / Shield Conventions

## Application Architecture & Modules

- Application space comprises shared code under `app/` and 8 root-level PSR-4 feature modules: `Province/`, `Diocese/`, `Clergy/`, `Parish/`, `Worship/`, `Treasurer/`, `Volunteer/`, and `MemberPortal/`. Never modify files inside `system/` or `vendor/`.
- Organize controllers in `app/Controllers/` or the owning module (`<Module>\Controllers\*`). Views inside modules are referenced via `<Module>\Views\<Subfolder>/<view>` (e.g., `view('Treasurer\Views\Banking/index', $data)`).
- Route files: module routes live in `<Module>/Config/Routes.php` and are loaded by `app/Config/Routes.php`.

## Boundary Enforcement Filters & Security

- **Multi-Tier Scope Isolation**: Routes must be gated with appropriate isolation filters defined in `Config\Filters`:
  - `parishScope`: Scopes request context to operational/home parish.
  - `dioceseScope` / `provinceScope`: Enforces regional tenancy boundaries.
  - `diocesanOperationalBoundary`: Blocks diocesan admins from direct parish operational mutations.
  - `adminFinanceBoundary`: Enforces financial tenant separation.
  - `deviceVerification`: Enforces browser fingerprint verification challenge in production.
  - `sessionTracking`: Enforces maximum concurrent session limits (max 3).
- Use Shield’s `auth()->user()` to access the authenticated user.
- Protect routes with Shield filters (`session`, `permission:*`) and validate permissions inside controllers.
- Never bypass `pending_review` gating except via `BaseController::canBypassPendingReviewGate()`.
- **Shield Groups vs. Ecclesial Orders/Offices**: Never create Shield groups for `bishop`, `priest`, `deacon`, `rector`, `cathedral_dean`, or `regional_dean`. Shield groups represent coarse app account tiers (`systemadmin`, `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, and lay). The `clergy` group is a portal-compatibility role, not ordination proof.
- **Scoped Authorization Formula**: Sensitive pastoral and governance actions require: authenticated user + Shield route permission + active person-linked verified order (when an ordained function) + currently approved compatible office/delegation + exact target scope + current canonical standing (uninhibited/unsuspended). Account mutations additionally enforce `AdminHierarchyGuard`.
- **Managed Access Grants (`clergy_access_grant`)**: Permissions scoped to entities must track provenance (`grant_type`: `office` vs `delegation`, `source_position_id`, `granted_by_user_id`, term limits). Revocation removes only managed permissions whose backing sources are terminated; never wipe unmanaged/manual direct grants blindly.

## Validation

- Use CI4’s `Validation` class for every form/POST endpoint.
- Return validation errors through the standard CI4 pattern or JSON for AJAX.

## Models & Casts

- Models live in `app/Models/` or module namespaces.
- Cast types must use the nullable prefix form: `?int`, `?string`, `?bool`, `?datetime`.
- Never use suffix casts like `int?` or `string?`.

## Code Generation

- Use `php spark` commands to generate controllers, models, migrations, seeders, and tests.
- Do not create CI4 boilerplate files by hand.

## Tests

- Write tests with `CIUnitTestCase`.
- Prefer `php spark make:test` and follow existing test conventions.
- Run tests before declaring a task complete.

## Queries

- Use Query Builder or prepared statements.
- Parish-scope queries by `parish_id`; elevated admins can cross scopes explicitly.

## BaseController Patterns

- Profile is lazy-loaded via `getProfile()`; do not eagerly load it in `initController()`.
- Use `$this->audit()->log(...)` for audit events; redact credentials, contact data, financial values, and freeform notes.
- Permission-denied logging is rate-limited to 10 entries/hour per user.
- Localization resolution order: cookie → session → `profile.locale` → `Accept-Language` → default (`en`).
- Parish attribution policies:
  - `ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT`: operational parish first, home parish fallback.
  - `ATTRIBUTION_POLICY_HOME_DEFAULT`: home parish first, operational parish fallback.
- Use `db_connect()` for ad-hoc queries; prefer models for domain operations.

## Email

- Resolve sender identity and SMTP only through `EmailScopeService` (`senderConfig()`, `mailer()`, `systemMailer()`); never read `email.*` settings directly in senders.
- Feed `Email::setFrom()` only from `senderConfig()` output. It sanitizes `from_name` of RFC 5322 address-list specials — a literal `,` inside a Q-encoded display name makes Exchange reject with `550 5.1.20`.
- Credential-bearing mail (activation, magic link, OTP, device verification, password-change notices) must use `credentialMailer($scope)` — System transport with a System-aligned From mailbox plus the tenant display name and Reply-To. Never put a tenant `from_email` on System transport (DMARC misalignment).
- For delivery failures and provider alignment, use the `/email-deliverability` skill and `docs/EMAIL_DELIVERABILITY.md`.

## API/AJAX Responses

- Follow `docs/API_AJAX_ERROR_CONTRACT.md`.
- Success shape: `{ "success": true, "status": "success", "message": "...", "meta": { "http_status": 200, "ts": "..." } }`.
- Error shape: `{ "success": false, "status": "error", "message": "...", "error": { "code": "...", "message": "...", "details": {} }, "errors": {}, "meta": {} }`.
- `error.code` is mandatory for new/updated endpoints.
- Use generic messages for 500s; never return raw exception text.
- HTTP status map: 400 malformed, 401 unauthenticated, 403 forbidden/scope, 404 not-in-scope, 409 conflict, 422 validation, 429 rate-limited, 500 server error.
