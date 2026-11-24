# Encryption at Rest Release Gate (Canonical)

This document is the production release gate template for Section 2.2 (Data Encryption at Rest).
Use it as a required checkpoint before promoting a release to production.

## Policy

- Any `Fail` on items 1, 2, 3, 4, 5, or 9 blocks release.
- Maximum `Conditional Pass` allowed: 2 items.
- Every gate item must include linked evidence before final approval.
- Evidence must come from the target release candidate environment or an approved production-like environment.

## Release Metadata

- Release Name: ____________________
- Version/Tag: ____________________
- Environment: ____________________
- Change Window: ____________________
- Date: ____________________
- Release Manager: ____________________
- Security Reviewer: ____________________
- DevOps Reviewer: ____________________
- DBA Reviewer: ____________________

Final Decision: `Pass` / `Fail` / `Conditional Pass`

## Gate Checklist

| # | Control | Status (Pass/Fail/Conditional) | Required Evidence | Owner | Reviewer | Notes |
|---|---|---|---|---|---|---|
| 1 | Sensitive data inventory is complete and approved |  | Data classification register with encrypted fields (`profile.phone`, `profile.address`, `donations.amount`, `guests.phone`, etc.); architecture approval note; migration diff showing no unclassified sensitive fields | App Lead | Security | Agent draft: inventory verified in code — see Evidence Register #1. Approval still required. |
| 2 | Cryptography implementation is secure and authenticated |  | Crypto design note (algorithm/mode, nonce/IV handling, auth tag); framework config snapshot; tamper/failure test results | Security Eng | Security | Agent draft: AES-256-CTR + HMAC-SHA512 via CI4 OpenSSLHandler; `enc:v1:` envelope; roundtrip test exists, dedicated tamper tests absent — see #2/#8. |
| 3 | Key material is externalized and access-controlled |  | KMS/secret manager policy export; proof keys are not in repo or DB rows; IAM least-privilege policy; key-access audit logs | DevOps | Security | OPERATOR ITEM — HostGator env/secrets handling to be evidenced at deploy. |
| 4 | Key versioning/envelope strategy is implemented |  | Schema/metadata showing `key_version`; sample records across versions; tests proving version-aware decrypt path | App Lead | Security | Partial agent evidence: `enc:v1:` prefix is the version marker; `encryption.previousKeys` gives decrypt fallback. Operator to confirm rotation/version strategy. |
| 5 | Key rotation runbook is tested end-to-end |  | Rotation SOP; drill report (new key, write switch, re-encrypt/lazy re-encrypt, validation, rollback); post-rotation read/write validation | DevOps | Security + QA | OPERATOR ITEM — no rotation SOP/drill exists yet. |
| 6 | Query/search strategy does not leak plaintext |  | Blind index/hash design; query examples using index fields only; log inspection report (app/db/debug logs) proving no plaintext leakage | Backend Lead | Security | Agent draft: HMAC-SHA256 blind indexes (`phone_hash`, `email_hash`) verified; exact-match search only — see #6. Log inspection pending on RC env. |
| 7 | Decryption access boundaries are enforced |  | RBAC matrix for decrypt-capable paths; authorization test results (allow/deny); audit samples for decrypt operations | Backend Lead | Security | Agent draft: decryption is model-layer (`afterFind`), gated by existing scope filters; no per-decrypt audit event — gap noted, see #7. |
| 8 | Performance and error-path behavior is validated |  | Load/perf report with encryption enabled and SLO comparison; tests for missing key/corrupt ciphertext/invalid tag; safe user-facing error samples | QA | QA Lead | Agent draft: fail-closed error paths verified in code; dedicated corrupt-ciphertext/tamper tests absent — see #8. Perf report pending on RC env. |
| 9 | Backup and restore are verified with encryption |  | Backup job logs; restore rehearsal report; proof restored data decrypts correctly; environment key separation proof; RTO/RPO measurements | DBA + DevOps | Security + Ops | Partial: B2 local restore drill passed (2026-11-23) but decrypt-on-restored-data not explicitly verified; key separation + prod rehearsal pending. |
| 10 | Monitoring, alerting, and compliance evidence are complete |  | Alerts for key access/decrypt failures; dashboard screenshots; incident playbook; evidence bundle (SOPs, approvals, test artifacts) | Security + Ops | Release Manager | OPERATOR ITEM — decrypt failures log via `log_message('error', …)`; alert wiring TBD. |

## Evidence Register

| Item # | Evidence Link/Path | Collected On | Verified By | Notes |
|---|---|---|---|---|
| 1 | `app/Libraries/DataEncryption.php`; `app/Models/ProfileModel.php` (`encryptSensitiveFields`/`decryptSensitiveFields`, fields `phone`, `address`, `address_line_2`); `app/Models/PersonModel.php` (`email`, `phone`); `app/Models/DonationModel.php` (`amount`); `app/Models/FamilyModel.php` (`phone`); `app/Models/MemberParishAffiliationModel.php` (`phone`, `address`); `app/Models/ParishModel.php` (`phone`); `app/Models/FundModel.php` (`amount`); `app/Models/{Diocese,Province,Parish,Organisation,System}SettingModel.php` (`value` gated by `is_encrypted=1`, incl. SMTP credentials via `app/Services/EmailSettingsService.php`); `tests/database/DataEncryptionAtRestTest.php` | 2026-11-24 | Agent (code inspection + test) | Fields verified via model encrypt/decrypt hooks; `person.email_hash`/`phone_hash` and `profile.phone_hash` blind-index columns confirmed by test assertions on stored rows. Guest table dropped — canonical `person`/`parish_affiliation` now hold contact data. Architecture approval + classification sign-off still required from App Lead/Security. |
| 2 | `app/Config/Encryption.php` (OpenSSL driver, `AES-256-CTR`, `digest=SHA512`, `rawData=true`); `app/Libraries/DataEncryption.php` (`enc:v1:` prefix + base64 envelope, delegates to `service('encrypter')`); CI4 `OpenSSLHandler` = AES-CTR encryption + HMAC-SHA512 authentication tag; `tests/database/DataEncryptionAtRestTest.php::testProtectedFieldsAreStoredAsCiphertextAndReadAsPlaintext` | 2026-11-24 | Agent (code inspection + test) | CI4 OpenSSLHandler provides encrypt-then-MAC; decrypt failures throw and are caught in `DataEncryption::decrypt` → logged + null. **Gap:** no dedicated tamper/corrupt-ciphertext/invalid-tag test in suite — candidate for Conditional Pass or a follow-up test ticket. |
| 3 | | | | OPERATOR — KMS/secret-manager evidence to be collected on HostGator deploy. |
| 4 | `app/Libraries/DataEncryption.php` (`PREFIX = 'enc:v1:'` — version marker in every ciphertext); `app/Config/Encryption.php` (`$previousKeys` array, env `encryption.previousKeys` comma-separated `hex2bin:` entries; decrypt tries current key then previous) | 2026-11-24 | Agent (code inspection) | Version-aware decrypt path exists via `previousKeys` fallback. Operator to confirm key-versioning strategy + sample records across versions. |
| 5 | | | | OPERATOR — rotation SOP + drill required. |
| 6 | `app/Libraries/DataEncryption.php::blindIndex/phoneIndex/emailIndex` (HMAC-SHA256 over normalized value, keyed by `encryption.blindIndexKey` ?: `encryption.key`, fail-closed `RuntimeException` when unset); search paths use `phone_hash`/`email_hash` columns only (`ProfileModel` keyword search line ~224, `PersonModel`, `PeopleDirectoryService`, `VisitorIntakeService`); `tests/database/DataEncryptionAtRestTest.php::testExactPhoneAndEmailSearchStillResolveThroughBlindIndexes`; `tests/unit/TenantScopeAndContactHashTest.php` (keyed-HMAC, phone-digit-strip, typed-index usage assertions) | 2026-11-24 | Agent (code inspection + tests) | Exact-match lookups only — no `LIKE` on ciphertext fields. Log inspection for plaintext leakage must run against RC environment logs. **Risk flagged:** `app/Services/PersonContactService.php::hash()` catches blind-index failure and falls back to **unsalted SHA-256** (lines 59–66) for legacy-row compatibility — if `encryption.key`/`blindIndexKey` is ever unset in prod, indexes silently degrade to rainbow-table-enumerable hashes. Verify prod `.env` sets the key (ties to item 3); consider gating the fallback to non-production envs. |
| 7 | Decryption occurs in model layer (`afterFind` hooks + explicit `decrypt*Row` methods) — there is no separate decrypt-capable role; access = whatever scope filters already gate the row (`parishScope`, `dioceseScope`, `permission:*`). Admin display paths decrypt under admin auth (`app/Controllers/SystemAdminUsers.php` lines ~667/744/790, `ProvinceDioceseAdmins.php`, `DioceseParishAdmins.php`, `DioceseAdminUsers.php`). `audit()->log()` convention exists for critical writes | 2026-11-24 | Agent (code inspection) | **Gap:** no per-decrypt audit event — decryption is transparent to authorized readers. If the gate requires decrypt-operation audit samples, this is a Conditional Pass candidate needing either an audit hook or documented risk acceptance. |
| 8 | `app/Libraries/DataEncryption.php::decrypt` — `try/catch (Throwable)` → `log_message('error', 'Failed to decrypt protected field')` → returns `null` (no plaintext leak, no exception to caller); malformed `enc:v1:` envelopes (empty payload or invalid Base64) also log an error and return `null`, so every decrypt-failure path is visible to log-based monitoring; `encrypt` idempotent + null/empty passthrough; `blindIndex` fail-closed on missing key | 2026-11-24 | Agent (code inspection) | Error paths verified by inspection; user-facing surfaces show empty field, not stack traces (generic 500 contract per API rules). **Gap:** no dedicated corrupt-ciphertext/invalid-tag/missing-key test, no load/perf report on RC env. |
| 9 | `build/backup_database.sh` + `build/restore_database.sh`; B2 isolated restore drill evidence in `docs/LAUNCH_READINESS.md` (2026-11-23, 189 objects, row-count parity) | 2026-11-23 | Agent + Operator | Decrypt-on-restored-data not explicitly verified in drill; prod rehearsal + key separation proof pending on HostGator. |
| 10 | `DataEncryption::decrypt` failure logging (`log_message('error', …)`) is the only decrypt-failure signal found; no dedicated alert/dashboard/playbook located | 2026-11-24 | Agent (code inspection) | OPERATOR — monitoring/alerting + incident playbook to be defined for prod. |

## Conditional Pass Exception Log

| Item # | Gap Description | Risk Level | Mitigation Plan | Owner | Due Date | Approved By |
|---|---|---|---|---|---|---|
|  |  | Low / Medium / High |  |  |  |  |

## Approval Matrix

- Security Approval: `Pass` / `Fail`
- QA Approval: `Pass` / `Fail`
- Operations Approval: `Pass` / `Fail`
- Release Manager Approval: `Pass` / `Fail`

- Approved Release Window: ____________________
- Blocking Issues (if any): ____________________
- Go-Live Decision: `Approved` / `Not Approved`

## Minimum Test Pack (Recommended)

1. Encrypt/decrypt unit tests for all protected model fields.
2. Integrity-failure tests (modified ciphertext/auth tag).
3. Key-version compatibility tests (old + new keys).
4. Rotation simulation test (pre/post-rotation reads and writes).
5. Backup/restore rehearsal with decrypt validation on restored dataset.
6. Authorization tests for decrypt-protected endpoints.

## Usage Notes

- Store all referenced evidence in your release artifacts location and link it in the Evidence Register.
- If any blocking gate item fails, open a release-blocker ticket and rerun this gate after remediation.
- Reuse this file for every release by copying this template into release-specific records.
