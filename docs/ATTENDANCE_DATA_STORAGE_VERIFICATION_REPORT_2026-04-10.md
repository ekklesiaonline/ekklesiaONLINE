# Attendance Data Storage Verification Report (2026-04-10)

## Objective

Verify where attendance data is stored in the live `ekklesiaONLINE` database and where it is written/read in application code.

## Verification Method

1. Live schema inspection via MySQL `information_schema`.
2. Live table row-count checks.
3. Code-path inspection for writes and reads.

## Executive Conclusion

Canonical member attendance facts are stored in the `attendance` table.

Sunday attendance dashboard/chart values are read from `service_metrics` (`reported_present_count`, `reported_communion_count`) joined to `services`.

Member attendance registration is decoupled from `service_metrics` updates.

## Live Tables Confirmed

Attendance-related storage/aggregate tables present in schema:

- `attendance`
- `service_metrics`
- `services`
- `diocesan_sunday_service_metrics`
- `diocesan_sunday_weekly_metrics`
- `diocesan_sunday_monthly_metrics`
- `diocesan_attendance_monthly`

## Core Storage Structures

### 1) attendance (canonical per-member fact)

Fields verified:

- `id` (PK)
- `service_id`
- `user_id`
- `attended` (`tinyint(1)`, `NOT NULL`, default `0`)
- `communicant` (`tinyint(1)`, `NOT NULL`, default `0`)
- `created_at`

Uniqueness/integrity:

- Unique index exists on (`service_id`, `user_id`): `uq_attendance_service_user`
- This prevents duplicate attendance rows per member per service.

### 2) service_metrics (service-level counts used for charts/reports)

Relevant fields verified:

- `service_id` (PK)
- `reported_present_count`
- `reported_communion_count`
- plus metadata fields (`format_of_service`, `attendance_classification`, etc.)

### 3) services (service context)

Relevant fields:

- `id`
- `parish_id`
- `date`
- `service`
- plus liturgical and schedule metadata.

## Current Live Row Counts

As verified on 2026-04-10:

- `attendance`: 107
- `service_metrics`: 25
- `services`: 27
- `diocesan_sunday_service_metrics`: 15
- `diocesan_sunday_weekly_metrics`: 14
- `diocesan_sunday_monthly_metrics`: 4
- `diocesan_attendance_monthly`: 4

## Application Write Paths

### Attendance writes

In `app/Controllers/AttendanceController.php`:

- Check-in writes via `recordAttendance(...)`.
- Bulk writes via `recordAttendance(...)` in loop.
- Update writes via `recordAttendance(...)`.
- Delete removes attendance row.

### Separation note

`AttendanceModel::syncServiceMetrics(...)` exists as a separate routine, but registration controller endpoints no longer call it.

Interpretation:

- Member-level source of truth: `attendance`.
- Service-level chart/report counts: `service_metrics`, maintained by separate process(es), not by member registration write path.

## Application Read Paths

### Recent attendance list reads

- `AttendanceModel::getRecentForService(...)` used by manual UI endpoint in `AttendanceController::recent(...)`.

### Sunday dashboard chart reads

In `MemberPortal/Controllers/Dashboard.php`:

- Reads `COALESCE(sm.reported_present_count, 0)` and `COALESCE(sm.reported_communion_count, 0)`.
- Uses `JOIN service_metrics sm ON sm.service_id = s.id` (no attendance-table chart read).
- Verified query blocks around lines 262-266 and 308-311.

Interpretation:

- Dashboard Sunday attendance chart uses service-level metrics (`service_metrics`) joined with `services`.

## Final Verification Statement

Attendance storage is split as follows:

1. `attendance`: canonical per-member attendance facts.
2. `service_metrics`: service-level attendance totals for dashboards/reports.
3. Dashboard Sunday chart: reads from `service_metrics` + `services`.

This is consistent with the enforced separation between member registration and service metrics processing.
