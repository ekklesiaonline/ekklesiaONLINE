# MemberPortal Cutover Checklist

Date: 2026-03-14

## Goal

Cut over the first module, MemberPortal, from legacy app routing/controllers to the namespaced module path with the least risk.

## Current State Summary

MemberPortal is only partially modularized right now.

- Module routes exist in MemberPortal/Config/Routes.php.
- Module controllers exist in MemberPortal/Controllers.
- Both module controllers are thin wrappers that extend legacy app controllers.
- MemberPortal does not yet own any views.
- The onboarding routes /profile/complete and POST /profile/complete are still only defined in app/Config/Routes.php.

## Important Constraint

Do not delete app/Controllers/Profile.php during this cutover.

Reason:

- MemberPortal/Controllers/Profile.php currently extends app/Controllers/Profile.php.
- MemberPortal/Controllers/Dashboard.php currently extends app/Controllers/Dashboard.php.
- Until logic is moved into module-owned controllers or a shared base/service layer, the legacy app controller remains a runtime dependency.

## Prerequisite Checklist

### File: composer.json

Status: Required before route cutover is considered complete.

Tasks:

- Add PSR-4 mapping for MemberPortal\\ to MemberPortal/.
- Run composer dump-autoload.
- Verify the namespace MemberPortal\Controllers resolves.

Acceptance:

- MemberPortal\Controllers\Dashboard and MemberPortal\Controllers\Profile autoload without fallback hacks.

### File: app/Config/Routes.php

Status: Partially duplicated.

Current role-module include:

- app/Config/Routes.php#L28
- app/Config/Routes.php#L29

Legacy MemberPortal-related route block still active:

- app/Config/Routes.php#L42
- app/Config/Routes.php#L43
- app/Config/Routes.php#L44
- app/Config/Routes.php#L45
- app/Config/Routes.php#L46
- app/Config/Routes.php#L47
- app/Config/Routes.php#L48

Tasks:

- Keep the MemberPortal module include in place.
- Do not remove legacy profile routes until module routes and onboarding coverage are complete.
- After module route coverage is complete, delete only the duplicated profile routes that are replaced by MemberPortal.
- Keep or relocate the onboarding routes if they remain intentionally app-owned.

Acceptance:

- No duplicate route ownership remains for the same endpoint.
- /profile, /profile/edit, /profile/update, and /profile/delete-picture resolve through the MemberPortal route file.
- /profile/complete and POST /profile/complete are explicitly assigned either to MemberPortal or intentionally left in the app layer.

## Route Block Checklist

### Route Block: MemberPortal module routes

File:

- MemberPortal/Config/Routes.php

Current coverage:

- MemberPortal/Config/Routes.php#L11
- MemberPortal/Config/Routes.php#L12
- MemberPortal/Config/Routes.php#L15
- MemberPortal/Config/Routes.php#L16
- MemberPortal/Config/Routes.php#L17
- MemberPortal/Config/Routes.php#L18
- MemberPortal/Config/Routes.php#L19

Current endpoints:

- GET /member/dashboard
- GET /profile
- GET /profile/edit
- POST /profile/update
- POST /profile/delete-picture

Gap:

- GET /profile/complete is missing.
- POST /profile/complete is missing.

Tasks:

- Decide whether onboarding belongs in MemberPortal. Recommendation: yes, because it is member-facing profile flow.
- If yes, add both onboarding routes to MemberPortal/Config/Routes.php.
- If no, document that /profile/complete remains app-owned and should not be removed from app/Config/Routes.php during this phase.

Acceptance:

- MemberPortal route file contains the full intended member profile surface.

### Route Block: Legacy profile routes

File:

- app/Config/Routes.php

Current endpoints:

- app/Config/Routes.php#L43
- app/Config/Routes.php#L44
- app/Config/Routes.php#L45
- app/Config/Routes.php#L46
- app/Config/Routes.php#L47
- app/Config/Routes.php#L48

Tasks:

- Remove only these routes after equivalent module coverage exists:
- GET /profile
- GET /profile/edit
- POST /profile/update
- POST /profile/delete-picture
- Keep or move these before deleting them:
- GET /profile/complete
- POST /profile/complete

Acceptance:

- The app route file no longer owns routes that are already owned by MemberPortal.
- There is no accidental loss of profile onboarding flow.

## Controller Checklist

### File: MemberPortal/Controllers/Profile.php

Current state:

- Wrapper only.
- MemberPortal/Controllers/Profile.php#L5 extends App\Controllers\Profile.

Tasks:

- Short-term safe path: keep inheritance and route cutover first.
- Medium-term cleanup path: move profile logic out of app/Controllers/Profile.php into either:
- MemberPortal/Controllers/Profile.php directly, or
- a shared service/base class used by both app and module layers.
- Decide whether onboarding methods complete() and completeSubmit() should move here.

Acceptance:

- MemberPortal controller owns the intended member-facing logic or intentionally delegates through a documented shared layer.

Deletion status:

- app/Controllers/Profile.php is not deletable in Phase 1.

### File: MemberPortal/Controllers/Dashboard.php

Current state:

- Wrapper only.
- MemberPortal/Controllers/Dashboard.php#L5 extends App\Controllers\Dashboard.

Tasks:

- Short-term safe path: keep inheritance and validate GET /member/dashboard.
- Decide whether member dashboard should remain a thin alias of the app dashboard or gain module-specific behavior.
- If module-specific behavior is desired, move dashboard logic into MemberPortal/Controllers/Dashboard.php or a shared extracted service.

Acceptance:

- GET /member/dashboard resolves correctly and returns the intended member-facing destination.

Deletion status:

- app/Controllers/Dashboard.php is not affected by this module cutover and should remain.

### File: app/Controllers/Profile.php

Current state:

- Still active.
- Still referenced by legacy routes.
- Still contains actual business logic for all profile actions.

Tasks:

- Keep during initial MemberPortal route cutover.
- After route cutover, decide whether to:
- leave it as shared profile logic, or
- extract logic into MemberPortal and keep only shared pieces elsewhere.

Acceptance:

- No route still points to app/Controllers/Profile.php unless deliberately retained.

Deletion status:

- Keep for now.

## View Checklist

### Files: app/Views/profile/view.php, app/Views/profile/edit.php, app/Views/profile/complete.php

Current state:

- These are still the only profile views.
- MemberPortal does not have its own views yet.

Tasks:

- Choose one of two approaches:
- Phase 1: keep using app/Views/profile/\*.php and only cut over routes/controllers.
- Phase 2: create MemberPortal/Views/profile/\*.php and update controller rendering paths.
- If onboarding remains in MemberPortal, include complete.php in the move decision.

Recommendation:

- For the first cutover, keep the existing app profile views to reduce moving parts.

Acceptance:

- Views render successfully after route cutover.
- There is a documented plan for whether profile views remain shared or become module-owned later.

Deletion status:

- No app profile view is deletable in the first cutover.

## Testing Checklist

### Route Tests

Verify these routes after autoload and route changes:

- GET /member/dashboard
- GET /profile
- GET /profile/edit
- POST /profile/update
- POST /profile/delete-picture
- GET /profile/complete
- POST /profile/complete

### Behavior Tests

Verify:

- authenticated users can reach their profile
- profile.edit permission still protects edit and update
- delete-picture still returns the expected JSON response
- onboarding redirect from Dashboard::index to /profile/complete still works for pending_profile users
- pending_review users still land on the approval view

### Regression Check

Confirm there is no change in these unrelated routes:

- GET /dashboard
- GET /dashboard/switch/{id}

## Safe First-Cut Sequence

1. Add MemberPortal PSR-4 autoload mapping.
2. Confirm MemberPortal controllers resolve.
3. Decide onboarding ownership for /profile/complete.
4. If onboarding moves, add those routes to MemberPortal/Config/Routes.php.
5. Verify all MemberPortal routes manually or with tests.
6. Remove only the duplicated legacy profile routes from app/Config/Routes.php.
7. Keep app/Controllers/Profile.php and app profile views in place for now.

## Definition of Done

MemberPortal first-cut is complete when:

- MemberPortal namespace autoload works.
- MemberPortal owns all intended profile/member dashboard routes.
- Legacy app route duplicates for profile are removed.
- Onboarding routes are either moved or explicitly retained by design.
- The profile controller and views still function without regression.
- No file is deleted prematurely.
