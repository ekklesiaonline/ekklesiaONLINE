---
name: worship-planning
description: Explain service creation, worship planning, song repertoire management, service order items, and attendance reporting
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

# Worship Planning & Service Management

Help with liturgical service creation, worship planning, hymns/songs, and attendance tracking.

## Key Sources

- Controllers:
  - `Worship\Controllers\Services` (Service CRUD, liturgical metadata, schedules)
  - `Worship\Controllers\WorshipPlanning` (Order of service, hymns/songs, participant assignments)
  - `Worship\Controllers\Attendance` (Service attendance check-in and counts)
  - `Parish\Controllers\WorshipDashboard` (Parish worship dashboard)
- Models:
  - `App\Models\ServiceModel` (Table `service`)
  - `App\Models\ServiceMetricsModel` (Table `service_metric`)
  - `App\Models\WorshipSongModel` (Hymns/songs library)
  - `App\Models\ServiceWorshipItemModel` (Service order items)
  - `App\Models\ServicePeopleModel` (Liturgical participant assignments)
- Commands:
  - `php spark attendance:sync-sunday-service-metrics`
  - `php spark service-metrics:refresh`
- Routes:
  - `Worship/Config/Routes.php` (`/services/*`, `/worship/*`)
  - `Parish/Config/Routes.php` (`/parish/worship`)

## Roles & Dashboards

- Shield groups: `clergy` and `worship_coordinator` land on `/parish/worship`.
- Volunteers: `worship_volunteer` views scheduled duties from the personal `/me` portal.
- Permissions: `service.view`, `service.manage`, `attendance.view`, `attendance.record`.

## Database Schema Invariants

- Canonical service table is `service` (singular).
- Metric tracking table is `service_metric` (singular); `service_metrics` is a read-only compatibility SQL VIEW.
