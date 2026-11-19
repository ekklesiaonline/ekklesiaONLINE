# MemberPortal Module Security Audit — ekklesiaONLINE

**Scope:** `MemberPortal/` module (~60 routes in `MemberPortal/Config/Routes.php`, 16 controllers): personal dashboard (`MyDashboard`, `Dashboard`), profile self-service (`Profile`, `ProfileOnboarding`), household management (`Families`, `FamiliesPages`, `Family`), stewardship (`AnnualPledges`), good standing (`Standing`), leadership offices (`LeadershipPositions`), session security (`SessionSecurity`), audit viewing (`AuditLogs`, `AuditLogExports`), media serving (`PublicAssets`), and public entry (`PublicHome`, `PublicLocale`). Canonical services reviewed: `MemberAffiliationService`, `MemberAccessService`, `NonPortalMemberProvisioningService`, `TrustedDeviceService`, `AuditLogQueryService`, `MemberParishAffiliationModel`. Boundary filters re-verified in `app/Config/Filters.php`: `parishScope`, `diocesanOperationalBoundary`, `adminFinanceBoundary`, `apiRateLimit`, `loginRateLimit`, `deviceVerification`, `sessionTracking`.

**Method:** static review of routes, filters, controllers, and the canonical services/models they call. Builds on the earlier MemberPortal remediation (PR #47, findings F1–F9: affiliation scope pinning, serveUpload auth gate, event-media visibility, pending_review holds, pledge audit redaction, rate-limit coverage); this pass covers the remaining attack surface, prioritizing the cross-tenant affiliation IDOR.

**Remediation status (this pass):** all findings M-1 through M-5 were fixed in the same session — `Profile.php` (M-1), `AnnualPledges.php` (M-2 + year bound), `Standing.php` + `Views/Standing/index.php` (M-3), `AuditLogExports.php` (M-4), `Dashboard.php` + `SessionSecurity.php` (M-5). Lang keys added in en/es/fr (`Profile.affiliationOutsideDiocese`, `Pledges.memberNotInParish`). New regression test: `testCrossDioceseAffiliationIsRejected` in `ProfileAffiliationScopeIntegrationTest`. All changed files pass `php -l`; 158 related PHPUnit tests pass.

---

## Confirmed vulnerabilities

### M-1 — HIGH: cross-tenant affiliation IDOR in `Profile::update` — self-service `affiliations[]` could inject a live `parish_affiliation` row into ANY parish

**Evidence:** `MemberPortal/Controllers/Profile.php` — the posted `affiliations[*][parish_id]` list was validated only for *existence* (`ParishModel->find()`); PR #47 pinned new rows to `membership_status='visitor'` and stopped status rewrites, but imposed no tenant boundary. Any authenticated `profile.edit` holder could declare an affiliation to an arbitrary parish in any diocese/province and `MemberAffiliationService::replaceSecondaryAffiliationsForUser()` would write the row immediately — bypassing `ProfileOnboarding::joinParishSubmit()`, which routes the same action through `pending_review` admin approval.

**Impact:** an injected `visitor`/`regular_attender` affiliation lands in the foreign parish's `/visitors` directory (`total_visitors` counts `membership_status IN ('visitor','regular_attender')`), triggers its visitor follow-up pipeline, writes `person_status_history` entries, and makes the member selectable in that parish's household workflows — cross-tenant register pollution with no approval trail. (`is_member=0` on visitor rows limits escalation, but the write itself is unauthorized.)

**Fix:** `Profile::update` now resolves the member's permitted dioceses up front — profile `diocese_id`, pending registration-request diocese, the primary parish's diocese, and the dioceses of all existing live affiliations — then a posted parish that is (a) not already affiliated and (b) outside that diocese set is rejected with `Profile.affiliationOutsideDiocese` (en/es/fr). Existing live affiliations (including operator-granted cross-diocese rows) are preserved untouched, matching the `onlyCreateMissing` reconciliation semantics. Regression coverage: `testCrossDioceseAffiliationIsRejected`; fixtures updated to seed real dioceses.

---

### M-2 — HIGH: `AnnualPledges::store` accepted a `user_id` with no affiliation check — pledge rows fileable against arbitrary cross-tenant users

**Evidence:** `MemberPortal/Controllers/AnnualPledges.php:95` — `'user_id' => 'required|is_not_unique[users.id]'` was the only check; `parish_id` is forced server-side to the attribution parish, but the *member* the pledge belongs to was never verified to belong to that parish. A `finance.manage` holder could file `annual_pledge` rows against any user id in the system (cross-tenant IDOR), and the row then surfaces in that parish's pledge list joined to the victim's name/email.

**Fix:** after the attribution parish resolves, `MemberParishAffiliationModel::isUserAffiliatedWithParish($targetUserId, $attributionParishId, false)` is now required — any live (non-deleted) affiliation qualifies, covering `member`/`regular_attender` pledgers. Denials return `Pledges.memberNotInParish` (en/es/fr).

---

### M-3 — MEDIUM: `Standing::refresh` let any `member.edit` holder trigger a system-wide delete+rebuild of `member_standing_summary`

**Evidence:** `MemberPortal/Controllers/Standing.php:38` — `refresh()` gated on `member.edit` then called `MemberStandingSummaryModel::refreshForYear($year)`, which `DELETE`s every row for the year and recomputes snapshots for **all** users in **all** parishes. No parish-scoped variant exists. A parish-level `member.edit` holder (or a compromised one) could rewrite — or repeatedly recompute to exhaust — every tenant's standing data; `year` was also unbounded.

**Fix:** `refresh()` now requires an elevated admin (`systemadmin`/`provinceadmin`/`dioceseadmin`); the refresh card in `Views/Standing/index.php` renders only when `can_refresh` is true. `index`, `summaryApi`, and `refresh` clamp `year` to `2000..currentYear` via `boundStandingYear()`.

---

### M-4 — MEDIUM: audit-log CSV export written with raw `fputcsv` — formula-injection vector via audit payloads

**Evidence:** `MemberPortal/Controllers/AuditLogExports.php:45,66` — `exportCsv()` streamed `audit_log` fields (actor-controlled `user_agent`, `old_values`, `new_values`) straight through `fputcsv()`. Cells beginning with `=`, `+`, `-`, `@`, tab, or CR execute as formulas when an admin opens the export in a spreadsheet — the same class of bug the Clergy audit fixed via `fputcsv_safe`/`csv_safe_row` (`format_helper`, autoloaded).

**Fix:** header and data rows now use `fputcsv_safe()`, matching the Clergy convention.

---

### M-5 — LOW: contract-shape drift on `followupStats` and `keepalive` JSON responses

**Evidence:** `Dashboard::followupStats` returned `{success, <metrics>}` and `SessionSecurity::keepalive` returned `{success, ts}` / a partial error shape — both missing the contract's `status`/`meta` keys (and `error.details`/`errors` on the keepalive denial).

**Fix:** both endpoints now emit the full success/error envelope per `docs/API_AJAX_ERROR_CONTRACT.md` (`status`, `message`, `meta.http_status`, `meta.ts`; `error.details` + `errors` on denials).

---

## Verified-secure paths (spot-checked, no findings)

- **`Families`/`FamiliesPages`/`Family`:** every mutation enforces `family.manage` + `requireParish()`; `family_id` and `family_member` relation ids are resolved and verified against `family.parish_id === getOperationalParishId()` before write; posted `user_id`/`head_of_household_id` must satisfy `isUserAffiliatedWithParish(..., true)`; `availableMembers` is parish-scoped; `addMemberNonPortal`/`enablePortal` delegate to `MemberAccessService`, which independently re-verifies `family.parish_id`, the target's `family_member` link, and the target's active parish affiliation before minting credentials. Duplicate-relation races handled via unique-constraint catch.
- **`LeadershipPositions`:** `leadership_position.manage` + `canManage()` scope check + `guardTargetUserMutation` (AdminHierarchyGuard) + `ClergyAuthorizationService` ecclesial check (`pastoral.parish.manage`/`clergy.appointment.manage` against the position's real scope) + `eligibilityFor` on create and reactivation; province admins hard-blocked; diocesan admins confined to `assignedDioceseId()`. `update`/`delete` re-resolve the position and re-run the whole chain.
- **`SessionSecurity`:** device-verify code is sha256-hashed in session, 5-attempt lockout, 60-second resend cooldown, expiry enforced; `revoke`/`revokeAll`/`forgetCurrent` are user-scoped via `TrustedDeviceService` (`where('user_id', $userId)`); `safeReturnUrl` blocks external and self-referential redirect targets.
- **`AuditLogs`/`AuditLogExports`:** `isAuditAdmin()` gate + `AuditLogQueryService::applyScopeFilter` on every list/export/diff path; non-system `parish_id` filters are dropped when outside scope; exports are row-capped and stream unbuffered.
- **`MyDashboard`:** `receipt` requires `donation.user_id === auth()->id()`; `statement`/`submitPledge` force `parish_id`/`user_id` from the auth profile, gate `pending_review`, bound the year, validate, and redact financial values in audit meta.
- **`PublicAssets`:** `serveUpload` requires auth + path sanitizer + sensitive-prefix denylist; `profilePicture` requires self/systemadmin or shared-parish affiliation; parish/event media route through `canAccessParish`/`EventVisibilityService` (`getEventWithCountsForParishAudience`); all file paths are traversal-guarded under `WRITEPATH/uploads` with `realpath` containment.
- **`ProfileOnboarding`:** `completeSubmit` locks `province_id`/`diocese_id`/`parish_id` to the registration request, validates `dioceseInProvince`/`parishInDiocese`, auto-approves only elevated admins; `joinParishSubmit` requires admin review (`pending_review`) — the channel M-1 now defers to.
- **`Dashboard::switchParish`:** province-admin only, parish must resolve inside `resolveAdminProvinceId()`, covered by `provinceScope`/`dioceseScope`/`apiRateLimit` in `Filters.php`.
- **Filters (re-verified):** `parishScope` covers `families/*`, `leadership/*`; `diocesanOperationalBoundary` covers `families/*`, `standing/*`; `adminFinanceBoundary` covers `pledges/*`; `apiRateLimit` covers `pledges/*`, `families/*` (incl. `addMemberNonPortal`, `enablePortal`), `leadership/*`, `standing/refresh`, `profile/update`, `profile/join-parish`, `me/pledge`, `dashboard/switch/*`; `loginRateLimit` covers `session/device-verify*`.

---

## Observations (not vulnerabilities)

- `families/addMemberNonPortal` and `families/enablePortal/(:num)` are defined outside the `session` filter group by design ("controller enforces auth/scope and always returns JSON"); `auth()->loggedIn()` + permission checks are in-controller, and CSRF/`registrationGate`/`deviceVerification`/`sessionTracking` still apply globally.
- `locale/set/(:segment)` is a GET that mutates session + `profile.locale` — acceptable for a locale switcher; return-URL resolution already restricts to same-host paths and blocks API/media/locale prefixes.
- `AnnualPledges::store` `pledge_year` has a lower bound (2000) but no upper bound in the store rule — cosmetic; index filter is now clamped.
- `AuditLogs` AJAX endpoints (`actorAutocomplete`, `loadMore`, `statistics`, `realtimeUpdates`) return `{error: '...'}` on denials rather than the full contract envelope — consistent internally; contract shape is only enforced on the diff endpoint.

## Suggested follow-ups

- Consider a parish-scoped `refreshForYear($year, $parishId)` variant in `MemberStandingSummaryModel` so parish admins can recompute their own tenant's standing without a system-wide rebuild (M-3 currently denies them entirely).
- Same-diocese self-service affiliation additions (M-1) currently write instantly as `visitor`; if product wants parity with `joinParish`'s review gate, route new secondary affiliations through `pending_review` as well.

---

## Remediation note — password write paths (follow-up pass)

All password write paths outside self-registration previously bypassed Shield's `passwordValidators`, left operator-chosen passwords permanent, and did not invalidate sessions/devices on reset. Remediated in this pass:

- `BaseController::validateAdminPassword()` now runs `service('passwords')->check()` (composition, nothing-personal, dictionary, SafePwned breach check) after the DB-driven composition checks, and floors the effective minimum at `config('Auth')->minimumPasswordLength` (never below 12); `password_policy.min_length` in system settings can no longer be set below 12.
- `MemberAccessService::enablePortalAccess` and `PersonConversionService::createPortalUser` hash via `service('passwords')->hash()`; raw `password_hash()` calls removed.
- Admin reset endpoints (`SystemAdminUsers`, `ProvinceDioceseAdmins`, `DioceseAdminUsers`, `DioceseParishAdmins` via `SystemAdminUserService::updatePasswordIdentity`) write `force_reset=1`, revoke all trusted devices (`TrustedDeviceService::revokeAll`), and delete all tracked sessions (`UserSessionModel::removeAllForUser`).
- `Clergy\Controllers\VisitorLifecycle::promote` (`create_account`) requires `strong_password`; `createPortalUser` writes `force_reset=1` and sends the canonical portal-invite email.
