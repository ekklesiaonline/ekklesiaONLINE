# API and AJAX Error Contract

Date: 2026-04-02
Status: Active baseline for Phase B hardening

## Purpose

Define one backward-compatible JSON error contract for all API/AJAX endpoints so frontend handling, logging, and diagnostics are consistent.

## Canonical Response Shape

### Success response

{
  "success": true,
  "status": "success",
  "message": "Optional human-readable success message",
  "meta": {
    "http_status": 200,
    "ts": "2026-04-02T18:00:00+00:00"
  }
}

### Error response

{
  "success": false,
  "status": "error",
  "message": "Human-readable safe error message",
  "error": {
    "code": "MACHINE_READABLE_CODE",
    "message": "Human-readable safe error message",
    "details": {
      "field_name": "Validation detail"
    }
  },
  "errors": {
    "field_name": "Validation detail"
  },
  "meta": {
    "http_status": 422,
    "ts": "2026-04-02T18:00:00+00:00"
  }
}

Notes:

- message remains top-level for compatibility with existing frontend handlers.
- errors remains top-level for inline validation behavior.
- error.code is mandatory for new/updated endpoints.
- error.details mirrors errors when field errors exist.

## HTTP Status Guidelines

- 400: malformed request payload
- 401: unauthenticated
- 403: authenticated but forbidden by scope/permission
- 404: resource not found in current scope
- 409: conflict (duplicate, state conflict)
- 422: validation errors
- 429: rate-limited
- 500: server error (generic client message only)

## Phase B Initial Adoption

Initial normalization is applied through:

- Base controller JSON payload normalization
- Scope filters (parish, diocesan, province)
- Device verification filter
- API rate-limit filter
- Session keepalive JSON endpoint

## Frontend Compatibility

Existing frontend helpers should continue to read:

- message for toast/alert text
- errors for inline validation

New consumers should also branch on:

- status == error
- error.code for deterministic client logic

## Security Rules

- Never return raw exception messages to clients.
- Use generic user-safe messages for 500 responses.
- Keep sensitive fields redacted in logs and audit records.
