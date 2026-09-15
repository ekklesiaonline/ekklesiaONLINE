---
name: debug-no-parish
description: Diagnose why a logged-in user sees "No parish assigned"
argument-hint: "<user_id_or_email>"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

A user sees "No parish assigned". Follow this checklist from `AGENTS.md`:

1. Identify the user (by id or email). Read `users`, `auth_identities`, and `profiles`.
2. Check for a `person` row for that user.
3. Check for a `user_person` mapping.
4. Check `profile.parish_id` (and `diocese_id` / `province_id`).
5. Check `parish_affiliation` rows (`linked_user_id`, `parish_id`, `person_id`).
6. If the user is the parish admin, check `parish_admin_primary`.
7. Report exactly which link is missing and the fix order:
   - create `person`
   - create `user_person`
   - update `profile` (`person_id`, `parish_id`)
   - insert `parish_affiliation`
   - insert `parish_admin_primary` if applicable
8. After fixing, recommend running:
   ```bash
   php spark parish-people:sync
   ```

Cite the actual rows/status you found. Do not write changes without explicit user confirmation.