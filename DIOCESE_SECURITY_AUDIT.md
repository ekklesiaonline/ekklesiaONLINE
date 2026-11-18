# Diocese Module — Security Audit

Read-only audit of the Diocese surface of ekklesiaONLINE: `Diocese/` module controllers
(`ChartOfAccounts`, `ClergyAccounts`, `Dashboard`, `FinanceDashboard`, `FollowupWorkflow`,
`LiturgicalCalendar`, `Parishes`, `ParishStaff`, `RegistrationRequests`), the `App\Controllers\Diocese\*`
controllers (`ClergyReview`, `Deaneries`, `EmailSettingsController`), `App\Controllers\DioceseAdminUsers`
and `App\Controllers\DioceseParishAdmins`, the route files (`Diocese/Config/Routes.php`,
`app/Config/Routes.php` `parish_list`/`system` groups), the boundary filters
(`DioceseScopeFilter`, `DiocesanOperationalBoundaryFilter`), `EmailSettingsTrait` /
`EmailSettingsService`, `ClergyReviewService` / `ClergyAuthorizationService`, and the
`Diocese/Views/*` templates.

Verdict: diocese tenant isolation is broadly well implemented — every controller resolves
scope server-side (`resolveDiocesanScopeId`, `adminDioceseId`, `requirePrimaryDiocese`,
`canManageParish`, `clergyInScope`, `deaneryInDiocese`, `canMutateAccount`, `canManageRow`)
and `ClergyReview` goes further with `ClergyAuthorizationService` + `isCurrentOrdinary`.
The material gaps are: one cross-tenant IDOR on email settings (HIGH), a missing
self/hierarchy guard on clergy deactivation, a missing primary-parish-admin guard, and
the by-now-familiar `apiRateLimit` coverage gap on every diocese mutation route.

**No code was changed.** This document is the findings report plus the remediation plan.

---

## Findings

| # | Severity | Finding | Location |
|---|----------|---------|----------|
| D1 | **HIGH** | Cross-tenant email-settings IDOR: `diocese_id`/`scope_id` taken from request, never checked against caller's scope — read/modify another diocese's sender config, SMTP credentials, and fire test emails through a foreign transport | `app/Controllers/Diocese/EmailSettingsController.php:19–62`; `app/Controllers/EmailSettingsTrait.php`; same flaw in `app/Controllers/Province/EmailSettingsController.php` |
| D2 | MEDIUM | `ClergyAccounts::deactivate` has no self-check, no primary-diocesan-admin check, no `AdminHierarchyGuard` — any `clergy.manage` holder can deactivate a peer diocesan admin holding `clergy` group (lockout) or themselves | `Diocese/Controllers/ClergyAccounts.php:637–661` |
| D3 | MEDIUM | `apiRateLimit` covers **none** of the diocese mutation routes (account creation, password resets, invitation resends, deanery/finance/workflow writes, parish_list mutations) | `app/Config/Filters.php` (`apiRateLimit.before` list) vs `Diocese/Config/Routes.php`, `app/Config/Routes.php` |
| D4 | LOW | `DioceseParishAdmins::deactivate`/`resetPassword` lack the `ParishAdminPrimaryModel` guard that `DioceseAdminUsers` has for the primary diocesan admin — a diocesan primary can deactivate/reset a parish's designated primary admin | `app/Controllers/DioceseParishAdmins.php:205–316` |
| D5 | LOW | Admin-set staff passwords validate `min_length[8]` while the auth policy is 12 (raised in PR #46) | `app/Services/ParishStaffService.php:46` |
| D6 | LOW | `Parishes` JSON error responses violate the API contract — `{success:false,message}` without `error.code`/`apiErrorPayload` | `Diocese/Controllers/Parishes.php` (`store`, `delete`, `deleteImpact`, `bulkDelete`) |
| D7 | LOW | `diocese/*` paths absent from `dioceseScope` filter `before`-list — scoping relies solely on per-controller checks (all verified present today); filter-layer coverage is the defense-in-depth norm | `app/Config/Filters.php` (`dioceseScope.before`) |
| D8 | LOW | `liturgical-source` route group has only the `session` filter; gating is in-controller (verified correct) — fragile pattern: a future route added there inherits no permission gate | `Diocese/Config/Routes.php:53–55` |
| D9 | INFO | Dead `DiocesanScopeFilter` alias class (extends `DioceseScopeFilter`, referenced nowhere) — confusion risk about which filter is canonical | `app/Filters/DiocesanScopeFilter.php` |
| D10 | INFO | `RegistrationRequests` lives in the Diocese module but enforces a **parishadmin** scope (`resolveParishModerationScope`) — diocesan admins without `parishadmin` cannot moderate registrations; likely intended (parish moderation is parish-level) but worth a comment/confirm | `Diocese/Controllers/RegistrationRequests.php:196–225`, routes in `Clergy/Config/Routes.php:47–52` |

### Detail

#### D1 — Cross-tenant email-settings IDOR (HIGH)

`EmailSettingsController::index` resolves `diocese_id` from the **path, then `GET diocese_id`,
then the caller's profile**; `save`/`test` accept `POST scope_id` the same way
(`app/Controllers/Diocese/EmailSettingsController.php:19–62`). `EmailSettingsTrait` only
verifies the Shield permission (`diocese.admin`) and that the submitted scope matches what
the controller already resolved — it **never checks the scope belongs to the caller**.

A diocesan admin of diocese A can therefore:

- `GET /diocese/email-settings?diocese_id=<B>` — read diocese B's effective sender config
  (from-name, reply-to, SMTP host/username; password is masked but everything else renders).
- `POST /diocese/email-settings` with `scope_id=<B>` — overwrite diocese B's general settings;
  a primary diocesan admin (`diocese.primary`) can overwrite B's **SMTP credentials**.
- `POST /diocese/email-settings/test` with `scope_id=<B>` and an arbitrary `test_recipient` —
  send mail through B's transport to an attacker-chosen address (reputation abuse, phishing
  from a legitimate diocesan relay, confirmation that B's SMTP creds work).

`app/Controllers/Province/EmailSettingsController.php` has the identical flaw
(`province_id`/`scope_id` from the request, only `province.admin` checked). The Parish
variant is safe: it resolves `getOperationalParishId()` server-side and ignores request scope.

Fix mirrors the Parish pattern: resolve the diocese/province server-side
(`resolveDiocesanScopeId()` / `resolveAdminProvinceId()`), reject any request-supplied scope
that does not match, and let system admins address any scope explicitly.

#### D2 — `ClergyAccounts::deactivate` missing self/hierarchy guard (MEDIUM)

`deactivate` (`Diocese/Controllers/ClergyAccounts.php:637–661`) checks only
`authorizedDiocese()` + `clergyInScope()` then writes `users.active = 0`. Any user with the
`clergy.manage` permission — i.e. **any** diocesan admin, not only the primary — can
deactivate:

- Themselves (self-lockout — the UI offers the button on their own row).
- A peer or the **primary** diocesan admin who also holds the `clergy` group (very common:
  bishops/ordinaries are clergy). Peer lockout is a real intra-tenant attack.

Siblings do this correctly: `DioceseAdminUsers::deactivate` blocks self-deactivation and the
primary diocesan admin (`isPrimaryDioceseUser`); `ParishStaff` runs `guardTargetUserMutation`
(`AdminHierarchyGuard`). `ClergyAccounts::update` and `resendInvitation` also lack hierarchy
checks but carry less impact.

#### D3 — `apiRateLimit` absent on all diocese mutations (MEDIUM)

`apiRateLimit.before` covers none of:

- `diocese/clergy/*` — `store`, `update`, `deactivate`, `resend-invitation`
  (**outbound email**, invitation spam)
- `diocese/clergy-review/*` — ~15 POST endpoints (approve/revoke offices, delegations,
  standing, report policy)
- `chart-accounts/*` — `store`, `update`, `toggle`
- `diocese/deaneries/*`, `diocese/congregations/*/type`
- `diocese/admins/*`, `diocese/parish-admins/*` — **account creation, password reset,
  deactivate, reactivate, delete** (high-value targets)
- `diocese/followup-workflow/*`
- `diocese/email-settings` (save — only `/test` is covered)
- `parish_list/*` — `store`, `update`, `delete`, `bulk-delete` (**destructive**), staff mutations

Same class of gap fixed in the Volunteer remediation (PR #61). Note `ApiRateLimitFilter`
now strips `index.php/` (commit `c06d07a`), so anchored patterns must match the normalized
path.

#### D4 — `DioceseParishAdmins` lacks primary-parish-admin guard (LOW)

`deactivate` and `resetPassword` guard self only
(`app/Controllers/DioceseParishAdmins.php:205–316`). `DioceseAdminUsers` additionally blocks
mutating the **primary** account of the diocese (`isPrimaryDioceseUser`). The analogue —
blocking mutations of a parish's designated primary admin (`ParishAdminPrimaryModel`) — is
missing, so a diocesan primary can deactivate or reset the password of a parish's primary
admin. Possibly intentional (the diocesan primary is the delegation root and a recovery path
is desirable); confirm intent — if kept, document it.

#### D5 — Staff password policy below auth minimum (LOW)

`ParishStaffService::staffValidationRules` enforces `min_length[8]` for admin-set passwords
(`app/Services/ParishStaffService.php:46`). The auth audit raised the account minimum to 12.
Diocese/parish-created staff accounts can still be issued 8-char passwords.

#### D6 — `Parishes` JSON envelope contract violations (LOW)

`store`/`delete`/`deleteImpact`/`bulkDelete` return `['success' => false, 'message' => …]`
without `error.code` — violating `docs/API_AJAX_ERROR_CONTRACT.md` (`error.code` mandatory)
and the shared-JS error handling that key off `error.code`. Example:
`return $this->jsonResponse(['success' => false, 'message' => lang('Diocesan.parishDeleteRestricted')], 403);`

#### D7 — `diocese/*` not in `dioceseScope` before-list (LOW, defense-in-depth)

`dioceseScope.before` covers `parish_list*`, `dashboard/switch/*`, `chart-accounts*`,
`liturgical-source*` — but not `diocese/*`. Every diocese controller self-resolves scope
today (verified), so nothing is exploitable; adding the filter gives a second enforcement
layer and auto-populates `diocesan_scope_id` for `dioceseadmin`-group callers.

#### D8 — `liturgical-source` group is `session`-only (LOW)

`Diocese/Config/Routes.php:53–55` gates `diocese/liturgical-source*` with just `session`.
The controller self-gates correctly (view = system/diocese/province admin, manage = system
only — verified), but a future mutation added under this group inherits no permission
filter.

#### D9 — Dead `DiocesanScopeFilter` alias (INFO)

`app/Filters/DiocesanScopeFilter.php` is an alias extending `DioceseScopeFilter`, referenced
nowhere — delete to avoid confusion about which is canonical.

#### D10 — `RegistrationRequests` placement (INFO)

A `Diocese`-module controller enforcing `parishadmin` scope (parish moderation) is
surprising; behavior is correct (`RegistrationApprovalService` + hierarchy guard inside),
just confirm diocesan admins aren't expected to moderate registrations.

---

## Verified clean

- **`ClergyReview`** — every action runs `ClergyAuthorizationService::allows($userId, $perm, 'diocese', $dioceseId)`; delegation/policy actions additionally require `isCurrentOrdinary`; `liftStanding`/`revokeDelegation` read jurisdiction from the row, not the request; `approveMissionOffice` verifies `scope_type=parish`; contract-shaped errors throughout.
- **`Deaneries`** — `authorizedDiocese(perm)` + `deaneryInDiocese`/`parishInDiocese` on every mutation; writes pinned `WHERE diocese_id`.
- **`DioceseAdminUsers`** — `diocese.primary` required (`requirePrimaryDiocese`), `targetIsInDiocese`, self-guards on reset/delete, `isPrimaryDioceseUser` protected, audit-logged.
- **`Dashboard` / `FinanceDashboard` / `switchParish`** — `isDioceseAdmin` + `resolveDiocesanScopeId`; parish filters validated against scope.
- **`LiturgicalCalendar`** — view requires system/diocese/province admin; manage requires system only; entries scoped by ownership.
- **`ChartOfAccounts`** — `isSystemAccount` read-only + `canMutateAccount(account,'diocese',$id)` on every mutation.
- **`ParishStaff`** — province block, role whitelist, `guardTargetUserMutation`, `isUserInParish`, self-demotion guards, `logRoleAudit`.
- **`FollowupWorkflow`** — `canManageRow` requires `parish_id IS NULL` + `diocese_id` match; `resolveScopeAssignment` server-side.
- **`Parishes`** — `canAccessParishesModule`/`canCreateParish`/`canDeleteParish`/`canManageParish`; province read-only; identity fields system-only; logo/banner uploads use `is_image` + `mime_in` + `getRandomName` + traversal-safe `resolveStoredUploadAbsolutePath`.
- **`RegistrationRequests`** — `parishadmin` + province-read-only block + `RegistrationApprovalService` (hierarchy guard inside); bulk action whitelist; `member.delete` gate on delete.
- **Views** — `esc()`/`lang()` used on all rendered data; only structural `<?=` remain.
- **`DioceseScopeFilter`** — validates `parish_id` GET/POST against the caller's diocese for covered routes.

---

## Remediation plan

Ordered, self-contained Devin prompts. **Each prompt produces exactly one logical commit.**
Run them in order; P1–P3 are the security fixes, P4–P8 hardening, P9 verify-first cleanup.
Same conventions as `VOLUNTEER_SECURITY_AUDIT.md`: work on a fresh branch off `main`,
`vendor/bin/phpunit --no-coverage` green, `vendor/bin/phpstan analyse` clean, and every new
JSON error must be `apiErrorPayload`-shaped with an `error.code`.

> ### P1 — Scope-lock diocese and province email settings to the caller's scope (fixes D1)
>
> Context: `app/Controllers/Diocese/EmailSettingsController.php` resolves `diocese_id` from
> the path/GET/POST (`scope_id`), and `app/Controllers/Province/EmailSettingsController.php`
> does the same for `province_id`. `EmailSettingsTrait` checks only the Shield permission —
> a diocesan admin can read/write ANY diocese's email settings (including SMTP credentials
> when they hold `diocese.primary`) and send test emails through a foreign transport.
> `app/Controllers/Parish/EmailSettingsController.php` is the safe model: it resolves
> `getOperationalParishId()` server-side.
>
> Change (one logical commit):
> - In `Diocese\EmailSettingsController::{index,save,test}`: resolve the diocese server-side
>   via `resolveDiocesanScopeId()`. If the caller is a system admin, allow the
>   request-supplied id; otherwise require the resolved diocese to match any supplied
>   `diocese_id`/`scope_id` (mismatch → `apiErrorPayload('Forbidden', 403, 'SCOPE_FORBIDDEN')`
>   for POST / toast+redirect for GET, matching each endpoint's existing response style).
> - Same fix in `Province\EmailSettingsController` using `resolveAdminProvinceId()`.
> - Leave `EmailSettingsTrait` untouched (it is shared with the safe Parish variant and System).
> - Tests (`CIUnitTestCase`): diocesan admin of diocese A cannot GET index, save, or test
>   against diocese B's id (403 + no row changed); non-primary diocesan admin still cannot
>   edit SMTP fields; system admin CAN act on an arbitrary scope. Add the diocese and
>   province variants.
> - Commit title: `Fix cross-tenant scope bypass in diocese/province email settings`.

> ### P2 — Guard `ClergyAccounts::deactivate` against self and elevated targets (fixes D2)
>
> Context: `Diocese/Controllers/ClergyAccounts.php:637–661` deactivates any `clergy`-group
> account in the admin's diocese — including a peer diocesan admin who holds `clergy`
> (common for ordinaries) or the actor themselves. Siblings:
> `DioceseAdminUsers::deactivate` (self + `isPrimaryDioceseUser`),
> `ParishStaff` (`guardTargetUserMutation` via `AdminHierarchyGuard`).
>
> Change (one logical commit):
> - In `ClergyAccounts::deactivate`, after `clergyInScope`: return 403/redirect-deny when
>   `$id === auth()->id()`; run `guardTargetUserMutation($id)` (or equivalent
>   `AdminHierarchyGuard` check) so a `clergy.manage` holder cannot deactivate a
>   `systemadmin`/`provinceadmin`/`dioceseadmin` target; also refuse when the target is the
>   primary diocesan admin (`DioceseAdminPrimaryModel::getByDiocese`), with a
>   `Diocesan.*`/`Clergy.*` lang message telling them to transfer primacy first.
> - Apply the same `guardTargetUserMutation` check to `update` and `resendInvitation` for
>   consistency (lower impact, same one-line guard).
> - Tests: self-deactivate denied; deactivating a dioceseadmin-holding clergy user denied;
>   ordinary clergy deactivate still succeeds (users.active=0 + audit row).
> - Commit title: `Add self and hierarchy guards to diocesan clergy deactivation`.

> ### P3 — Extend `apiRateLimit` to all diocese mutation routes (fixes D3)
>
> Context: `app/Config/Filters.php` `apiRateLimit.before` covers no diocese mutation route.
> `ApiRateLimitFilter::normalizePath` strips `index.php/` (commit `c06d07a`) — patterns must
> match the normalized path.
>
> Change (one logical commit): add to `apiRateLimit.before`:
> - `diocese/clergy/store`, `diocese/clergy/update/*`, `diocese/clergy/deactivate/*`,
>   `diocese/clergy/resend-invitation/*` (30/min tier — sends email)
> - `diocese/clergy-review/*`
> - `chart-accounts/*`
> - `diocese/deaneries/*`, `diocese/congregations/*/type`
> - `diocese/admins/*`, `diocese/parish-admins/*`
> - `diocese/followup-workflow/*`
> - `diocese/email-settings` (POST save)
> - `parish_list/store`, `parish_list/update/*`, `parish_list/delete/*`,
>   `parish_list/bulk-delete`, `parish_list/staff/*`
> - Give `resend-invitation` and `admins`/`parish-admins` mutations the strictest tier
>   (invitation + credential-bearing mail), consistent with the tiers used in PR #61.
> - Tests: a burst past the limit on `diocese/clergy/resend-invitation` and
>   `parish_list/bulk-delete` returns the contract-shaped 429.
> - Commit title: `Rate-limit diocese mutation endpoints`.

> ### P4 — Normalize `Parishes` JSON error envelopes to the API contract (fixes D6)
>
> Context: `Diocese/Controllers/Parishes.php` `store`/`delete`/`deleteImpact`/`bulkDelete`
> return `['success' => false, 'message' => …]` without `error.code`
> (`docs/API_AJAX_ERROR_CONTRACT.md` makes `error.code` mandatory; shared JS keys off it).
>
> Change (one logical commit): convert every JSON error in those methods to
> `$this->jsonResponse($this->apiErrorPayload(msg, status, 'CODE', details?), status)` —
> codes like `VALIDATION_FAILED`, `PARISH_NOT_FOUND`, `PARISH_DELETE_RESTRICTED`,
> `BULK_DELETE_PARTIAL` — keeping the existing status codes and messages. Convert only JSON
> error paths; success envelopes and `denyOrRedirect`/redirect paths stay as-is.
> - Tests: each converted error path asserts `error.code` present + correct status.
> - Commit title: `Return contract-shaped errors from diocese parish endpoints`.

> ### P5 — Align admin-set staff password minimum with the 12-char auth policy (fixes D5)
>
> Context: `app/Services/ParishStaffService.php:46` requires `min_length[8]`; PR #46 raised
> the account minimum to 12. Diocese `ParishStaff` (and the parish controller sharing this
> service) accept 8-char passwords.
>
> Change (one logical commit): raise the rule to `min_length[12]` (or reference the
> configured auth minimum if one exists), update any view hint text, add
> `Diocesan.*`/`App.*` lang keys as needed.
> - Tests: 8–11-char password rejected with `VALIDATION_FAILED`; 12+ accepted.
> - Commit title: `Raise staff password minimum to match auth policy`.

> ### P6 — Protect a parish's primary admin from diocesan deactivation/reset (fixes D4)
>
> Context: `app/Controllers/DioceseParishAdmins.php` `deactivate`/`resetPassword` guard self
> but not the parish's designated primary (`ParishAdminPrimaryModel`), unlike
> `DioceseAdminUsers` which protects `isPrimaryDioceseUser`.
>
> Change (one logical commit): in `deactivate` and `resetPassword` (and `delete` if it
> doesn't already), deny when `ParishAdminPrimaryModel` marks the target as that parish's
> primary — with a lang message directing the admin to designate a successor first. If the
> product intent is that diocesan primaries *should* be able to do this, still add the
> guard's denial path behind a documented constant and note the decision in the commit body.
> - Tests: primary parish admin cannot be deactivated/reset; a non-primary parish admin can.
> - Commit title: `Guard parish primary admin from diocesan account mutations`.

> ### P7 — Add `diocese/*` routes to the `dioceseScope` filter (fixes D7)
>
> Context: `dioceseScope.before` covers `parish_list*`, `chart-accounts*`,
> `liturgical-source*` but not `diocese/*`; every diocese controller self-resolves scope
> today, so this is defense-in-depth + auto-populating `diocesan_scope_id`.
>
> Change (one logical commit): add `diocese` and `diocese/*` to `dioceseScope.before`. Then
> regression-check the full diocese route list: `DioceseScopeFilter` only restricts
> `dioceseadmin`-group users and only validates `parish_id` GET/POST params — confirm no
> diocese endpoint legitimately accepts a `parish_id` outside the caller's diocese or a
> `parish_id` with a different meaning (check `followup-workflow`, `deaneries`,
> `clergy-review` POST bodies). If any conflict, scope the glob to the safe subpaths.
> - Tests: diocesan admin POSTing `parish_id` of another diocese to a `diocese/*` endpoint
>   gets the filter denial; same request with in-diocese `parish_id` passes.
> - Commit title: `Extend dioceseScope filter to diocese/* routes`.

> ### P8 — Explicit permission filter on `diocese/liturgical-source*` (fixes D8)
>
> Context: `Diocese/Config/Routes.php:53–55` gates the group with `session` only;
> `LiturgicalCalendar` self-gates (view = system/diocese/province admin, manage = system).
>
> Change (one logical commit): split the group — read endpoints keep `session` (controller
> gates the 3-role view set, which a single Shield permission cannot express) and add a
> comment saying so; manage endpoints get an explicit permission filter matching the
> controller's manage gate (or a route-level `permission:system.admin` if that is the
> canonical system-only pattern used elsewhere).
> - Tests: anonymous/member requests denied; diocesan admin can view but not manage.
> - Commit title: `Add permission filter to liturgical-source mutations`.

> ### P9 — Remove dead `DiocesanScopeFilter` alias; document `RegistrationRequests` scope (fixes D9, D10)
>
> Change (one logical commit): delete `app/Filters/DiocesanScopeFilter.php` (referenced
> nowhere; verify with `rg DiocesanScopeFilter` first). Add a one-line class-docblock note
> on `Diocese\Controllers\RegistrationRequests` stating it enforces parish-level moderation
> scope (parishadmin), intentionally not diocesan scope.
> - Commit title: `Remove dead DiocesanScopeFilter alias; document moderation scope`.
