# i18n Closure Artifact - Remaining Modules

Date: 2026-04-02
Scope: Pastor, MemberPortal, Treasurer, Volunteer, Worship, plus shared views still outside prior diocesan/province closure passes.

## Goal

Start Phase C i18n closure with measurable evidence and a repeatable sweep process for remaining modules.

## Completion Update (Current Pass)

1. Localized shared HTTP error-mapping literals in base and module layout scripts to App language keys.
1. Localized remaining hardcoded fallback strings in critical Members management/action views.
1. Re-ran scans and captured post-pass metrics.

Status: In progress with measurable closure movement.

## One-Pass Closure Sweep (This Request)

1. Localized high-impact remaining literals in:

- app-level and member-portal device verification views
- Treasurer donation create form
- public home landing copy
- per-page labels in Worship and Volunteer lists
- shared session-expiring hint fallback in all main layouts

1. Added locale keys in EN/ES/FR for:

- Auth device verification labels/messages
- App shared per-page and session hint text
- Home landing page copy

## Baseline Evidence Captured

1. Language pack inventory

- Central language path present: app/Language
- Locales present: en, es, fr
- Module files per locale: 26
- Total key files across locales: 78

1. Translation key volume (quick key-count proxy)

- en: 1975 key lines
- es: 1975 key lines
- fr: 1975 key lines

1. View localization usage baseline

- lang() usages across target views: 2355

1. Residual literal-candidate baseline (triage queue)

- Candidate user-facing literals in views (coarse grep): 150

## Post-Pass Metrics (This Execution)

1. View localization usage count after updates

- lang() usages across target views: 2440

1. Residual literal-candidate count after updates

- Candidate user-facing literals in views (coarse grep): 89

1. Targeted hardcoded fallback scan (Members/layout hot paths)

- Remaining matches for updated literals: 0

1. Reviewed unresolved set (framework/debug exclusions applied)

- Remaining unresolved candidates: 0
- One residual scanner false positive: app/Views/auth/layout.php title render section

## Locked Exclusion Baseline (Future Scans)

These exclusions are locked for future literal scans unless explicitly changed by architecture/security direction.

1. Framework debug template bucket

- Path: app/Views/welcome_message.php
- Reason: upstream/framework sample page, non-production UX surface, intentionally not part of product i18n closure scope.

1. Diagnostic error template bucket

- Path prefix: app/Views/errors/html/
- Included templates: error_exception.php, error_403.php, error_404.php, production.php
- Reason: operational/diagnostic pages with environment-sensitive output and framework conventions; tracked separately from product-surface localization sweep.

1. Structural scanner false-positive bucket

- File: app/Views/auth/layout.php
- Pattern: title render section output (renderSection('title'))
- Reason: structural template placeholder, not a hardcoded user-facing literal.

1. Rule of engagement for baseline changes

- Any addition/removal to exclusion buckets requires an update note in this artifact with date and rationale.
- Product-surface views/controllers must not be added to exclusions to suppress real localization debt.

## Sweep Method (Phase C Standard)

1. Run literal candidate scan across views and JS in PHP views.
1. Triage each match into:

- false positive (not user-facing)
- already localized
- needs localization key

1. Add keys in en/es/fr triads in app/Language.
1. Replace literals in views/controllers with lang() and JS json_encode(lang(...)) patterns.
1. Re-run scan and record remaining candidate count.
1. Run php -l on all touched files.

## Initial Prioritized Buckets

1. High-traffic UX surfaces

- Pastor members/follow-up/guests/events
- MemberPortal profile/families/dashboard

1. Finance/admin surfaces

- Treasurer expenses/donations/reports
- Worship services/attendance

1. Remaining static copy and placeholder/tooltips

- Button titles
- Form placeholders
- Alert strings in embedded JS

## Deliverables Started in This Pass

1. Authenticated smoke script now validates explicit error.code payloads for Members/Families/Follow-up actions once seeded credentials are present.

- build/e2e_members_families_followup_smoke.sh

1. Contract-depth unit test coverage added for critical workflow route guards and explicit error-code presence.

- tests/unit/CriticalWorkflowContractCoverageTest.php

1. i18n cleanup pass applied in shared layouts and critical members views.

- app/Views/layouts/main.php
- Pastor/Views/layouts/main.php
- Province/Views/layouts/main.php
- Diocesan/Views/layouts/main.php
- Treasurer/Views/layouts/main.php
- Worship/Views/layouts/main.php
- Volunteer/Views/layouts/main.php
- Pastor/Views/Members/admin-management.php
- Pastor/Views/Members/view.php
- app/Language/en/App.php
- app/Language/es/App.php
- app/Language/fr/App.php
- app/Language/en/Auth.php
- app/Language/es/Auth.php
- app/Language/fr/Auth.php
- app/Language/en/Home.php
- app/Language/es/Home.php
- app/Language/fr/Home.php
- app/Views/session/device_verify.php
- MemberPortal/Views/Session/device_verify.php
- app/Views/home/index.php
- Treasurer/Views/Donations/create.php
- Worship/Views/Services/index.php
- Volunteer/Views/Volunteers/index.php
- Volunteer/Views/Volunteers/schedules.php

## Closure Criteria (for full Phase C completion)

1. Residual literal-candidate count reaches reviewed-acceptable threshold with tracked exemptions.
1. Remaining module views/controllers have no untracked user-facing literals.
1. EN/ES/FR keys remain in sync for new additions.
1. A final closure report is published with before/after metrics and scanned command outputs.

## Regression Note (2026-04-04)

1. Worship service form parity: liturgical day/feast dropdown localization now aligned between create and edit forms.
1. Canonical values remain stored in `name` while localized display uses `liturgical_day_label(...)`.
1. `/services/liturgical-days` now emits localized `label` values consumed by dynamic dropdown refresh.
1. Added test coverage: `tests/database/ServicesLiturgicalDaysLocalizationTest.php` asserts localized `label` output for `en`, `es`, and `fr` on the endpoint payload.
