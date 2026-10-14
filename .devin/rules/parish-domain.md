---
description: Parish hierarchy and data integrity rules for ekklesiaRD
trigger: always_on
---

# Parish Domain & Data Integrity

## Hierarchy & Scopes

- Dashboard routing hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is canonical for landing-path resolution.
- `BaseController::canAccessMyDashboard()` gates `/me`. Parish roles always qualify; elevated admins qualify only with a `parish_affiliation` row.
- Personal dashboard is served by `MemberPortal\Controllers\MyDashboard` + `App\Services\MemberDashboardDataService`.
- **Deanery is a geographic scope (`diocese → deanery → parish`), NOT an administrative tier.** It does not insert a new level into `AdminHierarchyGuard`.

## Membership Status Invariants

- `parish_affiliation.membership_status` is the only stored lifecycle status. `is_member` is a generated column (`status IN ('member','inactive')`); `affiliation_type`, `canonical_status`, and `is_good_standing` no longer exist — never write any of them. Visitors are `membership_status='visitor'` (the `/visitors` directory + follow-up feature); legacy `'guest'` input normalizes to `'visitor'` via `MembershipStatusService`. Event-registration `guest_*` fields are plus-one companions, not lifecycle records — never conflate them.
- `is_member` means "on the parish register": `status IN ('member','inactive')` (`MembershipStatusService::isOnRegister()`). `isMember()` is the stricter "active member" check (`status='member'`). Do not conflate them.
- Off-register removal (detach, member delete, tag revoke) writes `visitor` + `end_date` — never a stale `member` status.
- New affiliations default to `visitor`: registration approval, non-portal person creation (children, unflagged), and `attachToParish()`'s signature default all start at `visitor`. `member` is only ever set by an explicit operator choice (member checkbox) or a `MembershipPathwayService` promotion.
- Good standing lives on `member_standing_summary.is_good_standing` (keyed by `user_id` + `year`), not on the affiliation row.
- Clergy/staff are expressed through `leadership_position`, not lifecycle statuses.
- Leadership eligibility is enforced by `LeadershipPositionService::eligibilityFor()` on every create and on reactivation (active 0→1) — never gate on `users.id` existence alone, because non-portal people still get a `users` row. Portal access is required for ALL positions including clergy: `users.active=1` + `auth_identities(type='email_password')` + `profile.portal_access='enabled'` (an `invited` profile is not enough). Lay positions additionally require a recorded baptism plus the standing block (confirmation, current-year pledge, communicant-in-good-standing or `good_standing_override`); clergy are exempt from the standing block only. `good_standing_override` never bypasses portal access or baptism.
- `revokePortalAccess()` refuses to strip portal access while the user holds active leadership positions — end the positions first.
- Audit existing violations with `php spark leadership:audit-eligibility` (report-only; remediation is manual).
- Audit existing data with `php spark people:audit-affiliation-drift`.
- Full plan: `docs/MEMBERSHIP_STATUS_SIMPLIFICATION_PLAN.md`.

## People Counts

- Parish-level people counts must be based on `parish_affiliation.person_id` (the canonical `person` row), not `parish_affiliation.linked_user_id` and not `profile`.
- `countDistinctMembers()` and `countDistinctPeople()` must `JOIN person` and `COUNT(DISTINCT parish_affiliation.person_id)`.
- `total_visitors` = `parish_affiliation` rows where `membership_status IN ('visitor','regular_attender')` (the legacy `guest` table was dropped).
- Visitor management is `/visitors/*` (`Clergy\Controllers\Visitors`); the visitor→member promotion is `POST /visitors/promote/{personId}` → `PersonConversionService::convertToMember()`. See `docs/VISITOR_LIFECYCLE.md`.

## Person Identity

- The canonical person key is `person.id`.
- `profile.person_id` links a profile to the canonical person.
- `/people/view/{id}` route `id` is `profile_id`; downstream services must use `profile.person_id`.

## Sacramental Records

- `sacramental_record` is the canonical source of truth for baptism/confirmation.
- `person_membership_details.is_baptized` / `is_confirmed` and `profile.baptism_date` / `confirmation_date` / `is_confirmed` are derived.
- After every sacrament CRUD operation, call `SacramentFlagSyncService`.
- Sacrament type comparisons must use `LOWER()` because the dropdown emits lowercase but the column is free-text.
- `is_communicant` is attendance-based (`member_standing_summary.communion_count >= 3`), not derived from sacramental records.

## Elevated Admin Scope Restrictions

- Province admins have **read-only** access to parish data and are **blocked** from staff management (`staff`, `addStaff`, `promoteMember`, `removeStaffRole`, `demoteStaff`).
- Tax ID editability:
  - System Admin: all parishes.
  - Diocesan Admin: parishes within their diocese.
  - Parish Admin: their own parish only.
- Error key for province admin staff block: `Diocesan.provinceAdminStaffManagementDenied`.

## Registration Approvals

- Use `RegistrationApprovalService` for all approve/dismiss/ban/delete actions.
- Lower-tier admins cannot act on requests from higher-tier admin groups; `AdminHierarchyGuard` enforces this.
- Higher-tier groups (`systemadmin`, `provinceadmin`, `dioceseadmin`) are auto-approved and bypass parish `pending_review`.
- Use `lang('Dashboard.approveLabel')`, `lang('Dashboard.dismissLabel')`, `lang('Dashboard.confirmApproveRegistration')`, `lang('Dashboard.confirmDismissRegistration')`. Do not use `lang('App.approve')` / `lang('App.dismiss')`.

## Canonical Person Diagnostics

- Use `php spark person:diagnose` and `php spark people:audit-affiliation-drift` to verify canonical identity integrity across `person`, `profile`, `user_person`, and `parish_affiliation`.

## Sunday Attendance Graphics

- See the `/sunday-attendance-charts` skill for chart-building guidance.

## Clergy, Deanery & Canonical Governance

- **Four-Axis Authorization Separation**:
  1. Ordained Order & Verification (`clergy_ordination`: `deacon`, `priest`, `bishop`).
  2. Approved, Dated Office (`leadership_position` with `scope_type` and active date intervals).
  3. Administrative Hierarchy Rank (`AdminHierarchyGuard`: `systemadmin=4`, `provinceadmin=3`, `dioceseadmin=2`, `parishadmin=1`, other=`0`).
  4. Scoped Application Capability (managed grants with explicit provenance).
  *Holy orders never imply administrative rank; administrative status never implies ecclesial or liturgical authority.*
- **Ordination vs Office Invariant**: `leadership_position.clergy_order` is user-submitted/display data, **not** verified ordination. Sacramental or ordaining functions require verified records in `clergy_ordination`.
- **Canonical Standing Blocks**: A status of `suspended` or `inhibited` in `clergy_canonical_status` hard-blocks all ministerial, signatory, and office functions in every diocese (system-wide) until its `effective_to` passes; transferred/retired are diocese-local.
- **Deanery Scoping Rules**:
  - Diocese is the **only** parent of a deanery (`diocese_id` FK). Provinces have no direct deanery access.
  - Parishes without a deanery remain valid.
  - Territorial containment: Deanery A never extends to Deanery B. A Regional Dean has pastoral coordination/viewing across member parishes, but does **not** inherit Rector, Priest-in-Charge, or Diocesan Bishop governance powers.
  - `deanery_parish_membership` invariants: `parish.diocese_id` must match `deanery.diocese_id`, with at most **one effective active membership** per parish at any time (`started_at <= ended_at`). Past deanery membership is preserved for historical attribution but never resurrects access.
- **Identity & Accounts**: Unlinked clergy without portal logins may be tracked in the canonical registry (`clergy_ordination`, `clergy_canonical_status`), but application permissions require an active, linked `users` account (`users.active=1`, `auth_identities`, `profile.portal_access='enabled'`).
- **Deprecated Tables**: `user_scope_assignment` was permanently dropped; never reintroduce it as an authority source.

## Parish Finance & Canonical Stewardship

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
- **Finance Readiness**: `FinanceReadinessService` is canonical for distinguishing `no_funds`, `no_active_funds`, `all_active_funds_locked`, and `ready`. Donation entry must be blocked when no usable fund exists; managers link to `/funds/create`, while non-managers receive escalation guidance.
- **Default Fund Provisioning**: `php spark finance:audit-readiness` is report-only by default. `--provision` may create one audited `Unrestricted Operating Fund` only for a parish with zero fund records; it must be idempotent and must never reactivate, unlock, replace, or supplement existing funds automatically.

## Bootstrapping & Permissions

- Fresh environment seeding: `php spark db:seed DatabaseSeeder`.
- Sync role-to-menu permissions: `php spark db:seed PermissionRoleSyncSeeder`.
- Canonical policy table: `docs/ROLE_MENU_PERMISSION_POLICY.md`.
