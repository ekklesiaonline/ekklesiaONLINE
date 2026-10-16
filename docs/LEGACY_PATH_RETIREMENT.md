# Legacy Path Retirement (Tier 4)

Status: **in progress**. Canonical model: `person` (identity) +
`parish_affiliation` (parish relationship/status) + `person_membership_details`

+ `user_person` (portal linkage). Legacy tables: `profile`, `guest`,
`parish_people`.

## Evidence (dev MySQL `ekklesiaONLINE`, 2026-09-17)

| Check | Result |
| --- | --- |
| `profile.person_id` populated | 29/29 |
| `profile.person_id` dangling (points at missing `person`) | **2 rows** — cleanup needed |
| `parish_affiliation.person_id` populated (non-deleted) | 9/9 |
| `parish_affiliation` legacy status values | 0 stored |
| `parish_people` vs active affiliations | 9 = 9 (parity only because all affiliated persons have portal users) |
| `team_member` rows | 0 (legacy `user_id`-only shape unevidenced) |
| `guest` rows | **0** — legacy table unused in dev |
| `guest.opted_out_channels` column | **Missing in MySQL** though `GuestModel::allowedFields` declares it — latent write error; opt-outs never honored for guests |

## Dual-write coverage (confirmed)

| Direction | Mechanism |
| --- | --- |
| profile → person identity | `PersonProfileSyncService::syncFromProfile` on profile save |
| affiliation → profile scope mirror | `MemberAffiliationService::syncProfileParishMirror` (legacy mirror write) |
| affiliation → parish_people | `ParishPeopleRegistryService::syncParish` (full snapshot rebuild) |
| status → history | `MembershipPathwayService::transition` → `person_status_history` (person_id) |
| guest → person | `PersonConversionService` on conversion |

## Inventory & dispositions

### `parish_people` (derived snapshot; keyed `user_id`/`profile_id`)

Cannot represent non-portal persons — any reader undercounts vs canonical.

**Readers → replace with canonical `parish_affiliation`+`person` queries**

| Site | Usage | Disposition |
| --- | --- | --- |
| `HierarchyService::parishAffiliationsForUser` | user's parish list + hierarchy + `is_primary` | Rewrite on `parish_affiliation` (linked_user_id) + `parish`/`diocese` joins |
| `Diocesan\Controllers\Dashboard` `getMembershipOverview` / `getParishSummaryRows` | diocese-wide counts via `is_member`/`membership_status`/`is_good_standing`/`is_leader` | Rewrite on affiliation + `member_standing_summary` + `leadership_position` |
| `Clergy\Controllers\People` `parish_people_for_dropdown` | member picker (user_id, profile_id, name, person_id) | Rewrite on affiliation + `person` + `profile` |
| `MemberPortal` Profile views | affiliation display via `parishAffiliationsForUser` | Covered by HierarchyService rewrite |
| Merge/cleanup FK lists (`PersonMergeService`, `MemberMergeService`, `PersonCleanupService`) | row moves/deletes during merge | Remove table from lists once dropped |
| `SchemaResolver` | table column introspection | No change needed |

**Writers → remove**

| Site | Usage |
| --- | --- |
| `BaseController::syncParishPeopleForUser` | refresh after affiliation changes |
| `MemberPortal\Controllers\Profile::syncPrimaryParishPeople` | direct `ParishPeopleModel::syncPrimaryParish` write (4 call sites) |
| `Clergy\People::create` → `syncParish` | post-create refresh |
| `PersonMergeService`, `RegistrationApprovalService` (3 sites), `MembershipPathwayService` | post-mutation refreshes |
| `NonPortalMemberProvisioningService` | comments reference snapshot |
| Commands: `SyncParishPeople`, `ParishPeopleIntegrityCheck`, `RepairOrphanedParishPeople`, `SacramentsReconcile` (partial) | maintenance/repair CLIs |
| `ParishPeopleRegistryService`, `ParishPeopleModel` | delete after call sites removed |

**End state:** drop table via migration (user-approved).

### `profile` (legacy user-facing record)

| Path | Disposition |
| --- | --- |
| `BaseController` `profile.province_id`/`diocese_id` fallbacks (lines ~250, ~504, ~545) | Retire fallbacks; `*_admin_primary` tables are canonical. Keep profile read only where `profile` remains the store (portal edit form) |
| `MemberAffiliationService::syncProfileParishMirror` | Retire once no reader needs `profile.parish_id`/`province_id`/`diocese_id` |
| 2 dangling `profile.person_id` rows | Data cleanup + `user_person`-verified relink or null-out |
| `LinkPersonProfiles` command reads `profile.parish_id` | One-time backfill — keep or remove after run audit |

### `guest` (legacy guest + follow-up store)

Follow-up tables are **already person-keyed** (`followup_log.person_id`,
`call_task.person_id`, `followup_action` = config). The `guest` row itself is
thin: contact fields, `visit_date`, `service_attended`, `followed_up`,
`converted_*`, `notes`.

| Path | Disposition |
| --- | --- |
| `guest` row → `person` + `parish_affiliation` (`affiliation_type='guest'`, `membership_status='visitor'`) | Backfill migration |
| `guest_visit` (guest_id) | Re-key to `person_id` or fold into affiliation metadata |
| `converted_to_member_at` / `converted_*` | Map to `MembershipPathwayService` transition (visitor → member) + `person_status_history` |
| `followed_up` flag | Derive from `followup_log` (any executed action) |
| `PeopleMetricsService::total_guests` (legacy + visitor sum) | Drop legacy branch after migration — fixes double-count risk |
| `BulkMessageService::guestRows` | Resolve guests from `parish_affiliation` visitor rows via `person` |
| Guests module (`Clergy\Guests`, `FollowupWorkflow`, `MemberPortal\GuestConversions`, `SendFollowups`, `Import`) | Repoint store to person+affiliation |
| `GuestModel::opted_out_channels` phantom column | Resolved by migration — opt-outs live on `person` |
| `guest` + `guest_visit` tables | Drop via migration after readers/writers repointed |
| `event_registration.guest_id` + `fk_er_guest` | Drop — dead column, never written; dangling FK after `guest` drop |

### T4.5 outcome (implemented 2026-09-18)

+ `2026_09_18_000001_MigrateGuestsToCanonical` backfills `guest` →
  `person` + `parish_affiliation` (`affiliation_type='guest'`,
  `membership_status='visitor'`, `followed_up`, `converted_to_member_at`,
  `converted_by_user_id`, `linked_user_id`) and `guest_visit` →
  `person_visit`, then drops both legacy tables. Idempotent; aborts on
  unresolvable rows.
+ `2026_09_18_000002_DropEventRegistrationGuestFk` drops the dangling
  `fk_er_guest` constraint + dead `event_registration.guest_id` column.
+ `GuestDirectoryService` is the canonical guest-shaped read/write layer over
  `person` + `parish_affiliation` + `person_visit`. `GuestIntakeService`
  remains the canonical intake path. `GuestModel` deleted.
+ `followup_log`/`call_task` are fully `person_id`-keyed; dead `guest_id`
  writes/reads removed. `FollowupLogModel::scheduleActiveSequencesForPerson`
  is the scheduler entry point; `BackfillFollowups` derives visit dates from
  `person_visit`.
+ All consumers rewired: `Clergy\Guests`, `FollowupWorkflow`,
  `GuestConversions`, `Import`, `Members`, `Reports`, `MemberPortal\Dashboard`,
  `Parish\WorshipDashboard`, `PeopleMetricsService`,
  `ParishDashboardDataService`, `FollowupDispatchService`,
  `GuestFollowupService`, `PersonConversionService`, `BulkMessageService`,
  `MemberMergeService`, `ParishDeletionService`, `SendFollowups`,
  `ServiceAttendanceController` (dead dep removed).
+ `MigrateFollowUpTasks` command deleted (target table `follow_up_tasks`
  never existed).
+ Canonical dumps (`canonical_schema*.sql`) updated: `guest`/`guest_visit`
  removed, `call_task`/`followup_log` person-keyed, `parish_affiliation`
  conversion columns + canonical `membership_status` enum,
  `person.interests`, `event_registration.guest_id` removed.
+ Dev MySQL verified: tables dropped, columns added, zero dangling FKs,
  migrations recorded (batches 76–77).

**Note:** dev `event_registration` had an unreproducible divergent shape
(`registration_status`/`person_id`, no `guest_name`/`user_id`) that no
migration or code produced — a pre-existing dev artifact. It was rebuilt to
the canonical dump shape (empty table; `event_registration_response` child
FK preserved).

### Kept compatibility layers (not retired)

+ `MembershipStatusService` legacy alias map (`active→member`,
  `attendee→regular_attender`, `guest→visitor`, `non_active→inactive`) —
  zero-cost normalization for old dumps/imports.
+ `team_member.user_id` → `user_person` resolution — real legacy data shape;
  keep until `team_member.person_id` backfill is evidenced.
+ `profile` table itself — still the portal-editable record pending person
  parity for its remaining fields.

## Execution order

1. T4.2 — parish_people readers → canonical
2. T4.3 — parish_people writers removed; service/model/commands deleted; drop migration
3. T4.4 — profile scope fallbacks + mirror retirement; dangling person_id cleanup
4. T4.5 — guest backfill to person+affiliation; module repoint; drop `guest`/`guest_visit`
