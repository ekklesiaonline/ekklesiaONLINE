# Sunday Attendance Chart Verification - March 1, 2026 (2026-04-10)

## Request Verified

Question: On the Sunday attendance graphic, March 1 appears as one Sunday point, but there were two different Sunday services that day.

## Verification Evidence

### 1) Service rows on March 1 exist as two distinct services

Live query result (`services`):

- Service ID 11, parish_id 1443, 2026-03-01 09:00:00, Morning Prayer Rite II
- Service ID 12, parish_id 1443, 2026-03-01 17:00:00, Evening Prayer Rite II + Benediction

### 2) Both service rows have service metrics

Live query result (`services` + `service_metrics`):

- Service ID 11: reported_present_count=5, reported_communion_count=0
- Service ID 12: reported_present_count=5, reported_communion_count=0

### 3) Bucket-level dashboard math for March 1

Live grouped verification for parish 1443 and Sunday date 2026-03-01:

- sunday_services_count = 2
- ASA (average present) = 5.00
- ASC (average communicants) = 0.00
- present_sum = 10

This proves both services are included in the metric calculation.

## Reason It Shows as One Sunday Point

The parish Sunday chart is bucketed by Sunday date/week, not by individual service instance time.

Implementation behavior in dashboard controller:

- Data source is per-service metrics joined to services.
- For parish view, weekly granularity uses `bucketKey = Y-m-d` (the Sunday date).
- Multiple services on the same Sunday share the same bucket key.
- The chart plots one point per Sunday bucket.

So for 2026-03-01:

- Two services are aggregated into one Sunday bucket.
- The displayed value is the average for that Sunday bucket (ASA), not service count.

## Why This Must Be This Way (Current Metric Definition)

Under current chart definition, this is correct and desirable because:

1. The chart KPI is ASA/ASC trend by Sunday period, not a count of service instances.
2. One point per Sunday avoids duplicate x-axis points for the same date.
3. It keeps weekly trend comparability stable even when some Sundays have one service and others have multiple services.
4. It reflects attendance intensity per Sunday period rather than multiplying points by number of liturgies that day.

## Clarification

The chart is not indicating "only one service happened".

It indicates "one Sunday bucket exists for that date", and both Sunday services are included in that bucket's average.

## If Different Behavior Is Desired

If you want to visualize each service instance separately (e.g., 9:00 and 17:00 as separate points/bars), that requires a different chart mode:

- x-axis by service instance (service_id or datetime), or
- dual view toggle: `Sunday bucket (ASA)` vs `Service-instance totals`.

Current behavior matches the ASA bucketed-Sunday design.
