# New-device verification behavior report

**Date:** 2026-09-21  
**Account investigated:** parish administrator account reported by the user  
**Environment:** local development (`ekklesiard.local`)

## Executive summary

The reported behavior is reproducible from the current configuration and code.
The password login succeeds, `SessionTrackingFilter` registers the login as a
new device and sends the six-digit verification email, then
`DeviceVerificationFilter` bypasses the challenge because local development has
`security.disableDeviceVerificationForTesting = true`.

This explains why the administrator can reach `/parish/dashboard` while still
receiving an email that says verification is required. It is not the intended
production behavior. It is an interaction defect between the development
bypass and session tracking.

There is a second architectural issue: "known device" status is inferred from
active `user_session` rows. Those rows are pruned after the normal PHP session
expiration (currently 1,800 seconds). Consequently, a browser can become a
"new device" again after approximately 30 minutes of inactivity. There is no
persistent trusted-device record or durable trusted-device cookie.

## Observed state

At inspection time, the account had one tracked `user_session` row:

- Created at the time of the reported login.
- Updated by subsequent dashboard activity.
- `is_new_device = 1`.
- Browser signature indicated Safari on macOS.

The audit log contained `auth.login.success` at the same timestamp. It did not
contain a corresponding device-verification completion event. No session token,
verification code, email secret, or fingerprint value was included in this
report.

## Current request flow

1. Shield validates the credentials and establishes an authenticated session.
2. On the first authenticated request, global `SessionTrackingFilter` runs.
3. The filter generates `ekklesia_session_token` and computes a fingerprint as
   `SHA-256(user-agent)`.
4. `UserSessionModel::registerSession()` prunes stale session rows, checks
   whether that fingerprint exists among the remaining rows, and inserts the
   current session.
5. If no matching row remains, the filter creates a six-digit challenge in the
   PHP session and immediately sends the verification email through System
   transport.
6. The global `DeviceVerificationFilter` runs later in the chain.
7. In development, when
   `security.disableDeviceVerificationForTesting = true`, it clears the
   challenge and permits access to the dashboard.

The bypass occurs too late to prevent the email. It also does not call
`UserSessionModel::markVerifiedByToken()`, leaving the database row marked as a
new device.

## Expected behavior

### Production

A genuinely unrecognized device should:

1. Pass primary credential authentication.
2. Receive a verification challenge email.
3. Be redirected to `/session/device-verify` before accessing application
   pages.
4. Become trusted only after the correct code is supplied.

It is reasonable for the login audit event to be recorded before the second
factor is completed, but the event should distinguish primary authentication
from fully completed login assurance.

### Development bypass

When the explicit development bypass is enabled, the application should choose
one coherent behavior:

- Bypass both challenge enforcement and challenge email generation; or
- Do not bypass, and exercise the complete device-verification flow.

It should not send an email saying verification is required and then silently
allow the session without verification.

## Findings

### Critical functional finding: bypass runs after email generation

`SessionTrackingFilter` sends the challenge before
`DeviceVerificationFilter` evaluates the development bypass. The user receives
an actionable security message for a challenge the application has already
removed.

**Impact:** confusing user experience, unnecessary email, reduced confidence in
security notifications, and false-positive security alerts.

### High: trusted-device state is coupled to short-lived session rows

`registerSession()` calls `pruneStaleForUser()` using `Config\Session::$expiration`
(1,800 seconds) before checking whether the fingerprint has been seen. Once all
rows for that browser are stale, its fingerprint no longer exists and the next
login is classified as a new device.

**Impact:** repeated verification emails/challenges after inactivity, rather
than only for genuinely new devices.

### High: user-agent-only fingerprint is not a reliable device identity

The fingerprint is only `SHA-256(user-agent)`.

- Different physical devices running the same browser/version can share the
  same user-agent, creating false negatives.
- A browser update can change the user-agent, creating false positives.
- User-agent is client-controlled and should not be treated as a secret or
  authentication factor.

### Medium: delivery failure is not handled as part of challenge state

Both initial and resend paths call `send(false)` without checking its boolean
result. Exceptions are logged, but a normal `false` send can leave the user
blocked behind a challenge whose code was not delivered.

### Medium: CSRF is explicitly excluded for verification submission

The verification forms already include `csrf_field()`, but the global CSRF
configuration excludes `session/device-verify`. The endpoint is authenticated
and rate-limited, but retaining CSRF protection is still preferable.

### Medium: verification lifecycle is only partially audited

Lockout is audited, but challenge issuance, send outcome, successful
verification, resend, trust revocation, and bypass use are not represented as
structured audit events. The verification code and email content must never be
logged.

### Low: messages and email templates are duplicated and hard-coded

Initial and resend emails have separate inline HTML bodies, and several
controller messages are hard-coded English. This can create message drift and
bypasses the established localization conventions.

### Positive controls already present

- Verification codes are stored as SHA-256 hashes, not plaintext.
- Codes expire after ten minutes.
- Failed attempts are capped at five.
- Initial and resend endpoints are covered by `loginRateLimit`.
- Credential-bearing mail uses System transport rather than tenant SMTP.
- AJAX requests receive a structured 403 with a verification redirect.

## Recommendations

### P0 — Correct the current local behavior

Centralize the bypass decision and evaluate it before challenge creation and
email delivery. Recommended implementation:

- Add a small `DeviceVerificationPolicy` service with `isEnabled()` or
  `shouldBypass()`.
- Use it in both `SessionTrackingFilter` and `DeviceVerificationFilter`.
- When bypassed, register/touch the session without setting
  `device_verification_required`, without generating a code, and without
  sending an email.
- Mark the inserted session row as not-new/verified when bypass is active.

For immediately testing real verification locally, set
`security.disableDeviceVerificationForTesting = false` and use a local mail
catcher or working System SMTP.

### P0 — Add regression tests for the exact defect

Add filter-level tests proving:

1. Development + bypass enabled: no challenge, no email, dashboard permitted,
   database session marked verified.
2. Development + bypass disabled: challenge and email created, dashboard
   redirected.
3. Production ignores the bypass flag.

### P1 — Separate trusted devices from active sessions

Introduce a dedicated `user_trusted_device` table. Suggested fields:

- `id`, `user_id`
- `selector` or public identifier
- `token_hash` (never store the raw token)
- `display_name`
- `first_seen_at`, `last_seen_at`, `expires_at`
- `last_ip_address`, optional user-agent metadata
- `revoked_at`, `revoked_by`
- timestamps

After successful verification, issue a random 256-bit device token in a
`Secure`, `HttpOnly`, `SameSite=Lax` cookie and store only its hash. On future
logins, validate the cookie against the user and the non-revoked, non-expired
record. Keep `user_session` solely for active-session tracking and concurrent
session limits.

A configurable trust period such as 30 or 90 days is more consistent with
common "remember this device" behavior than the current 30-minute lifetime.

### P1 — Add user-facing device management

Add a Security page that lists trusted devices with friendly browser/device
names and last-seen information. Support:

- Revoke one trusted device.
- Revoke all other devices.
- "Log out and forget this device."
- Ordinary logout without forgetting the trusted device.

Password changes, account recovery, suspension, and administrator-initiated
security actions should offer or force trust revocation.

### P1 — Make email delivery outcome explicit

Check the return value of `send(false)`. If delivery fails:

- Emit a redacted audit/operational event.
- Show a generic delivery problem on the challenge page.
- Preserve a safe resend path.
- Do not silently grant production access.

### P1 — Restore CSRF protection

Remove the verification routes from the CSRF exception list and retain the
existing `csrf_field()` values. Verify resend and submit tests with token
regeneration enabled.

### P2 — Improve audit semantics

Add redacted events for:

- `security.device_verification.challenge_issued`
- `security.device_verification.email_sent` / `email_failed`
- `security.device_verification.verified`
- `security.device_verification.resend`
- `security.trusted_device.revoked`
- `security.device_verification.bypassed` (development/testing only)

Do not include codes, code hashes, raw device tokens, session tokens, full email
bodies, or SMTP data.

Also distinguish `auth.primary_success` from `auth.login.completed` when a
second step is required.

### P2 — Consolidate and localize the UX

- Use one localized email template for initial and resend delivery.
- Parse the user agent into a friendly label (for example, "Safari on macOS")
  while retaining the raw string only where operationally necessary.
- Redirect to the originally requested URL after verification instead of the
  generic `/dashboard` route.
- Clarify notification wording: "Your password was accepted; verify this
  device to finish signing in."

## Suggested implementation sequence

1. Fix bypass ordering and add regression tests.
2. Check email send results and restore CSRF protection.
3. Add verification lifecycle audit events.
4. Implement `user_trusted_device` and hashed-cookie trust.
5. Add the Security / trusted-devices UI.
6. Consolidate localized email and UI messages.
7. Run the full test suite and manually verify these scenarios:
   - First login from an untrusted browser.
   - Correct and incorrect code.
   - Expired code and resend cooldown.
   - Subsequent login from a trusted browser after more than 30 minutes.
   - Browser update / cookie deletion.
   - Trust revocation.
   - Development bypass enabled and disabled.
   - Production ignoring the bypass flag.

## Conclusion

The email itself is triggered by a real "new device" classification, but the
observed combination of dashboard access plus a required-verification email is
a development-mode defect caused by filter ordering. The repeated-new-device
risk is broader: the current implementation tracks active sessions, not trusted
devices. Fixing the bypass ordering resolves the immediate symptom; adding a
separate, revocable trusted-device mechanism resolves the underlying design
problem.

## Implementation status (2026-09-21)

All recommendations have been implemented:

- **P0 bypass ordering — done.** New `App\Services\DeviceVerificationPolicy` is
  the single source of truth for the dev/testing bypass. `SessionTrackingFilter`
  evaluates it before any challenge/email side effects, registers bypassed
  sessions as known devices (`is_new_device = 0`), and audits
  `security.device_verification.bypassed`. `DeviceVerificationFilter` delegates
  to the same policy.
- **P0 regression tests — done.** `tests/app/Filters/DeviceVerificationBypassFilterTest.php`
  covers bypass-on (no challenge, no email, row marked known), bypass-off
  (challenge created), policy env whitelist, and enforcement-filter delegation.
- **P1 delivery outcome — done.** `DeviceVerificationService` owns challenge
  creation and the localized email; `send(false)` is checked and audited
  (`email_sent`/`email_failed`); a failure flags the session so the verify page
  shows a delivery warning while resend remains available.
- **P1 CSRF — done.** `session/device-verify` is no longer CSRF-exempt; both
  verify and resend POSTs are token-protected (`DeviceVerifyCsrfTest` updated).
- **P1 durable trusted devices — done.** `user_trusted_device` migration,
  `UserTrustedDeviceModel`, and `TrustedDeviceService` implement a
  selector+validator cookie (`ekklesia_device`, Secure/HttpOnly/SameSite=Lax,
  `security.trustedDeviceTtlDays` default 30). `user_session` remains for
  concurrent-session limits only; logout now drops the tracked session row
  without touching device trust.
- **P1 device management UI — done.** `GET /session/devices` lists trusted
  devices; revoke-one, revoke-others, and forget-this-device are supported via
  `SessionSecurity`. A "Security" sidebar link was added.
- **P1 revocation triggers — done.** Password changes
  (`SetPasswordController`) and admin ban/deactivate
  (`RegistrationApprovalService`) revoke all trusted devices and tracked
  sessions.
- **P2 audit — done.** `challenge_issued`, `email_sent`, `email_failed`,
  `verified`, `resend`, `bypassed`, `trusted_device.issued`,
  `trusted_device.revoked`, plus `auth.login.completed` alongside the existing
  `auth.login.success`. No codes, hashes, or tokens are logged.
- **P2 UX/i18n — done.** One localized email template (en/es/fr), friendly
  "Safari on macOS" device labels, post-verification redirect to the originally
  requested URL, and the stale `app/Views/session/device_verify.php` duplicate
  was removed.

Verification: `vendor/bin/phpunit --no-coverage` — 631 tests, 2,778 assertions,
6 skipped, 0 failures. PHPStan level 3 clean. Migration applied to the dev
MySQL database.
