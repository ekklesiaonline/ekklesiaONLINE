# Families Non-Portal API Contract (2026-04-11)

## Endpoints

- `POST /families/addMemberNonPortal`
- `POST /families/enablePortal/{userId}`

Both endpoints are JSON-only responses and enforce `families.manage` plus operational church scope.

## Deterministic Envelope

All responses use this exact top-level shape:

- `success` (boolean)
- `code` (string)
- `message` (string)
- `data` (object or null)
- `errors` (array)
- `meta` (object: `request_id`, `timestamp`, `church_id`)

## Top-Level Codes

Allowed values:

- `NON_PORTAL_MEMBER_CREATED`
- `PORTAL_ACCESS_ENABLED`
- `VALIDATION_FAILED`
- `BAD_REQUEST_JSON`
- `UNAUTHENTICATED`
- `FORBIDDEN`
- `OUT_OF_SCOPE`
- `FAMILY_NOT_FOUND`
- `USER_NOT_FOUND`
- `PORTAL_ACCESS_ALREADY_ENABLED`
- `EMAIL_ALREADY_IN_USE`
- `TRANSACTION_FAILED`
- `INTERNAL_ERROR`

## Status Mapping

- `201` for successful non-portal creation
- `200` for successful portal enablement
- `400` malformed JSON payload
- `401` unauthenticated
- `403` forbidden or out-of-scope
- `404` missing in-scope family or user
- `409` already enabled or duplicate email
- `422` validation failures
- `500` transaction/internal failures

## Frontend Integration Rule

In `MemberPortal/Views/Families/view.php`, client behavior branches by `response.code` and renders field-level validation from the `errors` array. Do not infer behavior from free-text messages.
