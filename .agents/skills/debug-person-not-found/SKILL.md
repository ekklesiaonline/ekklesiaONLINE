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
4. Check canonical affiliation: verify a `parish_affiliation` row exists for `parish_id` matching `pa.person_id = profile.person_id` or `pa.linked_user_id = profile.user_id` where `deleted_at IS NULL`.
5. If `profile.person_id` is missing or unlinked, `People::view()` automatically calls `ensureProfilePersonLink()` to repair it on the fly, or you can run:
   ```bash
   php spark person:link-profiles
   php spark person:diagnose
   ```
6. Verify downstream services (`PersonMembershipDetailsService`, `PersonOnboardingService`, `PersonStatusHistoryService`) receive `profile.person_id`, not the route `id`.

Report the root cause (missing profile row, missing parish affiliation, or cross-parish access attempt) and the recommended fix.