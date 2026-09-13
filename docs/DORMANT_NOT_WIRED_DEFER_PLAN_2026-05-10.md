# Dormant-Not-Wired Deferral Plan (Step 3)

Date: 2026-05-10
Scope: Dormant-Not-Wired table group
Decision: Defer formally for launch; no UI/routes until explicitly approved

## Objective

Keep dormant schema out of production feature scope unless a promised/contracted requirement mandates immediate wiring.

## Decision for current launch

1. Defer dormant domains formally.
2. Keep route surface closed (no user-facing module routes for dormant domains).
3. Track implementation as phased roadmap work after launch.

## Route-surface verification (this step)

A route keyword audit was executed across module route files for dormant domains (facility, vendor, bank/journal, communication campaigns, notification stack, portal access requests, preregistration, worship templates, person subdomain tables, electoral roll, sacramental records, governance/ministry terms).

Result: no matching route entries found in current module route files.

## Dormant domains (grouped)

### A) Person v2 extension domain

- person
- user_person
- person_address
- person_contact
- person_setting
- person_background_check
- person_safeguarding_training
- person_communication_preference
- person_communication_consent_event
- person_transfer_record

### B) Advanced finance/ledger domain

- accounting_period
- bank_account
- bank_transaction
- bank_reconciliation
- bank_reconciliation_item
- bank_transaction_journal_link
- journal_entry
- journal_entry_line
- journal_entry_balance
- vendor
- vendor_bill
- vendor_bill_line
- vendor_payment
- vendor_payment_allocation
- period_close_batch

### C) Campaign/notification/webhook domain

- communication_template
- communication_campaign
- communication_recipient
- communication_suppression
- campaign_delivery_event
- notification
- notification_recipient
- webhook_delivery_attempt

### D) Facilities/scheduling expansion domain

- facility
- facility_resource
- facility_reservation
- facility_reservation_slot

### E) Worship/formation/governance expansion domain

- worship_template
- sacramental_record
- sacramental_participant
- electoral_roll_entry
- membership_stage_history
- ministry
- ministry_role
- governance_term
- parish_user_role
- church_role_catalog

### F) Intake and personalization expansion domain

- portal_access_request
- pre_registration
- user_setting
- user_scope_assignment
- dashboard_personalization
- kpi_target
- social_media_post
- file_attachment
- parish_report
- data_backfill_run
- data_backfill_item
- data_quality_snapshot
- donation_idempotency
- pledge_idempotency
- pledge_status
- event_recurrence_day
- event_registration_response
- service_sacramental_counts

## Launch guardrails

1. Do not add routes/views/controllers for deferred domains in the launch release branch.
2. Do not include deferred domains in launch marketing/demo scope.
3. If a domain becomes contractually promised before launch, move it to an approved implementation tranche with explicit owner, tests, and migration verification.

## Post-launch roadmap gates

### Gate 1 (N+1 planning)

- Select highest-value dormant domain(s).
- Define route scope, permission model, and data contracts.
- Add model/service/controller/test skeletons.

### Gate 2 (N+1 implementation)

- Implement one domain tranche only.
- Add integration tests and smoke route checks.
- Validate rate limits, CSRF pattern, and tenancy scoping.

### Gate 3 (N+2)

- Expand to next tranche based on production feedback.
- Keep unreleased dormant domains route-closed.

## Exit criteria for Step 3 complete

- Formal defer plan documented.
- Dormant domains explicitly listed and grouped.
- Route-closure verified and guarded by a unit test.
