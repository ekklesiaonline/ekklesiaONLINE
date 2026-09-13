# Liturgy UI Multilingual Plan and Execution (en/es/fr)

Date: 2026-04-03
Status: Completed (Phase 1, Phase 2, and Phase 3 completed).

## 1) Goal

Make liturgy-related UI displays multilingual across the full system (Worship Services, Attendance, Worship Planning, and Diocesan Liturgical Source), preserving canonical values in storage while translating canonical labels at render time.

## 2) Inventory and Gap Summary

Current strengths:

- Most static UI labels already use lang() in Services, Attendance, Worship, and Diocesan views.
- Language packs exist in en/es/fr for Services, Attendance, Worship, and Diocesan namespaces.

Current gaps identified:

- Canonical service-type values rendered raw in several places (fallbacks/tables/badges).
- Liturgical season options rendered raw in Services create/edit dropdowns.
- Liturgical color labels from dynamic liturgical-day payload rendered as raw English (ucfirst).
- Some controller-provided page titles in diocesan liturgical source were hardcoded English.
- Additional pass still needed for dates/times and any residual raw canonical values in liturgy-adjacent screens.

## 3) Plan (Phased)

Phase 1 (started and implemented now):

- Add a liturgy i18n helper with canonical maps and safe fallback behavior.
- Localize service-type displays in Services view and Attendance screens.
- Localize liturgical season/color labels in Services create/edit, including AJAX-loaded day color labels.
- Localize diocesan liturgical-source controller page titles.

Phase 2 (completed):

- Localize remaining canonical liturgical labels in Worship Planning list/print/service headers.
- Standardize localized date/time formatting across liturgy pages using locale-aware formatting.
- Add any missing language keys discovered during runtime checks.

Phase 3 (closure):

- Run role-based locale matrix smoke tests for liturgy routes.
- Document residual non-translatable free-text behavior.
- Publish closure checklist and evidence report.

## 4) Implementation Executed in This Iteration

### 4.1 New Helper

Created:

- app/Helpers/liturgy_i18n_helper.php

Added functions:

- service_type_label()
- liturgical_season_label()
- liturgical_color_label()

Design:

- Maps canonical values to existing language keys.
- Leaves unknown/custom values unchanged.

### 4.2 Autoload Wiring

Updated:

- app/Config/Autoload.php

Change:

- Added liturgy_i18n to global helpers list.

### 4.3 Diocesan Liturgical Source Titles

Updated:

- Diocesan/Controllers/LiturgicalCalendar.php

Changes:

- page_title now uses lang('Diocesan.liturgicalTitle') on index.
- page_title now uses lang('Diocesan.liturgicalEditEntryTitle') on edit.

### 4.4 Service Type Localization in Worship/Attendance Surfaces

Updated:

- Worship/Views/Services/view.php
- Worship/Views/Attendance/service.php
- Worship/Views/Attendance/reports.php

Changes:

- Service type chips/fallbacks/table cells now render with service_type_label().

### 4.5 Liturgical Season and Color Localization in Services Forms

Updated:

- Worship/Views/Services/create.php
- Worship/Views/Services/edit.php

Changes:

- Liturgical season dropdown labels now use liturgical_season_label().
- Liturgical day color label now uses liturgical_color_label() for initial server-rendered options.
- AJAX-loaded day options now map color codes through localized JS color map generated from helper output.

### 4.6 Phase 2 Completion: Worship Planning and Date/Time Normalization

Updated:

- Worship/Views/WorshipPlanning/index.php
- Worship/Views/WorshipPlanning/service.php
- Worship/Views/WorshipPlanning/print.php
- Worship/Views/Attendance/service.php
- Worship/Views/Attendance/reports.php
- Worship/Views/Services/index.php
- app/Helpers/liturgy_i18n_helper.php

Changes:

- Worship Planning list now localizes canonical service type values and date display.
- Worship Planning service header now uses locale-aware long date formatting.
- Worship Planning print header now uses dynamic html lang and locale-aware date/time formatting.
- Attendance mark header date now uses locale-aware formatting.
- Attendance reports year header now uses locale-aware formatting helper.
- Services index list now localizes canonical service type values.
- Helper expanded with reusable locale-aware formatters:
  - liturgy_format_localized()
  - liturgy_format_date()
  - liturgy_format_long_date()
  - liturgy_format_time()

## 5) Validation Approach for This Iteration

- File-level static checks for edited files.
- Runtime verification through locale switching on services and attendance routes.
- Focus checks:
  - Service type values no longer displayed raw English in ES/FR where canonical mappings exist.
  - Liturgical season labels in create/edit reflect locale.
  - Liturgical color suffix in liturgical-day options reflects locale for both initial render and AJAX refresh.

Phase 2 smoke evidence:

- Authenticated route matrix passed with HTTP 200 and matching html lang in en/es/fr for:
  - /services
  - /services/view/{id}
  - /attendance/service/{id}
  - /worship-planning
  - /worship-planning/service/{id}
  - /worship-planning/print/{id}

## 6) Remaining Work Queue (Immediate Next)

Phase 3 completed in this iteration:

- Executed role/locale matrix for diocesan liturgical source management flows: /liturgical-source, /liturgical-source/edit/{id}, /liturgical-source/store (POST), /liturgical-source/update/{id} (POST), /liturgical-source/delete/{id} (POST).

- Published closure artifact with final status and evidence: docs/LITURGY_I18N_COMPLETION_REPORT_2026-04-03.md.

- Published optional canonical alias strategy for liturgical day names: docs/LITURGICAL_DAY_CANONICAL_ALIAS_STRATEGY_2026-04-03.md.

Phase 3 matrix summary:

- Read flows (`GET /liturgical-source`, `GET /liturgical-source/edit/{id}`) returned HTTP 200 and matched html `lang` in en/es/fr for tested accounts.
- Church admin management POST flows returned HTTP 403 for store/update/delete.
- Diocesan admin management POST flows returned HTTP 303 redirect to `/session/device-verify`, indicating step-up device verification is enforced before mutation.
- This confirms role/flow hardening behavior while preserving multilingual read rendering.

## 7) Translation Boundary

- User-authored notes/names/titles are not auto-translated.
- Canonical controlled vocabulary is translated through deterministic mappings.
- New canonical entries can be added quickly by extending helper maps and language keys.
