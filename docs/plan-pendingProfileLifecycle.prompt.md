## Plan: Pending-Profile Lifecycle Completion

Create a reliable, visible onboarding lifecycle with `user_registration_requests.status` as the canonical request state. Harden creation and transitions, expose incomplete users in the existing People registration area without treating them as review-ready, align schema-dependent code, and add behavioral tests/documentation.

**Steps**

**Phase 1 — State and persistence contract**
1. Document the state matrix: `pending_profile` means required profile fields/parish selection incomplete; `pending_review` means submitted and ready for parish review; terminal/moderation states remain `approved`, `dismissed`, and `banned`. Keep user activation and the Shield `users.active` flag conceptually separate from request status.
2. Validate the selected parish's complete parish → diocese → province chain before Shield account creation in `RegisterController`; ensure registration cannot create an account that silently lacks its request row. Replace the registration event's best-effort early exits with an explicit, observable failure strategy appropriate to registration (preserve error handling when onboarding tables are unavailable). Preserve `ScopedEmailActivator` sender resolution and Shield's activation lifecycle.
3. Align `UserRegistrationRequestModel`, `RegistrationApprovalService`, and migrations on canonical plural table / `requested_parish_id`, retaining legacy schema fallback only where deliberately supported. Fix `2026-09-22-002958_BackfillApprovedRegistrationAffiliationsMigration` to work against canonical schema and test that schema.

**Phase 2 — Lifecycle transitions**
4. Keep profile validation in `Profile::completeSubmit()` and `joinParishSubmit()`, but make profile/request persistence atomic where practical; send audit/notification side effects only after a successful commit. Preserve elevated-admin auto-approval.
5. Enforce a transition matrix in the existing lifecycle/moderation service paths: approval remains `pending_review` only; dismiss/ban may handle pending requests; escalation must reject `pending_profile` and accept only `pending_review`; deletion retains its existing permission/scope/hierarchy protections. Return localized errors consistent with current controller handling.
6. Add service/controller regression coverage for every allowed and rejected transition, including missing profile, missing/invalid hierarchy, scope mismatch, elevated-admin auto-approval, and retries/idempotency.

**Phase 3 — People queue and navigation**
7. Extend `UserRegistrationRequestModel` with a parish-scoped `pending_profile` list/count query, reusing its existing dynamic table and parish-column resolution. Keep the current `pending_review` query semantics unchanged.
8. Extend `People::index()` and `Clergy/Views/People/index.php` with a distinct incomplete-profile section or status filter. Display available identity/profile fields and age/created timestamp; do not expose approval for this stage. Provide only actions that are valid for incomplete requests (dismiss/ban, and delete only with existing permission); do not provide escalation until completion. Reuse `RegistrationApprovalService` and current CSRF/confirmation patterns.
9. Add a pending count badge to the existing `People` sidebar item for authorized parish admins, using the viewer's operational parish scope. Do not add a second `PendingUsers` controller or route. Keep the current profile-pending badge as a separate self-service signal.

**Phase 4 — Verification and documentation**
10. Add/update focused database integration tests for registration creation with valid hierarchy, rejected invalid hierarchy, user/profile/identity/request records, pending-profile listing isolation, stage-specific actions, scope/permission checks, and canonical migration behavior. Keep the existing test fixtures/schema compatible.
11. Update the canonical registration lifecycle documentation and retire stale claims about profile membership status; describe activation vs. registration status, incomplete-profile queue visibility, and the transition matrix.
12. Run focused tests, route smoke tests, PHP syntax checks for changed PHP files, then the full suite (`vendor/bin/phpunit --no-coverage`). Manually walk registration → email activation → profile completion → People queue → moderation, including a pending-profile rejection and successful pending-review approval.

**Relevant files**
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Controllers/Auth/RegisterController.php` — validate full hierarchy before calling Shield registration.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Config/Events.php` — onboarding bootstrap and current `pending_profile` creation.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Authentication/ScopedEmailActivator.php` and `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Config/AuthGroups.php` — preserve activation and default member-group behavior.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Models/UserRegistrationRequestModel.php` — canonical table/column resolution and stage-scoped queue queries.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/MemberPortal/Controllers/Profile.php` — validated completion and parish-join transitions.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Services/RegistrationApprovalService.php` — moderation transition guards and escalation restriction.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/Clergy/Controllers/People.php`, `/Users/juanrosariodelacruz/Sites/ekklesiaRD/Clergy/Views/People/index.php`, and `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Views/partials/sidebar.php` — existing queue and People navigation badge.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/Clergy/Config/Routes.php` and `/Users/juanrosariodelacruz/Sites/ekklesiaRD/Diocese/Controllers/RegistrationRequests.php` — preserve current route/action and scope ownership; no new Parish controller.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Database/Migrations/2026-09-22-002958_BackfillApprovedRegistrationAffiliationsMigration.php` and `/Users/juanrosariodelacruz/Sites/ekklesiaRD/app/Database/canonical_schema_tables.sql` — canonical column compatibility.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/tests/database/ProfileRegistrationCompletionIntegrationTest.php`, `/Users/juanrosariodelacruz/Sites/ekklesiaRD/tests/database/RegisterParishHardStopIntegrationTest.php`, `/Users/juanrosariodelacruz/Sites/ekklesiaRD/tests/database/MembersPendingRegistrationsScopeIntegrationTest.php`, and new/existing focused registration-service tests — behavior verification.
- `/Users/juanrosariodelacruz/Sites/ekklesiaRD/docs/REGISTRATION_TO_GOOD_STANDING_FLOW_REPORT_2026-04-10.md` and `/Users/juanrosariodelacruz/Sites/ekklesiaRD/docs/VISITOR_LIFECYCLE.md` — reconcile lifecycle documentation with current schema and behavior.

**Verification**
1. Run each focused suite separately: `vendor/bin/phpunit --no-coverage tests/database/ProfileRegistrationCompletionIntegrationTest.php`, `vendor/bin/phpunit --no-coverage tests/database/RegisterParishHardStopIntegrationTest.php`, and `vendor/bin/phpunit --no-coverage tests/database/MembersPendingRegistrationsScopeIntegrationTest.php`, plus focused tests added for status transitions and model query scope.
2. Run `vendor/bin/phpunit --no-coverage tests/unit/ModuleRoutesSmokeTest.php` and `php -l` on touched PHP files.
3. Run the full suite with `vendor/bin/phpunit --no-coverage`.
4. Manually verify: invalid/incomplete hierarchy does not leave an account without onboarding state; valid registration creates account, default `member` group, email/password identity, sparse profile and `pending_profile`; activation unlocks profile completion; failed validation leaves status unchanged; successful completion records `profile_completed_at` and becomes `pending_review`; only review-ready users can be approved/escalated; queue and badge respect parish scope and permissions.

**Decisions**
- Extend the existing People registration area; do not create `Parish/Controllers/PendingUsers.php` or a duplicate route/surface.
- Keep incomplete profiles distinct from review-ready requests; approval remains unavailable until completion.
- Require profile completion before escalation to `pending_review`.
- Treat canonical `user_registration_requests.status` as onboarding state; Shield activation and `users.active` are separate concerns.
- Exclude unrelated redesign/refactoring and do not change membership lifecycle semantics.