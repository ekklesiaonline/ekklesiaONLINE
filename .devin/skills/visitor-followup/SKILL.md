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

- `followup_sequence` (scoped by `parish_id` / `diocese_id`;
  both NULL = global; flags `is_active`, `is_default`,
  `is_{global,diocese}_default`) → `followup_action`
  (`sequence_id`, `day_offset`, `channel`, `template_id`, `assigned_to`,
  `instructions`) → `followup_log` rows per person.
- Clone lineage: parish copies carry `source_sequence_id`,
  `source_action_id`, `source_template_id`, and `is_customized`.
- Channels: email (scoped SMTP via `EmailScopeService`), text (`sms_queue` →
  Twilio/log_only), call (`call_task` queue, `task_type='call'`), manual
  (`call_task` row `task_type='manual'` assigned to a parish leader via
  `leadership_position`; `instructions` → `call_task.title` with tokens),
  mail (Dompdf PDF), gift (manual flag).
- Dispatch: `php spark followup:send [--parish N] [--dry-run]` →
  `FollowupLogModel::getPendingDue()` → `FollowupDispatchService`.
- Reconcile missing schedules: `php spark followup:backfill [--parish N] [--dry-run]`.
- Clone the seeded global default into parishes without a default:
  `php spark followup:apply-default [--parish N] [--dry-run]`.
- `call_task` is materialized at schedule time for call/manual logs (not
  just at dispatch) via `CallTaskSyncService` — the queue therefore shows
  pending/overdue work immediately; dispatch dedupes on `log_id`. Manual
  resolutions (mark done/skip/trigger-now) transition or create the task
  in a terminal state, conversion/delete cancels pending tasks, and
  completing a task resolves its pending/failed log.

## Single scheduling point

`PersonVisitModel::afterInsert` → `scheduleFollowups()` fires on
`visit_type='first'` inserts and calls
`FollowupLogModel::scheduleActiveSequencesForPerson($personId, $parishId, $visitDate)`.
The model resolves the parish's active default sequence (`is_default = 1`).
If no default exists, no rows are scheduled and intake surfaces a warning.
Callers must not call scheduling explicitly — the hook dedupes by `action_id`.
CI4 `afterInsert` payload is `['id', 'data' => [row], 'result']` — read
`$data['data']['visit_type']`, not `$data['visit_type']`.

## Fixed schema — no probing

`FollowupLogModel` assumes canonical columns: `scheduled_date` (the retired
`followup_date` dual-write column is dropped), `parish_id`, `action_id`,
`is_active` on `followup_sequence`. `call_task.log_id` is always set —
`BackfillCallTaskLogIds` reconciled legacy orphans.

Manual-channel assignees are validated by
`LeadershipPositionModel::getAssignableParishLeaders`/`isActiveParishLeader`
— parish-scoped `active=1` positions inside their `started_at`/`ended_at`
window. Scheduling stamps `followup_action.assigned_to` onto
`followup_log.assigned_to`.

## Scope rules

- Mutations (`triggerFollowupAction`, `logMarkDone`, `logReschedule`,
  `logSkip`, `logMarkFailed`, `completeCallTask`) require
  `visitorAffiliationExists()` — the affiliation must be
  `visitor`/`regular_attender`.
- Member promotion marks pending logs `skipped` and sets `followed_up = 1`.
- `followed_up` is a manual staff assertion — never auto-derive it from logs.
- Badge states: Converted → Followed Up → In Progress → Pending Follow-up
  (evaluated in that order).
- Permissions: `followup.view` read-only, `followup.manage` mutations;
  sequence/template admin lives under `/followup-workflow/*`.
- Upstream (global/diocese) sequences are read-only at the parish level;
  provinces are not a follow-up scope. System admins manage global rows
  under `/system/followup-workflow/*` (sidebar "Follow-up Workflows" and
  the Visitors card on `/system/settings`); diocesan admins manage their
  own diocese's rows under `/diocese/followup-workflow/*`
  (`diocese.admin`, diocesan sidebar) and can clone the global default
  into the diocese. Both extend
  `App\Controllers\UpstreamFollowupWorkflow`.
- Upstream `call`/`manual` steps store `assigned_to = NULL`;
  `FollowupCloneService` resolves it to the parish's primary admin at clone
  time (NULL when no primary admin exists).
- Default resolution cascades diocese → global via
  `FollowupSequenceService::resolveUpstreamDefaultForParish()`; parish
  creation and `followup:apply-default` clone the resolved upstream
  sequence, and parishes can clone manually via
  `POST /followup-workflow/sequences/clone/{id}`. The parish sequences
  list hides upstream suggestions by default; `upstream_global=1` /
  `upstream_diocese=1` checkboxes opt each group in. Propagation is
  recursive: a global edit flows global → diocesan clone → parish clones.
- `POST /visitors/assign-sequence/{personId}` never schedules against an
  upstream row directly: an in-hierarchy global/diocesan sequence is first
  cloned into the parish (`cloneSequenceIntoParish`), and cloning is
  idempotent per scope — re-selecting the same source reuses the existing
  clone. Out-of-hierarchy sequences are rejected (404).
- Upstream edits propagate into parish clones via
  `FollowupPropagationService`: non-customized clones update in place,
  customized clones (`is_customized = 1`, set on any parish-local edit) are
  skipped, upstream deletes orphan customized clones instead of deleting
  them. Upstream sequence deletion clears clone lineage only.
