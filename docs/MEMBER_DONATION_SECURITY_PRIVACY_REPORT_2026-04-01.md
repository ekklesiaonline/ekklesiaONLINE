# Member and Donation Data Security and Privacy Report

Date: 2026-04-01
Scope: Member-related and donation-related tables, models, and migrations
Audience: Engineering, Security, Product, Operations

## Purpose

This report identifies what is currently protected, what is still exposed,
and what must be implemented to make member and donation data secure, privacy-preserving,
and audit-ready for common data protection obligations (for example GDPR/CCPA-style principles).

## Scope and Data Map

### In-Scope Tables (Primary)

|Table|Data Type|Risk Level|
|---|---|---|
|profile|Member PII and profile attributes|High|
|member_parish_affiliations|Membership status and parish relationships|High|
|families|Household grouping and family address|High|
|family_members|Household relationship links|Medium|
|guests|Guest PII and follow-up data|High|
|donations|Financial transactions tied to members/parishes|High|
|annual_pledges|Planned/fulfilled giving commitments|High|
|expenses|Operational finance records|Medium|
|audit_logs|Security/event metadata and before/after values|High|
|parish_role_audit_logs|Role assignment history|Medium|

### Identity Linkage Tables (Sensitive by Association)

|Table|Data Type|Risk Level|
|---|---|---|
|users|Authentication account identity|High|
|auth_identities|Email/login identity secret field|High|
|auth_groups_users|Role/group assignments|Medium|

## Current Security and Privacy Posture

### Implemented Controls

Field-level encryption is implemented for member and guest contact fields:

- profile.phone (encrypted)
- profile.address (encrypted)
- guests.email (encrypted)
- guests.phone (encrypted)

Blind indexes are implemented for exact search:

- profile.phone_hash
- guests.email_hash
- guests.phone_hash

Multi-tenancy scoping is present at query/controller level via parish_id/affiliation boundaries.

Audit logging tables exist:

- audit_logs
- parish_role_audit_logs

### Major Gaps (Critical)

Donations and pledges are plaintext at rest.

- donations.amount is plain DECIMAL
- annual_pledges.pledged_amount and fulfilled_amount are plain DECIMAL
- donations.notes and annual_pledges.notes are plain TEXT

Family address is plaintext.

- families.address is plain TEXT

City/state/zip/country remain plaintext in profile.

- depending on your policy, these can be sensitive location data.

No explicit retention/deletion policy enforcement at table level.

- no data lifecycle jobs documented for old PII, logs, or finance notes.

No explicit consent/legal-basis records for guests and member contact usage.

- no per-purpose processing flags for communications/follow-up beyond followed_up workflow state.

No documented immutable audit policy for financial changes.

- audit table exists, but enforcement coverage and anti-tamper controls are not guaranteed.

Key management and rotation runbooks are not fully evidenced for production operation.

## Priority Findings and Required Actions

## Critical (Blocker Before Production-Grade Privacy)

Encrypt donation and pledge financial amounts at rest.

- Add encrypted columns plus blind indexes for required exact lookups.
- Recommended pattern:
  - donations.amount_enc (TEXT)
  - donations.amount_hash (CHAR(64), optional for exact-match checks)
  - annual_pledges.pledged_amount_enc (TEXT)
  - annual_pledges.fulfilled_amount_enc (TEXT)
- Keep aggregate/reporting compatibility by introducing derived reporting strategy:
  - Option A: maintain secure analytic projection table with least-PII.
  - Option B: tokenized values plus controlled decrypt aggregation service.

Encrypt high-risk free-text notes in financial/member tables.

- donations.notes
- annual_pledges.notes
- guests.notes (if it stores sensitive pastoral/personal details)

Encrypt family and location contact data where needed by policy.

- families.address
- optionally profile.city/state/zip/country (policy-driven)

Formalize key lifecycle controls.

- KMS-backed key source in production.
- Key version tagging in encrypted payload metadata.
- Annual rotation minimum, emergency rotation procedure, rollback proof.

## High

Add table-level privacy metadata and retention controls.

- Define retention class per table/field.
- Add scheduled purge/anonymization jobs.
- Examples:
  - Guest records inactive for N months: anonymize PII.
  - Old audit payload fields: redact old_values/new_values if containing PII.

Tighten audit trail quality for financial events.

- Mandatory audit for create/update/delete on donations and pledges.
- Store actor, reason, source IP, parish scope, and changed fields.
- Prevent hard delete of financial records; use soft-delete or reversal entries.

Implement data subject request workflow support.

- Export user data package.
- Correct inaccuracies.
- Restrict processing and communication preferences.
- Deletion/anonymization workflow with legal hold exceptions for finance records.

Add privacy-safe logging policy.

- Ban plaintext PII/financial values in app logs and exception traces.
- Enforce redaction middleware for structured logs.

## Medium

Add purpose limitation and consent artifacts for outreach.

- store consent_status, consent_source, consent_timestamp for guest/member communications.

Add row-level encryption readiness tests in CI.

- Verify ciphertext at rest and plaintext only on authorized read paths.
- Negative tests for unauthorized decryption attempts.

Add backup/restore privacy controls.

- Encrypted backups, key separation by environment.
- Restore drill validating decrypt success and access control boundaries.

## Recommended Control Matrix

|Control Area|Current|Target State|Priority|
|---|---|---|---|
|Member contact encryption|Partial|Complete for all policy-defined PII fields|High|
|Donation/pledge encryption|Missing|Implemented with reporting-safe design|Critical|
|Financial note protection|Missing|Encrypted and access-scoped|Critical|
|Key rotation evidence|Partial|Runbook + tested drill + sign-off|Critical|
|Audit completeness for finance|Partial|Mandatory + immutable process controls|High|
|Retention/deletion lifecycle|Missing|Automated retention and anonymization jobs|High|
|DSAR operations|Missing|Export/correct/delete workflow|High|
|Backup privacy controls|Partial|Encrypted backups + restore validation|Medium|

## Table-by-Table Required Hardening

### profile

- Keep current encryption on phone/address.
- Consider encrypting city/state/zip/country if your policy treats location as sensitive.
- Ensure phone_hash is generated from normalized phone only.

### families

- Encrypt address field.
- Restrict family household visibility by role and parish scope.

### guests

- Keep email/phone encryption and hash search.
- Encrypt notes if used for pastoral/private context.
- Add retention/anonymization for stale guest records.

### donations

- Encrypt amount and notes at rest.
- Introduce privacy-safe reporting architecture (projection/service strategy).
- Enforce append-only or reversible accounting event model.

### annual_pledges

- Encrypt pledged_amount, fulfilled_amount, and notes.
- Apply audit requirements for all updates.

### audit_logs

- Prevent sensitive payload leakage in old_values/new_values.
- Add masking/redaction before write.
- Define retention and legal hold policy.

## Compliance Readiness Checklist (Member and Donation Focus)

|Item|Status|Requirement|
|---|---|---|
|Data inventory by field|Partial|Complete and owner-assigned|
|Encryption at rest for PII|Partial|Complete for all in-scope fields|
|Encryption at rest for finance values|Missing|Implement for donations and pledges|
|Key rotation and recovery runbook|Partial|Documented, tested, approved|
|Access control evidence|Partial|Role/parish scoped tests and evidence|
|Audit integrity and coverage|Partial|Mandatory for financial changes|
|Data retention policy|Missing|Automated purge/anonymization|
|User rights operations (export/delete)|Missing|Operational workflow and SLA|
|Backup/restore privacy test|Missing|Quarterly tested and signed off|

## 30-60-90 Day Implementation Plan

### 0-30 Days

1. Encrypt donations and annual_pledges sensitive fields.
2. Encrypt families.address and financial/member notes.
3. Add/update integration tests proving ciphertext at rest.
4. Publish key rotation SOP and perform first drill.

### 31-60 Days

1. Implement retention and anonymization jobs for guests/audit payloads.
2. Add DSAR export/delete operational endpoints and admin workflow.
3. Enforce finance audit coverage and anti-hard-delete policy.

### 61-90 Days

1. Complete backup/restore privacy drills with evidence capture.
2. Add continuous monitoring alerts for decryption failures and key access anomalies.
3. Run external privacy/security review and close findings.

## Evidence Files to Maintain Per Release

1. Encryption field map (table/column level).
2. Key rotation drill report.
3. Backup/restore drill report.
4. Audit coverage test report (financial CRUD).
5. Privacy logging redaction verification report.
6. Retention/anonymization execution logs.

## Source References

- Member profile schema: app/Database/Migrations/2025_01_01_000002_CreateProfiles.php
- Member affiliation schema: app/Database/Migrations/2026_03_18_000005_CreateMemberParishAffiliations.php
- Family schema: app/Database/Migrations/2025_01_01_000003_CreateFamilies.php
- Guest schema: app/Database/Migrations/2025_01_01_000007_CreateGuests.php
- Encryption migration: app/Database/Migrations/2026_03_31_000004_AddDataEncryptionAtRest.php
- Donations schema: app/Database/Migrations/2025_01_01_000009_CreateDonations.php
- Annual pledges schema: app/Database/Migrations/2026_03_26_000002_CreateAnnualPledges.php
- Audit logs schema: app/Database/Migrations/2026_03_31_000006_CreateAuditLogs.php
- Profile encryption callbacks: app/Models/ProfileModel.php
- Guest encryption callbacks: app/Models/GuestModel.php
- Donations model (plaintext amounts): app/Models/DonationModel.php
- Annual pledges model (plaintext amounts): app/Models/AnnualPledgeModel.php

## Notes

- This is a technical security/privacy readiness report, not legal advice.
- Final legal compliance posture should be validated by counsel for applicable jurisdictions
and diocesan policy requirements.
