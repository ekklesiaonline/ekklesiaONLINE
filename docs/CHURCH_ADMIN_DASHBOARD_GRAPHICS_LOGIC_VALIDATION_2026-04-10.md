# Parish Admin Dashboard Graphics Logic Validation (2026-04-10)

## Scope

Validate graphics in parish admin dashboard context only (excluding diocesan admin context) and confirm chart data source is service metrics data stored in DB.

## Environment Scope Control (Excluding Diocesan)

Parish-admin path is selected when `is_diocesan_scope` is false.

Code evidence:

- Parish branch call: `getParishSundayAttendanceAverageTrend(...)`
  - `MemberPortal/Controllers/Dashboard.php` (line 165)
- View branch split:
  - `MemberPortal/Views/Dashboard/index.php` (line 117)
  - `MemberPortal/Views/Dashboard/index.php` (line 453)

## Graphic Validated (Parish Admin)

Sunday attendance chart rendered on parish dashboard:

- Canvas: `parishAttendanceChart`
  - `MemberPortal/Views/Dashboard/index.php` (line 429)
- JS dataset binding uses controller payload arrays:
  - `sunday_attendance_averages`
  - `communion_attendance_averages`
  - `MemberPortal/Views/Dashboard/index.php` (lines 634-636)

Note: In parish-admin scope these arrays now carry raw per-service values from `service_metrics` (legacy key names retained for view compatibility).

## Data Source Validation (Service Metrics)

### Controller query source for parish chart

`getParishSundayAttendanceAverageTrend(...)` reads from `services` joined to `service_metrics`:

- `JOIN service_metrics sm ON sm.service_id = s.id`
  - `MemberPortal/Controllers/Dashboard.php` (line 311)
- Selected fields:
  - `s.id AS service_id`
  - `s.time AS service_time`
  - `COALESCE(sm.reported_present_count, 0) AS present_count`
  - `COALESCE(sm.reported_communion_count, 0) AS communion_count`

- Raw-series builder (no ASA/ASC averaging for parish chart):
  - `buildParishSundayRawSeriesFromServiceRows(...)`
  - `MemberPortal/Controllers/Dashboard.php` (line 433)

Additional source evidence in same controller file:

- `JOIN service_metrics sm ON sm.service_id = s.id`
  - `MemberPortal/Controllers/Dashboard.php` (line 266)

### No attendance-table read in parish chart query

The parish chart query itself does not read from `attendance` table; it consumes service metrics values.

## Live DB Evidence

### A) Services + metrics for 2026-03-01 (parish 1443)

Two Sunday services exist and both have service metrics:

- Service 11 (09:00): present=5, communion=0
- Service 12 (17:00): present=5, communion=0

### B) Parish-chart-equivalent raw service rows

Query reconstructed to match parish chart logic (`services` + `service_metrics`, one row per Sunday service):

For 2026-03-01:

- Service 11, 09:00: present_count=5, communion_count=0
- Service 12, 17:00: present_count=5, communion_count=0

Interpretation for chart behavior:

- The chart receives two raw points for that date (one per service row).
- No per-date bucket averaging is applied in parish-admin path.

### C) Separation proof (metrics rows vs attendance-derived values)

Parish chart fetch path only reads `services` + `service_metrics`; it does not join `attendance`.

This confirms chart behavior is driven by `service_metrics` rows and remains decoupled from attendance-member table calculations.

## Logic Interpretation

1. Parish dashboard graphic uses service metrics DB values (`reported_present_count`, `reported_communion_count`).
2. Parish chart points are raw Sunday service rows (no ASA/ASC averaging in parish scope).
3. Behavior is consistent with the service-metrics pipeline and separate from member attendance registration statistics.

## Validation Result

PASS: In parish admin environment (non-diocesan), dashboard Sunday attendance graphic reads from service metrics stored in DB and not from attendance member table calculations.
