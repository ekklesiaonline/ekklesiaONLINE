---
name: add-member
description: Add a new person/member with all required canonical links
argument-hint: "<user_id_or_email>"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

Add a new person and wire them up correctly to profile, parish, and user.

1. Identify the target user and parish.
2. Create the canonical `person` row if it does not exist.
3. Create `user_person` mapping.
4. Update `profile` with `person_id` and `parish_id` (plus `diocese_id`/`province_id` if known).
5. Insert `parish_affiliation` with `linked_user_id`, `parish_id`, and `person_id`.
6. If the user is the primary parish admin, insert `parish_admin_primary`.
7. Re-sync the parish people snapshot:
   ```bash
   php spark parish-people:sync
   ```

Before writing, show the planned INSERT/UPDATE statements and ask for confirmation. Use transactions where possible.