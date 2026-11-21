# Security Invariants

Canonical policy: `SECURITY.md` at repo root (reporting, severity taxonomy, SDLC, data protection, incident response). The invariants below are merge acceptance criteria; they mirror `SECURITY.md` §4 and must hold on every merge.

## Tenant Isolation

- Scope every query by `parish_id`/`diocese_id`/`province_id` derived server-side from `profile.*` — never from request parameters — unless the actor holds an elevated Shield permission.
- New route prefixes must be covered by the correct isolation filters at creation time: `parishScope`, `dioceseScope`, `provinceScope`, `diocesanOperationalBoundary`, `adminFinanceBoundary`. Add a route/filter regression test with any new prefix.

## Authorization

- Sensitive pastoral/governance actions require: authenticated user + Shield route permission + verified order (when ordained) + approved office/delegation + exact scope + uninhibited canonical standing.
- Account mutations must pass `AdminHierarchyGuard::guardTargetUserMutation`.
- `*.view` permissions never gate writes. Legacy/alias routes carry the same granular `permission:*` filters as their canonical counterparts, or are deleted.

## Input Handling

- State changes via POST/PUT/DELETE through `$this->validate()` / `Validation` rules; no mutations in GET handlers.
- CI4 Models / Query Builder only; no unescaped raw SQL.
- CSV/export sinks neutralize formula injection (`=`, `+`, `-`, `@`, tab, CR cell prefixes).
- Uploads: allowlisted MIME/extension, size caps, stored under `writable/uploads` outside the web root, served only through scoped controller endpoints. Row-count caps on imports.

## Sessions & Credentials

- `session` filter on all authenticated routes; `sessionTracking` enforces max 3 concurrent sessions.
- `deviceVerification` and `forcePasswordReset` are global before-filters in production; `security.disableDeviceVerificationForTesting` must never be enabled in production.
- Admin-initiated password resets always set `force_reset = 1` and revoke the target's sessions and trusted devices (`SystemAdminUserService::updatePasswordIdentity`).
- `loginRateLimit` on auth and device-verification endpoints; `apiRateLimit` on every mutation, credential-mail, and outbound-send endpoint.

## Output & Browser Protections

- `esc()` on all rendered output; `textContent` (not `innerHTML`) for server-derived strings in JS.
- CSP enforced in every environment; new inline scripts/styles require `{csp-script-nonce}` / `{csp-style-nonce}` placeholders. `script-src-attr 'unsafe-inline'` is a documented temporary concession.
- CSRF `tokenRandomize = true`; native `fetch()` POSTs use the meta-tag token pattern in `.devin/rules/views.md`.

## Secrets & Config

- No secrets in the repo; `.env` is never committed; `.env.example` uses `CHANGE_ME` placeholders.
- SMTP passwords are encrypted at rest and never re-rendered into forms; test mailers never fall back to a stored `smtp_pass` when host/user were submitted.

## Audit & Observability

- Mutations on sensitive entities (accounts, positions, finance, reports, teams) emit `AuditService` events with the standard redaction set: credentials, contact data, financial values, freeform notes.
- Permission-denied logs are rate-limited; PII never appears in application logs.

## Security-Sensitive Changes

- Changes to filters, authz, uploads, mail, or crypto require maintainer sign-off and a regression test reproducing the finding (`SECURITY.md` §5).
- Vulnerability reports go through GitHub private vulnerability reporting — never a public issue or PR (`SECURITY.md` §3).
