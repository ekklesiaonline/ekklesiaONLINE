---
description: Sacramental record source-of-truth and flag-sync rules
trigger: glob
globs:
  - "**/Sacrament*"
  - "**/sacrament*"
---

# Sacramental Records

## Sacramental Records

- `sacramental_record` is the canonical source of truth for baptism/confirmation.
- `person_membership_details.is_baptized` / `is_confirmed` and `profile.baptism_date` / `confirmation_date` / `is_confirmed` are derived.
- After every sacrament CRUD operation, call `SacramentFlagSyncService`.
- Sacrament type comparisons must use `LOWER()` because the dropdown emits lowercase but the column is free-text.
- `is_communicant` is attendance-based (`member_standing_summary.communion_count >= 3`), not derived from sacramental records.

- For sacrament create/update/repair procedures, use the `/sacrament-sync` skill.
