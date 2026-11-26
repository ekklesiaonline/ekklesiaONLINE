# Cron Schedule — ekklesiaONLINE

Approved schedule matrix for scheduled `php spark` jobs and the nightly
backup on the production host (HostGator `ekklessia.online`, PHP 8.5).

**Status:** DRAFT for operator approval (launch-readiness D1). Install only
after sign-off; verify the first run of each job in its log.

## Install notes

1. Replace `/path/to/ekklesiaONLINE` with the deployed app root and `php`
   with the absolute PHP 8.5 binary (cron `PATH` is minimal — on HostGator
   EA4 this is `/opt/cpanel/ea-php85/root/usr/bin/php` — verified
   2026-11-25 on `sh00162.hostgator.com`; `/opt/alt/php85` does NOT exist
   there. Confirm with `which php` over SSH). The `build/*.sh` wrappers do **not** see the
   crontab's inline `php` path — they use `$PHP_BIN` and fall back to `php`
   on `PATH`. Each wrapper line below sets `PHP_BIN=...` **inline** so it
   works whether jobs are added via `crontab -e` or the cPanel Cron
   Jobs panel (which adds one command per job and may not honour
   crontab-level environment lines). Replace the path on every wrapper line.
2. All times are **server-local**. Check `date` on the host — if it runs
   UTC, shift the "morning" jobs (`followup:send`, `volunteers:remind`)
   into parish-local business hours.
3. Run every command once manually before enabling its cron line
   (add `--dry-run` first for `followup:send`, `sms:process`,
   `finance:sync-journal-history`, `pledge:sync-fulfillment`).
4. Logs append to `writable/logs/`; ensure that directory exists and is
   writable by the service account.

## Crontab

```cron
# ── Overnight batch (order matters: metrics → aggregates → backup) ──
30 1 * * *   PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash /path/to/ekklesiaONLINE/build/service_metrics_refresh_cron.sh
45 1 * * *   cd /path/to/ekklesiaONLINE && php spark attendance:sync-diocesan-aggregates --rolling-days 35 >> writable/logs/attendance_aggregates_cron.log 2>&1
00 2 * * *   cd /path/to/ekklesiaONLINE && sh build/backup_database.sh >> writable/logs/backup-cron.log 2>&1
00 3 * * *   cd /path/to/ekklesiaONLINE && php spark finance:sync-journal-history >> writable/logs/finance_journal_sync_cron.log 2>&1
10 3 * * *   cd /path/to/ekklesiaONLINE && php spark pledge:sync-fulfillment >> writable/logs/pledge_sync_cron.log 2>&1

# ── Sunday evening (after services, before standing:refresh) ──
30 21 * * 0  cd /path/to/ekklesiaONLINE && PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash build/cron_sync_sunday_service_metrics.sh >> writable/logs/sunday_service_metrics_cron.log 2>&1
00 23 * * 0  cd /path/to/ekklesiaONLINE && php spark standing:refresh --year $(date +\%Y) >> writable/logs/standing_refresh_cron.log 2>&1

# ── Daytime operations ──
30 7 * * *   cd /path/to/ekklesiaONLINE && php spark volunteers:remind >> writable/logs/volunteers_remind_cron.log 2>&1
00 8 * * *   cd /path/to/ekklesiaONLINE && php spark followup:send >> writable/logs/followup_send_cron.log 2>&1
*/15 * * * * cd /path/to/ekklesiaONLINE && php spark events:publish-due >> writable/logs/events_publish_cron.log 2>&1
*/30 * * * * cd /path/to/ekklesiaONLINE && php spark sms:process >> writable/logs/sms_process_cron.log 2>&1
15 * * * *   PHP_BIN=/opt/cpanel/ea-php85/root/usr/bin/php bash /path/to/ekklesiaONLINE/build/clergy_reconcile_grants_cron.sh
```

## Job matrix

| Job | Command | Frequency | Rationale | Log |
|---|---|---|---|---|
| Service metrics | `service-metrics:refresh --rolling-days 35 --rebuild-period-buckets` (wrapper) | Daily 01:30 | Documented in `SERVICE_METRICS_REFRESH_SCHEDULING_2026-04-10.md`; rebuilds the `diocesan_sunday_weekly_metrics`/`diocesan_sunday_monthly_metrics` rollups (note: the diocesan Sunday dashboard chart currently reads `service_metric` directly, not these rollups — see caveat below). The command snaps the 35-day start back to the 1st of its month, so the rebuild window is the 1st of the month containing (today − 34 days) → today — usually the previous month, but up to two months back (e.g. on Mar 1 it starts Jan 1) | `service_metrics_refresh_cron.log` |
| Diocesan aggregates | `attendance:sync-diocesan-aggregates --rolling-days 35` | Daily 01:45 | Dashboard monthly aggregates. Same month-snapped window as above (computed in the app timezone), so month-keyed summaries are always rebuilt from complete months; staggered after service-metrics, before backup | `attendance_aggregates_cron.log` |
| Backup | `sh build/backup_database.sh` | Daily 02:00 | Documented runbook §8; captures post-refresh aggregates | `backup-cron.log` |
| Journal history | `finance:sync-journal-history` | Daily 03:00 | GL entries track daily donation/expense activity; after backup so nightly snapshot stays consistent | `finance_journal_sync_cron.log` |
| Pledge fulfillment | `pledge:sync-fulfillment` | Daily 03:10 | Fulfilled amounts track tithe donations; staggered after journal sync | `pledge_sync_cron.log` |
| Sunday service metrics | `attendance:sync-sunday-service-metrics` (wrapper; default window Jan 1 → today, previous year's Jan 1 during the first week of January) | Weekly Sun 21:30 | Sunday-only data; full-range sync via existing wrapper; populates `diocesan_sunday_service_metrics` and the weekly/monthly rollups (not read by the current dashboard chart — see caveat below). (Independent of `standing:refresh`, which reads member `attendance` rows directly via `MemberStandingSummaryModel`, not these metrics) | `sunday_service_metrics_cron.log` |
| Good standing | `standing:refresh --year $(date +\%Y)` | Weekly Sun 23:00 | Documented in `STANDING_SUMMARY_SCHEDULING_TODO_2026-04-10.md` | `standing_refresh_cron.log` |
| Volunteer reminders | `volunteers:remind` (default `--days 2`) | Daily 07:30 | Day-granular shift reminders; morning send before volunteers check email | `volunteers_remind_cron.log` |
| Follow-up dispatch | `followup:send` | Daily 08:00 | `followup_log.scheduled_date` is `DATE` — once daily covers "due today or earlier"; morning send = same-business-day follow-up | `followup_send_cron.log` |
| Event publishing | `events:publish-due` | Every 15 min | `publish_at` is datetime; ≤15 min publish delay for scheduled announcements | `events_publish_cron.log` |
| SMS queue | `sms:process` | Every 30 min | Drains `sms_queue`; cheap poll covering SMS enqueued by any feature | `sms_process_cron.log` |
| Clergy grant reconcile | `clergy:reconcile-grants --apply` (wrapper) | Hourly at :15 | Documented hourly-or-daily in `ORDINARY_AUTHORIZATION.md`; keeps revoked/expired grants from conferring permissions | `clergy_reconcile_grants_cron.log` |

**Owner:** the cPanel/SSH service account that owns the deployed files
(same account that ran B1 migrations and B2 backup drills).

## Reconciliation with existing docs

- `service-metrics:refresh` daily 01:30 — matches
  `docs/SERVICE_METRICS_REFRESH_SCHEDULING_2026-04-10.md`. The wrapper
  `build/service_metrics_refresh_cron.sh` previously hardcoded the local
  dev path; it now resolves `ROOT_DIR` portably like the other wrappers.
- `standing:refresh` Sunday 23:00 — matches
  `docs/STANDING_SUMMARY_SCHEDULING_TODO_2026-04-10.md`.
- `clergy:reconcile-grants` hourly-or-daily — set to **hourly** per the
  wrapper's own recommendation (`15 * * * *`) and `ORDINARY_AUTHORIZATION.md`.
- Backup daily 02:00 — matches runbook §8 example (`0 2 * * *`).

## Known caveat — Sunday rollups have no reader

`DioceseDashboardDataService::getDiocesanSundayAttendanceAverageTrend()`
(`app/Services/DioceseDashboardDataService.php`) builds the diocesan Sunday
chart by querying `service` + `service_metric` directly. No application code
reads the `diocesan_sunday_service_metrics`, `diocesan_sunday_weekly_metrics`
or `diocesan_sunday_monthly_metrics` tables written by
`attendance:sync-sunday-service-metrics` / `service-metrics:refresh`. The chart
is therefore always live (it does not depend on these cron jobs), and
re-running the sync does **not** change what the chart shows. Track as a
follow-up: either switch the chart to the rollups (the original intent: "fast
chart reads") or retire the rollup jobs.

## Known caveat — `events:publish-due`

`event.publish_at` exists in `canonical_schema_tables.sql` and test
fixtures but **no migration creates it**. Databases built by
`php spark migrate` (local, CI, and the HostGator deploy) lack the
column; `EventPublishService` no-ops safely via its `fieldExists` guard,
but any code path that *writes* `publish_at` will fail there. Track as a
schema-drift item: either add a migration or confirm the feature is
canonical-schema-only. The cron line is harmless either way (no-ops
cleanly when the column is absent).

## Commands intentionally NOT scheduled

One-off/backfill/audit commands run manually only:

- `followup:backfill`, `followup:apply-default` — reconciliation/seed helpers
- `directory:sync-ueid`, `MergeUeidParishes` — directory imports
- `person:diagnose`, `people:audit-affiliation-drift`,
  `leadership:audit-eligibility`, `finance:audit-readiness`,
  `finance:audit-coa-categories` — report-only audits
- `sacraments:reconcile`, `person:link-profiles` — repair utilities
- `build/cron_sync_church_people.sh` — **stale**: invokes
  `church-people:sync`, which no longer exists; do not install.

## Verification after install

1. `crontab -l` shows the block; each job's log file appears under
   `writable/logs/` after its first run.
2. Manually run each command once before the first scheduled fire and
   confirm exit 0 + expected output.
3. After 24 h: check logs for errors; confirm `backup_database.sh`
   produced a fresh `.sql.gz` in `writable/backups/` (B2 retention rules).
