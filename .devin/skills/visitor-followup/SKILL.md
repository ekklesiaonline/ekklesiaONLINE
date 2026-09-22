---
name: visitor-followup
description: Explain the visitor follow-up engine — sequences, actions, scheduling, dispatch, call tasks, and cron
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Explain or work with the visitor follow-up engine. Canonical doc:
`docs/FOLLOWUP_WORKFLOW_SYSTEM.md`.

## Architecture

- `followup_sequence` (parish-scoped or global `parish_id IS NULL`, `is_active`)
  → `followup_action` (`sequence_id`, `day_offset`, `channel`, `template_id`,
  `is_automated`) → `followup_log` rows per person.
- Channels: email (scoped SMTP via `EmailScopeService`), text (`sms_queue` →
  Twilio/log_only), call (`call_task` volunteer queue), mail (Dompdf PDF),
  gift (manual flag).
- Dispatch: `php spark followup:send [--parish N] [--dry-run]` →
  `FollowupLogModel::getPendingDue()` → `FollowupDispatchService`.
- Reconcile missing schedules: `php spark followup:backfill [--parish N] [--dry-run]`.

## Single scheduling point

`PersonVisitModel::afterInsert` → `scheduleFollowups()` fires on
`visit_type='first'` inserts and calls
`FollowupLogModel::scheduleActiveSequencesForPerson($personId, $parishId, $visitDate)`.
Callers must not call scheduling explicitly — the hook dedupes by `action_id`.
CI4 `afterInsert` payload is `['id', 'data' => [row], 'result']` — read
`$data['data']['visit_type']`, not `$data['visit_type']`.

## Fixed schema — no probing

`FollowupLogModel` assumes canonical columns: `scheduled_date` (the retired
`followup_date` dual-write column is dropped), `parish_id`, `action_id`,
`is_active` on `followup_sequence`. `call_task.log_id` is always set —
`BackfillCallTaskLogIds` reconciled legacy orphans.

## Scope rules

- Mutations (`triggerFollowupAction`, `logMarkDone`, `logReschedule`,
  `logSkip`, `logMarkFailed`) require `visitorAffiliationExists()` — the
  affiliation must be `visitor`/`regular_attender`.
- Member promotion marks pending logs `skipped` and sets `followed_up = 1`.
- `followed_up` is a manual staff assertion — never auto-derive it from logs.
- Badge states: Converted → Followed Up → In Progress → Pending Follow-up
  (evaluated in that order).
- Permissions: `followup.view` read-only, `followup.manage` mutations;
  sequence/template admin lives under `/followup-workflow/*`.
