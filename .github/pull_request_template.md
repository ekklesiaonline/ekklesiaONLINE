## Summary

<!-- What changed and why. Link related issues, audits, or SECURITY.md sections. -->

## Security checklist

<!-- Invariants from SECURITY.md §4 — every box must hold for merge. See .devin/rules/security.md. -->

- [ ] **Tenant isolation:** queries scoped by `parish_id`/`diocese_id`/`province_id` derived server-side (never from request params) unless the actor holds an elevated Shield permission; new route prefixes covered by the correct isolation filters with a route/filter regression test.
- [ ] **Authorization:** sensitive actions require Shield permission + exact scope + `AdminHierarchyGuard` where applicable; `*.view` never gates writes; alias routes keep granular `permission:*` filters.
- [ ] **Input handling:** mutations via POST/PUT/DELETE through `$this->validate()` / `Validation` rules (no mutations in GET); Models/Query Builder only (no raw SQL); CSV exports formula-injection-safe; uploads allowlisted, size-capped, served via scoped endpoints.
- [ ] **Sessions & credentials:** `loginRateLimit`/`apiRateLimit` on auth, mutation, credential-mail, and outbound-send endpoints; no new unauthenticated state changes.
- [ ] **Output:** `esc()`/`textContent` on server-derived strings; CSP nonces for any new inline script/style.
- [ ] **Secrets:** no credentials, keys, or PII in code, logs, fixtures, or `.env` changes.
- [ ] **Audit:** sensitive mutations emit `AuditService` events with the standard redaction set.
- [ ] **Tests:** `vendor/bin/phpunit --no-coverage` passes; security-touching changes (filters, authz, uploads, mail, crypto) include a regression test.

## Notes for reviewers

<!-- Flag anything needing maintainer sign-off: filters, authz, uploads, mail, crypto. -->
