---
name: member-portal-security
description: Explain member personal dashboard, session security, device verification, household/family management, and communicant standing
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

# Member Portal, Session Security & Family Management

Help with member personal dashboard features, trusted devices, session security, family relationships, and good standing.

## Key Sources

- Controllers:
  - `MemberPortal\Controllers\MyDashboard` (Personal hub `/me`)
  - `MemberPortal\Controllers\SessionSecurity` (Session tracking, device verification management)
  - `MemberPortal\Controllers\Families` (Household creation, members, relationships)
  - `MemberPortal\Controllers\Standing` (Communicant in good standing evaluation)
  - `MemberPortal\Controllers\Profile` (Personal profile view/edit)
  - `MemberPortal\Controllers\LeadershipPositions` (Parish leadership offices)
- Services:
  - `App\Services\MemberDashboardDataService`
  - `App\Services\DeviceVerificationService`
  - `App\Services\FamilyService`
  - `App\Services\TrustedDeviceService`
- Models:
  - `App\Models\UserSessionModel` (Active sessions)
  - `App\Models\UserTrustedDeviceModel` (Browser device fingerprints)
  - `App\Models\FamilyModel` & `FamilyMemberModel` (Households)
  - `App\Models\MemberStandingSummaryModel` (Good standing snapshots)
- Routes: `MemberPortal/Config/Routes.php`

## Security Boundaries & Session Limits

- **Concurrent Session Cap**: A user can have at most **3 active sessions** simultaneously. Logins beyond this cap evict the oldest tracked session via `SessionTrackingFilter`.
- **Login Email 2FA**: `App\Authentication\ScopedEmail2FA` (Shield `Auth.actions['login']`) issues a 6-digit email code as a second factor at sign-in — for both password and magic-link logins (`ScopedMagicLinkController::verify` calls `startUpAction('login', $user)`). Scope is configured by `security.login_2fa_scope` (`SystemSettingService::getLoginEmail2faScope()`, env `SECURITY_LOGIN_2FA_SCOPE`): `admins` (default) challenges only `systemadmin`/`provinceadmin`/`dioceseadmin`/`parishadmin`, `all` challenges everyone, `off` disables. The class implements `ConditionalActionInterface::appliesTo()`, so out-of-scope users never get a challenge identity. Codes go out via `EmailScopeService::credentialMailer()` (System transport), are capped at 5 sends/hour per address, and 5 wrong entries invalidate the code. A successful verify stamps `login_2fa_verified_user_id` in the session — the durable signal `SystemAdminService::isUserMfaEnabled()` reads for the `mfa.required_for_admin_ops` gate (the `email_2fa` identity row itself is deleted on verify).
- **Device Verification**: Unrecognized devices are prompted for a 6-digit email verification challenge via `DeviceVerificationFilter` unless disabled for testing (`security.disableDeviceVerificationForTesting = true`).
- **Session Revocation**: Users can view their active browser sessions and remotely revoke other sessions at `/me/security`.
- **Magic Links**: Handled by `App\Controllers\Auth\ScopedMagicLinkController` (shadows Shield's `MagicLinkController` routes). Deactivated accounts (`users.active = 0`, e.g. ban/dismiss) receive **no** magic-link email and see the same generic "check your email" page as unknown addresses — the flow never reveals account existence or state. The verify endpoint (`GET login/verify-magic-link`) re-checks `active`/`isBanned` before `loginById()`, so a link issued before deactivation cannot log the account in. It is also rate-limited on GET via `LoginRateLimitFilter` (10/min per IP, shared login bucket), and each recipient address is capped at 3 sends/hour.
- **Upload Delivery**: `/uploads/*` (`PublicAssets::serveUpload`) is allowlist-gated via the `PUBLIC_UPLOAD_PREFIX_CHECKS` map, which is intentionally **empty** — the catch-all 404s every path. All upload types under `writable/uploads` are tenant-scoped private data served by dedicated, scope-checked endpoints: `profile/*` → `media/profile-picture/{userId}`, `parish/*` → `media/parish-logo|parish-banner/{id}`, `event/*` → `media/event-cover/{id}`, `attachment/event/*` → `media/event-attachment/{id}`, `attachment/expense/*` → `expenses/receipt/{id}` (Finance). **New upload prefixes must never be served through `/uploads/*`** — add a dedicated scoped endpoint instead; adding a map entry is an explicit, reviewed exception requiring a real access check.

## Password Write Paths

- **Validator chain everywhere**: every password write path — self-registration, `SetPasswordController::save`, all admin `resetPassword` endpoints, and visitor→member `create_account` promotion — runs Shield's configured `passwordValidators` (composition, nothing-personal, dictionary, SafePwned breach check) via `service('passwords')->check()` inside `BaseController::validateAdminPassword()` or the `strong_password` rule. The effective minimum length is floored at `config('Auth')->minimumPasswordLength` (never below 12), and `password_policy.min_length` in system settings cannot be set below 12.
- **Operator resets force rotation**: admin/operator-chosen passwords are written with `force_reset=1`, so the target must rotate on first login. The reset also revokes all trusted devices (`TrustedDeviceService::revokeAll`) and deletes all tracked sessions (`UserSessionModel::removeAllForUser`), so stale access cannot survive a reset.
- **Portal invites**: `MemberAccessService::sendPortalInviteEmail()` is the canonical sender; it is reused by `PersonConversionService::createPortalUser`.

## Family & Standing Workflows

- **Family Relationships**: Managed via `FamilyService`. Changes sync between heads of household and family members.
- **Good Standing**: Keyed by `(user_id, year)` on `member_standing_summary`. Requires baptism + communicant attendance (at least 3 recorded communions) + financial pledge or explicit pastoral override.
