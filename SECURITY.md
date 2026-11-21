# ekklesiaONLINE — Recommended Security Policy

**Status:** Recommendation for adoption (drafted 2026-11-21)
**Scope:** `ekklesiaonline/ekklesiaONLINE` — CodeIgniter 4.7 / Shield 1.4 / PHP 8.5 multi-parish management system.
**Basis:** Completed per-module security audits (`PROVINCE_SECURITY_AUDIT.md`, `DIOCESE_SECURITY_AUDIT.md`, `PARISH_SECURITY_AUDIT.md`, `CLERGY_SECURITY_AUDIT.md`, `FINANCE_SECURITY_AUDIT.md`, `VOLUNTEER_SECURITY_AUDIT.md`, `WORSHIP_SECURITY_AUDIT.md`) and their remediations.

---

## 1. Purpose

ekklesiaONLINE stores sacramental records, member PII, financial ledgers, and clergy canonical data for multiple parishes under a strict tenant hierarchy (system → province → diocese → parish → personal). This policy defines how vulnerabilities are reported and triaged, which releases are supported, the security invariants every change must preserve, and the operational controls required in production.

## 2. Supported Versions

| Version | Supported |
|---|---|
| `main` (current release) | Yes — security fixes land here first |
| Previous minor release | Critical/high fixes only, until next release +30 days |
| Older releases | No |

Security fixes are never backported to closed accounting-period migrations or retired modules (`MemberPortal` legacy paths, deprecated tables such as `user_scope_assignment`).

## 3. Reporting a Vulnerability

**Do not open a public issue or PR for a suspected vulnerability.**

1. Report privately to the maintainers via GitHub private vulnerability reporting (or the security contact configured in repository settings).
2. Include: affected route/module, actor role needed to exploit, tenant-scope impact, reproduction steps, and whether data crosses a `parish_id`/`diocese_id`/`province_id` boundary.
3. Expected response:
   - Acknowledgement within **3 business days**.
   - Triage and severity assignment within **7 days**.
   - Critical/high: fix target **14 days**; medium/low: next scheduled remediation batch.
4. Reporters are credited in the release notes unless they request anonymity. No bug bounty is offered at this time.

### Severity taxonomy (matches the audit convention)

| Severity | Definition | Example from repo audits |
|---|---|---|
| **High** | Cross-tenant data access or privilege bypass without insider knowledge | Filter-coverage gaps (`diocesanOperationalBoundary`, `adminFinanceBoundary`), IDOR across scopes |
| **Medium** | State change or data exposure reachable by an authenticated actor abusing a legitimate route | Unthrottled credential/test mailers, CSV formula injection, state-changing GETs |
| **Low** | Hardening gaps requiring chained conditions | Missing audit events, weak validation on non-critical fields |
| **Info** | Dead code, schema drift, documentation gaps | Unused filters, deprecated helpers |

## 4. Security Invariants (must hold on every merge)

These are enforced today and are the acceptance criteria for any new feature:

### Tenant isolation
- Every query is scoped by `parish_id` (or the actor's derived diocese/province) unless the actor holds an elevated Shield permission. Scopes are re-derived server-side from `profile.*` — **never** from request parameters.
- Route coverage: `parishScope`, `dioceseScope`, `provinceScope`, `diocesanOperationalBoundary`, `adminFinanceBoundary` before-lists must include every new route prefix at creation time. Filter-coverage gaps were the single largest audit finding class (W-1, F-1, F-2, D3) — add a route/filter regression test with any new prefix.

### Authorization
- Sensitive pastoral/governance actions require: authenticated user + Shield route permission + verified order (when ordained) + approved office/delegation + exact scope + uninhibited canonical standing. Account mutations must pass `AdminHierarchyGuard::guardTargetUserMutation`.
- Read permissions (`*.view`) never gate writes (see W-2, P-2).
- Legacy/alias routes must carry the same granular `permission:*` filters as their canonical counterparts, or be deleted (Province F1).

### Input handling
- All state changes via POST/PUT/DELETE through `$this->validate()` / `Validation` rules; no mutations in GET handlers (F2, F3, F8 audits).
- CI4 Models / Query Builder only; no unescaped raw SQL. `parish_id` scoping on every tenant query.
- CSV/export sinks neutralize formula injection (`csvSafe`-style prefix for `=`, `+`, `-`, `@`, tab, CR).
- Uploads: allowlisted MIME/extension, size caps, stored outside web root under `writable/uploads`, served only through scoped controller endpoints (the `uploads/*` 404 + `media/*` pattern). Row-count caps on imports.

### Sessions & credentials
- Shield `session` filter on all authenticated routes; `sessionTracking` enforces max 3 concurrent sessions.
- `deviceVerification` and `forcePasswordReset` are global before-filters in production; `security.disableDeviceVerificationForTesting` must never be enabled in production (guarded by `ProductionSecurityPosture`).
- Admin-initiated password resets always set `force_reset = 1` and revoke the target's sessions and trusted devices (`SystemAdminUserService::updatePasswordIdentity`).
- `loginRateLimit` on auth and device-verification endpoints; `apiRateLimit` on every mutation, credential-mail, and outbound-send endpoint.

### Output & browser protections
- `esc()` on all rendered output; `textContent` (not `innerHTML`) for server-derived strings in JS (`showAppToast` fix, F11).
- CSP is enforced in every environment (`default-src 'self'`); new inline scripts/styles require `{csp-script-nonce}` / `{csp-style-nonce}` placeholders; `script-src-attr 'unsafe-inline'` is a documented temporary concession.
- CSRF: `tokenRandomize = true`; native `fetch()` POSTs must use the meta-tag token pattern in `.devin/rules/views.md`.

### Secrets & config
- No secrets in the repo. `.env` is never committed; `.env.example` uses `CHANGE_ME` placeholders. SMTP passwords are encrypted at rest and never re-rendered into forms (F4).
- Test mailers must never fall back to a stored `smtp_pass` when host/user were submitted (F4 — prevents credential exfiltration to a rogue host).

### Audit & observability
- Mutations on sensitive entities (accounts, positions, finance, reports, teams) emit `AuditService` events with the standard redaction set: credentials, contact data, financial values, freeform notes.
- Permission-denied logs are rate-limited; PII never appears in application logs (Volunteer F6).

## 5. Secure Development Lifecycle

1. **CI gates** (`.github/workflows/ci.yml`): `composer validate --strict`, `composer audit --locked` (blocks on vulnerable locked dependencies), PHPStan, PHPUnit. `nightly-security-rate-limit.yml` probes the live rate limits nightly. Security-fix PRs must include a regression test reproducing the finding.
2. **Review:** every PR requires review; security-touching changes (filters, authz, uploads, mail, crypto) require sign-off from a maintainer familiar with the module's audit.
3. **Per-module audits:** the seven root-module audits are complete and remediated. New modules get an audit before enabling in production; audits repeat on a **quarterly** cadence or after any authz-model change.
4. **Dependencies:** CI4 framework/Shield track latest minor; `composer audit` failures block merge. PHP version floor follows CI4 requirements (currently 8.5).
5. **Branch protection:** `main` requires passing CI; direct pushes disabled.

## 6. Data Protection

- **Encryption at rest:** governed by `docs/ENCRYPTION_AT_REST_RELEASE_GATE.md` — the gate checklist is a hard release blocker. `encryption.key` and `encryption.blindIndexKey` are distinct high-entropy values; `encryption.previousKeys` supports rotation without re-encryption downtime.
- **PII classes:** member contact data, sacramental records, household/children data, donor financial data. Access requires the scoped-authorization formula above; exports are permission-gated, rate-limited, and formula-injection-safe.
- **Backups:** `backup.*` env config, 30-day retention default, stored under `writable/backups` outside `public/`. Backup credentials are least-privilege and distinct from the app DB user.
- **Canonical standing** (`clergy_canonical_status` suspended/inhibited) hard-blocks ministerial, signatory, and office functions system-wide — a data-integrity control, not just a business rule.

## 7. Production Deployment Checklist

Before any production promotion (complements `docs/DEPLOYMENT_RUNBOOK.md` and the edge playbook):

- [ ] `CI_ENVIRONMENT=production`, `app.baseURL` is `https://`, `app.forceGlobalSecureRequests=true`, `app.CSPEnabled=true`.
- [ ] `security.disableDeviceVerificationForTesting` absent/false; `ProductionSecurityPosture` emits no critical log on boot.
- [ ] Edge layer: `build/nginx/ekklesiaONLINE-rate-limit.conf` applied (login 10/min, writes 60/min auth / 20/min anon, `client_max_body_size 12m`); WAF rules per `docs/EDGE_RATE_LIMIT_AND_WAF_PLAYBOOK.md`.
- [ ] Encryption-at-rest release gate passed (max 2 conditional items).
- [ ] Scheduled jobs live: `events:publish-due` (replaces GET-side lazy publishing), backup, reminder queues.
- [ ] Credential-mail transport verified on system scope only; scoped tenant mail uses `EmailScopeService` sender identity.
- [ ] `writable/` not web-accessible; `uploads/*` catch-all returns 404 outside scoped serving.

## 8. Incident Response

1. **Contain:** revoke sessions (`sessionTracking`), disable compromised accounts, rotate `encryption.key`/`blindIndexKey` and SMTP/DB credentials if exfiltration is suspected.
2. **Assess blast radius by tenant:** enumerate affected `parish_id`/`diocese_id` from `AuditService` logs and request monitoring.
3. **Notify:** affected parish/diocese administrators; data-subject notification follows applicable church and civil requirements.
4. **Remediate:** fix on a hotfix branch, regression test, expedited review, deploy, and record a post-incident note alongside the module audit files.
5. **Post-incident:** add the finding class to this policy's invariants if novel; update the relevant audit document's "verified clean" section.

## 9. Residual Known Gaps (track to closure)

From the audits — accepted/deferred items that this policy treats as tracked debt:

- `SystemEvents` and `Clergy\Controllers\Events` still lazy-publish drafts on GET (Province F8 residual) — move them to the scheduled `events:publish-due` command, which already exists and covers Province.
- `script-src-attr 'unsafe-inline'` CSP concession — migrate inline handlers to `addEventListener`, then drop the keyword.
- `upgrade-insecure-requests` disabled for local HTTP dev — enable at production rollout.
- `diocese-admins` mutation routes were kept and hardened (granular `province.diocese_admin.manage` filter, `apiRateLimit`, `AdminHierarchyGuard`, POST-only); `quick-create-parish` likewise kept behind the canonical diocesan gate with validation. If product intent is read-only province access to parish staff, deleting them remains the lower-risk option.

## 10. Adoption Checklist

- [ ] Publish as `SECURITY.md` at repo root (GitHub surfaces it in the Security tab) and link from `README.md`.
- [ ] Enable GitHub private vulnerability reporting + Dependabot alerts for `composer.lock`.
- [ ] Configure a `security@` contact address routed to maintainers.
- [ ] Add this policy's invariant list to `.devin/rules/` and the PR template checklist.
