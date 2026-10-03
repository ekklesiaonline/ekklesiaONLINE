---
name: liturgical-calendar
description: Explain liturgical calendar source data, seasons, feasts, and service metadata
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with the liturgical calendar and service metadata.

Key sources:
- `Diocese/Controllers/LiturgicalCalendar.php`
- System routes under `/system/liturgical-source/*`
- Service migration lineage: `docs/SERVICE_MIGRATIONS.md`
- Liturgical day/season fields on `service` table

When asked:
1. Explain how liturgical days/seasons are sourced and stored.
2. Reference the `LiturgicalCalendar` controller, models, and any calendar tables.
3. Show how service records consume liturgical metadata.
4. Cite specific files and line ranges.
