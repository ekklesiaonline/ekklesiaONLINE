---
name: sacrament-sync
description: Reconcile sacramental records with derived flags and profile dates
argument-hint: "[--dry-run] [--parish=<id>]"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

Reconcile sacramental records with derived membership/profile flags.

1. Verify the canonical source of truth is `sacramental_record`; derived targets are:
   - `person_membership_details.is_baptized` / `is_confirmed`
   - `profile.baptism_date` / `profile.confirmation_date` / `profile.is_confirmed`
2. Run the reconciliation command:
   ```bash
   php spark sacraments:reconcile --dry-run
   # or for a specific parish:
   php spark sacraments:reconcile --parish=<parish_id>
   ```
3. If discrepancies are found, review `SacramentFlagSyncService::discrepanciesForPerson()`.
4. Confirm `LOWER()` is used for `sacrament_type` comparisons in any new queries.
5. Remind the user that `is_communicant` is attendance-based, not sacrament-based.

Report the command output and recommended next step.