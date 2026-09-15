# Parish Affiliation Workflow Analysis — System, Province & Diocese Admins

**Scope:** How members/users are affiliated with parishes and how system,
province, and diocese administrators can assign, remove, or change those
affiliations. Covers the controller/model/filter layer plus related
authorization, audit, concurrency, and data-integrity concerns.

**Files reviewed:**
- `app/Models/MemberParishAffiliationModel.php`
- `app/Models/MemberParishAffiliationModel.php`
- `app/Models/ParishModel.php`
- `app/Models/ParishPeopleModel.php`
- `app/Models/ProfileModel.php`
- `app/Services/ParishPeopleRegistryService.php`
- `app/Services/MemberAccessService.php`
- `app/Services/ParishConfigService.php`
- `app/Filters/ParishScopeFilter.php`
- `app/Filters/DiocesanScopeFilter.php`
- `app/Filters/ProvinceScopeFilter.php`
- `app/Filters/AdminFinanceBoundaryFilter.php`
- `app/Filters/DiocesanOperationalBoundaryFilter.php`
- `MemberPortal/Controllers/Profile.php`
- `MemberPortal/Controllers/Family.php`
- `Clergy/Controllers/Members.php`
- `Clergy/Controllers/People.php`
- `Diocesan/Controllers/Parishes.php`
- `app/Controllers/SystemAdminUsers.php`
- `app/Config/Routes.php`, `MemberPortal/Config/Routes.php`, `Diocesan/Config/Routes.php`, `Clergy/Config/Routes.php`
- `app/Config/AuthGroups.php`
- Related test files (`tests/database/ParishAuditLoggingIntegrationTest.php`, etc.)

---

## CRITICAL

### 1. IDOR in parish attach/detach/set-home operations (profile_id lookup before scope check)
**File:** `Clergy/Controllers/Members.php`
**Lines:** `attachParish()` L885‑926, `detachParish()` L928‑967, `setHomeParish()` L969‑1013

```php
$profile = $this->getProfileModel()->find($profileId);
if (!$profile || !$this->isProfileAffiliatedWithCurrentParish($profile)) {
    return $this->jsonResponse($this->apiErrorPayload(lang('Clergy.memberNotFoundInScope'), 404, ...), 404);
}
```

The controller fetches the profile by raw `profile_id` from POST **before**
verifying scope. Although the response is rejected with 404 when the profile is
not in scope, the lookup itself leaks the existence of arbitrary profile IDs
(side-channel enumeration), and any future change to the check or to the
`isProfileAffiliatedWithCurrentParish()` implementation risks exposing the full
profile record.

More importantly, **the scope check is performed against the *acting* admin's
operational parish**, not against the submitted `parish_id`. If an admin has any
legitimate parish scope, they can probe every `profile_id` in the database.

**Risk:** Cross-parish/cross-diocese membership enumeration and, depending on
how `isProfileAffiliatedWithCurrentParish()` is implemented, potential mutation
of profiles outside the admin's jurisdiction.

**Fix:** Never fetch the profile first. Validate the tuple `(user_id, current_parish_id)`
directly in the query:
```php
$profile = $this->getProfileModel()
    ->where('id', $profileId)
    ->whereIn('user_id', function ($builder) {
        $builder->select('linked_user_id')
            ->from('parish_affiliation')
            ->where('parish_id', $this->getOperationalParishId())
            ->where('is_member', 1);
    })
    ->first();
```
Or move the scope check into a reusable model/service method that receives the
actor's parish and refuses to return records outside it.

---

### 2. `setHomeParish()` model method is a no-op
**File:** `app/Models/MemberParishAffiliationModel.php`
**Lines:** 471‑475

```php
public function setHomeParish(int $userId, int $parishId): bool
{
    // is_home concept removed in parish_affiliation — no-op, return true.
    return true;
}
```

`Clergy/Controllers/Members::setHomeParish()` calls this method inside a
transaction and reports success to the user/UI. No database change actually
occurs. The `is_primary` flag passed by `attachParish()` (L894, L909) is also
ignored inside `attachToParish()`.

**Risk:** Users and admins believe they have changed the primary/home parish,
but `profile.parish_id` and any primary affiliation flag remain unchanged.
This silently corrupts the "home parish" semantics used by scope filters and
dashboard routing.

**Fix:** Either re-implement `setHomeParish()` to update `profile.parish_id`
(and clear/set `parish_affiliation.is_primary` if that column exists), or
remove the endpoint and redirect callers to `attachParish()` with a primary flag.

---

## HIGH

### 3. Missing audit logging for parish affiliation mutations
**File:** `Clergy/Controllers/Members.php`
**Lines:** 885‑1013

`attachParish()`, `detachParish()`, and `setHomeParish()` make no `$this->audit()->log()`
calls. By contrast, `toggleMemberTag()` in the same file logs every tag change.

**Risk:** No forensic trail for one of the most sensitive membership actions.
Cannot detect abuse, investigate data-integrity incidents, or satisfy audit
requirements.

**Fix:** Add an audit entry before each success/return, e.g.:
```php
$this->audit()->log('member.parish_attached', [
    'user_id'        => (int) auth()->id(),
    'entity_type'    => 'parish_affiliation',
    'entity_id'      => (int) $profile['user_id'],
    'ip_address'     => $this->request->getIPAddress(),
    'user_agent'     => esc($this->request->getUserAgent()->getAgentString()),
    'new_values'     => ['parish_id' => $parishId, 'status' => $status, 'is_primary' => $isHome],
]);
```

---

### 4. Race condition allows duplicate `(user_id, parish_id)` affiliation rows
**File:** `app/Models/MemberParishAffiliationModel.php`
**Lines:** 376‑410

```php
$existing = $this->where('linked_user_id', $userId)
    ->where('parish_id', $parishId)
    ->first();

if ($existing) {
    // update
} else {
    // insert
}
```

This is a classic check-then-act race. Two concurrent `attachParish()` requests
for the same user/parish can both see no record and both insert.

**Risk:** Duplicate membership rows, inconsistent counts, and ambiguous
"home parish"/active affiliation state.

**Fix:**
1. Add a unique database constraint on `(linked_user_id, parish_id)`.
2. Replace the logic with an upsert (`INSERT ... ON DUPLICATE KEY UPDATE` for
   MySQL, or CI4's `Model::upsert()` if the driver supports it).
3. Wrap in a transaction with appropriate isolation.

---

### 5. Staff role assignment trusts `profile.parish_id` instead of an active affiliation
**File:** `Diocesan/Controllers/Parishes.php`
**Lines:** `addStaff()` L790‑944, `promoteMember()` L946‑981

```php
$profile = $this->getProfileModel()->where('user_id', $userId)->where('parish_id', $parishId)->first();
if (!$profile) {
    return redirect()->back()->with('error', lang('Diocesan.memberNotInParish'));
}
```

The check only verifies that the target user's profile points at the parish.
It does **not** require an active `parish_affiliation` record (`is_member = 1`).
A user whose profile was mirrored to a parish but whose affiliation has been
detached could still receive staff roles.

**Risk:** Inconsistent state where staff roles exist without active parish
membership; potential data-integrity failures in reporting and access control.

**Fix:** Require an active `parish_affiliation` record before allowing
`addStaff`/`promoteMember`:
```php
$affiliated = model('MemberParishAffiliationModel')
    ->isUserAffiliatedWithParish($userId, $parishId, true);
if (!$affiliated) { ... }
```

---

## MEDIUM

### 6. ParishScopeFilter bypasses province and diocese admins entirely
**File:** `app/Filters/ParishScopeFilter.php`
**Lines:** 30‑32

```php
if ($user->inGroup('provinceadmin') || $user->inGroup('dioceseadmin')) {
    return null;
}
```

Province and diocese admins are allowed to operate without a selected parish
context. This is intentional for cross-parish workflows, but it means the
filter provides **no boundary enforcement at all** for those roles. Any
cross-parish IDOR bug in the controllers below the filter is exploitable by
those higher-level admins.

**Risk:** A compromised or malicious province/diocese admin can act on parishes
and members outside their jurisdiction if the controller forgets to enforce
its own scope.

**Fix:** Keep the bypass for convenience, but add controller-level enforcement
in every parish-mutation endpoint (already partially present in
`Clergy/Controllers/Members`, but inconsistent — see #5 and #7).

---

### 7. Inconsistent parish-scope validation across controllers
Several parish-related flows validate scope differently or not at all:

- `Clergy/Controllers/Members::attachParish()` validates the target parish is
  within the current diocesan scope (L901‑904).
- `MemberPortal/Controllers/Profile::joinParishSubmit()` validates parish belongs
  to diocese (reported around L633‑640).
- `Diocesan/Controllers/Parishes::addStaff()`/`promoteMember()` rely on
  `canManageParish($parishId)` but do not consistently verify the target user is
  actually affiliated (see #5).
- System admin creation in `SystemAdminUsers` sets `parish_id = null`, which is
  correct, but province/diocese admin creation does not verify the chosen
  parish/diocese belongs to the actor's scope.

**Risk:** Maintainers cannot rely on a single source of truth; new endpoints are
likely to miss required checks.

**Fix:** Centralize scope validation in a service such as
`ParishScopeValidator::canActorAccessParish($actor, $parishId)` and call it from
all parish-mutation controllers.

---

### 8. Parish deletion does not cascade to `parish_affiliation`
**File:** `Diocesan/Controllers/Parishes.php`
**Lines:** 640‑720

The delete impact analysis checks for child parishes and related summary rows,
but there is no documented cleanup of `parish_affiliation` records. If the table
lacks a foreign-key constraint, deleting a parish leaves orphaned rows.

**Risk:** Orphaned affiliation rows reference non-existent parishes; reports
and member queries return inconsistent data.

**Fix:** Add `ON DELETE CASCADE` foreign keys or explicit cleanup in the delete
service, and perform impact analysis that includes `parish_affiliation` counts.

---

### 9. No rate limiting on parish-switch endpoint
**File:** `MemberPortal/Config/Routes.php`
**Line:** `dashboard/switch/(:num)`

The route that lets users switch their active parish context is not listed in
the `apiRateLimit` filter configuration (`app/Config/Filters.php`).

**Risk:** Rapid parish enumeration or session-context churn; minor DoS vector.

**Fix:** Add `dashboard/switch/*` to `apiRateLimit`.

---

### 10. Potential data exposure in member-list helpers
**File:** `app/Models/MemberParishAffiliationModel.php`
**Lines:** 56‑85 (`getParishMembers`)

`getParishMembers()` decrypts and returns profile data but does not perform an
explicit authorization check. It relies on the calling controller/filters to
enforce scope.

**Risk:** If a future controller calls this method directly without a scope
filter, member data leaks.

**Fix:** Add an optional `$actorParishId` parameter and reject mismatched
`parishId` values, or wrap the method in a service that always checks scope.

---

## LOW

### 11. Hardcoded group names scattered through models and filters
**Files:** `app/Models/MemberParishAffiliationModel.php`,
`app/Filters/ParishScopeFilter.php`, `app/Filters/DiocesanScopeFilter.php`, etc.

Group strings `"provinceadmin"`, `"dioceseadmin"`, `"systemadmin"` are repeated as
literals. This makes renaming roles fragile.

**Fix:** Define constants in `AuthGroups.php` (e.g., `public const PROVINCE_ADMIN = 'provinceadmin';`)
and reference them throughout the codebase.

### 12. `setHomeParish` and `attachParish` accept arbitrary `membership_status`
**File:** `Clergy/Controllers/Members.php` L893, L909

`$status` comes from POST with only the model's validation rule as a backstop.
While the model limits it to an `in_list`, the controller does not canonicalize
or whitelist the value before passing it down.

**Fix:** Validate against a controller-level whitelist or use a strict enum
before passing to the model.

---

## Good patterns observed

- **Prepared statements / Query Builder** are used consistently; no raw SQL
  injection vectors were found in the affiliation model.
- **Transactions** wrap multi-step attach/detach/sync operations in
  `Clergy/Controllers/Members`.
- **Route-level scope filters** (`ParishScopeFilter`, `DiocesanScopeFilter`,
  `ProvinceScopeFilter`) are registered and provide a first line of defense.
- **Rate limiting** is configured for the main parish mutation endpoints
  (`members/attach-parish`, `members/detach-parish`, `members/set-home-parish`)
  in `apiRateLimit`.
- **Mass-assignment protection** is enabled on models (`$protectFields = true`).
- **Audit logging exists** for some flows (`toggleMemberTag`) and could be
  extended uniformly.

---

## Priority summary

| # | Issue | Severity |
|---|-------|----------|
| 1 | IDOR in attach/detach/set-home parish (profile fetched before scope check) | **Critical** |
| 2 | `setHomeParish()` model method is a no-op | **Critical** |
| 3 | Missing audit logging for parish affiliation mutations | **High** |
| 4 | Race condition permits duplicate `(user_id, parish_id)` rows | **High** |
| 5 | Staff role assignment trusts `profile.parish_id` over active affiliation | **High** |
| 6 | ParishScopeFilter bypasses province/diocese admins | **Medium** |
| 7 | Inconsistent parish-scope validation across controllers | **Medium** |
| 8 | Parish deletion does not cascade to `parish_affiliation` | **Medium** |
| 9 | No rate limiting on `dashboard/switch/*` | **Medium** |
| 10 | Member-list helpers lack explicit authorization | **Medium** |
| 11 | Hardcoded group names | **Low** |
| 12 | Un-canonicalized `membership_status` input | **Low** |

**Recommended immediate action:** fix #1 and #2 first. The IDOR enables
enumeration and potential cross-scope manipulation, and the no-op home-parish
function silently breaks a core piece of scope/session logic.
