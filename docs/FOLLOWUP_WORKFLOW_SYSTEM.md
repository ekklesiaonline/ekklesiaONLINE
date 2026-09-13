# Guest Follow-up Workflow System

**Status**: Active — last updated 2026-04-16  
**Module**: `Pastor` namespace  
**Database tables**: `followup_sequences`, `followup_actions`, `followup_templates`, `guest_visits`, `guest_followup_log`, `call_tasks`, `sms_queue`

---

## 1. Overview

The Guest Follow-up Workflow automates and tracks outreach to first-time guests over a configurable multi-week cadence.  It replaces the earlier ad-hoc `follow_up_tasks` table with a fully structured sequence engine.

**Key capabilities:**

- Named follow-up *sequences* (e.g., "6-Week First-Time Guest Journey") with ordered *actions*
- Five delivery channels: **email**, **text/SMS**, **call** (human task), **mail** (PDF letter), **gift** (manual)
- Reusable *templates* with `{merge_tag}` variable replacement
- Automatic scheduling: when a guest visit is recorded, all active sequences for the church are scheduled automatically via `GuestVisitModel::onAfterInsert()`
- Second-visit reward: a coupon email (week-4 gift action) fires automatically on the guest's second visit
- Spark CLI command `followup:send` for scheduled cron dispatch

---

## 2. Architecture

### 2.1 Data flow

```
Guest visits church
      │
      ▼
guests.store() → GuestVisit::insert()
                       │
                       ▼
            GuestVisitModel::onAfterInsert()
              ├─ scheduleActiveSequencesForGuest()   → guest_followup_log rows created
              └─ triggerSecondVisitReward()           → week-4 gift email (2nd visit only)
                       │
                       ▼
            [cron @ 9 AM] php spark followup:send
                       │
                       ▼
            GuestFollowupLogModel::getPendingDue($today)
                       │
                       ▼
            FollowupDispatchService::dispatch()
              ├─ email  → CI4 email service (SMTP)
              ├─ text   → SmsService → Twilio (or log_only stub)
              ├─ call   → creates call_tasks row for volunteer
              ├─ mail   → Dompdf PDF / HTML file in writable/mail_queue/
              └─ gift   → metadata flag (manual task for admin)
```

### 2.2 Models

| Model | Table | Notes |
|-------|-------|-------|
| `FollowupSequenceModel` | `followup_sequences` | `getActiveForChurch(churchId)`, optional church_id for global defaults |
| `FollowupTemplateModel` | `followup_templates` | `church_id = null` = system default |
| `FollowupActionModel`   | `followup_actions`   | `getBySequenceWithTemplates()`, `getAutomatedBySequence()` |
| `GuestVisitModel`       | `guest_visits`       | has `onAfterInsert` hook; `triggerSecondVisitReward()` |
| `GuestFollowupLogModel` | `guest_followup_log` | `scheduleActiveSequencesForGuest()`, `getPendingDue()`, `getTimelineForGuest()` |
| `CallTaskModel`         | `call_tasks`         | `getPendingForChurch()`, `markCompleted()` |
| `SmsQueueModel`         | `sms_queue`          | Outbound SMS queue |

### 2.3 Controllers

| Controller | URL prefix | Purpose |
|-----------|-----------|---------|
| `Pastor\Controllers\Guests` | `/guests` | Guest CRUD, AJAX follow-up timeline controls, `pendingCallsApi` navbar badge |
| `Pastor\Controllers\FollowupWorkflow` | `/followup-workflow` | Sequence/action/template admin, call-task queue, completion report |
| `Pastor\Controllers\FollowUp` *(legacy)* | `/followup` | Legacy ad-hoc `follow_up_tasks`; kept for data backwards-compatibility |

### 2.4 Services

| Service | Description |
|---------|-------------|
| `App\Services\FollowupDispatchService` | Routes a single log entry to the right channel handler |
| `App\Services\SmsService` | Twilio or log-only SMS sending |
| `App\Services\MessagingService` | Higher-level wrapper; also used for non-followup emails |

---

## 3. Default 6-Week Sequence

Seeded by `SixWeekFollowupSeeder` as a system-level default (`church_id = null`).  Churches may override by creating their own sequences via `/followup-workflow/sequences/create`.

| Week | Day offset | Channel | Automation | Template |
|------|-----------|---------|-----------|---------|
| 1 | 2 | email | Auto | Welcome email with service links |
| 1 | 5 | call  | Volunteer | Personal welcome call script |
| 2 | 10 | email | Auto | Small-group / ministry invite |
| 3 | 16 | text  | Auto | Friendly check-in SMS |
| 3 | 18 | call  | Volunteer | Prayer & connection call script |
| 4 | 25 | gift  | Volunteer | Welcome gift delivery instructions |
| 5 | 33 | email | Auto | Membership / next-steps invite |
| 6 | 40 | call  | Volunteer | Final membership invitation call |

---

## 4. Template Variables

Templates support these merge tags (replaced at dispatch time):

| Tag | Source |
|-----|--------|
| `{first_name}` | `guests.first_name` |
| `{last_name}` | `guests.last_name` |
| `{church_name}` | `churches.name` for the guest's church |
| `{visit_date}` | Scheduled date of the log entry |
| `{pastor_name}` | Blank by default; set manually or extend `FollowupDispatchService::buildVarMap()` |
| `{phone}` | Decrypted `guests.phone` |

---

## 5. Cron Setup

```bash
# Dispatch all pending followup actions each morning at 9 AM
0 9 * * * php /path/to/spark followup:send >> /path/to/writable/logs/followup.log 2>&1

# Scope to a single church (useful for per-church scheduling windows)
0 9 * * * php /path/to/spark followup:send --church 5
```

Dry-run test (no database writes):

```bash
php spark followup:send --dry-run
```

---

## 6. Installation / First-Time Setup

```bash
# 1. Run all migrations
php spark migrate --all

# 2. Seed permissions (creates followup.manage, followup.view, followup_volunteer group)
php spark db:seed PermissionRoleSyncSeeder

# 3. Seed the default 6-week framework (idempotent — safe to re-run)
php spark db:seed SixWeekFollowupSeeder

# 4. Configure email in .env (SMTP credentials)
#    See .env comments — never commit real credentials

# 5. (Optional) Configure Twilio SMS in .env
#    sms.provider = twilio
#    sms.twilio_sid = ACxxxxxxxx...
#    sms.twilio_token = xxxxxxxx...
#    sms.twilio_from  = +1XXXXXXXXXX

# 6. Add cron job (see section 5)
```

---

## 7. Permissions

| Permission | Roles | Description |
|-----------|-------|-------------|
| `followup.manage` | `churchadmin`, `pastor` | CRUD on sequences/actions/templates, trigger actions, complete call tasks |
| `followup.view` | `churchadmin`, `pastor`, `followup_volunteer` | Read-only: sequences, templates, call queue, timeline |
| `followup.tasks` *(legacy)* | `churchadmin`, `pastor` | Manage legacy `follow_up_tasks` table |

---

## 8. Guest Conversion Integration

When a guest is converted to a member via `Guests::convertToMember()`:

1. `guests.followed_up = 1` is set (marks followup complete)
2. `GuestModel::markConvertedToMember($guestId, $userId)` stamps `converted_to_member_at`, `converted_user_id`, and `converted_by_user_id`
3. Admin is redirected to `/members/create` with `guest_data` flash data pre-populating the form

The `guests` table retains the record permanently for historical reporting — converted guests simply no longer appear in the active guest list (`getChurchGuestCount(excludeConverted: true)` by default).

---

## 9. Known Limitations / Future Work

| Issue | Priority | Status | Notes |
| ----- | -------- | ------ | ----- |
| No guest consent / opt-out field | High | ✅ Resolved | `opted_out_channels` JSON column on `guests`; `GuestModel::isOptedOut()` checks in dispatcher before any send |
| No rate limiting in dispatcher | Medium | ✅ Resolved | `dispatchText()` now writes to `sms_queue`; `sms:process` cron drains at a controlled rate |
| No bounce/delivery-failure capture | Medium | ⏳ Pending | Requires Twilio / email provider webhook endpoint — deferred |
| Template variable validation | Low | ✅ Resolved | `saveTemplate()` returns `unknown_tokens[]` for any unrecognised `{tag}` in body/subject |
| No timezone awareness | Low | ✅ Resolved | `preferred_timezone` on guests; `scheduleActiveSequencesForGuest()` and `scheduleSequenceForGuest()` accept `$guestTimezone` and compute UTC-adjusted dates |
| Legacy `follow_up_tasks` coexists | Low | ✅ Resolved | Deprecation banner added to `Pastor/Views/FollowUp/index.php`; all new code uses `guest_followup_log` |

## 11. Guest Follow-up Badge States (4-State System)

Guest status badges appear in three places: `/guests` index table, `/guests/view/:id` profile page, and the `/guests/dashboard` recent guests panel.

### State logic (evaluated in order)

| State | Condition | Badge color | Lang key |
|---|---|---|---|
| **Converted** | `guests.converted_to_member_at IS NOT NULL` | `success` | `Guests.statusConverted` |
| **Followed Up** | `guests.followed_up = 1` | `success` | `Guests.statusFollowedUp` |
| **In Progress** | ≥1 `sent`/`skipped` log entry AND ≥1 `pending` log entry | `primary` | `Guests.followupInProgress` |
| **Pending Follow-up** | anything else | `warning` | `Guests.pendingFollowUp` |

### Implementation notes

- Index view (`Pastor/Views/Guests/index.php`): per-guest progress data is fetched in a single grouped query in `Guests::index()` and passed as `$progressMap[guest_id] = ['done' => N, 'pending' => N]`. No N+1.
- Dashboard recent-guests panel (`Pastor/Views/GuestDashboard/index.php`): same pattern via `$recentProgressMap`.
- All badge variants use the outline-first rule: `border border-{color} text-{color} fw-normal`.

---

## 12. Guest Dashboard KPI Tiles (`/guests/dashboard`)

### Tile order and data sources

| Position | Label | Color | Data source | Click target |
|---|---|---|---|---|
| 1 | Guests in Follow-up | `warning` | `COUNT(DISTINCT gfl.guest_id)` where `gfl.status='pending'` (same as main dashboard) | `guests?followup_stage=pending` |
| 2 | In Progress | `primary` | Guests with ≥1 sent/skipped AND ≥1 pending step | `guests?followup_stage=inprogress` |
| 3 | Converted to Members | `success` | `guests.converted_to_member_at IS NOT NULL` | `guest-conversions` |
| 4 | Pending Call Tasks | `danger`/`secondary` | `call_tasks` pending count | `followup-workflow/call-tasks` |
| 5 | Active Sequences | `info` | `followup_sequences` active count | `followup-workflow/sequences` |
| 6 | Templates | `secondary` | `followup_templates` count | `followup-workflow/templates` |

### Alignment rule

"Guests in Follow-up" on `/guests/dashboard` tile 1 **must always equal** the same tile on the main `/dashboard` AJAX widget. Both use:

```sql
SELECT COUNT(DISTINCT gfl.guest_id)
FROM guest_followup_log gfl
JOIN guests g ON g.id = gfl.guest_id
WHERE gfl.status = 'pending'
AND g.church_id = :churchId
```

### `followup_stage` index filter values

| Value | Meaning |
|---|---|
| `pending` | Guest has at least one `pending` log entry |
| `inprogress` | Guest has ≥1 `sent`/`skipped` AND ≥1 `pending` log entry |
| `sent` / `failed` / `skipped` | Guest has at least one entry with that status |
| `none` | Guest has no `sent`/`skipped` log entries |

---

## 13. Digital Connection Card (`/connect/{token}`)

A public (unauthenticated) URL that allows walk-in guests to self-submit their details.

### Flow

1. Church admin visits `/guests/import` — QR code and shareable URL are displayed
2. Guest scans QR / opens link → `GET /connect/{token}` renders `app/Views/public/connection_card.php`
3. Guest submits form → `POST /connect/{token}` handled by `Pastor\Controllers\Import::digitalCard()`
4. Controller upserts guest record (deduplication by encrypted email/phone hash)
5. If new guest: `GuestFollowupLogModel::scheduleActiveSequencesForGuest()` is called immediately
6. Redirects to `app/Views/public/connection_card_thanks.php`

### View paths (critical)

CI4's `view()` and `$this->extend()` resolve relative to `app/Views/`. Always use forward-slash paths:

```php
// ✅ Correct
view('public/connection_card', [...])
$this->extend('layouts/public')

// ❌ Wrong — causes ViewException
view('app\Views\public/connection_card', [...])
$this->extend('app\Views\layouts/public')
```

### Controller: `Pastor\Controllers\Import`

| Method | Route | Purpose |
|---|---|---|
| `index()` | `GET /guests/import` | Import hub: CSV upload + QR/digital card display |
| `showCard($token)` | `GET /connect/{token}` | Render connection card form |
| `digitalCard($token)` | `POST /connect/{token}` | Accept submission, upsert guest, schedule sequences |
| `processCsv()` | `POST /guests/import/process` | Bulk CSV import with dedup |
| `sampleCsv()` | `GET /guests/import/sample-csv` | Download sample CSV template |
| `qrCode()` | `GET /guests/import/qr` | Serve QR code PNG via server-side proxy |

### Required variables for connection card views

`connection_card.php` requires: `$church` (array), `$token` (string), `$title` (string). Optionally: `$errors` (array), `$old` (array).

`connection_card_thanks.php` requires: `$church` (array), `$title` (string).

---

| File | Purpose |
|------|---------|
| `app/Commands/SendFollowups.php` | `php spark followup:send` Spark command |
| `app/Services/FollowupDispatchService.php` | Channel-specific dispatch logic |
| `app/Services/SmsService.php` | Twilio / log-only SMS provider |
| `app/Models/FollowupSequenceModel.php` | Sequence CRUD + `getActiveForChurch()` |
| `app/Models/FollowupActionModel.php` | Action CRUD + sequence join methods |
| `app/Models/FollowupTemplateModel.php` | Template CRUD |
| `app/Models/GuestVisitModel.php` | Visit recording + auto-schedule hook |
| `app/Models/GuestFollowupLogModel.php` | Log CRUD + scheduler + timeline query |
| `app/Models/CallTaskModel.php` | Manual call task CRUD |
| `app/Database/Seeds/SixWeekFollowupSeeder.php` | Default 6-week sequence + templates |
| `app/Database/Seeds/FollowupPermissionsSeeder.php` | Permission + group bootstrap |
| `Pastor/Controllers/Guests.php` | Guest CRUD + followup AJAX endpoints |
| `Pastor/Controllers/FollowupWorkflow.php` | Sequence/action/template admin |
| `Pastor/Config/Routes.php` | All guest + followup routes |
