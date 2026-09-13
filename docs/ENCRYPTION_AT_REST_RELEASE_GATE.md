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
| 1 | Sensitive data inventory is complete and approved |  | Data classification register with encrypted fields (`profile.phone`, `profile.address`, `donations.amount`, `guests.phone`, etc.); architecture approval note; migration diff showing no unclassified sensitive fields | App Lead | Security |  |
| 2 | Cryptography implementation is secure and authenticated |  | Crypto design note (algorithm/mode, nonce/IV handling, auth tag); framework config snapshot; tamper/failure test results | Security Eng | Security |  |
| 3 | Key material is externalized and access-controlled |  | KMS/secret manager policy export; proof keys are not in repo or DB rows; IAM least-privilege policy; key-access audit logs | DevOps | Security |  |
| 4 | Key versioning/envelope strategy is implemented |  | Schema/metadata showing `key_version`; sample records across versions; tests proving version-aware decrypt path | App Lead | Security |  |
| 5 | Key rotation runbook is tested end-to-end |  | Rotation SOP; drill report (new key, write switch, re-encrypt/lazy re-encrypt, validation, rollback); post-rotation read/write validation | DevOps | Security + QA |  |
| 6 | Query/search strategy does not leak plaintext |  | Blind index/hash design; query examples using index fields only; log inspection report (app/db/debug logs) proving no plaintext leakage | Backend Lead | Security |  |
| 7 | Decryption access boundaries are enforced |  | RBAC matrix for decrypt-capable paths; authorization test results (allow/deny); audit samples for decrypt operations | Backend Lead | Security |  |
| 8 | Performance and error-path behavior is validated |  | Load/perf report with encryption enabled and SLO comparison; tests for missing key/corrupt ciphertext/invalid tag; safe user-facing error samples | QA | QA Lead |  |
| 9 | Backup and restore are verified with encryption |  | Backup job logs; restore rehearsal report; proof restored data decrypts correctly; environment key separation proof; RTO/RPO measurements | DBA + DevOps | Security + Ops |  |
| 10 | Monitoring, alerting, and compliance evidence are complete |  | Alerts for key access/decrypt failures; dashboard screenshots; incident playbook; evidence bundle (SOPs, approvals, test artifacts) | Security + Ops | Release Manager |  |

## Evidence Register

| Item # | Evidence Link/Path | Collected On | Verified By | Notes |
|---|---|---|---|---|
| 1 |  |  |  |  |
| 2 |  |  |  |  |
| 3 |  |  |  |  |
| 4 |  |  |  |  |
| 5 |  |  |  |  |
| 6 |  |  |  |  |
| 7 |  |  |  |  |
| 8 |  |  |  |  |
| 9 |  |  |  |  |
| 10 |  |  |  |  |

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
