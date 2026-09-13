# Service Migration Consolidation

Service-related migrations were consolidated to keep base installs aligned with current runtime behavior.

## Canonical Service Migration Files

- `app/Database/Migrations/2025_01_01_000005_CreateServices.php`
- `app/Database/Migrations/2026_03_07_000101_CreateServiceMetrics.php`
- `app/Database/Migrations/2026_03_07_000102_CreateServicePeople.php`
- `app/Database/Migrations/2026_03_07_000103_CreateServiceSacramentalCounts.php`

## Consolidated Behavior Included in Canonical Files

- `services.service` uses JSON (multi-select service labels model).
- `service_metrics` includes:
    - `service_family`
    - `service_schedule`
- `service_metrics.service_labels_json` is not part of canonical schema.

## Removed Superseded Service Migrations

- `2026_03_08_000001_AddServiceClassificationToMetrics.php`
- `2026_03_08_100000_RefactorServiceTypeToMultiSelect.php`
- `2026_03_09_000001_NullPlaceLabelsJsonInServiceMetrics.php`

These were transitional deltas and are now represented by canonical migration definitions.
