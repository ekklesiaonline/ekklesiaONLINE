# Query Optimization Phase 5 Report

Date: 2026-04-02
Scope: Query analysis, slow-query monitoring, and performance baseline capture for the current local environment.

## Commands Run

DB and cache baseline:

```bash
php spark optimize:benchmark --enable-slow-log --slow-log-threshold-ms 200
```

Authenticated HTTP baseline:

```bash
BASE_URL=http://127.0.0.1:8181 LOGIN_EMAIL=*** LOGIN_PASSWORD=*** REPEAT=3 sh build/performance_baseline.sh
```

## Slow Query Logging Status

- `slow_query_log`: `ON`
- `long_query_time`: `0.200000`
- `log_output`: `FILE`
- MySQL slow log file reported by the local server: `/opt/homebrew/var/mysql/Juans-MacBook-Pro-slow.log`
- Application slow-query threshold: `monitoring.slowQueryMs = 100`
- Application slow-request threshold: `monitoring.slowRequestMs = 800`

Result: slow-query monitoring is active in the current environment.

## DB and Cache Benchmarks

| Benchmark | Result | Target | Status | Notes |
| --- | ---: | ---: | --- | --- |
| Members list (active, 50/page) | 3.28 ms | <= 500 ms | PASS | 18 rows returned, 18 total |
| Services list (50/page) | 0.61 ms | <= 500 ms | PASS | 21 rows returned, 21 total |
| Volunteer teams list (50/page) | 0.41 ms | <= 300 ms | PASS | 1 row returned, 1 total |
| Volunteer schedules list (50/page) | 0.51 ms | <= 300 ms | PASS | 1 row returned, 1 total |
| Finance summary aggregate | 0.97 ms | <= 500 ms | PASS | donations=5, expenses=0, services=21, attendance=0 |
| Chart of accounts cache cold | 1.57 ms | <= 200 ms | PASS | 62 expense accounts |
| Chart of accounts cache warm | 0.06 ms | <= 50 ms | PASS | 62 expense accounts |

Result: `php spark optimize:benchmark` returned `GREEN`.

## HTTP Endpoint Baseline

Measured with an authenticated parish-admin session against the local app.

| Endpoint | HTTP | Avg | Min | Max |
| --- | --- | ---: | ---: | ---: |
| Dashboard | 200 | 26.31 ms | 18.66 ms | 37.33 ms |
| Members | 200 | 11.03 ms | 10.16 ms | 11.70 ms |
| Services | 200 | 11.14 ms | 10.39 ms | 11.83 ms |
| Volunteers | 200 | 9.74 ms | 9.62 ms | 9.85 ms |
| Reports | 200 | 11.69 ms | 10.72 ms | 13.10 ms |

Result: all measured authenticated endpoints were comfortably below the Phase 5 dashboard target of `<2s` and the API/page response target of `<200ms`.

## Findings

1. Phase 4 remains healthy: the query audit is green and the added indexes are active.
2. Phase 5 monitoring is now operational in the current local environment.
3. DB and cache reads are well within the thresholds defined by the optimization plan.
4. HTTP route timings are also comfortably within target, including dashboard and reports.
5. Chart of Accounts caching is behaving as expected, with a clear warm-cache improvement.

## Operational Notes

1. The MySQL slow query log is enabled at `200ms` for this environment; adjust higher in production if log volume becomes noisy.
2. The app-level structured monitoring remains more aggressive than MySQL, with `monitoring.slowQueryMs = 100` and `monitoring.slowRequestMs = 800`.
3. Re-run these commands after major schema changes, reporting changes, or dashboard data expansion.

## Phase 5 Status

Phase 5 is complete in the current environment.
