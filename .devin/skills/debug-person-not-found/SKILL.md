---
name: debug-person-not-found
description: Diagnose "Person not found" on /people/view/{id}
argument-hint: "<profile_id> [parish_id]"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

A request to `/people/view/{id}` returns "Person not found". Investigate:

1. Read `Clergy\Controllers\People::view()` to confirm the lookup logic.
2. Confirm whether the route `id` is `profile_id` (it is).
3. Look up the `profile` row and note `profile.person_id`.
4. Check the `parish_people` snapshot for the current parish and `profile_id`.
5. If the snapshot is stale or missing, run:
   ```bash
   php spark parish-people:sync
   # or
   php spark parish-people:integrity --repair --parish=<parish_id>
   ```
6. Verify downstream services (`PersonMembershipDetailsService`, `PersonOnboardingService`, `PersonStatusHistoryService`) receive `profile.person_id`, not the route `id`.

Report the root cause (stale snapshot vs. missing `profile.person_id`) and the exact command to repair.