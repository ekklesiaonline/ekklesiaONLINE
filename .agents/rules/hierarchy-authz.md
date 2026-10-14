---
description: Hierarchy scopes, elevated-admin restrictions, registration approvals, and clergy/deanery canonical governance
trigger: always_on
---

# Hierarchy & Scoped Authorization

## Hierarchy & Scopes

- Dashboard routing hierarchy: System → Province → Diocese → Parish → Personal.
- `BaseController::resolveDashboardScope()` is canonical for landing-path resolution.
- `BaseController::canAccessMyDashboard()` gates `/me`. Parish roles always qualify; elevated admins qualify only with a `parish_affiliation` row.
- Personal dashboard is served by `MemberPortal\Controllers\MyDashboard` + `App\Services\MemberDashboardDataService`.
- **Deanery is a geographic scope (`diocese → deanery → parish`), NOT an administrative tier.** It does not insert a new level into `AdminHierarchyGuard`.

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
