---
name: vestry-governance
description: Explain or work with vestry meetings, motions, and votes
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with vestry governance features.

Key facts:
- Shield groups: `vestry_member`, `warden`, `vestry_secretary` (the clerk certification role is granted via the `governance.report.certify_clerk` permission to `vestry_secretary`).
- Tables: `vestry_meeting`, `vestry_motion`, `vestry_vote` (migration `2026_08_24_000100_CreateVestryTables`).
- Controller: `Clergy\Controllers\Vestry` handles CRUD + voting at `/vestry/*`.
- Dashboard: `/parish/governance`.

When asked a question:
1. Search the controller, models, views, and migration.
2. Explain the data model and permission checks.
3. Cite specific files and line ranges.
4. Note any integration with `Parish` dashboards or registration flows.
