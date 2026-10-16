# Theme Toggle + Chart.js Recursion Options Report

Date: 2026-04-04
Status: Decision support
Scope: Diocesan finance page theme reliability and Chart.js integration safety

## Problem Statement

On the diocesan finance page, theme toggling became unreliable because chart-theme updates executed inside layout-level `applyTheme()` triggered a Chart.js recursion error (`Maximum call stack size exceeded`).

Current stabilized fix:

- Removed direct chart-theme mutation call from layout-level `applyTheme()` so the toggle can always switch `data-bs-theme` and persist state.

This report lists additional options to fully resolve chart theming without reintroducing toggle breakage.

## Root Cause Summary

The layout-level theme pipeline and chart-render lifecycle were tightly coupled. When `applyTheme()` attempted to mutate chart internals at the wrong time (or through unsafe defaults mutation paths), Chart.js entered a recursive setter flow and threw before interaction wiring could complete.

## Design Goals

1. Keep theme toggle deterministic on every page.
2. Keep chart styling synchronized with current theme.
3. Avoid mutating deep Chart.js global defaults during runtime.
4. Isolate failures so chart errors cannot break global UI controls.

## Option A: Event-Driven Page-Level Re-theme (Recommended)

Approach:

- Keep layout `applyTheme()` focused only on root theme state + event dispatch.
- In chart pages (for example diocesan finance), listen for `ekklesiaONLINE:themechange`.
- Re-apply chart option colors directly on each chart instance and call `chart.update('none')`.
- Never mutate nested `Chart.defaults.plugins.*` during runtime.

Pros:

- Strong separation of concerns.
- Chart errors stay local to chart pages.
- Compatible with existing custom event architecture.
- Lowest regression risk for non-chart pages.

Cons:

- Requires per-page chart theme handlers.
- Some repeated logic unless extracted to shared helper.

Effort:

- Low to medium.

Risk:

- Low.

## Option B: Shared Chart Theme Adapter Module

Approach:

- Add a single JS helper (for example `public/js/chart-theme-adapter.js`) exposing:

  - `applyChartThemeToInstances()`
  - `bindChartThemeEvents()`

- Each chart page registers chart instances with the adapter.
- Adapter reads CSS variables and updates only instance options.

Pros:

- Reusable across Diocesan, Treasurer, and other chart pages.
- Eliminates duplicated chart color logic.
- Easier testing and future maintenance.

Cons:

- Requires refactor in multiple views to register instances.
- Slightly higher up-front implementation cost.

Effort:

- Medium.

Risk:

- Low to medium.

## Option C: Full Chart Rebuild on Theme Change

Approach:

- On `ekklesiaONLINE:themechange`, destroy and recreate each chart with new themed config.

Pros:

- Avoids subtle stale option state.
- Straightforward mental model.

Cons:

- More CPU work and potential flicker.
- Must preserve interaction state manually (tooltips, zoom, etc. if used later).

Effort:

- Medium.

Risk:

- Medium.

## Option D: Guarded Runtime Global Defaults Update

Approach:

- Keep a global chart-theming path but with strict guards:

  - No deep writes unless object paths are verified.
  - Hard fail-safe with `try/catch` so theme toggling continues.
  - Debounced updates and recursion lock flag.

Pros:

- Centralized one-place behavior.

Cons:

- Most fragile option with Chart.js internal changes.
- Higher chance of future regressions.
- Harder to reason about and test.

Effort:

- Medium.

Risk:

- High.

## Option E: Version Strategy (Pin/Downgrade/Upgrade Chart.js)

Approach:

- Evaluate whether current Chart.js version has a known regression path with runtime defaults mutation.
- Pin to a known-safe version or upgrade with compatibility checks.

Pros:

- Can remove framework-specific edge case if version-related.

Cons:

- Not a complete fix by itself if architecture remains coupled.
- Requires full chart regression testing across modules.

Effort:

- Medium.

Risk:

- Medium.

## Comparison Matrix

| Option | Stability | Maintainability | Performance | Implementation Cost | Risk |
| --- | --- | --- | --- | --- | --- |
| A. Event-driven page-level re-theme | High | High | High | Low-Medium | Low |
| B. Shared adapter module | High | Very High | High | Medium | Low-Medium |
| C. Full rebuild | Medium-High | Medium | Medium | Medium | Medium |
| D. Guarded global defaults | Low-Medium | Low | High | Medium | High |
| E. Version strategy | Medium | Medium | High | Medium | Medium |

## Recommendation

Primary recommendation:

1. Implement Option A immediately for chart pages.
2. Evolve to Option B as a follow-up refactor to consolidate shared chart-theme logic.

Avoid as primary strategy:

- Option D, because it keeps the risky coupling in layout-level global runtime behavior.

## Proposed Rollout Plan

1. Stabilization phase (short term)

- Keep current layout fix in place (already done).
- Add page-level `themechange` chart handlers in diocesan finance and any other chart-heavy dashboards.
- Ensure handlers only update chart instance options, not deep `Chart.defaults` plugin trees.

1. Consolidation phase (mid term)

- Introduce shared chart-theme adapter module.
- Migrate diocesan/treasurer chart pages to adapter.

1. Hardening phase

- Add browser smoke checks:

  - Toggle theme on finance pages.
  - Verify `html[data-bs-theme]` changes and localStorage persistence.
  - Confirm no console `RangeError` or chart initialization failures.

## Acceptance Criteria

1. Theme toggle always changes between light and dark on diocesan finance.
2. Chart rendering remains intact after repeated toggles.
3. No uncaught console errors related to Chart.js setters.
4. Login and authenticated flows load Bootstrap JS without SRI blockage.

## Notes

Related touched files in this debugging cycle:

- `Diocesan/Views/layouts/main.php`
- `Diocesan/Views/Dashboard/finance.php`
- `app/Views/auth/layout.php`
