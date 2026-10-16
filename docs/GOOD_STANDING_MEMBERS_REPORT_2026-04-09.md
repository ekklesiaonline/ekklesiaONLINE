# Good Standing Members Report (2026-04-09)

## Purpose

This report documents the criteria used to determine good-standing members and provides current counts from the standing snapshot table.

## Source of Criteria

Primary documentation source:

- app/Language/en/Standing.php
  - Refresh description states that good-standing snapshots are based on:
    - attendance
    - communion frequency
    - membership status
    - annual pledge compliance

Supporting user documentation source:

- docs/USER_MANUAL_EN.md
  - Section 5.5 confirms that Good Standing status is produced by running the refresh process and reviewing updated outcomes.

Implementation source of truth:

- app/Models/MemberStandingSummaryModel.php
  - Method: refreshForYear
  - Method: updateSingleUserStanding
  - Method: getCurrentStandingCounts

## Implemented Good-Standing Logic

A member is computed as good standing when all conditions are true:

1. User is active.
1. Attendance count for the selected year is at least 3.
1. Communion count for the selected year is at least 3.
1. Annual pledge exists for the selected year.
1. Member is not currently under discipline during the evaluated year window.

Computed expression in code:

- is_good_standing = is_active_member AND attendance_count >= 3 AND communion_count >= 3 AND has_pledged AND NOT is_under_discipline

Refresh population scope behavior:

- Computes one row per user per year in member_standing_summary.
- Parish/diocese/province scoping is applied at read time using affiliation joins.

## Count Results (Database Snapshot)

Database: ekklesiaONLINE
Snapshot date: 2026-04-09

### By Year

| year | reviewed_total | good_standing_total | not_good_standing_total |
| --- | ---: | ---: | ---: |
| 2026 | 18 | 0 | 18 |
| 2025 | 18 | 0 | 18 |

### 2026 Parish Breakdown

| parish_id | parish_name | reviewed_total | good_standing_total |
| ---: | --- | ---: | ---: |
| 1443 | Iglesia Episcopal La Transfiguracion | 18 | 0 |

### 2026 Summary State

| year | active_members_total | good_standing_total |
| ---: | ---: | ---: |
| 2026 | 18 | 0 |

## Interpretation

- Current standing snapshots show zero members meeting all good-standing criteria for 2026.
- Snapshot rows are fully computed from source data; there are no manual override fields in member_standing_summary.

## Reproducibility

To refresh snapshots before recounting:

- php spark standing:refresh --year 2026

To count good-standing members by parish/year directly:

- SELECT COUNT(*)
  FROM member_standing_summary mss
  JOIN member_parish_affiliations mca ON mca.user_id = mss.user_id
  WHERE mca.parish_id = :parish_id
    AND mca.active = 1
    AND mss.year = :year
    AND mss.is_good_standing = 1;
