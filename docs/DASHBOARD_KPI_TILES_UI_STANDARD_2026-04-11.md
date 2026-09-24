# Dashboard KPI Tiles UI Standard (2026-04-11)

## Scope

This document records the dashboard KPI tile changes implemented at `/dashboard` (parish-scoped dashboard), including layout, interaction, data cards, and consistency rules for future enhancements.

## Final KPI Structure

The KPI area now contains 8 clickable tiles arranged in 2 rows.

Row 1:

- People
- Members
- Good Standing
- Families

Row 2:

- Birthdays This Week
- Visitors Pending Follow-up
- Done Follow-ups This Month (Admins)
- Events This Month

## Tile Links

All KPI tiles are clickable and route to their related modules.

- People -> `/people`
- Members -> `/members`
- Good Standing -> `/standing`
- Families -> `/families`
- Birthdays This Week -> `/people`
- Visitors Pending Follow-up -> `/visitors?followup_stage=pending`
- Done Follow-ups This Month (Admins) -> `/followup-workflow/call-tasks?status=completed`
- Events This Month -> `/events`

## Layout and Spacing Rules

The grid uses a single gutter system for consistency.

- Container uses Bootstrap `row g-3`.
- Horizontal and vertical gaps are intentionally equal.
- No manual spacer rows/divs are used between KPI rows.

Width alignment rule:

- Each tile is `col-md-3`.
- Two KPI tiles (`3 + 3`) equal one lower section width (`col-md-6`).
- This aligns pairs such as `People + Members` with blocks like `Recent Services`.

## Card Height and Density

KPI cards were reduced in perceived height for a tighter dashboard rhythm.

- Reduced top/bottom `card-body` padding for KPI links.
- Tightened heading spacing (`h6`) and number line-height (`h3`).

Redundant subtitle cleanup:

- Removed period subtext under second-row KPI values (for example, removed repeated "This week" and "This month" captions).
- The period context remains in the tile title itself.

## Interaction States (Clickable Feedback)

KPI tiles use subtle motion and depth to communicate clickability.

- Hover: slight lift (`translateY(-2px)`) + soft shadow.
- Active: returns to baseline (`translateY(0)`).
- Focus-visible: clear keyboard outline.
- Reduced-motion support: transitions are disabled when user preference requests reduced motion.

Implementation class:

- `dashboard-kpi-link` is the interaction hook for linked cards.

## Data Notes for New KPI Tiles

The second-row cards are computed in `MemberPortal/Controllers/Dashboard.php` for parish scope.

- Birthdays This Week:
  - Uses active parish affiliations and profile `birth_date` values.
  - Counts by month-day in the current week window.

- Visitors Pending Follow-up
  - Uses `VisitorFollowupService::pendingDistinctPersonsCount($parishId)`: `COUNT(DISTINCT gfl.person_id)` where `status = 'pending'`, scoped to live persons with a live, unconverted `visitor`/`regular_attender` affiliation in the parish. **Not** `call_task` rows — those only exist after the dispatcher runs a due call/manual action. **Not** month-scoped — pending is a backlog state and a month window hides overdue entries. A visitor with 7 remaining steps contributes 1 to the total.
  - The count must equal the "Visitors in Follow-up" tile on `/visitors/dashboard` and the row count of `/visitors?followup_stage=pending` — all three describe the same population.

- Done Follow-ups This Month (Admins):
  - Counts tasks with `status = completed`.
  - Uses `updated_at` in current month.
  - Includes assignments to admin/pastoral groups.

- Events This Month:
  - Counts parish events by `start_at` in current month.

## Strict SQL Compatibility Note

A production issue was fixed during this work:

- Removed invalid `DATE != ''` filtering against `profile.birth_date`.
- MySQL strict mode rejects empty-string comparisons for DATE fields (`Incorrect DATE value: ''`).
- Current query only requires non-null date values.

## Files Updated During This UI Cycle

- `MemberPortal/Views/Dashboard/index.php`
- `MemberPortal/Controllers/Dashboard.php`
- `app/Language/en/Dashboard.php`
- `app/Language/es/Dashboard.php`
- `app/Language/fr/Dashboard.php`

### Updated 2026-04-19

- `MemberPortal/Controllers/Dashboard.php` — tile query changed from `COUNT(*)` to `COUNT(DISTINCT gfl.guest_id)`; tile now counts unique guests with ≥1 pending step this month, not total pending steps
- `app/Language/{en,es,fr}/Dashboard.php` — tile label updated to "Guests Pending Follow-up This Month"

## Future Implementation Checklist

When adding or changing KPI tiles, keep all of the following:

1. Keep `row g-3` and avoid manual row spacer elements.
1. Preserve width parity with lower `col-md-6` sections.
1. Keep tiles clickable only when a meaningful destination exists.
1. Use concise tile titles and avoid redundant helper subtitles.
1. Keep hover/active/focus states subtle and accessible.
1. Avoid invalid DATE comparisons (never compare DATE columns to empty strings).
1. Re-run syntax and dashboard smoke checks after each UI change.
