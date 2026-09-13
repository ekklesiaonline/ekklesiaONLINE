# System Admin Functionality — Security & Code Quality Review

**Scope:** Global "System Admin" layer (superadmin over provinces, dioceses, and
admin-user management) plus the auth/Shield configuration it depends on.

**Files reviewed:** `app/Controllers/SystemAdminUsers.php`, `SystemAdminService.php`
(shared trait), `SystemPrimary.php`, `SystemAudit.php`, `SystemDashboard.php`,
`SystemDioceses.php`, `SystemProvinces.php`, `SystemOverview.php`,
`SystemMissionOutcomes.php`, `SystemSettings.php`, `BaseController.php`,
`app/Filters/PermissionFilter.php` and route-scope filters, `app/Config/Filters.php`,
`app/Config/Routes.php`, `app/Config/Auth.php`, `app/Config/AuthGroups.php`,
`app/Services/AuditService.php`, `app/Services/SystemSettingService.php`.

Severity is rated by real-world exploitability given the existing guard rails
(CSRF is on globally, all `system/*` routes require the `permission:system.admin`
filter), not just by pattern-matching against "bad practices."

---

## CRITICAL

### 1. Privilege escalation to `systemadmin` bypasses the "primary admin only" rule
**File:** `app/Controllers/SystemAdminUsers.php` — `updateAdminUser()` (≈L185‑237)

`storeAdminUser()` correctly restricts creation of new `systemadmin` accounts to
the primary system admin:

```php
if ($group === 'systemadmin') {
    ...
    if (!$this->isPrimarySystemAdmin()) {
        return redirect()->to(base_url('system/system-admins'))
            ->with('error', lang('System.onlyPrimaryCanCreateSystemAdmin'));
    }
}
```

`updateAdminUser()` has the exact same `role_group === 'systemadmin'` branch but
**never calls `isPrimarySystemAdmin()`**:

```php
if ($group === 'systemadmin') {
    $provinceId = 0;
    $dioceseId = 0;
}
```

`enforcePrimarySystemAdminAction($userId)` is only called to protect the
*target* row when the target is already a system admin — it does nothing when
promoting a non-admin/province/diocese admin **into** the `systemadmin` group.

**Impact:** Any non-primary `systemadmin` (a role that is explicitly documented
as more limited than the primary admin) can call
`POST /system/admins/update/{id}` with `role_group=systemadmin` on any existing
province/diocese admin (or even themselves) and grant full, unrestricted
system-admin rights — completely defeating the primary-admin gate that the
create flow enforces. This is a straightforward privilege-escalation path
reachable by any account that already holds *any* system-admin permission
(e.g. one that was itself created by mistake, or compromised).

**Fix:** Add the same primary-admin guard used in `storeAdminUser()`:
```php
if ($group === 'systemadmin' && !$currentIsSystemAdmin && !$this->isPrimarySystemAdmin()) {
    return redirect()->to(base_url('system/system-admins'))
        ->with('error', lang('System.onlyPrimaryCanCreateSystemAdmin'));
}
```

---

### 2. No brute-force protection on the primary-admin transfer password check
**File:** `app/Controllers/SystemPrimary.php` — `transferPrimarySystemAdmin()`

```php
$currentPassword = (string) $this->request->getPost('current_password');
if (!$actor || empty($actor->password_hash) || !service('passwords')->verify($currentPassword, $actor->password_hash)) {
    ... // audit + generic error, but no lockout / rate limit
}
```

Unlike `storeAdminUser()` (which calls `isAccountLocked()` and
`checkRateLimit(..., 'admin_creation', 5, 3600)`), this endpoint — which
re-authenticates the *current* password of the account about to transfer away
irreplaceable "primary" status — has **no `isAccountLocked()`, no
`checkRateLimit()`, and no `trackFailedAttempt()` calls at all**. It's also not
covered by the `loginRateLimit` filter (only bound to `login`/`register`) or the
`apiRateLimit` filter (its route list doesn't include `admins/transfer-primary/*`).

**Impact:** An attacker who has hijacked a system-admin session (XSS, stolen
cookie, malicious insider with lower-trust admin access to the same box, etc.)
can brute-force `current_password` with unlimited attempts, and if OTP is
disabled (`isPrimaryTransferOtpEnabled()` returns false — see #3), a successful
guess is sufficient to seize primary-admin status outright.

**Fix:** Reuse the existing helpers already built for this purpose:
```php
if ($this->isAccountLocked($actorUserId)) { ... }
if (!$this->checkRateLimit($actorUserId, 'primary_transfer_auth', 5, 900)) { ... }
// on failed password verify: $this->trackFailedAttempt($actorUserId, 'primary_transfer_auth');
```
Also add `admins/transfer-primary/*` to the `apiRateLimit` filter list in
`app/Config/Filters.php` as defense in depth.

---

## HIGH

### 3. OTP for primary-admin transfer is optional and config-toggled
**File:** `app/Controllers/SystemAdminService.php` — `isPrimaryTransferOtpEnabled()`,
`app/Controllers/SystemPrimary.php`

```php
private function isPrimaryTransferOtpEnabled(): bool
{
    return service('systemSettings')->isPrimaryTransferOtpRequired();
}
```

The most sensitive action in the entire admin layer (handing off the "primary"
super-admin singleton) is protected by password + optional OTP, where the OTP
requirement is a **DB-editable setting** (`system_settings` table via
`SystemSettingService`), itself only reachable by... a system admin. Combined
with #1 and #2, this creates a plausible chain: escalate to systemadmin (#1) →
disable the OTP requirement via `system/settings/update` → brute-force or guess
the primary's password (#2, no lockout) → transfer primary to self.

**Fix:** Make OTP mandatory (non-configurable) for this specific operation, or
at minimum require the *primary admin's own* MFA/OTP regardless of the global
toggle.

### 4. MFA requirement for admin operations is a soft, DB-toggleable setting
**File:** `SystemAdminUsers.php` L22‑25, `SystemAdminService.php` L366‑369

```php
if ($this->isMfaRequiredForAdminOperations() && !$this->isUserMfaEnabled(auth()->id())) { ... }
```

This is the *only* place MFA is enforced for admin-user creation, and it is
gated entirely by `service('systemSettings')->isMfaRequiredForAdminOps()`. If
that setting is off (or reset to a default of `false` — verify in
`SystemSettingService`/DB seed), account creation for new system/province/
diocese admins has no MFA requirement whatsoever. Nothing prevents a
`systemadmin` from flipping this off via `SystemSettings::update` and then
immediately creating admin accounts, or (worse) exploiting #1.

**Fix:** Treat MFA-for-admin-ops as a hard requirement in code with the setting
only able to make it *stricter*, never disable it; or require re-verification
of the acting admin's own MFA (session-level, checked at login) rather than a
config flag.

### 5. `updateAdminUser` and `resetPassword` have no rate limiting/lockout at all
**File:** `SystemAdminUsers.php`

Only `storeAdminUser()` calls `checkRateLimit`/`isAccountLocked`/
`trackFailedAttempt`. `updateAdminUser()`, `deleteAdminUser()`, and
`resetPassword()` — all of which mutate admin accounts or set new
credentials — have none of these controls, and are not in the `apiRateLimit`
filter list (`app/Config/Filters.php` only lists non-system routes). A
compromised/malicious system-admin session can be used to spam password resets
or role changes with no throttling or lockout.

**Fix:** Extract the guard block from `storeAdminUser()` into a shared
`guardSystemAdminWriteRateLimited(string $action)` helper in
`SystemAdminService` and apply it to all four mutating endpoints; add the
`admins/*` routes to `apiRateLimit`.

### 6. Rate limiting / lockout state lives only in the generic cache, keyed loosely
**File:** `SystemAdminService.php` L348‑361, L462‑492

```php
private function checkRateLimit(int $userId, string $action, int $maxAttempts, int $timeWindow): bool
{
    $cache = \Config\Services::cache();
    $cacheKey = "rate_limit_{$action}_{$userId}";
    $attempts = $cache->get($cacheKey) ?: 0;
    if ($attempts >= $maxAttempts) { return false; }
    $cache->save($cacheKey, $attempts + 1, $timeWindow);
    return true;
}
```

Two real issues, not just "cache can be cleared" (a deploy-time cache flush is
low-severity by itself):

- **Race condition / not atomic**: `get` then `save` is a check-then-act
  sequence with no locking or atomic increment. Concurrent requests (easy to
  script) can each read the same `$attempts` value before either write lands,
  letting an attacker exceed `maxAttempts` by firing requests in parallel.
  Depending on the configured cache handler (`app/Config/Cache.php`), this is
  especially true for file-based cache.
- **Keyed by `userId`, not IP** — appropriate here since these actions require
  an authenticated admin session, but it means the "rate limit" only throttles
  a single already-authenticated identity, not concurrent abuse from multiple
  sessions/devices of the same compromised account.

**Fix:** Use an atomic cache increment (`$cache->increment()` if the driver
supports it) or move to a DB-backed counter with a unique constraint /
`INSERT ... ON DUPLICATE KEY UPDATE` style atomic update.

---

## MEDIUM

### 7. Inconsistent / incomplete audit trail on `resetPassword`
**File:** `SystemAdminUsers.php` L470‑476

```php
$this->audit()->log('system.admin.password_reset', [
    'entity_type' => 'user',
    'entity_id' => $userId,
    'meta' => [
        'actor_user_id' => (int) (auth()->id() ?? 0),
    ],
]);
```

Compare to `storeAdminUser`/`updateAdminUser`/`deleteAdminUser`, which all log
`user_id`, `ip_address`, `user_agent`, and `timestamp` at the top level. This
call omits `ip_address`/`user_agent` and uses a different schema
(`meta.actor_user_id` instead of top-level `user_id`), and never records which
role group the target belonged to. Forensic reconstruction of "who reset whose
password from where" is degraded for this specific action — arguably the one
most likely to be abused by a malicious admin to take over another admin's
account.

**Fix:** Normalize all `audit()->log()` calls in this trait/controllers to a
single consistent payload shape.

### 8. Race condition on "cannot remove the last system admin" check
**File:** `SystemAdminUsers.php` L259‑264 (`updateAdminUser`), L356‑359 (`deleteAdminUser`)

```php
$totalSystemAdmins = (int) $db->table('auth_groups_users')->where('group', 'systemadmin')->countAllResults();
if ($totalSystemAdmins <= 1) { ... }
```
This count-then-act check happens *before* `$db->transStart()`, with no row
locking. Two concurrent demote/delete requests against two different last-two
system admins could both pass the check and leave the system with zero admins
(a real availability/lockout bug, not just theoretical — this app has no
"break glass" superuser recovery path visible in the codebase).

**Fix:** Move the count inside the transaction with `FOR UPDATE`, or add a DB
check constraint / trigger enforcing `COUNT(*) >= 1` for `group='systemadmin'`.

### 9. Raw SQL with string-concatenated identifiers/limits
**File:** `SystemOverview.php` L53‑79 (`getRecentNetworkActivity`)

```php
$sql = "... ORDER BY happened_at DESC LIMIT " . (int) $limit;
return $db->query($sql)->getResultArray();
```
`$limit` is cast to `int` before concatenation, so this specific instance is
**not currently exploitable** for SQL injection. It is still a bad pattern
(hand-written UNION query, bypasses the query builder's escaping entirely) that
is one accidental refactor away from an actual injection if a future caller
passes `$limit` from user input without the cast, or someone adds another
interpolated value to the query. `SystemAdminService::tableExistsSafe()` has the
same smell (`sprintf('SELECT 1 FROM %s LIMIT 1', $tableName)`), though its
inputs are hardcoded literals today, not request data.

**Fix:** Rebuild with the query builder's `union()`/`unionAll()` support (CI4
4.4+) or at minimum add a code comment + a strict `(int)`/whitelist assertion
right next to every interpolation site so future edits don't regress it.

### 10. Dual source of truth for "is system admin": group membership vs. permission
**File:** `app/Filters/PermissionFilter.php` L21‑23, used throughout controllers via `isSystemAdmin()` (group check) *and* `requirePermission('system.admin')` (permission check)

```php
if ($permission === 'system.admin' && $user->inGroup('systemadmin')) {
    return true;
}
```
Every system-admin controller action calls **both** `requirePermission('system.admin')`
*and* `isSystemAdmin()` redundantly. This isn't wrong, but it means there are
two independent ways to gain the same access (being in the `systemadmin` Shield
group, or having the `system.admin` permission row directly), and revoking one
without the other doesn't actually revoke access. If an admin tool ever adds
"remove the system.admin permission from a user" without also removing them
from the `systemadmin` group (or vice versa), the access-revocation would
silently fail while looking successful.

**Fix:** Pick one source of truth (recommend group membership, since
`AuthGroups.php` already maps `systemadmin` → `system.admin`) and have
`isSystemAdmin()`/`guardSystemAdminWrite()` be the single check; drop the
redundant `requirePermission()` calls or make the filter derive permission
purely from group.

### 11. Diocese/province deletion only checks one dependent table
**File:** `SystemDioceses.php`, `SystemProvinces.php` (delete actions)

Deletion guards only check for child `parish` rows before allowing delete.
Other diocese/province-scoped tables observed in the schema
(`diocese_setting`, `province_setting`, admin `profile` rows with
`diocese_id`/`province_id`, `parish_role_audit`, financial summary tables)
are not checked, risking orphaned rows or FK constraint failures surfaced as a
generic 500 instead of a friendly validation error.

**Fix:** Centralize a "can this diocese/province be safely deleted" check that
enumerates all FK-referencing tables, or rely on DB-level `ON DELETE RESTRICT`
consistently and catch/report the specific constraint violation.

---

## LOW

### 12. Inconsistent authorization boilerplate across controllers
`requirePermission('system.admin')` + `isSystemAdmin()` is copy-pasted at the
top of nearly every action in `SystemAudit`, `SystemOverview`,
`SystemMissionOutcomes`, `SystemDashboard`, etc., instead of being centralized
in `SystemAdminService::guardSystemAdminWrite()` (which already exists and is
used by the *write* endpoints in `SystemAdminUsers`/`SystemPrimary`). Read-only
controllers reimplement the same two-line check by hand. Low risk today since
the logic is consistent, but it's the kind of duplication that silently
diverges over time (see #10).

### 13. Generic error message reused for both "duplicate email" and unrelated failures
**File:** `SystemAdminUsers.php` L106‑109, L175‑181

The email-enumeration protection is good practice, but the same
`lang('System.adminUserCreateFailed')` string is also returned from the
generic `catch (\Throwable $e)` block. This is fine for the security property
it's protecting, but makes it hard for a legitimate admin to tell "email taken"
apart from "DB error" without checking server logs — a minor usability/
supportability gap rather than a vulnerability.

### 14. Phone number "sanitization" doesn't validate format
**File:** `SystemAdminUsers.php` L137, L287

```php
'phone' => preg_replace('/[^0-9+\s-]/', '', (string) ($this->request->getPost('phone') ?? '')),
```
Strips disallowed characters instead of validating and rejecting malformed
input. Not a security issue (data is later encrypted via `DataEncryption`
before storage per `listSystemAdmins()`), but can silently store garbled phone
numbers.

---

## Good patterns observed (for balance)

- CSRF filter is applied globally to all POST requests except two narrowly
  scoped, low-risk device-verification endpoints; all `system/*` mutating
  routes are POST and thus CSRF-covered.
- All `system/*` routes are grouped under a single `permission:system.admin`
  filter in `app/Config/Routes.php` rather than being checked ad hoc per-route.
- Consistent use of DB transactions (`transStart`/`transComplete`/`transRollback`)
  around multi-table admin mutations.
- Email enumeration is explicitly guarded against on admin creation.
- Passwords are verified via `service('passwords')->verify()` (Shield's
  hashing), never compared in plaintext; new admin passwords go through a
  DB-configurable composition policy (`validateAdminPassword`).
- `safeRedirectError()` (BaseController) separates the client-facing generic
  message from server-side exception logging — no stack traces leak to users.
- Primary-admin singleton is protected from self-deletion, deletion of the
  last remaining system admin (modulo the race in #8), and unauthorized
  modification by non-primary admins (except for the #1 escalation gap).
- Views largely use `esc()` on interpolated admin data (spot-checked
  `System/system_admins.php`).

---

## Priority Summary

| # | Issue | Severity |
|---|-------|----------|
| 1 | `updateAdminUser` lets any systemadmin self-promote/promote others to systemadmin, bypassing primary-only rule | **Critical** |
| 2 | No brute-force protection on primary-transfer password check | **Critical** |
| 3 | Primary-transfer OTP is optional/config-toggleable | High |
| 4 | MFA-for-admin-ops is a soft DB setting, not enforced | High |
| 5 | No rate limiting/lockout on update/delete/reset-password admin endpoints | High |
| 6 | Rate limiter is non-atomic (race condition) | High |
| 7 | Inconsistent/incomplete audit payload on password reset | Medium |
| 8 | Race condition on "last system admin" guard | Medium |
| 9 | Raw string-concatenated SQL (not currently exploitable, but fragile) | Medium |
| 10 | Dual source of truth for admin authorization (group vs permission) | Medium |
| 11 | Diocese/province delete only checks one dependent table | Medium |
| 12 | Duplicated auth-guard boilerplate | Low |
| 13 | Overloaded generic error message | Low |
| 14 | Phone sanitization strips rather than validates | Low |

**Recommended immediate action:** fix #1 and #2 first — they are the two
findings with a direct, realistic path to full takeover of the system-admin
layer by an account that is not supposed to have that power.
