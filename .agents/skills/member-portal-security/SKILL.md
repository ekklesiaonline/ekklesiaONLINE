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
- **Device Verification**: Unrecognized devices are prompted for a 6-digit email verification challenge via `DeviceVerificationFilter` unless disabled for testing (`security.disableDeviceVerificationForTesting = true`).
- **Session Revocation**: Users can view their active browser sessions and remotely revoke other sessions at `/me/security`.

## Family & Standing Workflows

- **Family Relationships**: Managed via `FamilyService`. Changes sync between heads of household and family members.
- **Good Standing**: Keyed by `(user_id, year)` on `member_standing_summary`. Requires baptism + communicant attendance (at least 3 recorded communions) + financial pledge or explicit pastoral override.
