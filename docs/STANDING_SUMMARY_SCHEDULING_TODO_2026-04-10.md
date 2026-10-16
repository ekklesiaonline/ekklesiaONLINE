# Standing Summary Scheduling Todo (2026-04-10)

## Implemented

- Added migration for `member_discipline`.
- Added migration for `member_standing_summary` with unique `(user_id, year)`.
- Added `MemberStandingSummaryModel` with:
  - `refreshForYear(int $year)` full-year recompute.
  - `updateSingleUserStanding(int $userId, ?int $year = null)` single-user upsert.
  - `getCurrentStandingCounts(?int $year = null)` dashboard counts.
- Updated `standing:refresh` command to call summary refresh and log runtime.
- Added app-level `UserModel` override with `afterUpdate` hook for immediate `users.active` standing refresh.
- Added `GoodStandingHelper::getCurrentStandingCounts()`.

## Scheduling

Use weekly Sunday 11:00 PM server local time:

```cron
0 23 * * 0 cd /Users/juanrosariodelacruz/Sites/ekklesiaONLINE && php spark standing:refresh --year $(date +\%Y) >> writable/logs/standing_refresh_cron.log 2>&1
```

Notes:

- Weekly cron ensures summary remains fresh for attendance, pledges, and discipline changes.
- `users.active` changes are handled immediately by the `afterUpdate` hook in `App\Models\UserModel`.

## Rollout Checklist

- Run migrations in target environment.
- Execute initial backfill: `php spark standing:refresh --year 2026`.
- Verify summary rows exist and unique `(user_id, year)` constraint is active.
- Deactivate and reactivate a test user; verify `is_good_standing` changes immediately.
- Confirm dashboard metrics read from `member_standing_summary`.
- Add the cron entry on production host and verify log output after first run.
