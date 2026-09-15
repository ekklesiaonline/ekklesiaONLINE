# Service and Attendance Data Model Report (2026-04-10)

## Scope

This report is generated from the live `ekklesiaRD` schema (`information_schema`) and focuses on:

1. Tables that store service and service-metrics information.
2. Tables used to store people or members attendance-related information.

## Count Summary

Service and service-metrics tables: 10.

People or members attendance-related tables: 6.

## Service and Service-Metrics Tables (10)

| table_name | fields | relationships |
| --- | --- | --- |
| services | id, parish_id, date, liturgical_day, service, place, time, preacher, celebrant, notes, created_at, updated_at | parish_id -> parishes.id |
| service_metrics | service_id, format_of_service, attendance_classification, service_family, service_schedule, reported_present_count, reported_communion_count, online_live_views, online_recorded_views_7d, online_peak_viewers, languages_json, place_labels_json, created_at, updated_at | service_id -> services.id |
| service_people | id, service_id, person_name, role_name, sort_order, created_at, updated_at | service_id -> services.id |
| service_sacramental_counts | service_id, baptism_adult, baptism_child, confirmation_adult, confirmation_teen, reception_count, created_at, updated_at | service_id -> services.id |
| service_worship_items | id, service_id, parish_id, song_id, item_type, title, leader_name, notes, sort_order, created_at, updated_at | service_id -> services.id; parish_id -> parishes.id; song_id -> worship_songs.id |
| diocesan_attendance_monthly | id, diocese_id, parish_id, period_month, sunday_services_count, sunday_attended_total, sunday_attendance_average, weekday_services_count, weekday_attended_total, weekday_attendance_average, source_last_service_date, generated_at, created_at, updated_at | diocese_id -> dioceses.id; parish_id -> parishes.id |
| diocesan_sunday_service_metrics | id, diocese_id, parish_id, service_id, service_date, present_count, communion_count, generated_at, created_at, updated_at | diocese_id -> dioceses.id; parish_id -> parishes.id; service_id -> services.id |
| diocesan_sunday_weekly_metrics | id, diocese_id, period_start, sunday_services_count, sunday_attended_total, sunday_communion_total, generated_at, created_at, updated_at | diocese_id -> dioceses.id |
| diocesan_sunday_monthly_metrics | id, diocese_id, period_month, sunday_services_count, sunday_attended_total, sunday_communion_total, generated_at, created_at, updated_at | diocese_id -> dioceses.id |
| attendance | id, service_id, user_id, attended, communicant, created_at | service_id -> services.id; user_id -> users.id |

## People or Members Attendance-Related Tables (6)

These are the tables directly used to represent member attendance and attendance metrics over time:

| table_name | why it is attendance-related | fields | relationships |
| --- | --- | --- | --- |
| attendance | Canonical per-member attendance row (`attended`, `communicant`) per service. | id, service_id, user_id, attended, communicant, created_at | service_id -> services.id; user_id -> users.id |
| services | Service occurrence context for attendance rows and metric rollups. | id, parish_id, date, liturgical_day, service, place, time, preacher, celebrant, notes, created_at, updated_at | parish_id -> parishes.id |
| service_metrics | Per-service reported attendance and communion counts used by charts/reports. | service_id, format_of_service, attendance_classification, service_family, service_schedule, reported_present_count, reported_communion_count, online_live_views, online_recorded_views_7d, online_peak_viewers, languages_json, place_labels_json, created_at, updated_at | service_id -> services.id |
| diocesan_sunday_service_metrics | Service-level diocesan rollup of attendance and communion counts. | id, diocese_id, parish_id, service_id, service_date, present_count, communion_count, generated_at, created_at, updated_at | diocese_id -> dioceses.id; parish_id -> parishes.id; service_id -> services.id |
| diocesan_sunday_weekly_metrics | Weekly diocesan aggregate for Sunday attendance and communion. | id, diocese_id, period_start, sunday_services_count, sunday_attended_total, sunday_communion_total, generated_at, created_at, updated_at | diocese_id -> dioceses.id |
| diocesan_sunday_monthly_metrics | Monthly diocesan aggregate for Sunday attendance and communion. | id, diocese_id, period_month, sunday_services_count, sunday_attended_total, sunday_communion_total, generated_at, created_at, updated_at | diocese_id -> dioceses.id |

## Relationship Notes

`attendance` is the only per-member attendance fact table. It links each member (`user_id`) to a specific service (`service_id`).

`service_metrics` stores per-service count metrics and is linked one-to-one or one-to-many by `service_id` depending on application behavior.

Diocesan tables are aggregate layers (`diocesan_sunday_service_metrics`, `diocesan_sunday_weekly_metrics`, `diocesan_sunday_monthly_metrics`, and `diocesan_attendance_monthly`) derived from service-level data for reporting and dashboards.

## Notes

This report reflects schema state on 2026-04-10.
