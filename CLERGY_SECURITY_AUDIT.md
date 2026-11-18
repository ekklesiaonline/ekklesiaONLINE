# Clergy Module Security Audit — ekklesiaONLINE

**Scope:** `Clergy/` module (~173 routes in `Clergy/Config/Routes.php`, 25 controllers): people/members directory (`People`, `Members`, `MembersAffiliation`, `PeopleFinance`, `PeopleSacraments`, `PeopleFamilies`, `PeopleLeadership`), merges and import (`PersonMerge`, `MemberMerge`, `Import`), visitors and follow-up (`Visitors`, `VisitorLifecycle`, `VisitorFollowup`, `FollowupSequences`, `FollowupTemplates`, `FollowupTasks`, `Reports`), messaging (`Messages`), groups (`Groups`), events (`Events`, `EventsRegistrations`, `EventCalendars`), governance (`Vestry`), and the clergy self-service portal (`Dashboard`, `Profile`). Boundary filters reviewed: `parishScope`, `diocesanOperationalBoundary`, `adminFinanceBoundary`, `clergyScope`, `apiRateLimit` in `app/Config/Filters.php`.

**Method:** static review of routes, filters, controllers, and the canonical services/models they call.

**Remediation status (this pass):** all findings F-1 through F-11 were fixed in the same session — `app/Config/Filters.php` (parishScope, diocesanOperationalBoundary, adminFinanceBoundary, apiRateLimit), `Clergy/Config/Routes.php` + `Clergy/Views/Vestry/view.php` (F-3), `People`/`Reports` (F-4 via `fputcsv_safe`/`csv_safe_row`), `MemberMerge` (F-5), `FollowupSequences`/`FollowupTasks`/`FollowupTemplates` (F-7), `Events` (F-8, F-9), `Members` (F-10), `Import` (F-11, 500-row cap). All changed files pass `php -l`; 32 related PHPUnit tests pass.

---

## Confirmed vulnerabilities

### F-1 — HIGH: `diocesanOperationalBoundary` does not cover `people/*`, `followup-workflow/*`, or `vestry/*` — diocesan admins bypass the parish-operational block

**Evidence:** `app/Config/Filters.php:324–343` — the boundary before-list covers `members`, `families`, `services`, `guests`, `visitors`, `standing`, `followup` (+`/*`). The unified People directory added later lives under `people/*`; the follow-up workflow under `followup-workflow/*` (URI matching is segment-based: `followup/*` does not match `followup-workflow/tasks`); vestry governance under `vestry/*`. None of these are listed. `people/*`, `followup-workflow/*`, `vestry/*`, `event-calendars`, `clergy`, and `api/followup/pending-calls` are likewise absent from the `parishScope` before-list (`Filters.php:285–320`).

The `DiocesanOperationalBoundaryFilter` (`app/Filters/DiocesanOperationalBoundaryFilter.php`) exists specifically to block `dioceseadmin` users from parish-operator workflows even when they hold the underlying Shield permission — and diocesan admins legitimately hold `member.edit`, `family.manage`, `leadership_position.manage`, `followup.manage`, `governance.vestry.manage` for diocesan-level screens.

Every Clergy people controller correctly blocks `isProvinceAdmin()` in writes but **not** `isDioceseAdmin()`. Reachable by a diocesan admin today:

- `people/sacrament*` — create/edit/delete canonical sacramental records + participants (`PeopleSacraments`, `member.edit`)
- `people/leadership*` — appoint/update/delete parish leadership positions (`PeopleLeadership`, `leadership_position.manage` + `clergyScope` where routed)
- `people/family*` — create/join/leave/relationship mutations (`PeopleFamilies`, `family.manage`)
- `people/save-membership-details` — baptism/confirmation/communicant/good-standing-override flags (`member.edit`)
- `people/toggle-member-tag`, `bulk-toggle-member-tag` — lifecycle status flips (`member.edit`)
- `people/enable-portal` — portal invite issuance (`member.edit`/`portal.manage`)
- `people/export`, `people/bulk-delete`, `people/merge/*` — directory export, bulk soft-delete, irreversible person merge (`member.delete` + `clergyScope`)
- `people/store-non-portal` — non-portal person creation (`member.create`)
- `followup-workflow/*` — parish follow-up sequences, templates, manual dispatch (`followup.manage`)
- `vestry/*` — create meetings/motions, cast votes, close motions (`governance.vestry.*`)

**Exploitation:** any `dioceseadmin` account (or a user granted `diocese.admin`) holding the matching permission performs parish-level mutations the boundary was designed to prevent — including writing canonical sacramental records, appointing parish leadership, and merging/deleting people — with full audit events but no boundary denial.

**Fix:** add `people`, `people/*`, `followup-workflow`, `followup-workflow/*`, `vestry`, `vestry/*` to the `diocesanOperationalBoundary` before-list (or document intentional per-route exceptions). Add `people`, `people/*`, `followup-workflow`, `followup-workflow/*`, `vestry`, `vestry/*`, `event-calendars` to the `parishScope` before-list for defense-in-depth (`parishScope` also sanitizes a stale `selected_parish_id` for non-elevated users). Decide deliberately whether `groups`/`messages` belong in the boundary — they are currently parish-operational yet reachable by diocesan admins with `groups.manage`/`messaging.send`.

### F-2 — HIGH: `adminFinanceBoundary` does not cover `people/donation*` and `people/pledge*` — finance tenant separation bypass

**Evidence:** `app/Config/Filters.php:345–369` — the boundary covers `donations/*`, `pledges/*`, `funds/*`, `treasurer/*`, `journal-entries/*`, `accounting-periods/*`, `finance/chart-of-accounts/*` (Finance audit F-2 remediation). The same donation/pledge mutations are also reachable under `people/donation/(:num)`, `people/donation/(:num)/update|delete`, `people/pledge/(:num)` and `people/pledge/(:num)/update|delete` (`Clergy/Config/Routes.php:68–73` → `Clergy\Controllers\PeopleFinance`). First URI segment is `people`, so the boundary filter never fires. `PeopleFinance` blocks `isProvinceAdmin()` but not `isDioceseAdmin()`.

**Exploitation:** a diocesan admin holding `donation.create|edit|delete` or `annual_pledge.manage` creates/updates/deletes parish ledger entries and pledges via `people/*` — exactly what `adminFinanceBoundary` blocks on the Finance-module paths. Combined with F-1 this is the widest concrete bypass in the module.

**Fix:** add `people/donation`, `people/donation/*`, `people/pledge`, `people/pledge/*` to the `adminFinanceBoundary` before-list; alternatively add explicit `isDioceseAdmin()` denials in `PeopleFinance` consistent with the boundary's intent.

### F-3 — MEDIUM: state-changing GET route — `GET vestry/motion/(:num)/close`

**Evidence:** `Clergy/Config/Routes.php:275` registers `closeMotion` via `$routes->get()`. `Clergy/Controllers/Vestry.php:259–290` tallies votes and writes `vestry_motion.status` on GET.

**Exploitation:** CSRF via an `<img>` tag, email link, or browser prefetch issued against a user holding `governance.vestry.manage` silently closes an open motion (records `passed`/`failed`/`tabled`). GET requests are not covered by CSRF protection; the audit event still records, but the governance outcome is irreversible in practice.

**Fix:** convert the route to POST and update the view to submit a form/button.

### F-4 — MEDIUM: CSV formula injection in Clergy exports

**Evidence:** `Clergy/Controllers/People.php:389–406` (`export()`) wraps every cell in `"` and escapes `"`→`""`, but quoting does **not** neutralize spreadsheet formula interpretation — a cell `"=cmd|'/c calc'!A1"` still executes in Excel/LibreOffice. `Clergy/Controllers/Reports.php:343–395` (`csvVisitorsAll`) streams visitor first/last name, email, phone via raw `fputcsv()` with no neutralization. Person names are operator-entered or self-entered via the public connect card.

**Exploitation:** a visitor submits `=HYPERLINK(...)` or `=cmd|...` as a name on the public connect card; the next staff export to Excel executes the payload. Same class as Finance audit F-3.

**Fix:** prefix dangerous leading characters (`=`, `+`, `-`, `@`, tab, CR) with `'` in a shared CSV helper used by `People::export`, `Reports::exportCsv`, and (ideally) the Finance `*Exports` remediation helper.

### F-5 — MEDIUM: `MemberMerge::merge` hierarchy-guards the duplicate but not the canonical

**Evidence:** `Clergy/Controllers/MemberMerge.php:115–120` calls `guardTargetUserMutation()` only on `$duplicateProfile['user_id']`. The merge then applies posted `field_choice = 'duplicate'` overrides **onto the canonical profile** (`MemberMergeService::merge`, line 132). `loadProfileForParish` only verifies `p.parish_id = $parishId` — no rank check on the canonical side.

**Exploitation:** a parish admin with `member.delete` + a `pastoral.parish.manage` office merges a low-rank duplicate into a higher-ranked canonical profile (e.g., a diocesan admin's profile row within the same parish) and overwrites the canonical's name/email/phone fields via the `duplicate` field choices — an `AdminHierarchyGuard` asymmetry.

**Fix:** run `guardTargetUserMutation` on the canonical profile's `user_id` as well before executing.

### F-6 — MEDIUM: `apiRateLimit` misses Clergy bulk, destructive, and outbound-send endpoints

**Evidence:** `app/Config/Filters.php:384–483` — the `apiRateLimit` before-list covers `members/store|update|delete`, `visitors/store|update|delete|promote|revert|promote-attender|demote`, and `events/*` basics, but omits:

- `members/bulk-delete`, `members/dedup/*` (merge + hard-delete), `members/store-non-portal`, `members/revoke-portal-access/*`, `members/attach-parish|detach-parish|set-home-parish` (present), `members/registrations/*` (present)
- all `people/*` mutations: `bulk-delete`, `merge/execute`, `export`, `store-non-portal`, `toggle-member-tag`, `bulk-toggle-member-tag`, `enable-portal/*`, `save-membership-details/*`, `donation/*`, `pledge/*`, `sacrament/*`, `family/*`, `leadership/*`
- `visitors/import/process` (bulk intake), `visitors/import/rotate-token`, `visitors/notes/add`, `visitors/followup-trigger/*`, `visitors/assign-sequence/*`, `visitors/followup-log/*`
- `messages/store`, `messages/send/*`, `messages/cancel/*`, `messages/templates/*` — outbound email/SMS dispatch
- `followup-workflow/*` writes (sequences, actions, templates, manual-trigger — sends real email/SMS on demand)
- `groups/*` mutations, `event-calendars/store`, `vestry/*`

**Exploitation:** a compromised or abusive staff account can mass-send email/SMS (`messages/send`, `manual-trigger`), bulk-merge/delete people, or bulk-import visitors with no per-user throttle. The followup manual-trigger and message send are the most damaging because they translate directly into provider quota burn and spam-flag risk.

**Fix:** extend the `apiRateLimit` before-list to the URIs above; prioritize `messages/send/*`, `followup-workflow/manual-trigger/*`, `visitors/import/process`, `people/merge/execute`, `members/dedup/merge`, `members/bulk-delete`, `people/bulk-delete`.

### F-7 — MEDIUM: cross-parish references accepted in follow-up actions and manual trigger

**Evidence:** `Clergy/Controllers/FollowupSequences.php:467` validates `template_id` as `is_not_unique[followup_template.id]` — existence only, no parish check. `Clergy/Controllers/FollowupTasks.php:58` loads the action via `$this->actionModel->find($actionId)` with no verification that its parent sequence belongs to the operational parish (the visitor is parish-scoped, the action is not). `Clergy/Controllers/FollowupTemplates.php:333–343` (`preview_template`) loads any template by id with no parish check.

**Exploitation:** a parish admin attaches another parish's template to a local action — the dispatch (`FollowupDispatchService`) then renders parish B's template body into email/SMS sent to parish A's visitors, leaking B's content and bypassing the "upstream templates are read-only" model. `manual_trigger` can fire another parish's action step (with its template) against a local visitor. `preview_template` discloses any parish's template body to a `followup.view` holder.

**Fix:** in `saveAction`, require `template_id` to be `NULL`-scoped (upstream) or `parish_id = $parishId`; in `manual_trigger`, verify the action's `sequence_id` resolves to a sequence with `parish_id = $parishId`; add the same parish check to `preview_template`.

### F-8 — LOW: `Events::delete` bypasses `AuditService` redaction; event create/update write no audit events

**Evidence:** `Clergy/Controllers/Events.php:365–378` inserts directly into `AuditLogModel` with `old_values => json_encode($event)` — the full event row including freeform `description`/`location` — skipping `MonitoringService::sanitizeContext` redaction and `buildParishContextMeta`. `store()` and `update()` write no audit events at all.

**Exploitation:** audit-log readers see unredacted freeform event text, inconsistent with the BaseController redaction rule; create/update are untraceable.

**Fix:** replace the raw insert with `$this->audit()->log('event.delete', ...)` (redacted fields), and add create/update audit events.

### F-9 — LOW: `syncInvitees` accepts arbitrary `user_id`s without parish validation

**Evidence:** `Clergy/Controllers/Events.php:439–446` → `EventInviteeModel::syncInvitees()` (`app/Models/EventInviteeModel.php:49–67`) inserts posted `invited_user_ids` verbatim. The UI picker only offers parish members, but the POST field is free input.

**Exploitation:** an `event.edit` holder adds any `user_id` as an `invited`-visibility invitee; that user then sees the event in their portal (cross-parish invite to a parish-scoped event). Minor — requires a valid target user id and yields only event visibility.

**Fix:** intersect posted ids with parish-affiliated users (e.g., `memberOptions`/`parish_affiliation`) before syncing.

### F-10 — LOW: `Members::revokePortalAccess` passes the *home* parish, not the operational parish

**Evidence:** `Clergy/Controllers/Members.php:439` resolves the profile with `getProfileWithUser($id, $operationalParishId ?: null)` (session-scoped), but line 450 calls `revokePortalAccess($userId, $this->parishId)` — `$this->parishId` is the lazy *home* parish property (`BaseController.php:43,383–411`), which differs from the operational parish whenever an elevated admin selected a different parish. The service re-verifies `profile.parish_id = $parishId` (`NonPortalMemberProvisioningService.php:190–207`), producing a false 404. If `$this->parishId` is still `null` (elevated admin, no home parish, profile found via session), passing it to `int $parishId` raises a `TypeError` → 500.

**Fix:** pass `$operationalParishId` (the same value used for the profile lookup) and guard `<= 0` before the call.

### F-11 — LOW: connection-card CSV import has no row-count cap

**Evidence:** `Clergy/Controllers/Import.php:100` limits the upload to 2 MB / csv|txt, but `parseCsvAndUpsert()` (`Import.php:376–469`) loops `fgetcsv` unbounded — each row runs the full `VisitorDirectoryService::intake` chain (person + affiliation + visit + follow-up scheduling).

**Exploitation:** a `visitor.create` holder uploads a ~20k-row CSV → single-request worker exhaustion and mass junk people + scheduled follow-ups. Authenticated-only, but the blast radius is large.

**Fix:** cap processed rows (e.g., 500) and reject or truncate beyond the cap.

---

## Observations (not vulnerabilities)

- `Members::store`/`getCreatableRoles` (`Members.php:396–425`) still lists `clergy` among diocesan-admin-creatable roles, contradicting the inline invariant ("clergy accounts are created only through `Diocese\Controllers\ClergyAccounts` invitation flow"). The branch is unreachable in practice — `diocesanOperationalBoundary` blocks `members/*` for `dioceseadmin` — but the dead role is a maintenance hazard.
- `Import::rotateToken` (`Import.php:300–323`) rotates the parish's public connect-card token under `visitor.create` — a low bar for an action that invalidates all printed QR codes; consider `parish.manage` or `visitor.edit`.
- `Import::ensurePublicToken(0)` returns `[]`; `connectionCards()`/`qrCode()` then read `$parish['public_token']` on an empty array (elevated admin with no resolvable parish) — null-key warning and a malformed `connect/` URL rather than a clean redirect.
- `FollowupSequences`/`FollowupTasks`/`FollowupTemplates` docblocks cite `visitors.view`/`visitors.edit`; the code and routes correctly use `followup.view`/`followup.manage`. Stale comments only.
- `VestryVoteModel::recordVote` lets a voter silently change a previously cast vote (update path, `VestryVoteModel.php:88–92`). If vote immutability is a governance requirement, switch to reject-on-existing.
- `EventCalendars::store` (`EventCalendars.php:64`) — both ternary branches evaluate `resolveDiocesanScopeId()` identically; harmless dead conditional, worth tidying.

## What was verified safe

- `MembersAffiliation` (attach/detach/set-home-parish): `findProfileInParishScope` + diocese-scope check + `parishScopeValidator` + hierarchy guard on every path.
- `People::view`/`saveMembershipDetails`/`bulkDelete`, `PeopleFinance`, `PeopleSacraments`, `PeopleFamilies`, `PeopleLeadership`: every mutation re-resolves the target inside the operational parish (`findInParish`, `profileForPersonManagement`, affiliation count) and applies `AdminHierarchyGuard`; leadership create/reactivate re-runs `eligibilityFor`.
- `VisitorFollowup` log mutations: each checks `log['parish_id'] === getOperationalParishId()` AND `visitorAffiliationExists`.
- `Messages` + `BulkMessageService`: audience resolution, campaign CRUD, and dispatch are parish-pinned end to end.
- `Groups`/`SmallGroupService`: `groupForParish` + `personInParish` on membership ops.
- `Events` visibility/management correctly delegates to `EventVisibilityService` (`getEventWithCountsForParishAudience` + `isVisibleEvent` + `canManageEvent`); attachments are event-scoped on removal and paths are traversal-guarded.
- `Visitors`/`VisitorLifecycle`: `findByPerson($id, $parishId)` everywhere; `link_user` promotion requires the linked account to be affiliated with the parish; `create_account` requires `member.create`.
- `Import::digitalCard` (public connect card): token shape validated, time-trap, date bounds — hardened in #51/#52.
- `Clergy\Profile` self-service writes only personal fields intersected with `ProfileModel::allowedFields`; canonical registry is read-only.
