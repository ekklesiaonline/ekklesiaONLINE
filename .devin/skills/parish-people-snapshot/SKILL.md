---
name: parish-people-snapshot
description: Re-sync the parish people snapshot after manual data changes
argument-hint: "[--parish=<id>]"
triggers:
  - user
allowed-tools:
  - read
  - grep
  - exec
---

Re-sync the parish people snapshot after manual changes to `person`, `profile`, `user_person`, or `parish_affiliation`.

1. Run a full snapshot re-sync:
   ```bash
   php spark parish-people:sync
   ```
2. Or repair a single parish:
   ```bash
   php spark parish-people:integrity --repair --parish=<parish_id>
   ```
3. Verify the command output reports no unresolved integrity issues.
4. If errors persist, inspect `parish_affiliation` and `person` rows for the affected parish before re-running.

Report the command output and the next step.