---
name: finance-parish
description: Explain parish-scoped finance modules and treasurer workflows
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with parish-scoped finance.

Key areas:
- Finance dashboard is at `/parish/finance` (Shield group `finance`).
- Modules & controllers:
  - Donations: `Finance/Controllers/Donations.php`
  - Expenses: `Finance/Controllers/Expenses.php`
  - Banking & Bank Reconciliation: `Finance/Controllers/Banking.php`, `App\Services\BankReconciliationService.php`, `App\Services\AccountingJournalSyncService.php`
  - Budgets: `Finance/Controllers/Budgets.php`
  - Fund Accounting: `Finance/Controllers/Funds.php`, `App\Models\FundModel.php`
  - Financial Reports: `Finance/Controllers/Reports.php`
  - Chart of Accounts: `Diocese/Controllers/ChartOfAccounts.php`
  - Annual Pledges: `MemberPortal/Controllers/AnnualPledges.php`
- Parish scoping: all financial transactions belong strictly to `parish_id`, enforced by `AdminFinanceBoundaryFilter`.
- Status: Bank Accounts, Bank Reconciliation, and Fund Management are fully implemented. Remaining pending gaps: Giving Statements, Directory Export.

When asked:
1. Identify which financial module the question relates to.
2. Reference controllers under `Finance/Controllers/`, `Diocese/Controllers/`, or `MemberPortal/Controllers/` and models under `app/Models/`.
3. Note parish-scoping rules and that `finance` users land on `/parish/finance`.
4. Cite the relevant files and line ranges.
