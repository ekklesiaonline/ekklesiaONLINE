---
description: CodeIgniter 4 + Shield coding conventions for ekklesiaRD
trigger: always_on
---

# CI4 / Shield Conventions

## Controllers & Auth

- Place Controllers in `app/Controllers/` or module namespaces (`Parish`, `Clergy`, `Diocesan`, `MemberPortal`).
- Use Shield’s `auth()->user()` to access the authenticated user.
- Protect routes with Shield filters (`session`, `permission:*`) and validate permissions inside controllers.
- Never bypass `pending_review` gating except via `BaseController::canBypassPendingReviewGate()`.

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
