---
name: api-ajax-contract
description: Implement, review, test, or diagnose CodeIgniter API and AJAX endpoints using ekklesiaRD's validation, CSRF, JSON response, status-code, and redaction contract. Use for new or changed POST endpoints, fetch handlers, keepalive flows, and endpoint smoke tests.
---

# API and AJAX Contract

Apply this skill to every new or modified API/AJAX endpoint. Read `docs/API_AJAX_ERROR_CONTRACT.md` before changing response behavior.

## Controller checklist

1. Protect the route with the appropriate `session`, `permission:*`, scope, CSRF, and rate-limit filters.
2. Validate every form/POST payload with CI4 Validation before domain mutation.
3. Resolve the authenticated user with Shield (`auth()->user()`) and enforce permission and parish scope in the controller/service.
4. Use models or Query Builder/prepared statements; never concatenate untrusted SQL.
5. Return the canonical success or error envelope, including `error.code` for new or updated endpoints.
6. Use generic text for 500 responses; never return exception, SQL, filesystem, or credential details.
7. Redact contact data, credentials, financial values, and freeform notes from logs/audit records.

## Response shapes

Success:

```json
{"success":true,"status":"success","message":"...","meta":{"http_status":200,"ts":"..."}}
```

Error:

```json
{"success":false,"status":"error","message":"...","error":{"code":"MACHINE_READABLE_CODE","message":"...","details":{}},"errors":{},"meta":{"http_status":422,"ts":"..."}}
```

Use status codes consistently: 400 malformed, 401 unauthenticated, 403 forbidden/scope, 404 not in scope, 409 conflict, 422 validation, 429 rate-limited, 500 generic server error.

## Native fetch contract

For native `fetch()` POSTs, follow `.devin/rules/views.md` exactly:

- Read the CSRF meta token once.
- Send `application/x-www-form-urlencoded` via `URLSearchParams`; do not send `JSON.stringify`.
- Include `X-Requested-With: XMLHttpRequest` and the configured CSRF header.
- Send same-origin credentials.
- Replace the CSRF meta value from the response header when present.
- Handle both the top-level `message`/`errors` compatibility fields and `status`/`error.code` deterministic fields.
- Use toasts for success/error feedback and redirect unauthenticated keepalive responses to login where required.

## Verification

- Add or update a `CIUnitTestCase` test for validation, authorization/scope, status code, response envelope, and non-leakage.
- Exercise representative malformed, unauthorized, not-found-in-scope, conflict, and success cases.
- Use `build/targeted_forms_smoke.sh` for staging form/AJAX checks when credentials and `BASE_URL` are explicitly supplied.
- Run `vendor/bin/phpunit --no-coverage` before completion; this project does not provide `php spark test`.

## References

- `docs/API_AJAX_ERROR_CONTRACT.md`
- `.devin/rules/codeigniter.md`
- `.devin/rules/views.md`
- `app/Controllers/BaseController.php`
- `app/Filters/CsrfTokenHeaderFilter.php`
- `build/targeted_forms_smoke.sh`
