# ekklesiaRD

## Overview

**ekklesiaRD** is a multi-church management system built for Episcopal dioceses and church networks.
It covers member management, liturgical service tracking, guest follow-up automation, financial
accounting, volunteer coordination, event management, and worship planning.

### Pending Modules (as of May 2026)

| Module/Feature      | Schema Present? | Code Implemented? | Notes/Plan                                  |
| ------------------- | :-------------: | :---------------: | ------------------------------------------- |
| Bank Accounts       |       Yes       |        No         | Needed for finance, see implementation plan |
| Bank Reconciliation |       Yes       |        No         | Needed for finance, see implementation plan |
| Small Groups        |       Yes       |        No         | Needed for membership pathway, see plan     |
| Group Membership    |       Yes       |        No         | Needed for small groups                     |
| Bulk Messaging      |       No        |        No         | Needs schema and module                     |
| Giving Statements   |     Partial     |        No         | Export logic not implemented                |
| Directory Export    |     Partial     |        No         | Export logic not implemented                |
| Child Check-in      |       No        |        No         | Not started                                 |
| Online Giving       |       No        |        No         | Not started                                 |
| Forms/Surveys       |       No        |        No         | Not started                                 |
| REST API            |       No        |        No         | Not started                                 |

| **Feature**                | **Description**                                                                   |
| -------------------------- | --------------------------------------------------------------------------------- |
| **Member Management**      | Profiles, families, portal vs non-portal members, dedup/merge, import             |
| **Event Management**       | Event CRUD, calendars, registrations, attendance tracking                         |
| **Guest Follow-up**        | Automated 6-week follow-up sequences via email/SMS/call/mail/gift                 |
| **Volunteer Coordination** | Teams, scheduling, role assignment                                                |
| **Financial Tracking**     | Donations, expenses, fund accounting, chart of accounts, budgets, reports         |
| **Worship Planning**       | Song library, order-of-service builder, liturgical metadata, attendance KPIs      |
| **Member Standing**        | Annual pledges, good-standing tracking, communicant records, leadership positions |
| **Audit & Security**       | Audit logs, device verification, session tracking, rate limiting                  |

---

## Ministry Framework (Source: ParishDiocese.md)

ekklesiaRD is built to implement the **Diocesan Membership and Systems Framework** described in `ParishDiocese.md`.
The software is the operational layer for that framework. Key concepts below.

### Good Standing (canonical definition)

A member is in **good standing** when they meet all four criteria:

1. **Baptized** — recorded in the parish register.
2. **Confirmed or received** by a bishop.
3. **Communicant** — received Holy Communion at least three times in the preceding year.
4. **Annual pledge of record** — made a financial pledge to the parish.

Good standing is required for: voting at the Annual Meeting, serving on Vestry, and synod representation.

### Membership Scopes (Acts 1:8 Model)

| Scope                         | Who                                                                        | Member?                               |
| ----------------------------- | -------------------------------------------------------------------------- | ------------------------------------- |
| **Jerusalem**                 | Community mission field — not yet connected                                | No                                    |
| **Judea**                     | Regular attenders not yet in covenant                                      | No                                    |
| **Samaria**                   | Covenant members (Stage 1 complete); gathered but not yet in a small group | Yes — good standing                   |
| **Ends of the Earth (Local)** | Covenant members integrated into a small group                             | Yes — active                          |
| **Ends of the Earth (Sent)**  | Covenant members commissioned in a ministry                                | Yes — active; eligible for leadership |

### Three-Stage Membership Pathway

| Stage | Title                                                    | Outcome                                                      |
| ----- | -------------------------------------------------------- | ------------------------------------------------------------ |
| **1** | The Anglican Way (4 sessions)                            | Enrollment on electoral roll; Judea → Samaria                |
| **2** | Rooted in Community (4 sessions + small group placement) | Small group integration; Samaria → Ends of the Earth (Local) |
| **3** | Sent to Serve (4 sessions + ministry placement)          | Commissioned in ministry; → Ends of the Earth (Sent)         |

### Membership Categories

| Category                        | Definition                                                                |
| ------------------------------- | ------------------------------------------------------------------------- |
| **Baptized Member**             | Any baptized person recorded in the parish register                       |
| **Communicant Member**          | Baptized; receives Communion ≥ 3×/year                                    |
| **Confirmed / Received Member** | Baptized + confirmed/received by a bishop                                 |
| **Member in Good Standing**     | Confirmed + communicant + annual pledge                                   |
| **Active Member**               | Good standing + small group (Committed) or serving in ministry (Core)     |
| **Inactive Member**             | Has not met good standing criteria for 12 months; voting rights suspended |

---

## Domain Model Alignment

How the ministry framework maps to software modules in this codebase:

| Framework Concept                      | Source (ParishDiocese.md) | Software Implementation                                                                                                                   |
| -------------------------------------- | ------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Good standing (4 criteria)             | §2.4                      | `member_standing_summary` table; `Standing` controller + `MemberStandingSummaryModel`                                                     |
| Annual pledge of record                | §2.4 / Stage 2            | `annual_pledges` table; `AnnualPledges` controller                                                                                        |
| Communicant tracking (≥3/year)         | §2.4                      | `attendance.communicant` column; `communion_count` aggregated in `MemberStandingSummaryModel`                                             |
| Baptism record                         | §1.1                      | `profiles.baptism_date` field exists                                                                                                      |
| Confirmation record                    | §1.2                      | `profiles.confirmation_date` (DATE) + `profiles.is_confirmed` (BOOL); required in `MemberStandingSummaryModel` good standing calculation  |
| Electoral roll                         | §5.1                      | `member_church_affiliations` + `church_people` snapshot; People controller                                                                |
| Membership categories                  | §2.5                      | `profiles.membership_status` ENUM (active/inactive/visitor/pending/regular_attender); also `member_church_affiliations.membership_status` |
| Membership pathway stages              | §2.6                      | ⚠️ **Not tracked in schema** — see gaps below                                                                                             |
| Guest / visitor (Judea scope)          | §2.2                      | `guests` table; Guest management + 6-week follow-up workflow                                                                              |
| Guest → member conversion              | §2.2 / Stage 1            | `Guests::convertToMember()`; `GuestConversions` controller; conversion stamp fields                                                       |
| Family unit                            | §2 (covenantal community) | `families` + `family_members` tables; Family CRUD                                                                                         |
| Ministry placement (Stage 3)           | §2.6 Stage 3              | `volunteer_teams` + `team_members`; Volunteer module                                                                                      |
| Clergy / lay leadership positions      | §3 Leadership Manual      | `leadership_positions` table; `LeadershipPositions` controller                                                                            |
| Liturgical rhythm (Eucharist centered) | §1.3 / Core Values        | `services` with liturgical_day/season; Worship Planning module                                                                            |
| Annual systems self-assessment         | §4 / §7.3                 | Province + Diocesan dashboards; audit logs; KPI tiles                                                                                     |
| Financial stewardship                  | §1.3 / Stage 2            | Donations, Expenses, Fund Accounting, Budget modules                                                                                      |
| Diocesan reporting                     | §6.3                      | Diocesan Dashboard; `diocesan_attendance_monthly`; finance roll-up                                                                        |

### Framework vs. Code Gaps (Updated)

These are places where the framework definition and the actual code diverge — known implementation gaps or intentional simplifications. See also the "Pending Modules" table above.

| Gap                                    | Framework Requirement                                                                  | Actual Code Behavior                                                                                                                                                                                 |
| -------------------------------------- | -------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Baptism criterion**                  | Good standing requires baptism recorded in parish register (§2.4 criterion 1)          | `profiles.baptism_date` exists but is **not evaluated** in the standing calculation. Baptism is assumed (captured in register) rather than gated.                                                    |
| **Active member vs. `users.active`**   | Good standing requires active membership status                                        | Code uses `users.active = 1` as the `is_active_member` proxy — it is a system account flag, not a canonical membership status check.                                                                 |
| **Extra discipline criterion**         | Framework defines 4 criteria for good standing                                         | Code adds a 5th: `is_under_discipline = 0` (checks `member_discipline` table). This is a correct hardening beyond the framework.                                                                     |
| **Membership pathway stages**          | Three explicit stages with completion tracking                                         | ⏳ **Future implementation** — no `pathway_stage` field yet. Stage progress is inferred only from `membership_status`.                                                                               |
| **`membership_status` location**       | Framework implies a single member status                                               | Code maintains status in two places: `profiles.membership_status` (legacy/convenience) and `member_church_affiliations.membership_status` (authoritative for multi-church). Queries vary by context. |
| **Small group integration**            | Stage 2 completion = placed in a small group                                           | ⏳ **Future implementation** — no `small_groups` module or `group_membership` table yet.                                                                                                             |
| **Guest → member conversion pre-fill** | Conversion should carry guest name/email/phone into the create-member form             | `Guests::convertToMember()` flashes `guest_data` but `Members/create.php` only uses `old()` — the flash is never consumed. Form arrives blank. ⏳ **Not yet fixed.**                                 |
| **Leadership appointment gate**        | Good standing required for Vestry / synod roles (§2.4)                                 | `leadership_positions` has no `is_good_standing` check at insert time. Anyone can be appointed to any position. ⏳ **Not yet fixed.**                                                                |
| **Status transition automation**       | Pathway moves guest → visitor → regular_attender → pending → active via defined events | All status changes are manual admin edits. No programmatic promotion triggers exist. ⏳ **Future implementation.**                                                                                   |

---

## Current Implementation Status

> Last reviewed: April 2026. Based on actual codebase (CodeIgniter 4.7 + Shield 1.2).

### Implemented and Production-Ready

| Module                                 | Status     | Notes                                                                                                                                                                     |
| -------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Multi-tier Hierarchy**               | ✅ Full    | Province → Diocese → Church scoping with filters and scope guards                                                                                                         |
| **Authentication & Registration**      | ✅ Full    | Shield-based login, email verification, onboarding gate filter, rate limiting                                                                                             |
| **Role & Permission System**           | ✅ Full    | Roles: provinceadmin, diocesanadmin, churchadmin, clergy, treasurer, volunteer_coordinator, followup_volunteer, member                                                    |
| **Member Management**                  | ✅ Full    | Profiles, scoped search, status filters, profile pictures, church affiliation transfer, multi-parish membership                                                           |
| **Member Import**                      | ✅ Full    | Clergy/Controllers/Import.php; bulk CSV import with validation                                                                                                            |
| **Member Dedup / Merge / Hard-Delete** | ✅ Full    | MemberMergeService + MemberMerge controller; duplicate detection by name/phone, merge flow with field overrides, three-gate hard-delete                                   |
| **People / Church Registry**           | ✅ Full    | People controller + ChurchPeopleRegistryService; portal vs non-portal sync; church_people snapshot                                                                        |
| **Family Grouping**                    | ✅ Full    | Family CRUD, head of household, member linking, per-church scoping                                                                                                        |
| **Liturgical Service Logging**         | ✅ Full    | Services with liturgical day/season, format (in-person/hybrid/online), sacramental counts, service_metrics                                                                |
| **Attendance Marking**                 | ✅ Full    | Per-service individual attendance, bulk mark, AJAX mark, communicant tracking                                                                                             |
| **Attendance Reports**                 | ✅ Full    | ASA/AWA KPIs, weekly Sunday trend, per-period breakdowns, date-range filter                                                                                               |
| **Worship Planning**                   | ✅ Full    | WorshipPlanning controller; WorshipSongModel (title, author, CCLI, key, theme); ServiceWorshipItemModel; order-of-service builder; print view                             |
| **Guest Management**                   | ✅ Full    | Guest CRUD, follow-up task creation, guest-to-member conversion with conversion stamp                                                                                     |
| **Guest Follow-up Workflow**           | ✅ Full    | FollowupWorkflow controller; automated 6-week sequences (email/SMS/call/mail/gift); FollowupDispatchService; CallTaskModel; php spark followup:send cron command          |
| **Guest Conversions**                  | ✅ Full    | GuestConversions controller; conversion tracking (converted_to_member_at, converted_by_user_id)                                                                           |
| **Events Module**                      | ✅ Full    | Events + EventCalendars controllers; EventModel, EventRegistrationModel, EventAttachmentModel; event calendar scoping (EventVisibilityService)                            |
| **Donations**                          | ✅ Full    | Record donations, fund assignment, COA account linkage, category filter, fund filter, active filter chips                                                                 |
| **Expenses**                           | ✅ Full    | Record expenses, fund assignment, approval tracking, COA linkage, same filter/chip UX as donations                                                                        |
| **Fund Accounting**                    | ✅ Full    | funds table with unrestricted/restricted/permanent types, locked funds, balance calculations                                                                              |
| **Chart of Accounts**                  | ✅ Full    | 4-digit COA, 5 account types, budget flag, Episcopal section metadata                                                                                                     |
| **Budget Management**                  | ✅ Full    | Budgets controller + ChurchBudgetModel; COA-aligned budgetable accounts; fiscal-year scoping                                                                              |
| **Annual Pledges**                     | ✅ Full    | AnnualPledges controller + AnnualPledgeModel; pledge tracking per member per year                                                                                         |
| **Member Standing**                    | ✅ Full    | Standing controller + MemberStandingSummaryModel; good-standing criteria: active + **confirmed** (`is_confirmed`) + communicant ≥3 + annual pledge + not under discipline |
| **Leadership Positions**               | ✅ Full    | LeadershipPositions controller + LeadershipPositionModel; clergy and lay position tracking                                                                                |
| **Volunteer Teams**                    | ✅ Full    | Team CRUD, member assignment, role labeling                                                                                                                               |
| **Volunteer Scheduling**               | ✅ Full    | Calendar week/list views; team-scoped AJAX member picker; service linkage; status confirm from ⋮ dropdown; automated email reminders via `volunteers:remind` cron         |
| **Liturgical Calendar Source**         | ✅ Full    | Province-managed global entries + church-specific overrides; season/color/icon metadata                                                                                   |
| **Province Dashboard**                 | ✅ Full    | Province-wide summary, per-diocese breakdown, attendance averages by period, audit log, AJAX updates                                                                      |
| **Diocesan Dashboard**                 | ✅ Full    | Finance dashboard with income/expense/fund summary; diocesan attendance monthly roll-up                                                                                   |
| **Church Dashboard**                   | ✅ Full    | Member counts, recent services, monthly income/expense, pending follow-ups, guests                                                                                        |
| **Clergy Reports**                     | ✅ Full    | Clergy/Controllers/Reports.php; ROI / second-visit analytics                                                                                                              |
| **Audit Logs**                         | ✅ Full    | AuditLogs controller + AuditLogModel; action logging across modules; audit log viewer                                                                                     |
| **SMS / Messaging Services**           | ⚠️ Partial | SmsService (Twilio or log-only); MessagingService; SmsQueueModel — used by follow-up workflow only; no general bulk messaging module                                      |

### Not Yet Implemented (Backlog)

See the table above for a summary. For each, see the implementation plan below.

---

## Implementation Backlog & Next Steps (May 2026)

### Finance: Bank Accounts & Reconciliation

- Implement models/controllers for `bank_account`, `bank_transaction`, `bank_reconciliation`, `bank_reconciliation_item`
- Build UI for account management and reconciliation workflow
- Integrate with donations/expenses for auto-matching
- Add permissions and audit logging

### Small Groups Module

- Implement models/controllers for `small_groups`, `group_membership`
- Add group assignment UI to member profile
- Integrate with member standing (Stage 2 completion)

### Bulk Messaging

- Design schema for message queue/logs
- Build messaging service (email/SMS)
- Add UI for composing and sending bulk messages

### Guest Follow-up Enhancements

- Fix guest-to-member conversion pre-fill
- Add reporting for follow-up effectiveness
- Add workflow automation for status transitions

### Other Gaps

- Add `pathway_stage` to profiles for membership tracking
- Enforce good standing at leadership appointment
- Implement PDF/Excel exports for giving statements, directories

---

---

## Feature Development Backlog

> Ordered by module, then estimated complexity (low → high).

### Member Standing Module

- [ ] Add `baptism_date` evaluation to standing calculation (currently captured but not gated)
- [ ] Add `pathway_stage` field to profiles (1/2/3/null) to formally track membership pathway progress
- [ ] Add Stage 1/2/3 completion tracking to member profile UI

### Small Groups Module (New — Stage 2 of Membership Pathway)

- [ ] Design `small_groups` and `group_memberships` tables (church-scoped, leader_id FK)
- [ ] Build small group CRUD (Clergy module)
- [ ] Add member placement UI (assign member to a small group after Stage 2)
- [ ] Wire small group placement as Stage 2 completion criterion in standing calculation

### Volunteer Module

- [x] Add calendar view for volunteer schedules (CSS Grid week view + list view; no external library)
- [ ] Add member availability and skills fields (extend profiles or create member_availability table)
- [x] Implement automated email/SMS reminders for upcoming volunteer duties (`volunteers:remind` Spark command + cron)

### Financial Module

- [ ] Wire DomPDF export to Donations index (PDF report by date range / fund)
- [ ] Wire PhpSpreadsheet export to Donations and Expenses (Excel download)
- [ ] Build per-member contribution statement generator (PDF, date-range filter, tax letter format)
- [ ] Add online giving member portal page (giving history; later: payment gateway)
- [ ] Integrate a payment gateway (Stripe or PayPal) for online donations

### Communication Module (New)

- [ ] Design messages / notification_log table schema
- [ ] Build bulk email module (group-targeted, church-scoped, template-based)
- [ ] Add in-app notification bell with unread badge
- [ ] Add push notification support (web push or mobile)

### Forms & Surveys Module (New)

- [ ] Design forms, form_fields, form_responses tables
- [ ] Build form builder UI
- [ ] Build public form response page (accessible without login for visitors)
- [ ] Add responses dashboard and CSV export

### Mobile / API Layer (New)

- [ ] Design RESTful API versioning strategy (/api/v1/)
- [ ] Implement API authentication (Shield API tokens)
- [ ] Expose read endpoints for: profile, attendance history, donations, family, upcoming schedule
- [ ] Build PWA manifest and offline shell
- [ ] Evaluate native mobile app (React Native / Expo) once API is stable

### Reporting & Exports

- [ ] Build cross-module report builder (combine attendance + finance + membership in one export)
- [ ] Add printable member directory (PDF, sorted by family or last name)
- [ ] Add vCard / CSV member roster export
- [ ] Add province-level financial roll-up report (income/expenses across all churches by fund type)
- [ ] Wire financial export actions (CSV/Excel/PDF) for Donations, Expenses, and Budget controllers

### Child Check-in

- [ ] Add child check-in feature for services and events

## Not Yet Implemented

| Feature                             | Gap Description                                                                                                                                                 |
| ----------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Bulk Communication Module**       | No general-purpose bulk email/SMS broadcast. Follow-up workflow sends targeted sequences only; no group messaging, no notification bell, no push notifications. |
| **Contribution / Donor Statements** | No per-member giving statement PDF generator for tax purposes. DomPDF is in vendor/ but not wired to a statement action.                                        |
| **Financial Exports**               | DomPDF and PhpSpreadsheet present in vendor/; export actions are stubs or absent for Donations, Expenses, and Budget controllers.                               |
| **Member Directory Export**         | Member list and view exist; no printable directory or vCard/CSV export.                                                                                         |
| **Child Check-in**                  | No child check-in feature for services or events.                                                                                                               |
| **Online Giving / Payment Gateway** | No payment processor integration (Stripe, PayPal, etc.); no donation portal for end members.                                                                    |
| **Forms and Surveys**               | No forms/surveys module.                                                                                                                                        |
| **Mobile App / REST API**           | No mobile app; no REST/JSON API layer for external consumers.                                                                                                   |
| **Advanced / Cross-module Reports** | No report builder combining attendance + finance + membership. Finance and attendance reports are separate single-module pages.                                 |
