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
- Exactly one **default sequence** per parish is scheduled automatically;
  non-default sequences are available for explicit assignment only.
- A system/global default sequence is seeded on install and cloned into
  every new parish; existing parishes can be backfilled with
  `php spark followup:apply-default`.
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
      │   → resolves the parish's active default sequence
      │   → one pending followup_log row per action in that sequence
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
| `followup_sequence` | `parish_id` (null = global), `name`, `is_active`, `is_default`, `is_global_default` |
| `followup_action` | `sequence_id`, `week_number`, `day_offset`, `channel`, `template_id`, `assigned_to`, `instructions` |
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
- `manual` actions always produce a human task; the editor hides the
  template picker and requires an assignee and instructions.

## 4. Models & services

| Class | Role |
| --- | --- |
| `FollowupLogModel` | Log CRUD, `getPendingDue*`, `scheduleActiveSequencesForPerson`, `scheduleSequenceForPerson`, status transitions |
| `FollowupSequenceModel` / `FollowupActionModel` / `FollowupTemplateModel` | Sequence/action/template CRUD |
| `PersonVisitModel` | Visit recording; `afterInsert` schedules first-visit follow-ups |
| `CallTaskModel` | Manual call-task queue |
| `VisitorFollowupService` | Read-side: progress badges, next pending, timeline, dashboard aggregates |
| `FollowupDispatchService` | Channel dispatch for a single log row |

## 5. Default-sequence resolution

First-visit scheduling now uses **one** effective default sequence per parish,
not every active sequence. Resolution order:

1. **Parish default** — `followup_sequence` row with `parish_id = <parish>`,
   `is_active = 1`, and `is_default = 1`.
2. If no parish default exists, the intake/dispatcher records a warning and
   schedules nothing. The parish dashboard and visitor intake surfaces this.

The global default sequence (`parish_id IS NULL`, `is_global_default = 1`)
serves as the **template** for new parishes: it is cloned into a parish when
that parish is created, and the copy is marked as the parish default. Existing
parishes can be backfilled with `php spark followup:apply-default`.

Non-default sequences remain available for **explicit manual assignment**
through `POST /visitors/assign-sequence/{personId}`.

### Upstream scopes and the clone cascade

A `followup_sequence` / `followup_template` row is scoped by whichever of
`parish_id`, `diocese_id` is set; both NULL means the row is **global**.
Provinces are part of the organizational hierarchy but are **not** a
follow-up scope. Upstream rows are *templates*, not operational
workflows — a parish gets its own editable copy via cloning:

- `call`/`manual` steps on upstream sequences carry `assigned_to = NULL`
  (there is no parish leader pool upstream); `FollowupCloneService`
  resolves the assignee to the parish's primary admin at clone time,
  leaving it unassigned when no primary admin exists.
- `php spark followup:apply-default` (and parish creation) resolves the
  best upstream default per parish — **diocesan default → global
  default** — via
  `FollowupSequenceService::resolveUpstreamDefaultForParish()`, then clones
  it with `FollowupCloneService::cloneSequenceIntoParish()`.
- Diocesan admins seed their diocesan default by cloning the global
  default (`POST /diocese/followup-workflow/sequences/clone/{id}`) or by
  building their own; additional diocesan sequences appear in parish
  lists as clonable suggestions.
- Parishes can also clone any visible upstream sequence on demand via
  `POST /followup-workflow/sequences/clone/{id}`; the parish sequences
  list hides upstream suggestions by default — the filter checkboxes
  `upstream_global` / `upstream_diocese` opt each group in.

### Clone lineage and propagation

Clones record their source (`source_sequence_id`, `source_action_id`,
`source_template_id`). When an upstream record is edited,
`FollowupPropagationService` pushes the change into every parish clone:

- **Updated** upstream action/template/sequence → non-customized clones are
  updated in place (`assigned_to` is parish-local and never overwritten).
- **Created** upstream action → inserted into each clone sequence; its
  template is cloned on demand when the parish lacks a copy.
- **Deleted** upstream action/template → non-customized clones are removed;
  customized clones are orphaned (`source_*_id` cleared) rather than
  deleted.
- A clone that the parish has edited is flagged `is_customized = 1` and is
  skipped by all subsequent propagation.
- Deleting an upstream *sequence* does not propagate — clones' lineage is
  cleared and they become standalone parish sequences.
- Propagation does not reschedule existing `followup_log` rows; pending
  logs keep their `scheduled_date` snapshot, and template content resolves
  at dispatch time.

### Admin UIs

- **System admins** manage global records under
  `/system/followup-workflow/*` (`system.admin`). Reachable from the
  sidebar ("Follow-up Workflows") and from the Visitors card on
  `/system/settings`.
- **Diocesan admins** manage their diocese's records under
  `/diocese/followup-workflow/*` (`diocese.admin`), scoped via
  `BaseController::resolveDiocesanScopeId()` and reachable from the
  diocesan sidebar. The diocesan sequences list also shows global rows
  read-only so they can be cloned into the diocese.
- Both share `App\Controllers\UpstreamFollowupWorkflow` and the
  parameterized `Clergy\Views\System\FollowupWorkflow\*` views.
- Each scope has one default flag: `is_global_default`,
  `is_diocese_default`.

### Default sequence lifecycle

- A parish can have at most one sequence marked `is_default = 1`. Setting a new
  default clears the flag from any other parish sequence.
- Deactivating a default sequence removes its default flag; the parish may then
  have no default until an admin selects one.
- Changing the default does **not** reschedule visitors who already have pending
  logs. It only affects new first-time visits.

## 5. Scope rules

- Follow-up mutations (`triggerFollowupAction`, `logMarkDone`,
  `logReschedule`, `logSkip`, `logMarkFailed`, `completeCallTask`) verify
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
# clone the global default sequence into parishes that lack a default
php spark followup:apply-default [--parish N] [--dry-run]
```

## 8. Permissions

| Permission | Description |
| --- | --- |
| `followup.manage` | Sequence/action/template CRUD, trigger actions, complete call tasks |
| `followup.view` | Read-only: sequences, templates, call queue, timeline |
| `followup.manage` | Sequence/action/template CRUD, trigger actions, complete call tasks |
| `system.admin` | Manage global (parish_id = NULL) sequences and templates |
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
| `Clergy/Controllers/FollowupWorkflow.php` | Parish sequence/action/template admin, call-task queue |
| `app/Controllers/SystemFollowupWorkflow.php` | System admin UI for global sequences/templates |
| `Clergy/Config/Routes.php` | `/visitors/*` + `/followup-workflow/*` routes |
| `app/Config/Routes.php` | `/system/followup-workflow/*` routes |
