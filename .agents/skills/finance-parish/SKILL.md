---
name: finance-parish
description: Explain parish-scoped finance modules, general ledger sync, and treasurer workflows
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with parish-scoped finance, accounting, and treasury workflows.

## Key Areas & Architecture
- **Finance Dashboard**: `/parish/finance` (Shield group `finance`, permission `finance.view`).
- **Legacy Compatibility**: `/treasurer/dashboard` redirects to `/parish/finance`.
- **Tenant Scoping**: All financial transactions belong strictly to `parish_id`, enforced by `AdminFinanceBoundaryFilter` and controller queries. Province and Diocesan admins are blocked from directly modifying parish transactional funds.

## Core Modules & Controllers (`Finance/`)
- **Donations & Giving**:
  - `Finance\Controllers\Donations`: CRUD, sequential receipt generation (`DonationReceiptModel`), encrypted amounts.
  - `Finance\Controllers\DonationStatements`: Single-member and bulk annual giving statements (batch combined PDF and ZIP archive with IRS 501(c)(3) tax disclosures via `GivingStatementService`).
  - `Finance\Controllers\DonationExports`: Streaming unbuffered CSV (up to 50k rows) and PDF exports.
  - **Prohibition of Pass-Through (Conduit) Donations**: Earmarks designating specific individual beneficiaries are strictly prohibited from tax-deductible receipting (IRC §170, Rev. Rul. 62-113, Title III Canon 9.6.b.6). All contributions and alms must be under the church entity's full ownership and unconstrained pastoral discretion.
  - **Handling Earmarked Gifts (Treasurer & Clergy Protocol)**:
    1. *Do Not Deposit Immediately*: Hold earmarked checks/transfers without recording or depositing into operating or discretionary accounts.
    2. *Contact Donor & Explain Law*: Inform donor that IRS regulations and Church canons prohibit tax-deductible donations designated for specific individuals; gifts are accepted only if given to the general Discretionary Fund with full discretion resting with the Rector.
    3. *Offer Two Choices*:
       - **Option A**: Give to the general Discretionary Fund without restrictions (valid tax receipt issued; Rector exercises pastoral discretion).
       - **Option B**: Return the check to the donor to give directly to the individual as a personal, private, non-tax-deductible gift.
- **Expenses & Payables**:
  - `Finance\Controllers\Expenses`: CRUD, capital project tracking (`is_capital`), approver tagging (`approved_by`).
  - `App\Services\ExpenseReceiptUploadService`: Secure receipt/invoice attachments and downloads (`writable/uploads/receipts/`).
  - `Finance\Controllers\ExpenseExports`: CSV and PDF exports.
- **Banking & Reconciliation**:
  - `Finance\Controllers\Banking`: Bank account registration, cash ledger registers, account edit (`/banking/edit/{id}`), and active/archive toggle (`/banking/toggle-active/{id}`) with open reconciliation guard (`BankAccountModel`, `BankTransactionModel`).
  - `Finance\Controllers\BankingReconciliation`: Statement reconciliation worksheets with unbalanced close prevention (`BankReconciliationModel`, `BankReconciliationItemModel`).
  - `Finance\Controllers\BankingImport`: Statement file importing (OFX 1.x/2.x, QFX, CSV) with duplicate detection via `BankStatementParserService`.
- **Double-Entry General Ledger & Fiscal Periods**:
  - `Finance\Controllers\JournalEntries`: General Ledger Explorer UI (`/journal-entries`, `/journal-entries/{id}`, `/journal-entries/export`) with date/source/account filtering, debits/credits line drilldown modal, balanced indicators, and streaming CSV export.
  - `Finance\Controllers\AccountingPeriods`: Accounting Period Management UI (`/accounting-periods`, `/accounting-periods/generate`, `/accounting-periods/store`, `/accounting-periods/close/{id}`, `/accounting-periods/reopen/{id}`) to manage, generate 12 monthly periods, create, close, and reopen fiscal periods.
  - `App\Services\AccountingJournalSyncService`: Real-time synchronous auto-posting of donations and expenses into double-entry journal entries (`JournalEntryModel`, `JournalEntryLineModel`).
  - Account 1120 (`Cash - Checking`) default asset debit/credit resolution.
  - `App\Models\BankTransactionJournalLinkModel`: Links bank transactions to general ledger entries.
  - `App\Models\AccountingPeriodModel`: Period open/closed status; locks mutations (`donations`, `expenses`, `bank_transactions`) in closed periods.
  - `App\Commands\SyncJournalHistory` (`php spark finance:sync-journal-history`): Backfills historical donations and expenses into general ledger entries.
- **Fund Accounting**:
  - `Finance\Controllers\Funds`: Fund creation, restriction types (unrestricted, temporarily restricted, permanently restricted/endowment), active balances (`FundModel`).
  - `Finance\Controllers\FundFlags`: Locking/unlocking and activation flags.
- **Budgets**:
  - `Finance\Controllers\Budgets`: Fiscal year budget lines vs real-time actuals, utilization percentage, multi-line saving (`ParishBudgetModel`), export CSV (`/budgets/export-csv`), Vestry PDF document export with certification blocks (`/budgets/export-pdf`), and automated budget roll-forward / cloning with adjustment factor and audit logging (`POST /budgets/clone`).
- **Financial Reports**:
  - `Finance\Controllers\Reports`: Date-filtered KPIs, Fund Breakdown, Chart of Accounts Category Breakdown, Trial Balance (`/reports/trial-balance`), Statement of Financial Position (Balance Sheet) (`/reports/balance-sheet`), Statement of Activities (`/reports/statement-of-activities`), and Episcopal Parochial Report Financial Lines Compiler (`/reports/parochial-report`) via `FinanceReportsDataService`.
  - `Finance\Controllers\ReportExports`: CSV, PDF, Trial Balance exports (`/reports/trial-balance/export-csv`, `/reports/trial-balance/export-pdf`), Balance Sheet exports (`/reports/balance-sheet/export-csv`, `/reports/balance-sheet/export-pdf`), Statement of Activities exports (`/reports/statement-of-activities/export-csv`, `/reports/statement-of-activities/export-pdf`), and Parochial Report exports (`/reports/parochial-report/export-csv`, `/reports/parochial-report/export-pdf`).
- **Chart of Accounts (COA)**:
  - `Diocese\Controllers\ChartOfAccounts`: Canonical 4-digit Episcopal chart of accounts (1000s Assets, 2000s Liabilities, 3000s Net Assets, 4000s/5000s Income, 6000s-8000s Expenses). Reference guidelines in `docs/CHART_OF_ACCOUNTS_GUIDELINES.md`.
- **Annual Pledges & Stewardship**:
  - `MemberPortal\Controllers\AnnualPledges`: Parish stewardship pledges, search, pagination (`AnnualPledgeModel`).
  - `App\Services\PledgeFulfillmentService`: Automated fulfillment tracking when tithe/pledge donations are recorded.
  - `MemberPortal\Controllers\MyDashboard`: Member self-service pledging (`POST /me/pledge`), statement download (`/me/statement`), and self-service receipt access (`/me/receipt/{id}`).

## Active Roadmap & Pending Gaps
- **Priority 4: General Ledger Visibility & Fiscal Controls**:
  - `[x]` 4.1 General Ledger Explorer UI (`/journal-entries`).
  - `[x]` 4.2 Trial Balance Report (`/reports/trial-balance`).
  - `[x]` 4.3 Accounting Period Management UI (`/accounting-periods`) to close/reopen periods.
  - `[x]` 4.4 Bank Account Edit & Archive (`/banking/edit/{id}`, active toggle).
- **Priority 5: Compliance Statements & Budget Polish**:
  - `[x]` 5.1 Statement of Financial Position (Balance Sheet) (`/reports/balance-sheet`, CSV/PDF exports).
  - `[x]` 5.2 Statement of Activities (`/reports/statement-of-activities`, CSV/PDF exports).
  - `[x]` 5.3 Episcopal Parochial Report finance lines compiler (`/reports/parochial-report`, CSV/PDF exports, delegate from `ParochialReportDataService`).
  - `[x]` 5.4 Budget CSV/PDF export and roll-forward cloning (`/budgets/export-csv`, `/budgets/export-pdf`, `POST /budgets/clone`).
- **Priority 6: Operational Intake & Governance**:
  - Sunday batch collection plate / envelope quick entry (`/donations/batch-create`).
  - Parish read-only Chart of Accounts reference page (`/finance/chart-of-accounts`).
  - Multi-step expense approval workflow (`draft` → `pending` → `approved` → `paid`).

When asked:
1. Identify which financial module or workflow the question relates to.
2. Reference controllers under `Finance\Controllers\`, `Diocese\Controllers\`, or `MemberPortal\Controllers\` and models under `app/Models/`.
3. Note parish-scoping rules and that `finance` users land on `/parish/finance`.
4. Enforce double-entry integrity ($\Delta = 0$) and closed period mutation locks when writing financial logic.
5. Cite the relevant files and line ranges.
