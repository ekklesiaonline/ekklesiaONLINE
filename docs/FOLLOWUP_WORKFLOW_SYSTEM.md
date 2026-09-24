# Visitor Follow-up Workflow System

**Status**: Canonical — rewritten for the visitor-domain architecture.
**Module**: `Clergy` namespace.
**Tables**: `followup_sequence`, `followup_action`, `followup_template`,
`followup_log`, `call_task`, `sms_queue`, `person_visit`.

> Supersedes the guest-era version of this document. The retired `guests`,
> `guest_visits`, and `guest_followup_log` tables no longer exist; follow-up
> operates on `parish_affiliation` rows at `visitor`/`regular_attender`
> status with `person_visit` as the visit history.

---

## 1. Overview

The follow-up workflow automates and tracks outreach to first-time visitors
over a configurable multi-week cadence.

- Named *sequences* (e.g., "6-Week First-Time Visitor Journey") with ordered *actions*.
- Six channels: **email**, **text/SMS**, **call** (human task), **mail** (PDF), **gift** (manual), **manual** (assigned leadership task).
- Reusable *templates* with `{merge_tag}` variable replacement.
- Automatic scheduling on a person's **first** visit via the
  `PersonVisitModel::afterInsert` hook — the single scheduling point.
- `php spark followup:send` cron dispatch; `followup:backfill` for reconciliation.

## 2. Architecture

```
Visitor intake (staff form / CSV / public card / update)
      │
      ▼
PersonVisitModel::insert(visit_type='first')
      │
      ▼  afterInsert → scheduleFollowups()
FollowupLogModel::scheduleActiveSequencesForPerson(person_id, parish_id, visit_date)
      │   → one pending followup_log row per action in each active sequence
      ▼
[cron] php spark followup:send
      │
      ▼
FollowupLogModel::getPendingDue($today)
      │
      ▼
FollowupDispatchService::dispatch()
      ├─ email → CI4 email service (scoped SMTP via EmailScopeService)
      ├─ text  → SmsService → Twilio (or log_only stub) via sms_queue
      ├─ call  → call_task row for a volunteer (task_type='call', always carries log_id)
      ├─ manual → call_task row assigned to a parish leader (task_type='manual', title = instructions with tokens applied)
      ├─ mail  → Dompdf PDF / HTML in writable/mail_queue/
      └─ gift  → metadata flag (manual task)
```

### call_task ⇄ followup_log sync (CallTaskSyncService)

`call_task` is the operational projection of the human-channel logs
(`call` and `manual`); `followup_log` stays canonical. The sync rules:

- **Schedule time** — `FollowupLogModel::scheduleSequenceForPerson` /
  `scheduleActiveSequencesForPerson` materialize a `pending` task for each
  new call/manual log, so the queue tracks pending *and* overdue work even
  before the cron runs. `materializeForLog` dedupes on `log_id`, so
  dispatch never creates a second row (it returns `existing => true`).
- **Manual resolutions** — `logMarkDone` → task `completed`; `logSkip` /
  conversion / visitor-delete → task `cancelled`; `logReschedule` moves
  `scheduled_date` on a still-pending task; `manual_trigger` resolves to
  `completed`. When a log was resolved before a task existed, a terminal
  task is created so completed/skipped calls appear in queue history.
- **Task completion** — completing a task via
  `POST /followup-workflow/tasks/complete/{id}` also executes a still
  `pending` or `failed` linked log (early completion / retry).
- **Backfill** — `BackfillHumanChannelTasks` materializes tasks for
  pre-existing logs: `pending→pending` (live affiliation only),
  `sent→completed`, `skipped→cancelled`. `failed` gets no task.

## 3. Canonical schema (fixed — no probing)

`FollowupLogModel` assumes the canonical schema; it does not probe for
legacy variants (`followup_sequences`, `followup_actions`, `followup_date`,
`active` vs `is_active`).

| Table | Key columns |
| --- | --- |
| `followup_sequence` | `parish_id` (null = global), `name`, `is_active` |
| `followup_action` | `sequence_id`, `week_number`, `day_offset`, `channel`, `template_id`, `assigned_to`, `instructions`, `is_automated` |
| `followup_template` | `parish_id`, `channel`, `name`, `subject`, `body_text`, `body_html`, `variables` |
| `followup_log` | `person_id`, `parish_id`, `action_id`, `assigned_to`, `scheduled_date`, `executed_at`, `status`, `notes`, `response_metadata` |
| `call_task` | `person_id`, `log_id` (always set), `assigned_to`, `task_type` (`call`\|`manual`), `title`, `phone`, `status`, `scheduled_date`, `completed_at` |

`followup_log.scheduled_date` is the only schedule column — the legacy
`followup_date` dual-write column was dropped
(`DropFollowupLogFollowupDate`). Orphaned `call_task` rows were reconciled
by `BackfillCallTaskLogIds`, which synthesized `followup_log` rows and
linked them.

`call_task` is the generic volunteer-task store: `task_type` discriminates
`call` (phone outreach) from `manual` (any other human action).

### Manual channel (assigned leadership tasks)

A `manual` step produces a task for a specific parish leader instead of
contacting the visitor — e.g., "Send {first_name} a social-media
invitation".

- `followup_action.assigned_to` holds the leader's `users.id`;
  `followup_action.instructions` is the task text (template tokens are
  applied at dispatch into `call_task.title`).
- The step editor's assignee picker lists
  `LeadershipPositionModel::getAssignableParishLeaders($parishId)` — active
  `leadership_position` rows scoped to the parish whose
  `started_at`/`ended_at` window (when defined) covers today.
  `saveAction` re-validates the same rule server-side
  (`isActiveParishLeader`), so arbitrary user IDs are rejected.
- Scheduling copies `action.assigned_to` → `followup_log.assigned_to` and
  materializes the `call_task` row (`task_type='manual'`, `assigned_to`,
  `title`, `log_id`); `dispatchManual` reuses it (dedupe on `log_id`) or
  inserts when missing. Nothing is sent to the visitor.
- The queue at `/followup-workflow/tasks` lists both types with a
  type badge, assignee column, and a `?type=call|manual` filter; the same
  complete/cancel flow applies.
- `is_automated` is meaningless for `manual` — the channel is inherently
  human; the editor hides the flag and the template picker.

## 4. Models & services

| Class | Role |
| --- | --- |
| `FollowupLogModel` | Log CRUD, `getPendingDue*`, `scheduleActiveSequencesForPerson`, `scheduleSequenceForPerson`, status transitions |
| `FollowupSequenceModel` / `FollowupActionModel` / `FollowupTemplateModel` | Sequence/action/template CRUD |
| `PersonVisitModel` | Visit recording; `afterInsert` schedules first-visit follow-ups |
| `CallTaskModel` | Manual call-task queue |
| `VisitorFollowupService` | Read-side: progress badges, next pending, timeline, dashboard aggregates |
| `FollowupDispatchService` | Channel dispatch for a single log row |

## 5. Scope rules

- Follow-up mutations (`triggerFollowupAction`, `logMarkDone`,
  `logReschedule`, `logSkip`, `logMarkFailed`) verify
  `VisitorDirectoryService::visitorAffiliationExists()` — the person's
  affiliation must be `visitor` or `regular_attender`. Members and exited
  rows cannot be mutated through visitor endpoints.
- Scheduling dedupes by `action_id` per person — re-running never
  double-books.
- On member promotion, `PersonConversionService` marks pending log rows
  `skipped` and stamps `followed_up = 1`.

## 6. Visitor badge states

See `docs/VISITOR_LIFECYCLE.md` §3 — Converted / Followed Up / In Progress /
Pending Follow-up, evaluated in that order.

## 7. Cron

```bash
0 9 * * * php /path/to/spark followup:send >> /path/to/writable/logs/followup.log 2>&1
# per-parish or dry-run
php spark followup:send --parish 5
php spark followup:send --dry-run
# reconcile visitors missing scheduled rows
php spark followup:backfill [--parish N] [--dry-run]
```

## 8. Permissions

| Permission | Description |
| --- | --- |
| `followup.manage` | Sequence/action/template CRUD, trigger actions, complete call tasks |
| `followup.view` | Read-only: sequences, templates, call queue, timeline |
| `visitor.edit` | Promote/demote/member-promotion endpoints |

## 9. Digital connection card (`/connect/{token}`)

Public unauthenticated intake. `GET /connect/{token}` renders
`app/Views/public/connection_card.php`; `POST` is handled by
`Clergy\Controllers\Import::digitalCard()` → `VisitorIntakeService::record()`
(dedup by email/phone hash) → `person_visit` insert fires scheduling.

## 10. Key files

| File | Purpose |
| --- | --- |
| `app/Commands/SendFollowups.php` | `followup:send` dispatcher |
| `app/Commands/BackfillFollowups.php` | `followup:backfill` reconciliation |
| `app/Services/FollowupDispatchService.php` | Channel dispatch |
| `app/Services/VisitorFollowupService.php` | Read-side queries |
| `app/Models/FollowupLogModel.php` | Log CRUD + scheduling |
| `app/Models/PersonVisitModel.php` | Visit recording + auto-schedule hook |
| `Clergy/Controllers/Visitors.php` | Directory + follow-up AJAX endpoints |
| `Clergy/Controllers/FollowupWorkflow.php` | Sequence/action/template admin, call-task queue |
| `Clergy/Config/Routes.php` | `/visitors/*` + `/followup-workflow/*` routes |
