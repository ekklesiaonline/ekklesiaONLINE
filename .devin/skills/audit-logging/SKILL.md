---
name: audit-logging
description: Explain audit service usage, observability policy, and redaction rules
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with audit logging and observability in ekklesiaRD.

Key sources:
- `BaseController::audit()` returns `App\Services\AuditService`.
- `docs/OBSERVABILITY_POLICY.md` defines signals, redaction, retention, and alerting.
- `BaseController::requirePermission()` rate-limits permission-denied audit logs to 10/hour per user.

Redaction requirements:
- Credentials and secrets
- Personal contact data
- Financial values
- Freeform notes

Environment switches:
- `monitoring.enabled`
- `monitoring.fileLogEnabled`
- `monitoring.slowQueryMs` (default 100)
- `monitoring.auditDbEnabled`
- `monitoring.auditLogEnabled`

Retention baseline:
- Application logs: 30 days online.
- Audit logs: 90 days online.

When asked:
1. Show how to call `$this->audit()->log($action, $data)` with parish scope and metadata.
2. Explain what must be redacted.
3. Reference the observability policy and any relevant controller code.
