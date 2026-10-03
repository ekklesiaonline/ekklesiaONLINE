---
name: data-integrity
description: Diagnose or safely change ekklesiaRD parish, person, profile, affiliation, membership, sacrament, snapshot, and schema-alignment data. Use for missing-person/parish issues, manual repairs, reconciliation, migrations, or queries involving canonical identity and lifecycle state.
---

# Parish Data Integrity

Use `.agents/rules/parish-domain.md` (or `.devin/rules/parish-domain.md`) and verify the current model/migration before changing data. Prefer domain services and transactions over ad-hoc writes. Scope every operation by `parish_id` unless the operator is explicitly elevated.

## Canonical identity links

For a portal person, verify this chain in order:

1. `users` and Shield identity.
2. `person` canonical row (`person.id` is the identity key).
3. `user_person` mapping.
4. `profile.person_id` and `profile.parish_id` plus diocese/province where applicable.
5. `parish_affiliation` with `linked_user_id`, `person_id`, and `parish_id`.
6. `parish_admin_primary` only when the user is the primary parish administrator.

`/people/view/{id}` accepts a `profile_id`; downstream services must receive `profile.person_id`. People counts use distinct `parish_affiliation.person_id`, not `linked_user_id` or `profile`.

## Lifecycle invariants

- `parish_affiliation.membership_status` is the only stored lifecycle state.
- New affiliations start as `visitor`; promote explicitly through `MembershipPathwayService` or the approved conversion flow.
- Normalize legacy aliases with `MembershipStatusService`; never store `guest` or `active` aliases.
- `is_member` is generated and means on-register (`member` or `inactive`); active-member checks are stricter.
- Good standing, pathway stage, and leadership positions are separate domains.
- Do not conflate event `guest_*` plus-one fields with visitor lifecycle records.

## Repair workflow

1. Read the relevant model, migration, service, and route before writing.
2. Identify the exact missing or stale link and the affected parish.
3. Show the intended changes before manual SQL or use a safe service command.
4. Use a transaction and prepared/query-builder operations; never log contact data or credentials.
5. Re-sync derived data after canonical changes:
   - `php spark parish-people:sync`
   - `php spark parish-people:integrity --repair --parish=<id>` for a single-parish repair
6. For sacrament changes, treat `sacramental_record` as canonical and call `SacramentFlagSyncService`; compare types with `LOWER()` and remember `is_communicant` is attendance-based.
7. Run `php build/verify_alignment.php` after schema/table-name changes and inspect warnings instead of hiding them.
8. Verify targeted tests, then `vendor/bin/phpunit --no-coverage`.

## Authority and safety

Use `RegistrationApprovalService` for registration approve/dismiss/ban/delete operations. Use `LeadershipPositionService::eligibilityFor()` for leadership eligibility. Do not bypass pending-review gates or repair a higher-scope record from a lower-tier admin. Manual data mutation requires explicit user authorization; diagnostic reads do not.

## References

- `.agents/rules/parish-domain.md`
- `.agents/skills/debug-no-parish/SKILL.md`
- `.agents/skills/debug-person-not-found/SKILL.md`
- `.agents/skills/parish-people-snapshot/SKILL.md`
- `.agents/skills/sacrament-sync/SKILL.md`
- `docs/VISITOR_LIFECYCLE.md`
- `docs/PROFILE_PICTURE_SECURITY_AND_DELIVERY.md`
- `build/verify_alignment.php`
