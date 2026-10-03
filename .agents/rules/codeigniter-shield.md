---
description: CodeIgniter 4.7+ & Shield 1.4+ standards and ekklesiaRD domain conventions
trigger: always_on
---

# CodeIgniter 4.7+ & Shield 1.4+ Development Standards

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
- Utilize class-based routing arrays (`$routes->get()`) and protect routes with Shield filters (`session`, `permission:*`).
- **Authentication**: Use `auth()->user()`, `auth()->loggedIn()`, `auth()->id()`. Never inspect raw session keys manually.
- **User Discovery**: Use `auth()->getProvider()` to fetch/manipulate user instances rather than instantiating raw DB models directly.
- **Config & Actions**: Do not modify `Config/Auth.php` or `Config/AuthGroups.php` without ensuring the `Actions` array handles 2FA and email activation cleanly.
- Never bypass `pending_review` gating except via `BaseController::canBypassPendingReviewGate()`.
- **Shield Groups vs. Ecclesial Orders/Offices**: Never create Shield groups for `bishop`, `priest`, `deacon`, `rector`, `cathedral_dean`, or `regional_dean`. Shield groups represent coarse app account tiers (`systemadmin`, `provinceadmin`, `dioceseadmin`, `parishadmin`, `clergy`, and lay). The `clergy` group is a portal-compatibility role, not ordination proof.
- **Scoped Authorization Formula**: Sensitive pastoral and governance actions require: authenticated user + Shield route permission + active person-linked verified order (when an ordained function) + currently approved compatible office/delegation + exact target scope + current canonical standing (uninhibited/unsuspended). Account mutations additionally enforce `AdminHierarchyGuard`.
- **Managed Access Grants (`clergy_access_grant`)**: Permissions scoped to entities must track provenance (`grant_type`: `office` vs `delegation`, `source_position_id`, `granted_by_user_id`, term limits). Revocation removes only managed permissions whose backing sources are terminated; never wipe unmanaged/manual direct grants blindly.

## Validation & Forms
- Use CI4’s `Validation` class or `$this->validate()` for every form/POST endpoint.
- Always retrieve request data via `$this->request->getPost()`, `getGet()`, or `getJSON()`. Never trust global `$_POST` or `$_GET`.

## Models, Queries & Casts
- Cast types MUST use the nullable prefix form: `?int`, `?string`, `?bool`, `?datetime`. NEVER use suffix casts (`int?`, `string?`).
- Database actions must use CI4 Models or Query Builder (`$this->db->table()`). Do not write unescaped raw SQL.
- Always scope tenant queries by `parish_id` unless the user holds elevated permissions.
- Use `db_connect()` for ad-hoc queries; prefer Models for domain operations.

## BaseController Architecture
- Profile is lazy-loaded via `getProfile()`; do not eagerly load it in `initController()`.
- Use `$this->audit()->log(...)` for audit events; redact credentials, contact data, financial values, and freeform notes.
- Permission-denied logging is rate-limited to 10 entries/hour per user.
- Localization resolution order: cookie → session → `profile.locale` → `Accept-Language` → default (`en`).
- Parish attribution policies:
  - `ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT`: operational parish first, home parish fallback.
  - `ATTRIBUTION_POLICY_HOME_DEFAULT`: home parish first, operational parish fallback.

## Email & Deliverability
- Resolve sender identity and SMTP only through `EmailScopeService` (`senderConfig()`, `mailer()`, `systemMailer()`). Never read `email.*` settings directly in mailers.
- Feed `Email::setFrom()` strictly from `senderConfig()` output (it strips RFC 5322 punctuation from `from_name` to prevent Exchange `550 5.1.20` bounces).
- Credential-bearing mail (activation, magic link, OTP, device verification, password reset) must use `credentialMailer($scope)` on System transport with tenant display name/Reply-To (avoids DMARC misalignment).

## API & AJAX Responses
- Follow `docs/API_AJAX_ERROR_CONTRACT.md`.
- Success shape: `{ "success": true, "status": "success", "message": "...", "meta": { "http_status": 200, "ts": "..." } }`.
- Error shape: `{ "success": false, "status": "error", "message": "...", "error": { "code": "...", "message": "...", "details": {} }, "errors": {}, "meta": {} }`.
- `error.code` is mandatory for new/updated endpoints.
- Return generic messages on 500s; never leak raw exception traces.

## Scaffolding & Testing
- Use `php spark make:*` commands to generate boilerplate; do not write manual boilerplate from scratch.
- Write tests extending `CIUnitTestCase`.
- Run tests using `vendor/bin/phpunit --no-coverage`.
