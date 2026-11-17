# Finance Module Security Audit — ekklesiaONLINE

**Scope:** `Finance/` module (~95 routes in `Finance/Config/Routes.php`), finance-bearing routes in `MemberPortal/Config/Routes.php` (`pledges/*`, `me/pledge`, `me/statement`, `me/receipt/:num`) and `Clergy/Config/Routes.php` (pledge routes under `annual_pledge.manage`), plus the canonical services (`DonationService`, `ExpenseService`, `FinanceReadinessService`, `BankStatementParserService`, `BankReconciliationService`, `AccountingJournalSyncService`, `FinanceReportsDataService`, `GivingStatementService`) and boundary filters (`parishScope`, `adminFinanceBoundary`, `apiRateLimit`).

**Method:** static review of routes, filters, controllers, services, models, and existing test inventory. Audit only — no code changed.

---

## Confirmed vulnerabilities

### F-1 — HIGH: `requireParish()` lets elevated admins mutate finance with parish scope 0

**Evidence:** `app/Controllers/BaseController.php:418–488` (`getOperationalParishId`) and `:705–764` (`requireParish`). `requireParish()` rejects `parishId <= 0` only for non-admins; admins pass through with scope 0. `getOperationalParishId()` auto-falls back to the *first parish of the admin's diocese* and writes `selected_parish_id` into the session, but only when the diocese actually has parishes — a diocesan admin of an empty diocese, or a province admin path that resolves no parish, still lands on 0.

`Finance/Controllers/AccountingPeriods.php` `generate()` and `store()` call `requireParish()` but then proceed to insert rows using the resolved `parishId` — including `parish_id = 0` for an admin with no resolvable parish. That writes accounting periods into a phantom "parish 0" tenant, which other parish-scoped queries will never see (silent data corruption of the fiscal calendar) and which pollutes any unscoped aggregate.

**Exploitation:** a diocesan admin account whose diocese has no parishes (or whose parish resolution fails) POSTs to `accounting-periods/generate`/`store`; rows persist with `parish_id=0`, breaking the "never continue a finance mutation with parish scope 0" invariant in `.devin/rules/parish-finance.md`.

**Fix:** in every finance mutation, require `$parishId > 0` after `getOperationalParishId()` regardless of admin status (the explicit `if ($parishId <= 0)` guards used in `Budgets`, `PeopleFinance`, and `MyDashboard::submitPledge` are the correct pattern — `AccountingPeriods` lacks them).

### F-2 — MEDIUM: `adminFinanceBoundary` before-list omits `journal-entries/*`, `accounting-periods/*`, `finance/chart-of-accounts/*`

**Evidence:** `app/Config/Filters.php` — the `adminFinanceBoundary` URI list covers `reports/*`, `donations/*`, `expenses/*`, `budgets/*`, `banking/*`, `funds/*`, `pledges/*`, `treasurer/*`, but not `journal-entries/*`, `accounting-periods/*`, or `finance/chart-of-accounts/*`. Those routes rely solely on their `permission:*` filters.

**Exploitation:** `provinceadmin`/`dioceseadmin` groups lack `finance.*` permissions by default, so this is gated on an administrator individually granting e.g. `journal_entries.view` or `accounting_period.manage` to an elevated admin. Once granted, that admin reads/mutates parish ledgers with no financial-boundary check and — combined with F-1 — can write parish_id=0 rows. Lower severity than a default-deny miss, but the omission is real and asymmetric with the rest of the module.

**Fix:** add the three URI prefixes to the `adminFinanceBoundary` before list (or explain in a comment why they are intentionally excluded).

### F-3 — MEDIUM: CSV formula injection across all finance exports

**Evidence:** `Finance/Controllers/DonationExports.php`, `ExpenseExports.php`, `FundExports.php`, and `JournalEntries.php::export()` all stream rows via `fputcsv()` with no neutralization of cells beginning with `=`, `+`, `-`, `@`, tab, or CR. Donor names, notes/memo fields, expense descriptions, and fund names are all user-controlled and land verbatim in the CSV.

**Exploitation:** a parish admin (or member via pledge notes) enters `=cmd|'/c calc'!A1`-style content in a donation note or expense description; a treasurer exporting the ledger to Excel/LibreOffice executes the payload when opening the CSV. Financial exports are the most likely CSV-to-spreadsheet path in the system.

**Fix:** prefix dangerous leading characters with `'` when writing CSV cells (central helper used by all `*Exports` controllers).

### F-4 — MEDIUM: bank statement import has no size, MIME, or row-count limits

**Evidence:** `app/Services/BankStatementParserService.php::parse()` — `file_get_contents($filePath)` reads the entire upload into memory; format detection is by filename extension (`ofx|qfx|csv|txt` allowlist in `BankingImport`) plus content sniffing for `<OFX>` markers; no `getSize()`/MIME check in the controller and no cap on parsed transaction count.

**Exploitation:** a finance.manage user uploads a multi-hundred-MB CSV/OFX file → worker memory exhaustion (DoS), or uploads a file that sniffs as OFX but contains garbage → mass forged `bank_transaction` rows pending reconciliation.

**Fix:** enforce a max upload size (validation `max_size` rule + `post_max_size`), cap parsed rows, and validate content type before parsing.

### F-5 — MEDIUM: audit redaction misses `description`/`memo` — freeform finance notes logged raw

**Evidence:** `AuditService::log` routes values through `MonitoringService::sanitizeContext`; the redacted key-fragment list covers `amount|gift|pledge|notes|email|phone|address|password|token|secret|credential` but **not** `description` or `memo`. `Expenses` audit payloads include `description`; `AccountingJournalSyncService` writes `donation['notes']` into journal line `memo` (`AccountingJournalSyncService.php:346`). Clergy pledge audits log full `old_values`/`new_values` pledge rows including `notes` (redacted by fragment match on `notes`, but `description` on expenses is not).

**Exploitation:** anyone with audit-log read access sees unredacted expense descriptions/memos — donor-adjacent freeform notes the BaseController rule says must be redacted ("financial values and freeform notes").

**Fix:** add `description`, `memo`, `reference` to the redaction fragment list.

### F-6 — MEDIUM: `Budgets::save()` never verifies posted `accountId`s belong to the operational parish

**Evidence:** `Finance/Controllers/Budgets.php::save()` validates amounts and enforces `parishId > 0`, but iterates submitted `accountId` keys without checking each chart account's `parish_id` (or system-account mutability). `save()` also writes no audit event.

**Exploitation:** a parish admin POSTs budget lines keyed by another parish's account IDs → cross-parish budget rows / referential corruption. Not a direct data leak (reads stay scoped) but an integrity violation and unaudited.

**Fix:** look up each submitted `accountId` through the parish-scoped account query (or `canMutateAccount`) before persisting; add an audit event to `save()`.

### F-7 — MEDIUM: `Banking::update()` accepts client `is_active` and validates `chart_account_id` only as integer

**Evidence:** `Finance/Controllers/Banking.php::update()` — the update payload lets the client set `is_active` (bypassing the dedicated `toggle-active` endpoint's flow) and validates `chart_account_id` with an `integer` rule only — no check that the account is `account_type=asset`, active, or belongs to the parish's/system's allowed COA.

**Exploitation:** a parish admin links a bank account to an arbitrary chart account (income/expense/wrong parish); `AccountingJournalSyncService::resolveOperatingCashChartAccountId` then posts donation/expense cash legs to that account — mis-posting ledger entries. `is_active` via update also skips whatever guards exist on `toggle-active`.

**Fix:** strip `is_active` from the update payload (lifecycle changes go through `toggle-active` only); validate `chart_account_id` as an active asset account in scope.

### F-8 — LOW: `isDateInClosedPeriod` treats only `status='closed'` as locked — `soft_closed` periods stay writable

**Evidence:** `app/Models/AccountingPeriodModel.php::isDateInClosedPeriod()`. Donations, expenses, and journal writes check this before allowing a dated mutation; `soft_closed` passes.

**Exploitation:** during a soft-close (review) window, dated finance mutations still post into the period. If `soft_closed` is meant only as a warning stage this is by design; if it is meant to freeze writes pending audit sign-off, it is a real gap. Flagged for product confirmation.

**Fix:** confirm semantics; if soft-close must block writes, extend the check.

### F-9 — LOW: `apiRateLimit` does not cover funds, banking, journal/period/COA mutations

**Evidence:** `app/Config/Filters.php` `apiRateLimit` list covers `donations/store+delete`, `expenses/store+update+delete`, `budgets/save`, `pledges/*`, `me/pledge` — but not `funds/setup`, `funds/:num/*` lifecycle endpoints, `banking/*` mutations (import, reconcile, store), `accounting-periods/*`, or `chart-of-accounts` writes.

**Exploitation:** scripted bulk mutations (e.g. mass-fake donations via `funds/setup` churn, rapid bank-import uploads compounding F-4) face no per-user throttle.

**Fix:** add finance mutation URIs to the rate-limit list, prioritizing `banking/import` and `funds/setup`.

### F-10 — LOW: no `parishScope`/`deviceVerification` filters inside the Finance module — all isolation is controller-convention

**Evidence:** `Finance/Config/Routes.php` applies only `session` + `permission:*`. `deviceVerification` is a global filter so its coverage depends on the global URI list; `parishScope` is absent by design (controllers call `getOperationalParishId()`). This works today because the controllers are disciplined, but any future finance route whose author forgets the scope calls ships an IDOR.

**Fix (defense-in-depth):** consider applying `parishScope` to the finance group so context resolution is uniform, and add finance mutation URIs to `deviceVerification`'s coverage if not already global.

### F-11 — LOW: missing audit events on `Budgets::save()` and other unaudited mutations

**Evidence:** `Budgets::save()` persists without `audit()->log`. (Most other finance mutations do log.) Combined with F-6, budget changes are both unscoped-per-account and unaudited.

**Fix:** log `budget.save` with parish_id and line count (amounts redacted per redaction policy).

---

## Verified-secure paths (spot-checked, no findings)

- **Route permission granularity:** every mutation route requires a write permission (`finance.manage`, `donation.*`, `expense.*`, `banking.manage`, etc.); read routes require `finance.view`/`report.view`. No mutation reachable under `finance.view` found. `match(['get','post'])` on `batch-statements` still requires the same permission. Alternate `edit/:num` vs `:num/edit` patterns both carry the same permission filter.
- **Tenant scoping on `:num` lookups:** `Donations`, `DonationStatements`, `DonationExports`, `Expenses`, `ExpenseExports`, `Funds`, `FundFlags`, `Banking*`, `JournalEntries::view/export`, `Reports`/`ReportExports` all verify `row.parish_id === operational parish` (via `findInParish`, `accountForParish`, `reconciliationForParish`, or explicit checks) before returning or mutating. No cross-parish IDOR confirmed.
- **Mass assignment:** donation/expense `parish_id` forced server-side to the attribution parish; `person_id`/`user_id`/`parish_id` not accepted from POST on finance writes (`MyDashboard::submitPledge` forces `user_id`/`parish_id` from auth profile).
- **Fund lifecycle:** `findUsableFund` enforces same-parish + `is_active=1` + `is_locked=0`; `restrictionDataForFund` derives restriction from `fund.fund_type` (client restriction fields never trusted); `provisionDefaultFund` is idempotent, transactional, parish-validated, and audited; Last Usable Fund Guard blocks locking/deactivating the final usable fund; `fund_type` change blocked when the fund has transactions; historical-fund retention enforced on edits.
- **Ledger integrity:** `AccountingJournalSyncService` derives `parish_id` from the donation/expense *row* (not session); `DonationService::resolveIncomeAccount` requires an active income account; `ExpenseService::findExpenseAccount` requires active `account_type='expense'` — satisfies the "never fund.chart_account_id" separation rule. Journal sync failures are caught/logged without leaking traces.
- **Reports/exports (non-CSV):** `Reports`/`ReportExports` require `report.view` + `requireParish` on every endpoint, parish-scoped datasets, static filenames, and **real Dompdf PDFs** — the MemberPortal "PDF returns HTML" bug does **not** recur here. `reports/api/summary` is scoped and permissioned. Province-admin branches in Reports are unreachable behind `adminFinanceBoundary`.
- **Banking scoping:** `accountForParish`/`reconciliationForParish` on every `:num` route; reconciliation mutations check status (`not_open`/`unbalanced`) before write.
- **MemberPortal/Clergy finance routes:** `AnnualPledges` parish-scoped under `finance.manage`; `MyDashboard::submitPledge` forces parish from the member's profile, gates `pending_review`, validates, and is rate-limited; `receipt()` checks `donation.user_id === auth user`; `statement()` scopes to `profile.parish_id`; Clergy `PeopleFinance` pledge routes require `annual_pledge.manage`, verify pledge parish scope, and run `guardTargetUserMutation`.
- **Treasurer aliases:** `Finance/Config/Aliases.php` is a pure `class_alias` BC shim; no `Treasurer` routes file exists; no route resolves through `Treasurer\` — dead surface, confirmed unreachable.
- **CSRF/input:** CSRF filter is global on POST; all finance input flows through `$this->request->getPost()`/`validate()` — no raw `$_POST`/`$_GET` in finance code; queries use Query Builder/Models, no unescaped SQL.
- **Audit coverage:** all major finance mutations emit `audit()->log` events with parish context meta.

---

## Prioritized remediation

1. **F-1** — Enforce `$parishId > 0` on every finance mutation for admins too (`AccountingPeriods::generate/store` first; sweep for other mutations relying on `requireParish()` alone).
2. **F-2** — Add `journal-entries/*`, `accounting-periods/*`, `finance/chart-of-accounts/*` to `adminFinanceBoundary`.
3. **F-3** — Centralize CSV formula-cell escaping across `DonationExports`, `ExpenseExports`, `FundExports`, `JournalEntries::export`.
4. **F-4** — Size/row/MIME limits on bank-statement import; add `banking/import` to `apiRateLimit` (F-9).
5. **F-5** — Add `description`/`memo`/`reference` to audit redaction fragments.
6. **F-6 + F-11** — Parish-verify posted `accountId`s and add audit logging in `Budgets::save()`.
7. **F-7** — Strip `is_active` from `Banking::update`; validate `chart_account_id` as an active in-scope asset account.
8. **F-8** — Decide `soft_closed` semantics; extend `isDateInClosedPeriod` if needed.
9. **F-10** — Optional hardening: `parishScope`/`deviceVerification` on finance group.

**Test gaps to add (FeatureTestTrait):** cross-parish IDOR regression tests for `accounting-periods`, `banking/:num`, `funds/:num`, `journal-entries/:num`; permission-denied tests for province/diocese admins hitting the three boundary-excluded route families; a parish_id=0 admin mutation test; closed/soft-closed period lock tests; CSV-formula-injection export tests. Existing coverage is decent (`BankingControllerIntegrationTest`, `FundsControllerIntegrationTest`, `ReportsFinanceBoundaryIntegrationTest`, etc.) but does not exercise the F-1/F-2 paths.
