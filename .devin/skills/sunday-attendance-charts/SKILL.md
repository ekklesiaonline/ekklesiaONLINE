---
name: sunday-attendance-charts
description: Build parish admin Sunday attendance charts from raw service metrics
argument-hint: "[--parish=<id>]"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

Build parish admin Sunday-attendance charts using raw `service_metric` (or `service_metrics` view) values joined to `service`.

1. Query `service_metric` joined to `service` for the target parish and date range.
2. Do not bucket, average, or apply derived ASA/ASC calculations.
3. Preserve separate service points when multiple Sunday services share a date.
4. Verify the chart source uses raw service metrics directly, not aggregated or synthetic series.

Report the query or code being reviewed and any recommended corrections.