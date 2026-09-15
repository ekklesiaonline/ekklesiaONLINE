# Modularization Cutover Execution Plan

Date: 2026-03-14

## Closure Update (2026-04-02)

Status: Section 2.1 is now closed.

Completed closure actions:

- PSR-4 module namespace mappings are present in composer.json and active.
- Public and session/locale ownership is module-owned in MemberPortal routes/controllers.
- Temporary compatibility redirects were retired and replaced with direct module-owned legacy alias routes (no redirect hop).
- Legacy app shim controllers were retired: app/Controllers/Home.php, app/Controllers/LocaleController.php, app/Controllers/SessionController.php.
- Non-MemberPortal modules now use module-owned layout files and no longer depend on app/Views/layouts/main.php.
- Routing defaults were updated to module ownership: defaultNamespace = MemberPortal\\Controllers; defaultController = PublicHome.

Guard tests added/updated for closure:

- tests/unit/RouteOwnershipCutoverTest.php asserts session/home/locale ownership in MemberPortal module routes.
- tests/unit/RouteOwnershipCutoverTest.php asserts legacy compatibility aliases remain module-owned and no longer use addRedirect.
- tests/unit/ModuleRoutesSmokeTest.php asserts root route loader includes module route files (including Province).
- tests/unit/ModuleRoutesSmokeTest.php verifies expected handlers are present in each module route file.
- tests/unit/HomeRedirectMatrixTest.php was updated to use MemberPortal\\Controllers\\PublicHome (no App\\Controllers\\Home dependency).
- tests/unit/ModuleOwnershipClosureTest.php asserts module route files avoid App\\Controllers fallback references.
- tests/unit/ModuleOwnershipClosureTest.php asserts module route files do not use addRedirect shims.
- tests/unit/ModuleOwnershipClosureTest.php asserts non-MemberPortal module views do not extend layouts/main.

Verification snapshot (2026-04-02):

- Route conflict audit (method + path duplicate scan on resolved route table): no duplicates found.
- Targeted modularization closure suite: pass (coverage-driver warning only).
- Full regression sanity: pass with warnings only.
- Full regression metrics: Tests = 63; Assertions = 368; Warning = no code coverage driver available.

## Goal

This document records the executed cleanup sequence, ordered by safest-first cutover and finalized with closure guardrails.

## Core Rule

No module was cut over until the global prerequisites were complete.

## Global Prerequisites

These items were completed once before module-specific cutover.

- PSR-4 namespace mappings for Province, Diocesan, Pastor, Treasurer, Volunteer, and MemberPortal were added in composer.json.
- Autoload resolution was verified for namespaced controllers.
- View ownership strategy was executed as module-owned layouts/views for non-MemberPortal modules.
- Smoke-test coverage was established for migrated route groups.
- Province fallback/no-parishes rendering was provided through module-owned view coverage.

## Ranking Method

Modules were ranked by:

1. Number of routes to replace.
2. Number of controllers involved.
3. Dependence on shared or cross-module logic.
4. Risk of route collision.
5. Whether the module has cross-module ownership problems.

## Executed Cutover Order

### 1. MemberPortal

Risk: Lowest

Why it was first:

- Smallest functional surface.
- Only two controllers: Dashboard and Profile.
- Minimal business complexity compared with finance, diocesan, or pastor flows.
- Good first test of namespaced controller autoload and route cutover.

Cutover scope delivered:

- MemberPortal/Controllers/Dashboard.php
- MemberPortal/Controllers/Profile.php
- MemberPortal/Config/Routes.php

Legacy files affected:

- app/Controllers/Profile.php
- app/Views/profile/\*.php
- app/Config/Routes.php profile routes and member dashboard route

Cutover actions completed:

1. MemberPortal namespace loading was confirmed.

2. MemberPortal route/controller ownership was completed and validated.

3. Legacy profile route ownership in app/Config/Routes.php was removed from app scope.

4. Access checks were run for:

- GET /member/dashboard
- GET /profile
- GET /profile/edit
- POST /profile/update
- POST /profile/delete-picture

Post-cutover deletion status:

- app/Controllers/Profile.php moved to deletion-candidate status during cutover.

### 2. Volunteer

Risk: Low

Why it was second:

- Limited domain scope.
- One main functional controller plus a dashboard controller.
- Fewer cross-module data concerns than Pastor or Diocesan.

Cutover scope delivered:

- Volunteer/Controllers/Dashboard.php
- Volunteer/Controllers/Volunteers.php
- Volunteer/Config/Routes.php

Legacy files affected:

- app/Controllers/Volunteers.php
- app/Views/volunteers/\*.php
- app/Config/Routes.php volunteers group

Cutover actions completed:

1. Volunteer namespace autoload was verified.

2. Volunteer controller/route ownership was moved to module scope.

3. Legacy volunteers route ownership was retired from app scope.

4. Functional checks covered:

- team listing
- view team
- create team
- store team
- add member
- remove member
- schedules
- create schedule
- store schedule

Post-cutover deletion status:

- app/Controllers/Volunteers.php moved to deletion-candidate status.

### 3. Treasurer

Risk: Low to Medium

Why it was third:

- Finance routes are broader than Volunteer but still bounded.
- No obvious cross-module ownership issue like the diocesan create-parish mismatch.
- The route surface is moderate and predictable.

Cutover scope delivered:

- Treasurer/Controllers/Dashboard.php
- Treasurer/Controllers/Donations.php
- Treasurer/Controllers/Budgets.php
- Treasurer/Controllers/Expenses.php
- Treasurer/Controllers/Reports.php
- Treasurer/Config/Routes.php

Legacy files affected:

- app/Controllers/Donations.php
- app/Controllers/Budgets.php
- app/Controllers/Expenses.php
- app/Controllers/Reports.php
- app/Views/donations/\*.php
- app/Views/budgets/\*.php
- app/Views/expenses/\*.php
- app/Views/reports/\*.php
- app/Config/Routes.php donations, budgets, expenses, and reports groups

Cutover actions completed:

1. Treasurer namespace autoload was verified.

2. Finance subgroups were cut over in order: donations, budgets, expenses, reports, then treasurer dashboard.

3. Module-owned view/layout ownership was finalized for Treasurer surfaces.

4. Permission and CRUD checks were run before retiring legacy route blocks.

Post-cutover deletion status:

- App finance controllers moved to deletion-candidate status as each subgroup completed.

### 4. Province

Risk: Medium

Why it was fourth:

- Route surface is small.
- It has admin-critical actions.
- It depends on a missing fallback view and is referenced by diocesan create-parish behavior.

Cutover scope delivered:

- Province/Controllers/Dashboard.php
- Province/Config/Routes.php

Legacy files affected:

- app/Controllers/ProvinceDashboard.php
- app/Views/dashboard/province.php
- app/Views/dashboard/no_parishes.php
- app/Config/Routes.php province group

Cutover blockers that were resolved:

1. no_parishes rendering was provided through module-owned view coverage.

2. Province/Diocesan ownership boundaries were stabilized as part of module route/controller ownership checks.

Cutover actions completed:

1. Province dashboard rendering paths were validated for both populated and no-parish states.

2. Province route/controller ownership was finalized under module namespace.

3. Province dashboard, attendance breakdown, and admin actions were revalidated.

4. Legacy province route ownership was retired from app scope.

Post-cutover deletion status:

- app/Controllers/ProvinceDashboard.php moved to post-resolution deletion-candidate status.

### 5. Diocesan

Risk: Medium to High

Why it was fifth:

- Broadest admin surface outside Pastor.
- Multiple controllers under one module.
- Cross-module dependency exists because diocesan create-parish still points to Province logic.
- Includes parishes, dioceses, chart accounts, liturgical source, dashboard, finance, and system overview.

Cutover scope delivered:

- Diocesan/Controllers/Dashboard.php
- Diocesan/Controllers/FinanceDashboard.php
- Diocesan/Controllers/SystemOverview.php
- Diocesan/Controllers/Dioceses.php
- Diocesan/Controllers/Parishes.php
- Diocesan/Controllers/ChartOfAccounts.php
- Diocesan/Controllers/LiturgicalCalendar.php
- Diocesan/Controllers/RegistrationRequests.php
- Diocesan/Config/Routes.php

Legacy files affected:

- app/Controllers/DiocesanDashboard.php
- app/Controllers/FinanceDashboard.php
- app/Controllers/SystemOverview.php
- app/Controllers/Dioceses.php
- app/Controllers/Parishes.php
- app/Controllers/ChartOfAccounts.php
- app/Controllers/LiturgicalCalendar.php
- app/Views/dashboard/\*.php for diocesan pages
- app/Views/dioceses/\*.php
- app/Views/parishes/\*.php
- app/Views/chart_accounts/\*.php
- app/Views/liturgical_calendar/\*.php
- app/Config/Routes.php diocesan, parishes, dioceses, chart-accounts, liturgical-source groups

Cutover actions completed:

1. Create-parish ownership boundaries were resolved.

2. Subareas were cut over in order: dashboard, finance, system overview, chart accounts, liturgical source, dioceses, parishes, then registration requests.

3. Corresponding legacy routes were removed one subarea at a time.

4. Remaining legacy diocesan app controllers were kept/deleted according to passing ownership and regression checks.

Post-cutover deletion status:

- Diocesan legacy controllers moved to deletion-candidate status after subarea and ownership stabilization.

### 6. Pastor

Risk: Highest

Why it was last:

- Largest route surface among the migrated role modules.
- Contains the core operational workflows: members, services, attendance, guests, and followup.
- High business impact and many CRUD/action endpoints.
- Any mistake here affects the most day-to-day workflows.

Cutover scope delivered:

- Pastor/Controllers/Dashboard.php
- Pastor/Controllers/Members.php
- Pastor/Controllers/Services.php
- Pastor/Controllers/Attendance.php
- Pastor/Controllers/Guests.php
- Pastor/Controllers/FollowUp.php
- Pastor/Config/Routes.php

Legacy files affected:

- app/Controllers/Members.php
- app/Controllers/Services.php
- app/Controllers/Attendance.php
- app/Controllers/Guests.php
- app/Controllers/FollowUp.php
- app/Views/members/\*.php
- app/Views/services/\*.php
- app/Views/attendance/\*.php
- app/Views/guests/\*.php
- app/Views/followup/\*.php
- app/Config/Routes.php members, services, attendance, guests, and followup groups

Cutover actions completed:

1. Subgroups were cut over in order: followup, guests, services, attendance, members, then pastor dashboard.

2. Highest-risk Members cutover was kept near the end of the Pastor phase.

3. Detailed permission and regression tests were run at each step.

Post-cutover deletion status:

- Pastor-related legacy controllers were retired last among duplicated app controllers.

## Executed Phases

### Phase 0: Readiness

1. Namespace autoload mappings were completed.

2. View ownership strategy was finalized and applied.

3. no_parishes rendering path was covered by module-owned views.

4. Smoke and ownership guard tests were prepared.

### Phase 1: Low-Risk Wins

1. MemberPortal

2. Volunteer

3. Treasurer

### Phase 2: Admin Domains

1. Province

2. Diocesan

### Phase 3: Core Operational Domain

1. Pastor

## Deletion Policy

A legacy app controller or view was only deleted after:

1. The module namespace is autoloadable.

2. The module route group was live and tested.

3. The legacy route group was removed from app/Config/Routes.php.

4. The module controller no longer depended on legacy app view paths unless intentionally retained as shared.

## Final Outcome

Execution followed MemberPortal, then Volunteer, then Treasurer as the lowest-risk path to validate the modularization approach. Province and Diocesan were stabilized after shared admin concerns were addressed, and Pastor was completed last due to highest workflow criticality.
