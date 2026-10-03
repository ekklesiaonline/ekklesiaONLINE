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
- Treasurer dashboard is now at `/parish/finance` (replaces the diocese-scoped treasurer dashboard).
- Modules: Donations, Expenses, Fund Accounting, Chart of Accounts, Budgets, Annual Pledges.
- Parish scoping: finance data belongs to `parish_id`.
- Pending gaps: Bank Accounts, Bank Reconciliation, Giving Statements, Directory Export.

When asked:
1. Identify which module the question relates to.
2. Reference controllers under `Parish/` or `Clergy/` and models under `app/Models/`.
3. Note parish-scoping rules and that `treasurer` users land on `/parish/finance`.
4. Cite the relevant files and line ranges.
