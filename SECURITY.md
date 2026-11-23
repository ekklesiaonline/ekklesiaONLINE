# Security Policy

**Status:** Adopted (drafted 2026-11-21)
**Scope:** `ekklesiaonline/ekklesiaONLINE` — CodeIgniter 4.7 / Shield 1.4 / PHP 8.5 multi-parish management system.
**Basis:** Completed per-module security audits (`PROVINCE_SECURITY_AUDIT.md`, `DIOCESE_SECURITY_AUDIT.md`, `PARISH_SECURITY_AUDIT.md`, `CLERGY_SECURITY_AUDIT.md`, `FINANCE_SECURITY_AUDIT.md`, `VOLUNTEER_SECURITY_AUDIT.md`, `WORSHIP_SECURITY_AUDIT.md`) and their remediations.

ekklesiaONLINE is developed and deployed continuously from the `main` branch.
There are no maintained release branches or tags; security fixes land on `main`
and reach production through the standard deployment runbook
(`docs/DEPLOYMENT_RUNBOOK.md`).

| Version | Supported |
| ------- | --------- |
| Latest `main` (deployed production) | :white_check_mark: |
| Older deployments | :x: — upgrade to current `main` |

## Reporting a Vulnerability

**Do not open a public issue, pull request, or discussion for security
vulnerabilities.**

Report privately using one of the following channels:

1. **GitHub Private Vulnerability Reporting** — if enabled on this repository,
   use *Security → Advisories → Report a vulnerability*.
2. **Email** — `security@ekklesiaonline.org`. Include enough detail for us to
   reproduce and assess the issue.

Please include in your report:

- A description of the vulnerability and its impact.
- Affected module, route, or component (e.g. `Parish`, `Finance`,
  `MemberPortal`, a specific controller or filter).
- Steps to reproduce, proof-of-concept code or requests, and screenshots if
  applicable.
- Your assessment of severity and any suggested remediation.
- Whether the issue involves tenant/scope isolation, authentication, or
  sensitive data exposure (see *Priority Areas* below).

## What to Expect

- We aim to acknowledge reports within **5 business days**.
- We will triage, reproduce, and assign a severity; we may ask for additional
  information during this phase.
- Critical and high-severity issues are remediated on a prioritized basis and
  deployed per the release gate in `docs/DEPLOYMENT_RUNBOOK.md`.
- Coordinated disclosure: once a fix is deployed, we will credit reporters who
  wish to be named (unless you prefer anonymity). Please give us reasonable
  time to remediate before any public disclosure.

## Scope

**In scope:**

- The ekklesiaONLINE application code under `app/` and the feature modules
  (`Province/`, `Diocese/`, `Clergy/`, `Parish/`, `Worship/`, `Finance/`,
  `Volunteer/`, `MemberPortal/`).
- Authentication and session handling (CodeIgniter Shield integration,
  magic-link login, device verification, login email 2FA).
- Multi-tenant authorization: Shield permissions, scope filters
  (`provinceScope`, `dioceseScope`, `parishScope`), boundary filters, managed
  `clergy_access_grant` provenance, and `AdminHierarchyGuard`.
- Exposure of member, visitor, sacramental, or financial data across
  parish/diocese/province boundaries.
- Injection, XSS, CSRF bypass, insecure file upload/download, and other
  web-application vulnerabilities.
- Cryptographic or secrets-handling flaws (encryption keys, blind-index keys,
  SMTP/OAuth credentials, session tokens).
- Dependency vulnerabilities in `composer.lock` (we run `composer audit` in
  CI; still report unpatched advisories that affect this codebase).

**Out of scope:**

- Vulnerabilities in third-party services we integrate with (SMTP providers,
  Google APIs, hosting/CDN platforms) — report those to the vendor.
- Issues requiring physical access to a server or compromise of the hosting
  environment.
- Findings from automated scanners without demonstrated impact.
- Social-engineering attacks against staff or congregants.

## Priority Areas

Given the sensitivity of the data this system holds, we treat the following as
highest priority:

1. **Tenant isolation failures** — any path that lets a parish-, diocese-, or
   province-scoped actor read or mutate another tenant's data.
2. **Authorization bypasses** — Shield permission checks, scope/boundary
   filters, pending-review gating, or `AdminHierarchyGuard` evasion.
3. **Authentication weaknesses** — credential brute force, session fixation,
   magic-link or OTP weaknesses, device-verification bypass in production.
4. **PII and financial data exposure** — member/visitor records, sacramental
   records, donation and banking data leaking via logs, exports, email, or
   API responses.

## Security Practices

This repository already enforces a defense-in-depth baseline; reports that
contradict it are especially valuable:

- **Access control:** Shield groups for coarse tiers plus scoped permissions;
  canonical standing checks for clergy; `AdminHierarchyGuard` on account
  mutations.
- **Request hardening:** session-based CSRF with token randomization and
  per-request regeneration, `SecureHeaders`, nonce-based CSP
  (`docs/CONTENT_SECURITY_POLICY.md`), `ForceHTTPS` in production, honeypot
  filtering.
- **Abuse controls:** login and API rate-limit filters, concurrent-session
  limits, trusted-device verification, admin login email 2FA.
- **Auditability:** structured audit logging with credential/contact/financial
  redaction; permission-denied logging is rate-limited.
- **Supply chain & CI:** `composer validate --strict`, `composer audit
  --locked`, PHPStan, PHPUnit on PHP 8.5, a dedicated MySQL 8 migration job,
  and a production install dry-run — all gating `main`.
- **Operations:** staging rate-limit stress testing (`build/stress_rate_limit_test.sh`, `docs/EDGE_RATE_LIMIT_AND_WAF_PLAYBOOK.md`), deployment go/no-go
  gate, and the encryption-at-rest release gate
  (`docs/ENCRYPTION_AT_REST_RELEASE_GATE.md`).

Module-level security audits live at the repository root
(`*_SECURITY_AUDIT.md`); their findings and remediation status are tracked
there.

## Secure Configuration Reminders for Deployers

- Never commit `.env`; copy `.env.example` and replace every `CHANGE_ME`.
- Production requires `CI_ENVIRONMENT = production`, HTTPS `app.baseURL`,
  `app.forceGlobalSecureRequests`, and
  `security.disableDeviceVerificationForTesting` absent or `0`.
- `encryption.key` and `encryption.blindIndexKey` must be independent,
  high-entropy values. When rotating `encryption.key`, move the old value to
  `encryption.previousKeys` so existing ciphertext still decrypts. Blind
  indexes have no previous-key fallback: rotating `encryption.blindIndexKey`
  (or `encryption.key` when no blind-index key is set) invalidates every
  stored email/phone hash, and lookups fail until the indexes are rebuilt.
- Set `app.CSPEnabled = true` (the `App` config default); `.env.example`
  ships it disabled for local development — do not carry that into production.
