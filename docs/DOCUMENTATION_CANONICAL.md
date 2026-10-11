# Documentation Canonical Set

This file is the single source of truth for which documentation should be retained in this repository.

## Keep These Docs

### Root-level docs

- `README.md`
  Canonical root project guide for setup, runtime, and references.

### docs/ folder

- `docs/CHART_OF_ACCOUNTS_GUIDELINES.md`
  Canonical accounting structure and fund-accounting guidance.
- `docs/SEEDERS.md`
  Canonical seeding strategy and entry points.
- `docs/SERVICE_MIGRATIONS.md`
  Canonical service migration lineage.
- `docs/MODULARIZATION_CUTOVER_EXECUTION_PLAN.md`
  Active execution plan for modularization work.
- `docs/MEMBERPORTAL_CUTOVER_CHECKLIST.md`
  Tactical checklist for the first modular cutover.
- `docs/FINAL_REPOSITORY_CONTROLLER_OWNERSHIP_SCAN.md`
  Latest ownership scan snapshot used to validate migration posture.
- `docs/OPTIMIZATION_SUMMARY.md`
  Implemented performance work summary (kept as implementation record).
- `docs/DEPLOYMENT_RUNBOOK.md`
  Canonical deployment, rollback, and operations runbook.
- `docs/EVENT_VISIBILITY_SERVICE_USAGE_SCAFFOLD.md`
  Canonical service usage scaffold for MemberPortal and Diocesan Events endpoints.
- `docs/EPISCOPAL_PAROCHIAL_REPORT_FOR_REFERENCE.md`
  Canonical field contract and implementation authority for the TEC Parochial Report screen.
- `docs/CHURCH_MANAGEMENT_PERMISSIONS_2026-04-23.md`
  Canonical rules and implementation details for parish editing, Tax ID access, and staff management permission constraints across all administrative roles.
- `docs/PROFILE_PICTURE_SECURITY_AND_DELIVERY.md`
  Canonical private profile picture model (per-user folders, random names, guarded delivery, path hardening, and cache behavior).
- `docs/EMAIL_DELIVERABILITY.md`
  Canonical scoped SMTP, SPF, DKIM, DMARC, provider alignment, and safe diagnostic guidance.
- `docs/VISITOR_LIFECYCLE.md`
  Canonical visitor lifecycle (`visitor → regular_attender → member`), intake paths, directory scope, promotion modes, and permissions.
- `docs/FOLLOWUP_WORKFLOW_SYSTEM.md`
  Canonical visitor follow-up engine: sequences, scheduling, dispatch, call tasks, and cron.
- `docs/ROLE_MENU_PERMISSION_POLICY.md`
  Canonical role-to-menu permission policy enforced by `PermissionRoleSyncSeeder`.
- `docs/ORDINARY_AUTHORIZATION.md`
  Canonical ordinary/episcopal authorization policy: shared schema boundaries, office approval, delegation, `canon_to_ordinary`, and the migration/activation runbook.
- `docs/DEANERY_AUTHORIZATION.md`
  Canonical deanery scoping policy: deanery creation/membership, regional-dean office scope, and deanery routes.

## Removed as Redundant or Historical

The following were removed because they were superseded, iterative pass artifacts, point-in-time status snapshots, or duplicated by canonical docs above:

- `CHART_OF_ACCOUNTS_IMPLEMENTATION.md`
- `CHURCH_PERMISSIONS_IMPLEMENTATION.md`
- `CODE_REVIEW_FIXES.md`
- `FAMILIES_MODULE_IMPLEMENTATION.md`
- `PERFORMANCE_OPTIMIZATIONS.md`
- `PROJECT_COMPLETE.md`
- `PROJECT_STATUS.md`
- `QUICKSTART.md`
- `REDIRECT_LOOP_FIX.md`
- `SUPERADMIN_TESTING.md`
- `docs/LEGACY_MODULARIZATION_CLEANUP_MATRIX.md`
- `docs/LOGIN_DASHBOARD_ONE_PAGE_MAP.md`
- `docs/LOGIN_TO_DASHBOARD_FLOW_REPORT.md`
- `docs/LOGIN_TO_PROVINCE_DASHBOARD_FLOW_REPORT_THIRD_PASS.md`
- `docs/role_based_mudule_strtucture_report.md`
- `README_EKKLESIAHELP.md`
- `docs/EPISCOPAL_PAROCHIAL_REPORT_REFERENCE.md`

## Rule for Future Docs

- Keep canonical references and active operational runbooks.
- Avoid storing iterative pass reports once a final/canonical doc exists.
- Prefer updating existing canonical docs over adding new status snapshot docs.
