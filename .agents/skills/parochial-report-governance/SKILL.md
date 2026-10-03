---
name: parochial-report-governance
description: Explain parochial report preparation, submission, multi-tier signature approval chain, revision flows, and cryptographic snapshot verification
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

# Parochial Report Governance & Approval Workflow

Help with parochial report filing, multi-tier signature approvals, and snapshot integrity.

## Key Sources

- Service: `App\Services\ParishReportApprovalService`
- Controllers:
  - `Worship\Controllers\ParochialReport` (Report generation and metrics collation)
  - `App\Controllers\Parish\ReportApprovals` (Signature certification and workflow endpoints)
- Tables: `parish_report`, `parish_report_signature`
- Routes: `/parish/report-signatures/*` in `Parish/Config/Routes.php`

## Multi-Tier Signing & Approval Flow

1. **Draft & Collation**:
   - Assembled by parish administrators or clergy via `Worship\Controllers\ParochialReport`.
2. **Submission**:
   - `POST /parish/report-signatures/submit/{year}`
   - Permission: `report.parochial`.
   - Freezes the underlying report data into `frozen_snapshot_json` and computes `snapshot_sha256`. Status transitions from `draft` to `submitted`.
3. **Clerk Certification**:
   - `POST /parish/report-signatures/{id}/clerk`
   - Permission: `governance.report.certify_clerk` (held by `vestry_secretary`).
   - Certifies that the vestry has reviewed the records.
4. **Warden Approval**:
   - `POST /parish/report-signatures/{id}/warden`
   - Permission: `governance.report.approve_warden` (held by `warden`).
   - Formally ratifies the filing on behalf of the lay leadership.
5. **Clergy Finalization**:
   - `POST /parish/report-signatures/{id}/clergy`
   - Permission: `governance.report.finalize_clergy` (requires verified rector/priest-in-charge order and office).
   - Certifies spiritual, pastoral, and sacramental registers.
6. **Diocesan Review & Approval**:
   - `POST /parish/report-signatures/{id}/diocese`
   - Permission: `governance.diocese.approve` (held by `dioceseadmin`).
   - Final canon acceptance into the diocesan registry.
7. **Revision Handling**:
   - `POST /parish/report-signatures/{id}/new-revision`
   - Permission: `report.parochial`.
   - Increments `report_revision`, preserves previous frozen history, and resets status to `draft` for adjustments.

## Integrity & Cryptographic Guarantees

- Each signature cryptographically binds the SHA256 hash of `frozen_snapshot_json`.
- Modifying underlying live attendance or financial metrics after submission does NOT alter the signed snapshot.
