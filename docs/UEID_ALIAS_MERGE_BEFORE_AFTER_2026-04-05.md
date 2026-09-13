# UEID Alias Merge Before/After Report (2026-04-05)

## Scope Implemented

- Added many-to-one diocesan alias table.
- Updated UEID merge command to resolve aliases from the new table.
- Added seeder for known mergers and renamed jurisdictions.
- Executed dry-run/apply validation cycle and captured report artifacts.

## Code Changes

- Migration: app/Database/Migrations/2026_04_05_191000_CreateDioceseAliases.php
- Command: app/Commands/MergeUeidParishes.php
- Seeder: app/Database/Seeds/DioceseAliasesSeeder.php
- Unified seeder wiring: app/Database/Seeds/UnifiedAppSeeder.php

## Execution Timeline

1. `php spark migrate`
2. `php spark parish:merge-ueid` (before baseline)
3. `php spark db:seed DioceseAliasesSeeder`
4. `php spark parish:merge-ueid` (after seeding)
5. `php spark parish:merge-ueid --apply`
6. `php spark parish:merge-ueid` (post-apply validation)

## Before vs After Summary

| Stage | Parsed | Created | Updated | Skipped | Unresolved | Report |
| --- | ---: | ---: | ---: | ---: | ---: | --- |
| Before (dry-run) | 6752 | 0 | 4 | 6567 | 181 | writable/reports/ueid_merge_report_20260405_231944.md |
| After seed (dry-run) | 6752 | 179 | 4 | 6567 | 2 | writable/reports/ueid_merge_report_20260405_231950.md |
| Apply | 6752 | 179 | 8 | 6563 | 2 | writable/reports/ueid_merge_report_20260405_231954.md |
| Final validation (dry-run) | 6752 | 0 | 4 | 6746 | 2 | writable/reports/ueid_merge_report_20260405_231957.md |

## Key Outcome

- Unresolved reduced from 181 to 2.
- Great Lakes merger aliases now resolve Eastern Michigan and Western Michigan into a single canonical target.

## Remaining Unresolved

The remaining unresolved source diocese is:

- Micronesia (2 rows)

Rows:

- 1462-9869, Epis Ch of St John the Divine, Tamuning, Guam
- 9713-0538, St Pauls Episcopal Church, Saipan, Northern Mariana Islands

## Notes

- A residual idempotency delta of 4 updates remains in dry-run after apply, consistent with prior behavior.
- The alias table now supports multiple historical names mapping to one canonical diocese.
