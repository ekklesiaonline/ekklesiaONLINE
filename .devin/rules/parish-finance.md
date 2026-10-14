---
description: Parish finance and canonical stewardship invariants
trigger: glob
globs:
  - "Finance/**"
  - "**/Donation*"
  - "**/Fund*"
  - "**/Expense*"
  - "**/donation*"
  - "**/fund*"
  - "**/expense*"
---

# Parish Finance & Canonical Stewardship

## Invariants

- **Pass-Through (Conduit) Donations Strictly Prohibited**: Under IRC §170 and Episcopal Canon III.9.6.b.6, donations earmarked by donors for specific named individuals cannot be accepted as tax-deductible charitable contributions and must never be receipted on tax statements. All alms and Discretionary Fund contributions belong to the church entity and must be under the rector's sole, unconstrained pastoral discretion.
- **Handling Earmarked Gifts (Treasurer & Clergy Protocol)**:
  When a parish receives a check or electronic gift designated for a specific person, the *Manual of Business Methods in Church Affairs* specifies this mandatory protocol:
  1. *Do Not Deposit Immediately*: Do not deposit an earmarked gift into the operating account or discretionary account without clarification.
  2. *Contact the Donor & Explain the Law*: Kindly inform the donor: *"IRS regulations and Church canons do not permit us to accept tax-deductible donations designated for specific individuals. We can only accept this gift if it is given to the general Discretionary Fund with full discretion resting with the Rector."*
  3. *Offer the Donor Two Choices*:
     - **Option A**: Give the gift to the general Discretionary Fund without restrictions (the donor receives a valid tax receipt; the Rector uses pastoral judgment).
     - **Option B**: Return the check to the donor so they can give the money directly to the individual as a personal, private gift (non-tax-deductible, but directly helpful to the individual).
- **Clergy Discretionary Funds**: Dedicated alms from one Sunday a month Holy Eucharist loose offerings (Canon III.9.6.b.6). Owned by the church entity under its EIN, with monthly statements reviewed by an independent Reviewer (Treasurer/Senior Warden) while keeping recipient identities confidential. Cannot be used for personal clergy expenses or operating budget items.
- **Title I, Canon 7 Fiduciary Invariants**: Double-entry general ledger balancing ($\Delta = 0$), approved depositories with dual signatures on withdrawals, closed fiscal period mutation locks, annual independent CPA/committee audits filed with the Bishop by September 1, and property held in trust.
- **Donation Fund Invariants**: New donations require a positive operational `parish_id` and a fund from that exact parish with `is_active=1` and `is_locked=0`. Use `FundModel::getUsableParishFunds()` for selectors and `DonationService::findUsableFund()` for writes; never continue a finance mutation with parish scope `0`.
- **Restriction Source of Truth**: Donation restriction status and purpose derive from the selected fund through `DonationService::restrictionDataForFund()`. Do not trust or persist client-supplied restriction fields that conflict with the fund. Historical donations may retain their existing inactive/locked fund during unrelated edits, but cannot be reassigned to another unusable fund.
- **Donation Validation**: All donation write surfaces must use `DonationService::validationRules()` and then perform parish-scoped usable-fund and active-income-account checks.
- **Finance Readiness**: `FinanceReadinessService` is canonical for distinguishing `no_funds`, `no_active_funds`, `all_active_funds_locked`, and `ready`. Donation and expense entry must be blocked when no usable fund exists. Managers use `POST /funds/setup` only for `no_funds`; inactive/locked states link to fund management, while non-managers receive escalation guidance.
- **Default Fund Provisioning**: Basic parish creation never creates funds. `POST /funds/setup` is the normal explicit finance-activation path and may create one audited `Unrestricted Operating Fund` only for a parish with zero fund records. `php spark finance:audit-readiness` remains report-only by default; `--provision` is the remediation path. Provisioning must be idempotent and must never reactivate, unlock, replace, or supplement existing funds automatically.
- **Fund Account Mapping**: `fund.chart_account_id` is the optional default donation income account, never a fund-balance/net-assets account. It must reference an active `account_type=income` account. Donation writes use `DonationService::resolveIncomeAccount()`: omitted account IDs inherit the fund default, while an explicitly selected different active income account is allowed as an override and remains visible in the donation audit data.
- **Last Usable Fund Guard**: Never lock or deactivate a parish’s final fund where `is_active=1 AND is_locked=0`; another usable fund must exist first. A blocked lifecycle attempt performs no mutation and writes no success audit event.
- **Historical Fund Retention**: Donation and expense edits may retain the record’s existing inactive/locked fund during unrelated corrections. Edit selectors show usable funds plus that current historical fund only; they must exclude every other inactive/locked fund, and server-side validation must reject reassignment to an unusable fund.
- **Expense Fund Invariants**: New expenses require a positive operational `parish_id` and a fund from that exact parish with `is_active=1` and `is_locked=0`. Expense selectors show usable funds plus the expense’s current historical inactive/locked fund during edits. Historical records may retain their current fund, but may not be reassigned to another unusable fund. Closed-period locks remain mandatory.
- **Expense Account Separation**: `fund.chart_account_id` is the default donation income account and must never be used as an expense-account default. Expenses require an independently selected active `account_type=expense` account. Any future fund-level expense default must use a separate explicit field or mapping.
- **Profile Donation Restriction UX**: Profile donation modals show fund-derived restriction type/purpose as read-only information. Never restore editable `is_restricted` or `restriction_purpose` controls; `DonationService::restrictionDataForFund()` remains authoritative.
- **Finance Route Regression Tests**: Use `FeatureTestTrait` to cover actual donation create/store and fund lock/deactivate routes in addition to direct controller tests. POST route tests must use form format and exercise CSRF with `withHeaders([csrf_header() => csrf_hash()])->withBodyFormat('form')`; never disable the security filter to make them pass.
