# Worship Module Security Audit — ekklesiaONLINE

**Scope:** `Worship/` module (23 routes in `Worship/Config/Routes.php`, 4 controllers + 1 alias shim): `Services`/`Service` (service CRUD + liturgical-day AJAX), `Attendance` (mark, bulk-mark, reports), `ParochialReport` (index/print/export-pdf/email-pdf/save), `WorshipPlanning` (service plans + song library). Supporting code: `app/Services/WorshipServiceDataService.php`, `app/Services/ParochialReportDataService.php`, `app/Models/{Service,ServiceMetric,ServicePeople,ServiceWorshipItem,WorshipSong,ParishFinancialSummary}Model`, `app/Config/Filters.php`, `app/Filters/ApiRateLimitFilter.php`.

**Method:** static review of routes, global/route filters, controllers, models, and views. Audit only — no code changed.

**Remediation status:** All findings remediated on branch `devin/worship-security-remediation` in 9 commits per the plan below: (1) boundary filters `2131a3b`, (2) parochial-report save hardening `868cd1f`, (3) email-pdf gate+throttle `fc40fda`, (4) cross-parish song fix `895f77a`, (5) apiRateLimit coverage `76318c6`, (6) audit events `7b0ec99`, (7) year/recompute/debug bounds `9bd9bbc`, (8) report date validation `dde95fc`, (9) payload row caps `097a9ac`. `php -l` clean on all touched files. Pre-existing unrelated failures under broad test filters (reproduce on clean `main`): `AccountingJournalSyncServiceTest::testCreateJournalForDonationAutoResolvesAndIsIdempotent` and `HomeRedirectMatrixTest` clergy dataset — both test-ordering/session-state leakage, not caused by these changes.

---

## Overall assessment

Controller-level hygiene is good: every service/song/attendance target is re-resolved inside the operational parish (`service['parish_id'] != getOperationalParishId()` checks, `getParishScopedService`/`getParishScopedSong` helpers), parish_id is server-pinned on writes, views escape output, and `Services` audits create/update/delete. The weaknesses concentrate in **URI-level filter coverage** (same class as Clergy F-1), the **ParochialReport write surface** gated by a read permission, and **missing audit/rate-limit coverage** for the newer `worship-planning` and `parochial-report` endpoints.

## Confirmed vulnerabilities

### W-1 — HIGH: `diocesanOperationalBoundary` does not cover `attendance`, `parochial-report`, or `worship-planning`

**Evidence:** `app/Config/Filters.php` — the boundary list covers `members`, `families`, `services`, `guests`, `visitors`, `standing`, `followup`, `people`, `followup-workflow`, `vestry`, `groups`, `messages` — but not `attendance/*`, `parochial-report/*`, or `worship-planning/*`. `parishScope` covers `attendance` but not `parochial-report` or `worship-planning` either.

**Impact:** A diocesan admin holding `attendance.mark`, `report.view`, or `worship.manage` (all granted to `dioceseadmin` in `AuthGroups`) can mark/overwrite parish attendance, write/finalize parochial-report storage rows, replace entire worship plans, and mutate the parish song library — direct parish operational mutations the boundary exists to block. Controllers check `isProvinceAdmin` nowhere in this module, and parish-ownership checks only compare against the *operational* parish, which a diocesan admin can steer via `selected_parish_id` (services are covered only because `services/*` happens to be in the boundary list).

### W-2 — HIGH: `parochial-report/save` is a privileged write under `report.view`, and draft saves wipe finalization

**Evidence:** `Worship/Config/Routes.php:40` wraps `save` in the group `permission:report.view` (a *read* permission held by ~6 groups). `ParochialReport::save` (lines 203–311):
- writes ~50 fields into `parish_report_storage` via `ParishFinancialSummaryModel::upsertForYear`, including financial overrides (`confirmed_pledges_amount`, `cash_balance`, `endowment_*`, `debt_amount`);
- `mark_done=1` sets `report_status='finalized'`, `finalized_at`, `finalized_by`, and builds a `frozen_snapshot_json`;
- on a non-done save the `$data` array **unconditionally** sets `report_status='draft'`, `finalized_at=null`, `finalized_by=null`, `frozen_snapshot_json=null` (lines 282–285), and `upsertForYear` (`ParishFinancialSummaryModel.php:162`) does a full-row `update`.

**Impact:** Any `report.view` holder (vestry member, secretary, clerk, diocesan admin…) can (a) overwrite the parish's canonical annual-report figures including financial totals, (b) self-finalize the report, and (c) — the sharpest edge — **destroy an existing finalization + frozen snapshot** simply by submitting a draft save, erasing the attestable snapshot. No `$this->validate()` is called; free-text fields have no length caps and date fields accept arbitrary strings (`rector_certified_date`, `warden_approved_date`). Elevated admins resolving operational parish 0 can also insert a `parish_id=0` row.

### W-3 — MEDIUM: `parochial-report/email-pdf` sends the full report to arbitrary recipients via parish SMTP, unthrottled, under `report.view`

**Evidence:** `ParochialReport::emailPdf` (lines 110–201) — gated only by `report.view`, accepts `recipient_email` (format-validated only), builds the full parochial report PDF, and sends it through `EmailScopeService->mailer(['parish_id' => $parishId])` — the parish's authenticated sender. `parochial-report` is absent from the `apiRateLimit` URI list.

**Impact:** Same class as Parish P-3 but with a higher-value payload: membership, sacramental, clergy, and **financial** figures exfiltrated to any external address under the parish's own mail identity, at most limited by nothing. Audit logs the recipient, but the send itself is unbounded.

### W-4 — MEDIUM: `saveService` lets a cross-parish `song_id` through when `title` is non-empty; `getServiceItems` joins `worship_song` unscoped

**Evidence:** `WorshipPlanning::saveService` (lines 124–135): the parish-ownership lookup runs inside `if ($songId > 0 && $itemType === 'song')` and only *uses* the song when `$title === ''`. With a non-empty posted `title`, the song row is never verified and the posted `song_id` is stored. `ServiceWorshipItemModel::getServiceItems` (lines 38–47) then `LEFT JOIN worship_song ws ON ws.id = swi.song_id` **without** a `ws.parish_id` constraint, rendering `ws.title`/`ws.artist` in the service view and print sheet.

**Impact:** An actor with `worship.manage` can embed another parish's song (title + artist rendered; id-linked) into their service plan — a cross-tenant reference/limited data leak. Fix: require the `worship_song` row to belong to the operational parish whenever `song_id > 0`, else null it; and add `ws.parish_id = swi.parish_id` (or equivalent) to the join as defense-in-depth.

### W-5 — MEDIUM-LOW: `apiRateLimit` misses `parochial-report/*` and `worship-planning/*`; the `attendance/bulkMark` legacy alias bypasses the bulk-mark limit

**Evidence:** `app/Config/Filters.php` apiRateLimit `before` list has `services/*` writes and `attendance/mark`, `attendance/bulk-mark` — but the legacy alias route is `attendance/bulkMark` (camelCase, `Routes.php:12`), which matches **neither** the URI list nor the `#^attendance/(mark|bulk-mark)$#` path-limit pattern → no rate limit at all. `parochial-report/save`, `parochial-report/email-pdf`, and all `worship-planning` POSTs (saveService, songs store/update/deactivate/reactivate/delete) are absent.

**Impact:** Outbound email sends, full-plan replace-all writes, song-library churn, and the bulk attendance rewrite are unthrottled. (Note: the filter only applies to write methods, so GETs like `export-pdf` need W-7-style bounds, not this list.)

### W-6 — MEDIUM-LOW: zero audit logging across `WorshipPlanning` mutations

**Evidence:** `saveService` (deletes + reinserts the entire service plan), `storeSong`, `updateSong`, `deactivateSong`, `reactivateSong`, `deleteSong` perform writes with no `$this->audit()->log` call — contrast `Services`, which audits create/update/delete with old/new values. `ParochialReport::save` also writes with no audit event (export/email are audited).

**Impact:** Plan replacement, song library edits, and report-storage writes leave no attribution trail — a gap for a governance-grade app.

### W-7 — LOW: `parochial-report` GETs accept unvalidated `year` and a `recompute=1` / PDF-render work trigger

**Evidence:** `index`, `print`, `exportPdf` take `year` via raw `(int)getGet()` with no bounds, and `recompute=1` forces a full recomputation in `ParochialReportDataService::buildReportViewData`; `exportPdf` additionally renders a full dompdf document per request. All are GETs (outside apiRateLimit's method scope).

**Impact:** Any `report.view` holder can force repeated expensive recomputes/PDF renders — resource-abuse surface; `pdf_debug=1` is also accepted (dompdf debugging output paths). Bound `year` (e.g. 1900–current+1), consider dropping `recompute`/`pdf_debug` for non-admin users, or require a POST for forced recompute.

### W-8 — LOW: `Attendance::reports` passes raw `start_date`/`end_date` to the query builder

**Evidence:** `Attendance.php:215–223` — `$startDate = $startDateInput ?: date('Y-01-01')` → `getParishServices($parishId, null, $startDate, $endDate)` binds them (parameterized — no SQLi) but malformed values can produce DB errors/500s and there is no `start <= end` check.

### W-9 — LOW: `ParochialReport::save` bypasses `$this->validate()` (mass-assignment via raw `getPost` closures)

**Evidence:** `ParochialReport.php:217–286` — ~50 fields collected via `getPost` + ad-hoc casts; string fields have no `max_length`; date fields accept free-form strings; `report_status`/flags written directly. Violates the project's "every form/POST endpoint must validate input" rule and allows garbage/absurd values (negative counts, >100 percents, oversized text) into the canonical storage row.

### W-10 — LOW: no row cap on `saveService` item arrays (nor on `service_people` rows in `upsertRelatedServiceData`)

**Evidence:** `WorshipPlanning.php:94–151` — `$rows = max(count(...))` over posted arrays, inserting unbounded rows in one transaction after deleting all existing items; `WorshipServiceDataService::upsertRelatedServiceData` does the same for `service_people`. Posted arrays are attacker-controlled in size.

**Impact:** Bounded by post_max_size, but a few-thousand-row payload still forces a large transaction; cap at a sane max (e.g. 100 rows) consistent with the UI.

---

## Positive controls observed (no action)

- `Services::view/edit/update/delete` and `Attendance::service/mark/bulkMark` all re-resolve the target inside the operational parish; `bulkMark` derives its member list server-side from `getParishMembers`, not posted IDs — good.
- `Attendance::mark` validates `isRecordableMember` against the operational parish before writing.
- `service_people` rows are free-text name/role pairs — no user-ID IDOR surface.
- Writes are transactional where multi-row (service store/update, bulkMark, saveService).
- Views consistently `esc()` output (including `song_artist`, `leader_name`, `notes`); `parochial-report` print view is heavily escaped (277 `esc()` calls).
- `parish_report` (signature chain) is a separate table from `parish_report_storage` — W-2 does not corrupt the staged approval workflow, though it undermines it conceptually.
- `attendance/manual` redirect target exists (`app/Config/Routes.php:188`).

---

## Remediation plan — per-finding prompts and commit split

Order: boundary filters first (largest blast radius, ~5 lines), then the parochial-report write surface, then the cross-tenant reference, then rate limits, audit gaps, and robustness lows. Each commit is independently revertable.

### Commit 1 — `Worship: close diocesan-operational boundary gaps (W-1)`

**Prompt:**
```
In app/Config/Filters.php:
- Add 'attendance', 'attendance/*', 'parochial-report', 'parochial-report/*',
  'worship-planning', 'worship-planning/*' to the diocesanOperationalBoundary
  before-list.
- Add 'parochial-report', 'parochial-report/*', 'worship-planning',
  'worship-planning/*' to the parishScope before-list (attendance/services
  are already present).
Verify no route collisions: the Diocese module has no top-level 'attendance',
'parochial-report', or 'worship-planning' prefixes of its own (check
Diocese/Config/Routes.php first — its groups are under the 'diocese' prefix).
Do not add 'services' — already present in both lists.
Files: app/Config/Filters.php only.
```

### Commit 2 — `Worship: harden parochial-report save (W-2, W-9)`

**Prompt:**
```
In Worship/Controllers/ParochialReport.php and Worship/Config/Routes.php:
1. Route: change parochial-report/save filter to permission:report.parochial
   (keep index/print/export-pdf on report.view).
2. In save(): require operational parish > 0 before writing (redirect with
   App.accessDenied if getOperationalParishId() resolves empty — prevents the
   parish_id=0 garbage row for elevated admins).
3. Preserve finalization: fetch the existing parish_report_storage row first;
   on a non-done save, carry over report_status/finalized_at/finalized_by/
   frozen_snapshot_json from the existing row instead of writing draft/null —
   i.e. only mark_done may set them, and a draft save must not demote a
   finalized report. (Decide: either block draft saves on finalized rows with
   an error, or keep them but retain the finalized fields — pick the
   least-destructive option consistent with the UI.)
4. Add $this->validate() for the posted fields: valid_date for the *_date
   fields, numeric/greater_than_equal_to[0] for counts and amounts,
   max_length bounds for free-text (e.g. 255 for names/addresses, 5000 for
   narrative fields), in_list for reporting_currency, decimal 0–100 for
   percent fields.
Files: Worship/Controllers/ParochialReport.php, Worship/Config/Routes.php,
lang keys in app/Language/{en,es,fr}/App.php as needed.
```

### Commit 3 — `Worship: rate-limit + permission-gate parochial email-pdf (W-3)`

**Prompt:**
```
- Route: parochial-report/email-pdf → permission:report.parochial (same as save).
- app/Config/Filters.php apiRateLimit before-list: add 'parochial-report/save',
  'parochial-report/email-pdf'.
- app/Filters/ApiRateLimitFilter.php pathLimits: add
  '#^parochial-report/email-pdf$#' => 10 and
  '#^parochial-report/save$#' => 20.
- In ParochialReport::emailPdf, constrain recipient: either restrict
  recipient_email to the actor's own verified email or require an explicit
  in-parish recipient (e.g. must match the parish's configured report
  recipient / the actor's auth_identities email) — choose the option that
  keeps the current UI flow working; at minimum keep the existing format
  validation + audit the recipient (already done).
Files: Worship/Config/Routes.php, Worship/Controllers/ParochialReport.php,
app/Config/Filters.php, app/Filters/ApiRateLimitFilter.php.
```

### Commit 4 — `Worship: fix cross-parish song reference in saveService (W-4)`

**Prompt:**
```
In Worship/Controllers/WorshipPlanning.php::saveService:
- Whenever $songId > 0 (regardless of $title), look up the song with
  ->where('id', $songId)->where('parish_id', $parishId); if not found, treat
  the row as having no song (set $songId = 0) or reject the submission with
  a validation error — prefer silent-null + continue consistent with current
  row-skipping behavior, or a hard error; pick one and note it.
In app/Models/ServiceWorshipItemModel.php::getServiceItems (defense in depth):
- Constrain the join so ws.parish_id must equal swi.parish_id
  (join('worship_song ws', 'ws.id = swi.song_id AND ws.parish_id = swi.parish_id', 'left'))
  so cross-parish song data can never render even if a stale FK exists.
Files: Worship/Controllers/WorshipPlanning.php,
app/Models/ServiceWorshipItemModel.php.
```

### Commit 5 — `Worship: extend apiRateLimit coverage (W-5)`

**Prompt:**
```
In app/Config/Filters.php apiRateLimit before-list, add:
  'attendance/bulkMark'          (legacy camelCase alias — currently bypasses
                                  both the URI list and the bulk-mark path limit)
  'parochial-report/save',
  'parochial-report/email-pdf',  (if not already added in Commit 3 — reconcile)
  'worship-planning/service/*/save',
  'worship-planning/songs/store',
  'worship-planning/songs/update/*',
  'worship-planning/songs/deactivate/*',
  'worship-planning/songs/reactivate/*',
  'worship-planning/songs/delete/*',
In app/Filters/ApiRateLimitFilter.php pathLimits, add:
  '#^attendance/bulk-?[Mm]ark$#' => 40   (covers both spellings) — or keep the
  existing '#^attendance/(mark|bulk-mark)$#' and add
  '#^attendance/bulkMark$#' => 40 explicitly; prefer the explicit entry to
  keep the list readable.
  '#^worship-planning/#' => 30
Files: app/Config/Filters.php, app/Filters/ApiRateLimitFilter.php.
Note: apiRateLimit only fires on POST/PUT/PATCH/DELETE, so GET endpoints are
unaffected.
```

### Commit 6 — `Worship: add audit events to planning + report saves (W-6)`

**Prompt:**
```
Add $this->audit()->log(...) with redacted/minimal payloads:
- WorshipPlanning::saveService — 'worship_plan.save', entity service_worship_item,
  meta: service_id, item_count (counts only, not full row dumps).
- storeSong/updateSong — 'worship_song.create'/'update', entity_id song id,
  new_values title/artist only (no lyrics/notes — freeform text, redact).
- deactivateSong/reactivateSong/deleteSong — 'worship_song.deactivate'/
  'reactivate'/'delete', meta song id + title.
- ParochialReport::save — 'parochial_report.save', meta: report_year,
  mark_done flag, parish_id — do NOT log field values (financial data).
Follow the existing audit shape used in Services::store/update/delete.
Files: Worship/Controllers/WorshipPlanning.php,
Worship/Controllers/ParochialReport.php.
```

### Commit 7 — `Worship: bound parochial-report year + recompute/debug flags (W-7)`

**Prompt:**
```
In ParochialReport::index/print/exportPdf/emailPdf:
- Clamp/validate `year` to a sane range (e.g. 1900..(current year)); fall back
  to default on out-of-range rather than erroring.
- Gate `recompute=1` behind a stricter permission (e.g. report.parochial or
  parish.edit) — a plain viewer should not trigger recomputation.
- Restrict `pdf_debug` to systemadmin or drop it entirely (it is already
  normalized; make normalizeDebugFlag return false unless authorized).
Files: Worship/Controllers/ParochialReport.php.
```

### Commit 8 — `Worship: validate attendance report date range (W-8)`

**Prompt:**
```
In Attendance::reports, validate start_date/end_date with a valid_date check
(strtotime or CI valid_date rule): on invalid input fall back to defaults;
if start > end, swap or error consistently with other dashboards (check
Parish dashboards' convention first). Keep the bound-parameter usage.
Files: Worship/Controllers/Attendance.php.
```

### Commit 9 — `Worship: cap repeated-row payloads (W-10)`

**Prompt:**
```
In WorshipPlanning::saveService, cap processed item rows at ~100 (skip or
error beyond the cap, consistent with the Import.php row-cap pattern).
In WorshipServiceDataService::upsertRelatedServiceData, apply the same cap to
the service_people arrays.
Files: Worship/Controllers/WorshipPlanning.php,
app/Services/WorshipServiceDataService.php.
```

### Suggested verification (run after each commit, and fully at the end)

```
php -l on every touched file
vendor/bin/phpunit --no-coverage --filter "Worship|Attendance|Service|Filter|RateLimit"
git diff --stat + manual route-table re-check (spark routes | grep -E "parochial|worship-planning|attendance")
```

### Intentionally out of scope / notes

- `attendance/bulkMark` legacy alias: keep the route (comment says cutover compatibility) but cover it in Commit 5; optionally consolidate to one method name later.
- `parish_report` (signature chain) vs `parish_report_storage` (this controller): separate tables — no cross-contamination, but Commit 2 keeps the storage row's own finalization semantics intact.
- `isDioceseAdmin()` denials inside controllers are NOT needed if Commit 1 lands — the boundary filter covers the whole module once `attendance`/`parochial-report`/`worship-planning` are added (verify none of those URIs intentionally serve diocesan actors first — checked: none do).
