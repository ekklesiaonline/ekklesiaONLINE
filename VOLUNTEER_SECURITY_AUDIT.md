# Volunteer Module — Security Audit

**Date:** 2026-11-18
**Scope:** `Volunteer/` module (routes, controllers, views), `Parish\Controllers\VolunteerDashboard`, `app/Models/{VolunteerScheduleModel,VolunteerTeamModel,TeamModel,TeamMemberModel}.php`, `app/Commands/VolunteerRemind.php`, and the filter coverage that protects the module (`app/Config/Filters.php`, `app/Filters/*`).
**Method:** Read-only code review against the repo's own invariants (`.devin/rules/*`, `docs/ROLE_MENU_PERMISSION_POLICY.md`, `docs/API_AJAX_ERROR_CONTRACT.md`). No code was changed.

## Findings

### F1 — MEDIUM: `volunteer.schedule` bypasses `volunteer.assign` — schedule endpoints never verify team membership

- `Volunteer/Controllers/VolunteerSchedules.php:217–221` (`storeSchedule`) and `:326–331` (`updateSchedule`) validate that `user_id` is an affiliated member of the operational parish via `isUserAffiliatedWithParish(...)`, but never check that the user is actually a member of `team_id`.
- The create/edit forms only offer roster members (populated via `teamMembersApi`), so the UI implies a roster invariant that the server does not enforce.
- Effect: a principal holding `volunteer.schedule` but **not** `volunteer.assign` can place any parish member onto any team's shift — a de-facto roster write that bypasses the `volunteer.assign` permission boundary and the pathway-stage recompute that `addMember` performs (`Volunteers::recomputePathwayStage`, `Volunteers.php:59–72` is only called on roster changes, not schedule changes).
- **Fix direction:** after the affiliation check in both endpoints, require `TeamMemberModel::isUserOnTeam($teamId, $userId)`; reject with a 422/error before `buildSchedulePayload`. If scheduling non-roster members is intentional, that needs an explicit product decision and a permission change instead.

### F2 — MEDIUM: `volunteers`/`volunteers/*` absent from `diocesanOperationalBoundary` filter list

- `app/Config/Filters.php:333–360` applies `diocesanOperationalBoundary` to `members`, `families`, `services`, `guests`, `visitors`, `standing`, `followup`, `people`, `followup-workflow`, `vestry`, `groups`, and `messages` — every parish-operator module **except** `volunteers`.
- The `dioceseadmin` group does not currently ship `volunteer.*` (`app/Config/AuthGroups.php:366–395`), so today the permission gate blocks the path; but any direct `user_permissions` grant or future group edit silently opens parish-operational team/schedule mutation to diocesan admins — the exact mutation class the boundary exists to deny. This is a defense-in-depth gap, not a live exploit.
- **Fix direction:** add `'volunteers'` and `'volunteers/*'` to the `diocesanOperationalBoundary.before` list.

### F3 — MEDIUM: `apiRateLimit` coverage gaps — `schedules/confirm` and all legacy mutation aliases unthrottled

- `app/Config/Filters.php:444–449` covers `volunteers/add-member`, `volunteers/remove-member/*`, `volunteers/store-team`, `volunteers/schedules/store`, `volunteers/schedules/update/*`, `volunteers/schedules/cancel/*`.
- Missing:
  - `volunteers/schedules/confirm/*` (canonical POST, `Volunteer/Config/Routes.php:41`).
  - Every legacy alias mutation route (`Volunteer/Config/Routes.php:17–21`): `volunteers/storeTeam`, `volunteers/store-schedule`, `volunteers/update-schedule/*`, `volunteers/cancel-schedule/*`, `volunteers/confirm-schedule/*`. These give an unthrottled parallel path to the same mutations, defeating the canonical-route limits in `ApiRateLimitFilter::$pathLimits` (`app/Filters/ApiRateLimitFilter.php:34`).
- **Fix direction:** add `volunteers/schedules/confirm/*` and the five legacy paths to the `apiRateLimit.before` list (or retire the legacy aliases outright — see F10).

### F4 — LOW: `addMember` allows duplicate roster rows and accepts a free-text `role`

- `Volunteer/Controllers/Volunteers.php:194–253`: no `isUserOnTeam` check before `TeamMemberModel::insert`; `team_member.team_id_user_id` is a plain `KEY`, not `UNIQUE` (`app/Database/canonical_schema_tables.sql:2024`), so the same user can be inserted repeatedly (pathway recompute then runs on a skewed roster).
- `role` is taken verbatim from POST (`:206`, `:226`) with no whitelist/`in_list` validation; `team_member.role` is `varchar(100)` ("e.g., coordinator, member") so arbitrary values persist and render as text.
- **Fix direction:** reject with 409 if `isUserOnTeam` is true; whitelist `role` (`member`, `coordinator`, `leader` — confirm canonical set) via `in_list` in request validation; optionally add a unique index on `(team_id, user_id)` in a migration.

### F5 — LOW: `storeTeam` `leader_id` accepts any existing user, not parish-scoped

- `Volunteer/Controllers/Volunteers.php:138–158`: validation covers only `name`; `leader_id` is read straight from POST (`:150`) with no `is_not_unique` or affiliation check. An operator can set a leader from another parish (or any user id); leader identity is then rendered in team listings.
- **Fix direction:** validate `leader_id` as `permit_empty|is_not_unique[users.id]` + `isUserAffiliatedWithParish` when set, mirroring the `addMember` check.

### F6 — LOW: member email written to application logs

- `app/Commands/VolunteerRemind.php:114–119`: on send failure the command logs `email={em}` — the volunteer's raw email address — violating the BaseController/audit redaction rule ("redact credentials, contact data, financial values, and freeform notes").
- **Fix direction:** log `user_id`, team, and the classified error only; drop the address.

### F7 — LOW: foreign-parish team roster names leak via `old('team_id')` re-render

- `Volunteer/Controllers/VolunteerSchedules.php:172–174` (`createSchedule`) and `:280–282` (`editSchedule`) repopulate the member dropdown from `old('team_id')` after a validation-error redirect. A `volunteer.schedule` holder can POST a tampered `team_id` for another parish's team, get bounced by the parish check, and the re-rendered form contains that foreign team's member names (names only — no emails in the view).
- **Fix direction:** only repopulate members when `old('team_id')` resolves to a team owned by the operational parish; otherwise leave the list empty.

### F8 — LOW: team creation is not audited

- `storeTeam` (`Volunteers.php:128–158`) writes a new parish team with no `$this->audit()->log(...)`, while `team_member.add`, `team_member.remove`, and all four `schedule.*` actions are logged. Team lifecycle events are invisible in the audit trail.
- **Fix direction:** add `team.create` audit entry with parish context and new values.

### F9 — LOW: PII over-fetch and API-envelope drift

- `TeamMemberModel::getTeamMembers` (`app/Models/TeamMemberModel.php:60–71`) selects `ai.secret AS email` for every member; neither `view_team` nor `teamMembersApi` renders email — the data is pulled and held in memory for no consumer.
- `addMember`/`removeMember` error payloads (`Volunteers.php:201, 211, 217, 252...`) return `['success'=>false,'message'=>...]` without the mandatory `error.code` from `docs/API_AJAX_ERROR_CONTRACT.md`; `teamMembersApi` (`VolunteerSchedules.php:433–444`) returns a bare `['error'=>...]` and no audit-denied log.
- **Fix direction:** drop the email column (or gate it behind an explicit flag); normalize error envelopes to the contract (`error.code` + `meta.http_status`).

### F10 — LOW: legacy alias routes broaden and split the attack/policy surface

- `Volunteer/Config/Routes.php:12–21` keeps parallel legacy paths (`volunteers/viewTeam`, `volunteers/createTeam`, `volunteers/schedule`, `volunteers/*-schedule`, `volunteers/storeTeam`, `volunteers/store-schedule`, `volunteers/update-schedule`, `volunteers/cancel-schedule`, `volunteers/confirm-schedule`) outside the `volunteers` group. Consequences: the F3 rate-limit gap, permission asymmetry (`volunteers/schedule` needs only `volunteer.schedule`, while canonical `volunteers/schedules` requires `volunteer.manage` + `volunteer.schedule`), and twice the surface for every future policy change.
- **Fix direction:** convert aliases to redirects or delete them once external callers are confirmed migrated; otherwise keep filter parity mechanically enforced by a route test.

### F11 — INFO: `volunteer_schedule` dual date columns / schema drift

- Canonical schema (`canonical_schema_tables.sql:2213–2232`) keeps legacy `date DATE NOT NULL` and `time_slot` alongside `service_date`/`service_time_slot`; the model writes only `service_*`. If the deployed schema still has `date NOT NULL` without a default, inserts fail under strict SQL mode; if it was relaxed locally, `date` silently drifts from `service_date`. Could not be verified without a live DB — needs a schema check in production, then drop or sync the legacy columns.

### F12 — INFO: dead and unscoped model helpers

- `VolunteerScheduleModel::getUserSchedule` (`:44–61`) and `getTeamSchedule` (`:66–83`) are not parish-scoped and have no callers outside the models; `TeamModel::getTeamWithMembers` decrypts member phones (also unused by Volunteer controllers). If wired up later they would leak cross-parish data / decrypted contact data. Delete or parish-scope them before reuse.
- `Volunteer\Controllers\Dashboard` (`Volunteer/Controllers/Dashboard.php`) extends the MemberPortal redirect shim; `/volunteer/dashboard` just bounces to `resolveDashboardScope`. Harmless dead alias — consider removing.

## Verified clean

- **Parish scoping on every mutation/read:** `viewTeam` (`Volunteers.php:173–177`), `addMember` (`:209–212`), `removeMember` (`:277–283`), `teamMembersApi` (`VolunteerSchedules.php:441–445`), and all schedule mutations via `getParishScopedSchedule` (`:457–470`) compare `team.parish_id` to `getOperationalParishId()`. `parishScope` filter covers `volunteers`/`volunteers/*` (`Filters.php:313–314`).
- **Member legitimacy:** `addMember` and both schedule writes require `isUserAffiliatedWithParish(..., activeOnly=true)` (`is_member=1`).
- **Linked service scope:** `resolveLinkedService` (`VolunteerSchedules.php:497–509`) enforces `service.parish_id == operational parish` and makes service date authoritative.
- **Session + permission gates on every route:** outer `session` filter; per-route `permission:*` filters plus in-controller `requirePermission`/`checkPermission` (`teamMembersApi`).
- **CSRF:** global `csrf` filter covers all POSTs; forms emit `csrf_field()`.
- **Status/enum whitelists:** `status` validated `in_list[scheduled,confirmed,cancelled]` at controller and model layers.
- **XSS:** all user-derived values in `Volunteer/Views/*` and `Parish/Views/Volunteer/index.php` pass through `esc()`; JS member injection uses the `Option` DOM API, not `innerHTML`.
- **No SQL injection surface:** all queries use Models/Query Builder bindings; GET filter params are bound or whitelisted.
- **`member-search` picker** requires `member.view` and scopes to operational parish (`app/Controllers/MemberSearch.php:24–28`).
- **Onboarding gates:** `registrationGate`, `sessionTracking`, `forcePasswordReset`, `deviceVerification` are global and do not exclude `volunteers/*` (`Filters.php:128–231`), so pending-review users cannot reach any volunteer endpoint.
- **Audit trail** exists for `team_member.add`, `team_member.remove`, `schedule.create|update|cancel|confirm` with parish context.
- **No file handling, uploads, redirects-from-input, or mass-assignment vectors** in the module (`$allowedFields` set on all three models; `protectFields = true`).

## Remediation plan — Devin prompts per commit

Run prompts **P1 → P10 in order** (P1–P3 are the security-relevant fixes; P4–P9 hardening; P10 verification + cleanup). Each prompt is self-contained and produces **one commit** unless noted. Give each prompt the repo `ekklesiaonline/ekklesiaONLINE`.

---

### P1 — Enforce team membership on schedule writes (fixes F1)

> In `ekklesiaonline/ekklesiaONLINE`, enforce the team-roster invariant on volunteer schedule writes.
>
> Context: `Volunteer/Controllers/VolunteerSchedules.php` — `storeSchedule()` (~line 217) and `updateSchedule()` (~line 326) verify `user_id` is an affiliated member of the operational parish via `MemberParishAffiliationModel::isUserAffiliatedWithParish()`, but never verify the user belongs to `team_id`. This lets a principal with `volunteer.schedule` but not `volunteer.assign` roster arbitrary parish members, bypassing the `volunteer.assign` permission.
>
> Changes:
> 1. In both `storeSchedule()` and `updateSchedule()`, after the affiliation check and before `buildSchedulePayload()`, call `$this->teamMemberModel->isUserOnTeam($teamId, $userId)`; when false, `redirect()->back()->withInput()->with('error', lang('Volunteer.memberNotOnTeam'))` (add the `memberNotOnTeam` key in `app/Language/en/Volunteer.php` and `es` if present, e.g. "The selected volunteer is not a member of this team.").
> 2. Add a regression test (e.g. `tests/unit/VolunteerScheduleTeamMembershipTest.php` following existing `tests/unit/*Test.php` conventions): posting `volunteers/schedules/store` with a parish-affiliated `user_id` who is NOT on the team is rejected and no `volunteer_schedule` row is created; a roster member succeeds.
>
> Run `vendor/bin/phpunit --no-coverage --do-not-cache-result` for the new test plus `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`. Commit as `Enforce team membership on volunteer schedule writes` — one commit.

### P2 — Add `volunteers` to `diocesanOperationalBoundary` (fixes F2)

> In `ekklesiaonline/ekklesiaONLINE`, add `'volunteers'` and `'volunteers/*'` to the `diocesanOperationalBoundary.before` URI list in `app/Config/Filters.php` (~lines 333–360), keeping alphabetical/grouping consistency with the existing entries (`volunteers` sits near `vestry`/`groups`).
>
> Rationale: every other parish-operator module is listed; `volunteers` was missed, so a diocesan admin who is ever granted `volunteer.*` would bypass the operational-boundary invariant from `.devin/rules/codeigniter-shield.md`.
>
> Add/extend a route-level test (look for existing filter-boundary tests under `tests/`) asserting a `dioceseadmin` user is denied `POST volunteers/store-team` and `GET volunteers` even with volunteer permissions granted. Run the focused test file, then `vendor/bin/phpstan analyse`. Commit as `Apply diocesan operational boundary to volunteers routes` — one commit.

### P3 — Close `apiRateLimit` gaps (fixes F3)

> In `ekklesiaonline/ekklesiaONLINE`, close volunteer `apiRateLimit` coverage gaps in `app/Config/Filters.php` (~lines 444–449).
>
> Add to `apiRateLimit.before`:
> - `volunteers/schedules/confirm/*` (canonical route currently unthrottled),
> - legacy mutation aliases: `volunteers/storeTeam`, `volunteers/store-schedule`, `volunteers/update-schedule/*`, `volunteers/cancel-schedule/*`, `volunteers/confirm-schedule/*`.
>
> Also extend `ApiRateLimitFilter::$pathLimits` (`app/Filters/ApiRateLimitFilter.php:34`) so the legacy aliases get the same 30/min cap as canonical volunteer mutations — e.g. widen `#^volunteers/(store-team|add-member|remove-member|schedules/)#` to also match `storeTeam|store-schedule|update-schedule|cancel-schedule|confirm-schedule`. Add a route-filter coverage test asserting every POST route in `Volunteer/Config/Routes.php` matches an `apiRateLimit.before` pattern. Run focused tests + phpstan. Commit as `Rate-limit volunteer confirm and legacy mutation routes` — one commit.

### P4 — Reject duplicate roster rows and whitelist `role` (fixes F4)

> In `ekklesiaonline/ekklesiaONLINE`, harden `Volunteers::addMember` (`Volunteer/Controllers/Volunteers.php:194–253`):
>
> 1. After the affiliation check, return a 409-style JSON/flash error (`Volunteer.memberAlreadyOnTeam`) when `$this->teamMemberModel->isUserOnTeam((int)$teamId, (int)$userId)` is already true.
> 2. Validate `role` server-side: `permit_empty|in_list[member,coordinator,leader]|max_length[100]` (adjust the list to the values actually used in views/lang keys; check `Volunteer/Views` and `app/Language/*/Volunteer.php` first).
> 3. Add a migration adding `UNIQUE KEY` on `team_member (team_id, user_id)` — first add a dedupe step in the same migration (`DELETE t1 FROM team_member t1 JOIN team_member t2 ON t1.team_id=t2.team_id AND t1.user_id=t2.user_id AND t1.id>t2.id`) so it cannot fail on existing dupes. Check `docs/` and `canonical_schema_tables.sql` for how schema changes are tracked.
>
> Add regression tests for both behaviors. Run focused tests + phpstan. Commit as `Prevent duplicate team members and whitelist roster roles` — one commit (migration + controller + tests together is intended).

### P5 — Parish-scope `leader_id` in `storeTeam` (fixes F5)

> In `ekklesiaonline/ekklesiaONLINE`, validate `leader_id` in `Volunteers::storeTeam` (`Volunteer/Controllers/Volunteers.php:138–158`): add `leader_id => permit_empty|is_not_unique[users.id]` to `$rules`, and when a value is provided require `MemberParishAffiliationModel::isUserAffiliatedWithParish($leaderId, operationalParishId, true)` — else `redirect()->back()->withInput()->with('error', lang('Volunteer.invalidLeader'))` (add the lang key). Add a unit/feature test covering foreign-parish leader rejection. Commit as `Validate team leader parish affiliation` — one commit.

### P6 — Redact email from reminder logs (fixes F6)

> In `ekklesiaonline/ekklesiaONLINE`, remove the raw email address from the failure log in `app/Commands/VolunteerRemind.php:114–119` — keep `user_id`, `team`, and the classified error message; drop `email={em}` (per the audit redaction rule: no contact data in logs). Commit as `Redact member email from volunteer reminder logs` — one commit, no test needed beyond phpstan.

### P7 — Stop foreign-team member names in validation re-renders (fixes F7)

> In `ekklesiaonline/ekklesiaONLINE`, fix `createSchedule()` and `editSchedule()` in `Volunteer/Controllers/VolunteerSchedules.php` (~lines 172–174 and 280–282): only call `getTeamMembers($oldTeamId)` when `old('team_id')` resolves to a `team` row whose `parish_id` equals the operational parish; otherwise pass `[]`. Add a test that a tampered foreign `team_id` on a failed POST does not return member names. Commit as `Scope schedule form member repopulation to parish teams` — one commit.

### P8 — Audit team creation; drop email over-fetch (fixes F8, F9a)

> In `ekklesiaonline/ekklesiaONLINE`:
> 1. Add `$this->audit()->log('team.create', [...])` in `Volunteers::storeTeam` after successful insert, matching the `team_member.add` shape (parish_id, entity_type `team`, entity_id, new_values name/leader_id only).
> 2. Remove `ai.secret as email` and the `auth_identities` join from `TeamMemberModel::getTeamMembers` (`app/Models/TeamMemberModel.php:60–71`) — verify no caller uses `email` (grep `getTeamMembers` callers) before removing; if `TeamModel::getTeamWithMembers` still needs email keep it there.
>
> Commit as `Audit team creation and drop roster email over-fetch` — one commit.

### P9 — Normalize volunteer JSON error envelopes (fixes F9b)

> In `ekklesiaonline/ekklesiaONLINE`, bring Volunteer JSON error responses onto `docs/API_AJAX_ERROR_CONTRACT.md`: `addMember`, `removeMember` (`Volunteer/Controllers/Volunteers.php`) and `teamMembersApi` (`Volunteer/Controllers/VolunteerSchedules.php`) must emit `error.code` and `meta.http_status` (use the existing `apiErrorPayload()` helper in `BaseController`, e.g. `MEMBER_NOT_FOUND`, `PARISH_SCOPE_REQUIRED`, `VOLUNTEER_PERMISSION_DENIED`). Also add a rate-limited `logAuthorizationDenial` call in `teamMembersApi`'s 403 path, matching `requirePermission`. Update/extend tests asserting the envelope shape (see `tests/unit` for prior contract tests like the personSearch envelope test). Commit as `Normalize volunteer API error envelopes` — one commit.

### P10 — Verify/clean `volunteer_schedule` schema drift and dead helpers (fixes F11, F12 — verify-first)

> In `ekklesiaonline/ekklesiaONLINE`, investigate and fix `volunteer_schedule` column drift: the canonical schema keeps `date DATE NOT NULL` + `time_slot` while the model only writes `service_date`/`service_time_slot`. First check production/staging schema (`SHOW COLUMNS FROM volunteer_schedule`) — if `date` is still NOT NULL without default, schedule creation is broken under strict mode; either way, write a migration that copies `date → service_date` where missing, then drops `date` and `time_slot` (or makes them generated/legacy-safe per `canonical_schema_tables.sql` conventions). Separately, delete unused `VolunteerScheduleModel::getUserSchedule`/`getTeamSchedule` and `TeamModel::getTeamWithMembers` if nothing calls them (grep first), or add parish scoping if they must stay. Also delete `Volunteer/Controllers/Dashboard.php` and its `/volunteer/dashboard` route if the redirect shim is confirmed unused. Run full suite + phpstan. Commit as `Clean volunteer schema drift and dead helpers` — one commit, but **open as a draft PR** noting what the live schema check found.

---

## Regression-test checklist (new coverage from the plan)

- POST `volunteers/schedules/store` + `update/:id` reject non-roster users (F1).
- `dioceseadmin` denied on `volunteers/*` with volunteer perms granted (F2).
- Every POST route in `Volunteer/Config/Routes.php` matched by `apiRateLimit` (F3).
- `volunteers/add-member` 409s on duplicate; bad `role` 422s (F4).
- Foreign-parish `leader_id` rejected in `volunteers/store-team` (F5).
- Tampered `team_id` on failed schedule POST returns no foreign member names (F7).
- JSON error envelopes carry `error.code` + `meta.http_status` (F9).
