# Parish Module Security Audit — ekklesiaONLINE

**Scope:** `Parish/` module (23 routes in `Parish/Config/Routes.php`, 7 controllers) plus the app-side controllers its routes reach (`app/Controllers/Parish/ReportApprovals.php`, `app/Controllers/Parish/EmailSettingsController.php`, `app/Controllers/EmailSettingsTrait.php`) and `app/Services/ParishReportApprovalService.php`. Six dashboards (`Dashboard`, `FinanceDashboard`, `GovernanceDashboard`, `SecretaryDashboard`, `WorshipDashboard`, `VolunteerDashboard`), read-only clergy visibility + hire requests (`Clergy`), the parochial-report signature chain (`ReportApprovals`), and parish SMTP/sender settings (`EmailSettingsController`).

**Method:** static review of routes, filter coverage (`app/Config/Filters.php`), controllers, and the canonical services they call.

**Remediation status:** All findings remediated — P-1 (min-length + `leadership_position.manage` on `person-search`), P-2 (same permission on `clergy/request` + `apiRateLimit` entry/path-limit), P-3 (`*/email-settings/test` rate-limited to 10/min on all tiers), P-4 (Worship visitor/follow-up metrics gated on `visitor.view`/`followup.view`), P-5 (explicit `isDioceseAdmin()` denial on parish `submitRequest` and email-settings `save`/`test`; `parish/*` intentionally stays out of `diocesanOperationalBoundary` because it hosts the diocesan approval steps). `php -l` clean on all touched files. Pre-existing unrelated failure: `HomeRedirectMatrixTest` "clergy without parish appointment" fails under broad filters due to session-state leakage between tests (reproduces on clean tree).

The module is small and mostly read-only; the write surface is `clergy/request`, `email-settings/*`, and `report-signatures/*`. Findings concentrate there.

---

## Confirmed vulnerabilities

### P-1 — MEDIUM: `parish/clergy/person-search` enumerates the diocesan clergy registry under `member.view`

**Evidence:** `Parish/Controllers/Clergy.php:89–116` — `personSearch` is gated by `member.view` (`Routes.php:29`), queries `person` joined to `user_person` with `like(first_name/last_name, $q)`, then post-filters each row through `personInRegistry($id, $parish['diocese_id'])`. There is no minimum query length: an empty `q` produces `LIKE '%%'`, matching every linked `person`, so the endpoint enumerates the **entire diocesan canonical registry** (person IDs + names — including clergy with canonical-status rows, e.g. suspended/inhibited) for whichever parish the actor has selected as operational.

`member.view` is one of the broadest grants in `AuthGroups.php` — held by parishadmin, clergy, finance, volunteer_coordinator, vestry_member, warden, vestry_secretary, followup_coordinator, plus province/diocesan admins.

**Exploitation:** any staff-tier account (e.g. a follow-up volunteer or vestry member) pulls the full ordained-person roster of the diocese, 25 rows per request — data that should be limited to the appointment workflow's actors.

**Fix:** require `q` of ≥ 2–3 characters, return 422 otherwise; consider gating with a stronger permission (`member.edit` or `clergy.appointment.manage`); cap results are already fine (25).

### P-2 — MEDIUM: `parish/clergy/request` writes to the diocesan appointment queue under a read permission, unthrottled

**Evidence:** `Routes.php:30` — `POST parish/clergy/request` is gated by `member.view`. `Clergy::submitRequest` (`Clergy.php:122–177`) inserts a pending `clergy_appointment_request` into the **diocesan review queue**. The only structural limits are an office allowlist and a per-(person, parish) pending dedup (`Clergy.php:148–156`). `parish/*` appears nowhere in the `apiRateLimit` before-list.

**Exploitation:** combined with P-1, any `member.view` holder enumerates diocesan person IDs and submits one pending request per person — flooding the diocesan appointment-review queue with junk requests, each correctly attributed to the parish (noise + governance friction). Requires only a staff-tier account, no elevated role.

**Fix:** gate `submitRequest` with a write-level permission (`member.edit`, or better a dedicated `clergy.appointment.request`/`governance.*` grant); add `parish/clergy/request` to `apiRateLimit`; the P-1 enumeration fix removes the ID source.

### P-3 — MEDIUM: `*/email-settings/test` is an unthrottled outbound-email endpoint accepting an arbitrary recipient

**Evidence:** `app/Controllers/EmailSettingsTrait.php:111–171` — `emailSettingsTest` sends a test message to posted `test_recipient` through the scope's configured SMTP, via `EmailSettingsService::sendTest`. The parish route (`Routes.php:33`, `parish.edit`), the diocese variant (`Diocese/Config/Routes.php:118`), and the province variant (`Province/Config/Routes.php:52`) all expose `test` — and none of `parish/email-settings/*`, `diocese/email-settings/*`, or `province/email-settings/*` is in `apiRateLimit` (`Filters.php`).

**Exploitation:** a compromised or abusive account holding `parish.edit` (or the diocese/province equivalents) sends unlimited emails to arbitrary recipients **through the tenant's own authenticated SMTP** — spam delivery at full sender reputation, potentially spoofing the parish identity. Audit events fire but nothing throttles the volume.

**Fix:** add `*/email-settings/test` (all tiers) to `apiRateLimit`; optionally constrain `test_recipient` to the actor's own verified email.

### P-4 — LOW: `WorshipDashboard` surfaces visitor PII under `service.view`

**Evidence:** `Parish/Controllers/WorshipDashboard.php:35–46` — under route permission `service.view`, the dashboard renders `recentForParish` visitor rows (names/visit data), a pending-followup backlog count, and call-task counts. `service.view` is held by worship_coordinator/worship_volunteer — a different domain from `visitor.view`/`followup.view`/`member.view`.

**Exploitation:** a worship volunteer with `service.view` but no visitor/member read permission sees the visitor directory's newest names and pastoral follow-up state — cross-domain data exposure the permission model does not intend.

**Fix:** gate the visitor/followup widgets on `visitor.view`/`followup.view` (hide the section or zero it when absent), or drop the tiles from the worship dashboard.

### P-5 — LOW: `parish/*` cannot join `diocesanOperationalBoundary` wholesale — parish-operator mutations inside it rely on route permission alone

**Evidence:** `parish/*` is absent from `diocesanOperationalBoundary`, `parishScope`, and `adminFinanceBoundary` — correctly so, because the same URI space intentionally serves diocesan actors (`report-signatures/:num/diocese` requires `governance.diocese.approve`; `lay-office/:num/approve` explicitly admits `isDioceseAdmin()` + `parishInDiocese` at `ReportApprovals.php:127–134`). Consequence: the *parish-operator* mutations under `parish/*` — `email-settings` save and `clergy/request` — are reachable by diocesan admins holding `parish.edit`/`member.view`, with only per-route permission checks, e.g. a diocesan admin can file hire requests attributed to a parish they have selected operationally (attribution is recorded, so audit is intact).

**Exploitation:** weaker than F-1 in the Clergy audit — the reachable writes are low-impact (queue insert, sender config) and correctly scoped/attributed. Flagged for deliberate confirmation, not assumed intent.

**Fix:** no filter change possible without breaking the diocesan approval routes; instead confirm the intent for `email-settings` and `clergy/request`, and add explicit `isDioceseAdmin()` denials inside those handlers if diocesan use is not intended.

---

## Observations (not vulnerabilities)

- `Clergy::personSearch` runs `personInRegistry` per candidate row — up to ~50 extra queries per search; fine at this volume but worth batching if the picker sees heavy use.
- `ReportApprovals::submit` takes `parishId` from the URL — safe because `ParishReportApprovalService::submit` enforces `canPrepare` (parish affiliation, or diocesan profile + `diocese.admin` in the same diocese) before writing.
- `FinanceDashboard` computes per-button capability flags with `inGroup('finance','parishadmin','systemadmin')` — display-only affordances; the underlying Finance routes carry their own permission filters.
- `Clergy::index` exposes appointed clergy's email/phone under `member.view` — consistent with the module's stated "read-only clergy visibility" purpose.
- Stale docblock: `FollowupSequences`/`FollowupTasks`/`FollowupTemplates` (Clergy module) still cite `visitors.view`/`visitors.edit` while enforcing `followup.*` — cosmetic only.

## What was verified safe

- **Signature chain (`ReportApprovals` + `ParishReportApprovalService`)** — exemplary: `SELECT … FOR UPDATE` row locking, strict status transitions (`submitted → clerk → warden → clergy → diocese`), `SELF_SIGNATURE_FORBIDDEN` across all four signer slots, snapshot-hash re-verification at each step, signature provenance rows (`source_position_id`/`source_grant_id`, `was_delegated`), scope via `matchingGrant`/`layOffice`/`canPrepare`, and transaction-wrapped revision archival (`startRevision` never overwrites signed history).
- **`EmailSettingsTrait`** — rejects posted `scope_type`/`scope_id` that differ from the controller-pinned scope, gates SMTP credential fields behind `parish.primary`/`system.admin`, audits save and test-send.
- **All six dashboards** — read-only, every query pinned to `getOperationalParishId()`; raw SQL is parameterized; `Dashboard::index` additionally requires `canAccessParishDashboard` (non-personal dashboard tier).
- **`Clergy::submitRequest`** — office allowlist, `is_not_unique` person check, diocesan-registry scope (`personInRegistry`), pending-request dedup, and an audit event on insert.
- **`approveLayOffice`** — explicit parishAdmin-vs-diocesanAdmin scope XOR with `parishInDiocese`, plus `ClergyReviewService` eligibility (separate-signatory review).
