# Service Metrics Refresh Scheduling (2026-04-10)

## Purpose

Make service metrics refresh an explicit operational process, separate from member attendance registration writes.

## Dedicated Command

Use the dedicated orchestration command:

`php spark service-metrics:refresh`

This delegates to Sunday service-metrics aggregate sync logic and supports the same options:

- `--from YYYY-MM-DD`
- `--to YYYY-MM-DD`
- `--rolling-days N`
- `--diocese ID`
- `--church ID`
- `--rebuild-period-buckets`

## Recommended Schedule (Cron)

Run nightly for rolling refresh and period buckets rebuild:

```cron
30 1 * * * cd /Users/juanrosariodelacruz/Sites/ekklesiaRD && php spark service-metrics:refresh --rolling-days 35 --rebuild-period-buckets >> writable/logs/service_metrics_refresh_cron.log 2>&1
```

## Optional Scoped Runs

Diocese-only refresh:

`php spark service-metrics:refresh --rolling-days 35 --diocese 2 --rebuild-period-buckets`

Church-only refresh (does not rebuild diocesan period buckets):

`php spark service-metrics:refresh --rolling-days 35 --church 9`

Backfill by fixed range:

`php spark service-metrics:refresh --from 2026-01-01 --to 2026-12-31 --rebuild-period-buckets`

## Operational Notes

1. This flow is intentionally separate from member attendance registration endpoints.
2. Member attendance writes only to `attendance` and does not update `service_metrics` in real time.
3. Dashboards that read Sunday service metrics rely on `service_metrics` and the derived diocesan aggregate tables refreshed by this process.

## Validation Checklist

1. Run command manually once and verify completion output.
2. Confirm cron log file updates:
   - `writable/logs/service_metrics_refresh_cron.log`
3. Confirm recent rows in:
   - `diocesan_sunday_service_metrics`
   - `diocesan_sunday_weekly_metrics`
   - `diocesan_sunday_monthly_metrics`
