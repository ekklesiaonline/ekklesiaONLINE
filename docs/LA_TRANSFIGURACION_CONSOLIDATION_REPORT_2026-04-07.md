# La Transfiguracion Consolidation Report (2026-04-07)

## Objective

Move all data currently tied to the no-UEID church record La Transfiguracion into the canonical UEID church record Iglesia Episcopal La Transfiguracion (UEID 9037-1469).

## Identified Records

- Source church (no UEID):
  - id: 1
  - name: La Transfiguracion
  - parish_ueid: NULL
  - diocese_id: 104
- Target church (canonical UEID):
  - id: 1443
  - name: Iglesia Episcopal La Transfiguracion
  - parish_ueid: 9037-1469
  - diocese_id: 104

## Impacted Rows (church_id = 1)

- annual_pledges: 1
- audit_logs: 9
- church_budgets: 26
- church_role_audit_logs: 13
- donations: 6
- event_registrations: 15
- events: 1
- expenses: 1
- families: 3
- funds: 5
- leadership_positions: 4
- member_church_affiliations: 20
- profile: 17
- service_worship_items: 10
- services: 25
- volunteer_teams: 1
- worship_songs: 1

## Conflict Check (Unique-Key Collisions)

Checked for collisions that could fail updates when moving from church_id = 1 to church_id = 1443.

- annual_pledges (user_id, church_id, pledge_year): 0 conflicts
- church_budgets (church_id, chart_account_id, fiscal_year): 0 conflicts
- funds (church_id, name): 0 conflicts
- member_church_affiliations (user_id, church_id): 0 conflicts

Result: Safe to proceed with direct reassignment in a single transaction.

## Recommended Execution Plan

1. Create a DB backup first.
2. Run pre-check SQL to confirm source/target IDs are unchanged.
3. Run transactional update script.
4. Validate counts moved and confirm no remaining rows on source.
5. Deactivate source church record (keep for traceability).

## Pre-Check SQL

USE ekklesiaRD;

SELECT id, name, parish_ueid, diocese_id, is_active
FROM churches
WHERE id IN (1, 1443)
ORDER BY id;

## Transaction SQL (Move All Data)

USE ekklesiaRD;

START TRANSACTION;

UPDATE annual_pledges SET church_id = 1443 WHERE church_id = 1;
UPDATE audit_logs SET church_id = 1443 WHERE church_id = 1;
UPDATE church_budgets SET church_id = 1443 WHERE church_id = 1;
UPDATE church_role_audit_logs SET church_id = 1443 WHERE church_id = 1;
UPDATE donations SET church_id = 1443 WHERE church_id = 1;
UPDATE event_registrations SET church_id = 1443 WHERE church_id = 1;
UPDATE events SET church_id = 1443 WHERE church_id = 1;
UPDATE expenses SET church_id = 1443 WHERE church_id = 1;
UPDATE families SET church_id = 1443 WHERE church_id = 1;
UPDATE funds SET church_id = 1443 WHERE church_id = 1;
UPDATE leadership_positions SET church_id = 1443 WHERE church_id = 1;
UPDATE member_church_affiliations SET church_id = 1443 WHERE church_id = 1;
UPDATE profile SET church_id = 1443 WHERE church_id = 1;
UPDATE service_worship_items SET church_id = 1443 WHERE church_id = 1;
UPDATE services SET church_id = 1443 WHERE church_id = 1;
UPDATE volunteer_teams SET church_id = 1443 WHERE church_id = 1;
UPDATE worship_songs SET church_id = 1443 WHERE church_id = 1;

-- Keep source row for audit history, but deactivate it.
UPDATE churches
SET is_active = 0, updated_at = NOW()
WHERE id = 1;

COMMIT;

## Post-Validation SQL

USE ekklesiaRD;

-- Should all be zero after move:
SELECT 'annual_pledges' AS t, COUNT(*) AS c FROM annual_pledges WHERE church_id = 1
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs WHERE church_id = 1
UNION ALL SELECT 'church_budgets', COUNT(*) FROM church_budgets WHERE church_id = 1
UNION ALL SELECT 'church_role_audit_logs', COUNT(*) FROM church_role_audit_logs WHERE church_id = 1
UNION ALL SELECT 'donations', COUNT(*) FROM donations WHERE church_id = 1
UNION ALL SELECT 'event_registrations', COUNT(*) FROM event_registrations WHERE church_id = 1
UNION ALL SELECT 'events', COUNT(*) FROM events WHERE church_id = 1
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses WHERE church_id = 1
UNION ALL SELECT 'families', COUNT(*) FROM families WHERE church_id = 1
UNION ALL SELECT 'funds', COUNT(*) FROM funds WHERE church_id = 1
UNION ALL SELECT 'leadership_positions', COUNT(*) FROM leadership_positions WHERE church_id = 1
UNION ALL SELECT 'member_church_affiliations', COUNT(*) FROM member_church_affiliations WHERE church_id = 1
UNION ALL SELECT 'profile', COUNT(*) FROM profile WHERE church_id = 1
UNION ALL SELECT 'service_worship_items', COUNT(*) FROM service_worship_items WHERE church_id = 1
UNION ALL SELECT 'services', COUNT(*) FROM services WHERE church_id = 1
UNION ALL SELECT 'volunteer_teams', COUNT(*) FROM volunteer_teams WHERE church_id = 1
UNION ALL SELECT 'worship_songs', COUNT(*) FROM worship_songs WHERE church_id = 1;

-- Confirm destination growth:
SELECT 'annual_pledges' AS t, COUNT(*) AS c FROM annual_pledges WHERE church_id = 1443
UNION ALL SELECT 'audit_logs', COUNT(*) FROM audit_logs WHERE church_id = 1443
UNION ALL SELECT 'church_budgets', COUNT(*) FROM church_budgets WHERE church_id = 1443
UNION ALL SELECT 'church_role_audit_logs', COUNT(*) FROM church_role_audit_logs WHERE church_id = 1443
UNION ALL SELECT 'donations', COUNT(*) FROM donations WHERE church_id = 1443
UNION ALL SELECT 'event_registrations', COUNT(*) FROM event_registrations WHERE church_id = 1443
UNION ALL SELECT 'events', COUNT(*) FROM events WHERE church_id = 1443
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses WHERE church_id = 1443
UNION ALL SELECT 'families', COUNT(*) FROM families WHERE church_id = 1443
UNION ALL SELECT 'funds', COUNT(*) FROM funds WHERE church_id = 1443
UNION ALL SELECT 'leadership_positions', COUNT(*) FROM leadership_positions WHERE church_id = 1443
UNION ALL SELECT 'member_church_affiliations', COUNT(*) FROM member_church_affiliations WHERE church_id = 1443
UNION ALL SELECT 'profile', COUNT(*) FROM profile WHERE church_id = 1443
UNION ALL SELECT 'service_worship_items', COUNT(*) FROM service_worship_items WHERE church_id = 1443
UNION ALL SELECT 'services', COUNT(*) FROM services WHERE church_id = 1443
UNION ALL SELECT 'volunteer_teams', COUNT(*) FROM volunteer_teams WHERE church_id = 1443
UNION ALL SELECT 'worship_songs', COUNT(*) FROM worship_songs WHERE church_id = 1443;

SELECT id, name, parish_ueid, is_active, updated_at
FROM churches
WHERE id IN (1, 1443)
ORDER BY id;

## Rollback Strategy

If any validation fails before COMMIT, run:

- ROLLBACK;

If COMMIT already happened and business rollback is required:

- restore from backup created in step 1.

## Operational Notes

- This report intentionally keeps church id 1 for history but deactivates it.
- Do not delete church id 1 unless all downstream external references are audited.
- member_standing_summary is user/year scoped and has no church_id column; church scope is applied via member_church_affiliations at read time.
- If desired, a follow-up cleanup can normalize country/state values on church id 1443 after consolidation.
