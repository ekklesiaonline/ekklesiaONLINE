---
name: person-member-merge
description: Explain person and member deduplication, previewing candidate matches, executing transactional merges, and relational reparenting
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

# Person & Member Deduplication and Merging

Help with detecting duplicate people or members, previewing merge impacts, and safely reparenting relational records.

## Key Sources

- Controllers:
  - `Clergy\Controllers\PersonMerge` (`/people/merge`, `/people/merge/preview`, `/people/merge/execute`)
  - `Clergy\Controllers\MemberMerge` (`/members/dedup`, `/members/dedup/preview`, `/members/dedup/merge`)
- Services:
  - `App\Services\PersonMergeService` (Canonical person merge and record reparenting)
  - `App\Services\MemberMergeService` (User/portal member reconciliation)
  - `App\Services\PersonCleanupService` (Cleaning unreferenced orphans)
- Permissions: `member.delete` (required for merge execution)
- Routes: `Clergy/Config/Routes.php`

## Merge Safety & Relational Reparenting

1. **Preview Before Mutation**:
   - Always run the preview endpoint first to inspect records to be transferred and identify any conflicting non-reparentable fields.
2. **Transaction Isolation**:
   - Merge operations run inside a strict database transaction (`$this->db->transBegin()`).
3. **Reparented Records**:
   - Sacramental records and participant entries (`sacramental_record`, `sacramental_record_participant`)
   - Affiliations (`parish_affiliation`)
   - Follow-up logs and tasks (`followup_log`, `call_task`)
   - Visits (`person_visit`)
   - Notes (`person_note`)
   - Household / family memberships (`family_member`)
   - Financial donations and annual pledges
4. **Audit Trail**:
   - Logged via `AuditService` under `person.merge` / `member.merge`, recording survivor ID, absorbed ID, and reparented record counts.
