# Schema Migration Pathway: ekklesiaRD → ekklesiaonline v2

**Prepared:** 2026-05-08  
**Scope:** Full database architecture alignment from current schema to `ekklesiaonline.sql`  
**Risk Level:** 🔴 HIGH — this is a complete data-model overhaul, not an additive migration  
**Estimated Phases:** 6 (must be executed in order with full backups between phases)

---

## 1. Executive Summary

The target schema (`ekklesiaonline.sql`) represents a **v2 architectural overhaul** with these core changes:

| Change | Details |
|--------|---------|
| `churches` → `parish` | Rename + expanded address fields, logo, tax_id |
| `profile` → `person` | Massive restructure: address → `person_address`, contacts → `person_contact`, membership → `parish_affiliation` |
| `member_church_affiliations` → `parish_affiliation` | New status enum, new fields |
| `families` → `family` | Rename, soft-delete, remove `church_id` |
| `family_members` → `family_member` | Rename, `user_id` → `person_id` |
| `services` → `service` | Rename, `date` → `service_date`, `time` → `service_time` |
| `donations` → `donation` | Rename, add `person_id` |
| `attendance` | Same name, adds `parish_id` + `person_id` |
| All `church_*` tables | Renamed to `parish_*` |
| New user–person bridge | New `user_person` table (was implicit via `profile.user_id`) |
| ~50 new tables | Banking, clergy, communication, facilities, governance, reporting |
| ~20 tables dropped | `church_people`, `service_metrics`, `worship_songs`, old followup tables, etc. |

**Critical invariant:** The application code (controllers, models, views) must be updated **in lockstep** with each phase — the database alone cannot be migrated atomically.

---

## 2. Complete Table Mapping

### 2.1 Renamed Tables (structure mostly compatible)

| Current Table | Target Table | Breaking Changes |
|--------------|-------------|-----------------|
| `churches` | `parish` | Add: logo, image, street, mailing_*, county, tax_id; Remove: none |
| `church_settings` | `parish_setting` | Minor: schema compatible |
| `church_integrations` | `parish_integration` | Compatible |
| `church_provider_credentials` | `parish_provider_credential` | Compatible |
| `church_role_audit_logs` | `parish_role_audit_log` | Compatible |
| `audit_logs` | `audit_log` | Significant field changes |
| `user_sessions` | `user_session` | Compatible |
| `call_tasks` | `call_task` | Compatible (minor) |
| `guest_followup_log` | `followup_log` | Add `person_id`; rename `scheduled_date` → `followup_date` |
| `guests` | `guest` | Add `person_id` |
| `annual_pledges` | `annual_pledge` | Add `person_id`, `status_id`; new `pledge_status` lookup |
| `volunteer_teams` | `team` | Remove `leader_id`, `description` kept |
| `team_members` | `team_member` | Add `person_id`, `joined_at`; `user_id` optional |
| `events` | `event` | Add `scope_level`, `scope_diocese_id`, `recurrence_*`, `deleted_at` |
| `event_calendars` | `event_calendar` | Add `diocese_id`, `scope_level` |
| `event_registrations` | `event_registration` | Add `person_id`, `guest_id`; restructured status fields |
| `event_attachments` | `event_attachment` | Compatible (minor field changes) |
| `families` | `family` | Remove `church_id`; add `deleted_at` |
| `family_members` | `family_member` | `user_id` → `person_id`; add `updated_at` |
| `services` | `service` | `date` → `service_date`; `time` → `service_time`; many fields removed |
| `donations` | `donation` | Add `person_id`; rename table |
| `funds` | `fund` | Compatible |
| `church_budgets` | `parish_budget` | Restructured around `chart_account_id` |
| `church_report_storage` | `parish_report` | Restructured |

### 2.2 Heavily Restructured Tables (require data migration)

| Current | Target | Migration Notes |
|---------|--------|----------------|
| `profile` | `person` + `person_address` + `person_contact` | First/last name, email, phone, birthdate stay. Address → `person_address`. Additional contacts → `person_contact`. |
| `member_church_affiliations` | `parish_affiliation` | Status enum changes: `active/inactive/visitor/pending/regular_attender` → `guest/attendee/member/inactive/transferred/deceased` |

### 2.3 Tables Removed (no target equivalent)

| Dropped Table | Reason |
|--------------|--------|
| `church_people` | Replaced by `parish_affiliation` + `person` |
| `service_metrics` | Replaced by `diocesan_sunday_service_metric` aggregates |
| `service_people` | Functionality absorbed into attendance |
| `service_sacramental_counts` | Replaced by `sacramental_record` |
| `worship_songs` | No direct equivalent (worship_template is different) |
| `service_worship_items` | No direct equivalent |
| `followup_sequences` | Replaced by simplified `followup_log` |
| `followup_templates` | Replaced by `communication_template` |
| `followup_actions` | No direct equivalent |
| `guest_visits` | Absorbed into `guest` + `attendance` |
| `sms_queue` | Replaced by `communication_campaign` system |
| `system_admin_primary` | Replaced by dedicated `*_admin_primary` tables |
| `user_registration_requests` | Replaced by `portal_access_request` / `pre_registration` |
| `member_discipline` | No direct equivalent (see `person_background_check`) |
| `member_standing_summary` | No direct equivalent (see `parish_affiliation.is_good_standing`) |
| `leadership_positions` | Replaced by `clergy_appointment` |
| `liturgical_calendar_entries` | No direct equivalent |
| `diocesan_attendance_monthly` | Replaced by `diocesan_attendance_monthly` (compatible) |
| `diocesan_sunday_period_aggregates` | Replaced by `diocesan_sunday_monthly_metric` |

### 2.4 Net-New Tables (safe to add immediately)

| Table | Purpose |
|-------|---------|
| `accounting_period` | Fiscal period management |
| `bank_account` | Bank account tracking |
| `bank_reconciliation` | Bank reconciliation |
| `bank_reconciliation_item` | Reconciliation line items |
| `bank_transaction` | Raw bank transactions |
| `bank_transaction_journal_link` | Transaction ↔ journal linkage |
| `campaign_delivery_event` | Communication delivery events |
| `church_role_catalog` | Role definitions catalog |
| `clergy_appointment` | Clergy appointment tracking |
| `clergy_license_document` | License documents |
| `communication_campaign` | Email/SMS campaigns |
| `communication_recipient` | Campaign recipients |
| `communication_suppression` | Opt-outs |
| `communication_template` | Message templates |
| `dashboard_personalization` | User dashboard config |
| `data_backfill_item` / `data_backfill_run` | Data ops tracking |
| `data_quality_snapshot` | Data quality metrics |
| `donation_idempotency` | Duplicate donation prevention |
| `electoral_roll_entry` | Anglican electoral roll |
| `event_recurrence_day` | Recurring event days |
| `event_registration_response` | Custom registration responses |
| `facility` + `facility_*` | Facility management |
| `file_attachment` | Generic file attachments |
| `governance_term` | Governance term tracking |
| `journal_entry` + `journal_entry_*` | Double-entry bookkeeping |
| `kpi_target` | KPI targets |
| `membership_stage_history` | Membership stage history |
| `ministry` + `ministry_role` | Ministry teams |
| `notification` + `notification_recipient` | In-app notifications |
| `organisation_setting` | Org-wide settings |
| `parish_affiliation` | Replaces `member_church_affiliations` (new) |
| `parish_user_role` | Explicit role ↔ parish ↔ user mapping |
| `password_reset` | Password reset tokens |
| `period_close_batch` | Accounting period close |
| `person` | Core person record |
| `person_address` | Normalized addresses |
| `person_background_check` | Background checks |
| `person_communication_consent_event` | GDPR consent |
| `person_communication_preference` | Comm preferences |
| `person_contact` | Phone/email contacts |
| `person_safeguarding_training` | Safeguarding records |
| `person_setting` | Per-person settings |
| `person_transfer_record` | Member transfers |
| `pledge_idempotency` | Duplicate pledge prevention |
| `pledge_status` | Pledge status lookup |
| `portal_access_request` | Portal access requests |
| `pre_registration` | Pre-registration queue |
| `province_admin_assignment_history` | Province admin history |
| `province_setting` | Province settings |
| `report` + `report_*` | Parochial report module |
| `sacramental_participant` + `sacramental_record` | Sacramental register |
| `settings` | Global settings |
| `signatory_role` | Report signatories |
| `social_media_post` | Social media scheduling |
| `user_person` | User ↔ person bridge |
| `user_scope_assignment` | Unused legacy; removed by `DropUserScopeAssignmentTable` migration |
| `user_setting` | Per-user settings |
| `vendor` + `vendor_*` | AP vendor management |
| `webhook_delivery_attempt` | Webhook delivery log |
| `worship_template` | Worship service templates |

---

## 3. Phased Migration Plan

### Prerequisites (do before any phase)

```bash
# 1. Full database backup
php spark db:seed --no-migrations  # verify seeder health
mysqldump -u root -p ekklesiard > backup_pre_v2_$(date +%Y%m%d_%H%M%S).sql

# 2. Create staging clone and test all phases there first
# 3. Put application in maintenance mode before phase 3+
```

---

### Phase 1 — Add Net-New Tables (SAFE — no breaking changes)

**Risk:** 🟢 None — purely additive  
**Downtime:** None required  
**Code changes:** None required

Create migration: `2026_05_08_010000_Phase1_AddNetNewTablesV2`

This phase creates all new tables that have no current equivalent. See migration file:
`app/Database/Migrations/2026_05_08_010000_Phase1_AddNetNewTablesV2.php`

---

### Phase 2 — Add Bridge Columns (LOW RISK — additive)

**Risk:** 🟡 Low — adds nullable columns to existing tables  
**Downtime:** None required  
**Code changes:** None required (columns are ignored until code is updated)

Changes:
- `attendance`: ADD `parish_id INT UNSIGNED NULL`, `person_id INT UNSIGNED NULL`
- `donations`: ADD `person_id INT UNSIGNED NULL`
- `guests`: ADD `person_id INT UNSIGNED NULL`
- `annual_pledges`: ADD `person_id INT UNSIGNED NULL`, `status_id INT UNSIGNED NULL DEFAULT 1`
- `team_members`: ADD `person_id INT UNSIGNED NULL`, `joined_at DATETIME NULL`
- `event_registrations`: ADD `person_id INT UNSIGNED NULL`, `guest_id INT UNSIGNED NULL`
- `call_tasks`: ADD `log_id INT UNSIGNED NULL` (link to `followup_log`)
- `families`: ADD `deleted_at DATETIME NULL`
- `services`: ADD `service_date DATE NULL`, `service_time TIME NULL` (duplicates of existing `date`/`time`)

Migration file: `app/Database/Migrations/2026_05_08_020000_Phase2_AddBridgeColumns.php`

---

### Phase 3 — Create `person` Table and Backfill (MEDIUM RISK)

**Risk:** 🟡 Medium — creates new table and backfills data; existing code unchanged  
**Downtime:** None required during creation, brief during backfill  
**Code changes:** None required yet

Steps:
1. Create `person` table
2. Create `user_person` bridge table  
3. Backfill from `profile`: one `person` row per `profile` row
4. Backfill `user_person`: one row per `profile` row (user_id → person_id)
5. Backfill bridge columns in Phase 2 tables using `profile` data

```sql
-- Conceptual backfill (run as migration)
INSERT INTO person (id, first_name, last_name, email, phone, birthdate, created_at, updated_at)
SELECT p.id, p.first_name, p.last_name, 
       ai.secret AS email,
       p.phone,
       p.birth_date,
       p.created_at, p.updated_at
FROM profile p
LEFT JOIN auth_identities ai ON ai.user_id = p.user_id AND ai.type = 'email_password';

INSERT INTO user_person (user_id, person_id, is_primary, linked_at)
SELECT user_id, id, 1, created_at FROM profile;

-- Backfill attendance.person_id
UPDATE attendance a
INNER JOIN profile p ON p.user_id = a.user_id
SET a.person_id = p.id, a.parish_id = p.church_id;
```

Migration file: `app/Database/Migrations/2026_05_08_030000_Phase3_CreatePersonAndBackfill.php`

---

### Phase 4 — Rename Core Tables (HIGH RISK — requires code changes)

**Risk:** 🔴 High — table renames will break all code referencing old names  
**Downtime:** Required (maintenance mode)  
**Code changes:** ALL models, controllers referencing renamed tables must be updated first

**Execution order (rename one at a time with code updates):**

#### 4a. `churches` → `parish`
- Update: `ChurchModel`, `ProfileModel`, `ChurchPeopleModel`, all controllers using church queries
- Rename table; update all `church_id` references that need to become `parish_id`

#### 4b. `church_settings` → `parish_setting`
- Update: `ChurchSettingModel`, all settings-reading code

#### 4c. `church_integrations` → `parish_integration`
- Update: `ChurchIntegrationModel`

#### 4d. `church_role_audit_logs` → `parish_role_audit_log`
- Update: `ChurchRoleAuditLogModel`, `MemberMergeService`

#### 4e. `audit_logs` → `audit_log`
- Update: `AuditLogModel`, all audit logging calls

#### 4f. `user_sessions` → `user_session`
- Update: `UserSessionModel`

#### 4g. `volunteers_teams` → `team`, `team_members` → `team_member`
- Update: `VolunteerTeamModel`, `TeamMemberModel`

#### 4h. `families` → `family`, `family_members` → `family_member`
- Update: `FamilyModel`, `FamilyMemberModel`
  - `family_member.user_id` → `person_id` (column rename, data already backfilled)

#### 4i. `annual_pledges` → `annual_pledge`
- Update: `AnnualPledgeModel`

#### 4j. `services` → `service`
- Update: `ServiceModel`, all service queries
  - Column rename: `date` → `service_date`, `time` → `service_time`

#### 4k. `donations` → `donation`
- Update: `DonationModel`

#### 4l. `guests` → `guest`, `guest_followup_log` → `followup_log`
- Update: `GuestModel`, `GuestFollowupLogModel`

#### 4m. `call_tasks` → `call_task`
- Update: `CallTaskModel`

**Use MySQL RENAME TABLE (atomic):**
```sql
RENAME TABLE churches TO parish,
             church_settings TO parish_setting,
             church_integrations TO parish_integration,
             ...;
```

Migration file: `app/Database/Migrations/2026_05_08_040000_Phase4_RenameCoreTablesV2.php`

---

### Phase 5 — Migrate `member_church_affiliations` → `parish_affiliation` (HIGH RISK)

**Risk:** 🔴 High — status enum changes break existing membership logic  
**Downtime:** Required  
**Code changes:** All code referencing `member_church_affiliations` and its status values

Status enum mapping:
| Old status | New status |
|-----------|-----------|
| `active` | `member` |
| `inactive` | `inactive` |
| `visitor` | `guest` |
| `pending` | `attendee` |
| `regular_attender` | `attendee` |

```sql
INSERT INTO parish_affiliation 
  (parish_id, person_id, linked_user_id, affiliation_type, membership_status, 
   is_member, is_good_standing, start_date, created_at, updated_at)
SELECT 
  mca.church_id,
  p.id AS person_id,
  mca.user_id,
  CASE 
    WHEN mca.membership_status IN ('active','inactive') THEN 'member'
    ELSE 'attendee'
  END AS affiliation_type,
  CASE mca.membership_status
    WHEN 'active' THEN 'member'
    WHEN 'inactive' THEN 'inactive'
    WHEN 'visitor' THEN 'guest'
    WHEN 'pending' THEN 'attendee'
    WHEN 'regular_attender' THEN 'attendee'
    ELSE 'guest'
  END AS membership_status,
  mca.is_member,
  0 AS is_good_standing,
  mca.start_date,
  mca.created_at,
  mca.updated_at
FROM member_church_affiliations mca
INNER JOIN profile p ON p.user_id = mca.user_id;
```

Migration file: `app/Database/Migrations/2026_05_08_050000_Phase5_MigrateAffiliations.php`

---

### Phase 6 — Drop Legacy Tables (LOW RISK if phases 1–5 complete)

**Risk:** 🟡 Low (if previous phases complete and verified)  
**Downtime:** None required  
**Code changes:** Remove any remaining references

Tables to drop:
- `profile` (data in `person` + `person_address` + `person_contact`)
- `member_church_affiliations` (data in `parish_affiliation`)
- `church_people` (data in `parish_affiliation`)
- `service_metrics`, `service_people`, `service_sacramental_counts`
- `worship_songs`, `service_worship_items`
- `followup_sequences`, `followup_templates`, `followup_actions`
- `guest_visits`, `sms_queue`
- `system_admin_primary`
- `user_registration_requests`
- `member_discipline`, `member_standing_summary`
- `leadership_positions`
- `diocesan_sunday_period_aggregates` (replaced by new aggregate tables)
- `liturgical_calendar_entries`

Migration file: `app/Database/Migrations/2026_05_08_060000_Phase6_DropLegacyTables.php`

---

## 4. Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| FK constraint failures during rename | High | High | Drop FKs before rename, re-add after |
| Code referencing old table names | Certain | High | Comprehensive search before each phase |
| Data loss in membership status mapping | Medium | High | Audit rows where status maps ambiguously |
| Person backfill email duplicates | Medium | Medium | Use IGNORE or check for duplicates first |
| Service metrics gap | Medium | Medium | Pause cron jobs during phase 4 |
| CI4 migration runner tracking | Low | Low | CI4 tracks by class name; new migrations safe |

---

## 5. Recommended Execution Order

```
Phase 1 → Deploy (no downtime)
Phase 2 → Deploy (no downtime)
Phase 3 → Deploy + run backfill (brief maintenance)
         → Verify person table data integrity
         → Smoke test application (old tables still active)
Phase 4 → Schedule maintenance window
         → Update all affected models/controllers/views
         → Deploy code changes
         → Run rename migrations
         → Smoke test
Phase 5 → In same maintenance window as Phase 4
         → Verify affiliation data
Phase 6 → After 2-week production validation window
         → Final cleanup
```

---

## 6. Code Scan: Files Requiring Updates

Before executing Phase 4, grep the codebase for every reference to renamed tables:

```bash
# Tables being renamed
grep -r "churches\|church_settings\|church_integrations\|audit_logs\|user_sessions\|volunteer_teams\|team_members\|family_members\|annual_pledges\|call_tasks\|guest_followup_log\|member_church_affiliations" \
  app/ Pastor/ MemberPortal/ Diocesan/ Province/ Worship/ Treasurer/ Volunteer/ \
  --include="*.php" -l | sort -u
```

---

## 7. Immediate Next Steps

1. **Run Phase 1 migration** — no risk, no code changes needed
2. **Run Phase 2 migration** — no risk, no code changes needed  
3. **Review Phase 3** — backfill `person` table, validate against `profile`
4. **Audit membership status mapping** — identify rows where `pending` vs `regular_attender` matters before migrating to `attendee`
5. **Create feature branch** for Phase 4 code changes
