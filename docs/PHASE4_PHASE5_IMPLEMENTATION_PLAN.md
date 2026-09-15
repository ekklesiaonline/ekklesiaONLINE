# Phase 4 & 5 Implementation Plan

**Created:** 2026-05-08  
**Scope:** Table renames (Phase 4) + affiliation migration (Phase 5)  
**Risk:** 🔴 HIGH — requires coordinated code + DB changes  
**Status:** PLANNING

---

## Phase 4: Table Renames

### Step 1: Update Model Classes (Rename + Update $table property)

| Old Class Name | Old $table | New Class Name | New $table | File |
|---|---|---|---|---|
| `ParishModel` | `parishes` | `ParishModel` | `parish` | `app/Models/ParishModel.php` → `app/Models/ParishModel.php` |
| `ParishSettingModel` | `parish_settings` | `ParishSettingModel` | `parish_setting` | Rename file |
| `ParishIntegrationModel` | `parish_integrations` | `ParishIntegrationModel` | `parish_integration` | Rename file |
| `ParishProviderCredentialModel` | `parish_provider_credentials` | `ParishProviderCredentialModel` | `parish_provider_credential` | Rename file |
| `ParishRoleAuditModel` | `parish_role_audit_logs` | `ParishRoleAuditModel` | `parish_role_audit_log` | Rename file |
| `AuditLogModel` | `audit_logs` | `AuditLogModel` | `audit_log` | Keep name, change $table |
| `UserSessionModel` | `user_sessions` | `UserSessionModel` | `user_session` | Keep name, change $table |
| `VolunteerTeamModel` | `volunteer_teams` | `TeamModel` | `team` | `app/Models/VolunteerTeamModel.php` → `app/Models/TeamModel.php` |
| `TeamMemberModel` | `team_members` | `TeamMemberModel` | `team_member` | Keep name, change $table |
| `FamilyModel` | `families` | `FamilyModel` | `family` | Keep name, change $table |
| `FamilyMemberModel` | `family_members` | `FamilyMemberModel` | `family_member` | Keep name, change $table; `user_id` → `person_id` |
| `AnnualPledgeModel` | `annual_pledges` | `AnnualPledgeModel` | `annual_pledge` | Keep name, change $table |
| `ServiceModel` | `services` | `ServiceModel` | `service` | Keep name, change $table; handle date/time columns |
| `DonationModel` | `donations` | `DonationModel` | `donation` | Keep name, change $table |
| `GuestModel` | `guests` | `GuestModel` | `guest` | Keep name, change $table |
| `GuestFollowupLogModel` | `guest_followup_log` | `FollowupLogModel` | `followup_log` | Rename class, change $table |
| `CallTaskModel` | `call_tasks` | `CallTaskModel` | `call_task` | Keep name, change $table |

### Step 2: Update All References in Code

**Files requiring updates (non-exhaustive):**

- **Controllers:**
  - `Pastor/Controllers/Members.php` — ParishModel → ParishModel, FamilyModel references
  - `Pastor/Controllers/Services.php` — ServiceModel references
  - `Pastor/Controllers/Donations.php` — DonationModel references
  - `Pastor/Controllers/FollowupWorkflow.php` — GuestFollowupLogModel → FollowupLogModel
  - All Diocesan controllers that reference parish-level data
  - All other module controllers

- **Models:**
  - Update FK declarations (e.g., `$foreignKeys` arrays in models)
  - Update model inheritance/composition references

- **Views:**
  - Search `@extends`, `@include` for partials that reference old model names

- **Services:**
  - `app/Services/ParishPeopleRegistryService.php` → uses ParishModel, FamilyModel
  - `app/Services/MemberMergeService.php` → uses ParishRoleAuditModel
  - Any service that queries old table names directly

---

## Phase 5: Affiliation Migration

### Changes Required

1. **MemberParishAffiliationModel** → Rename to **ParishAffiliationModel**
   - Table: `member_parish_affiliations` → `parish_affiliation`
   - Status enum map (OLD → NEW):
     - `active` → `member`
     - `inactive` → `inactive`
     - `visitor` → `guest`
     - `pending` → `attendee`
     - `regular_attender` → `attendee`

2. **Code Updates:**
   - All references to `$memberParishAffiliationModel` → `$parishAffiliationModel`
   - All status value checks (e.g., `'active'` → `'member'`)
   - All queries filtering on status

3. **Affected Files:**
   - `Pastor/Views/People/index.php` — filter dropdown
   - `Pastor/Controllers/Members.php` — form submission, validation
   - `Pastor/Controllers/People.php` — affiliation CRUD
   - All reports referencing membership status

---

## Execution Order (STAGED)

### Stage 1: Model Renames Only (0 Downtime)
1. Rename model classes
2. Update `$table` properties
3. Update FK and model references within models themselves
4. Commit code changes (migrations NOT run yet)

### Stage 2: Controller/Service Updates (0 Downtime)
1. Update all ParishModel → ParishModel references
2. Update all ServiceModel queries for date/time columns
3. Update all other table references
4. Commit code changes (migrations still NOT run)

### Stage 3: View Updates (0 Downtime)
1. Update any hard-coded table references in views
2. Update model variable names in templates
3. Commit code changes

### Stage 4: Run Migrations (MAINTENANCE WINDOW REQUIRED)
1. Backup database
2. Run Phase 4 migration (renames)
3. Run Phase 5 migration (affiliation migration)
4. Smoke test

### Stage 5: Verify & Cleanup
1. Check that no old table names are referenced anywhere
2. Run full test suite
3. Monitor application for errors

---

## Migrations to Build

- `2026_05_08_040000_Phase4_RenameCoreTablesV2.php`
  - RENAME TABLE statements for all ~15 tables
  - Drop/re-add FKs if needed

- `2026_05_08_050000_Phase5_MigrateAffiliationsV2.php`
  - INSERT INTO parish_affiliation from member_parish_affiliations
  - Status enum mapping
  - Drop old table

---

## Risk Mitigation

| Risk | Mitigation |
|------|-----------|
| Miss a reference to old table name | Comprehensive grep + manual spot-check before running migrations |
| FK constraint violations during rename | Test on dev first; may need to drop/re-add FKs |
| Status mapping ambiguity (pending vs regular_attender) | Both map to `attendee`; audit if this matters for logic |
| Application breaks after migration | Have rollback plan ready; test on staging first |

