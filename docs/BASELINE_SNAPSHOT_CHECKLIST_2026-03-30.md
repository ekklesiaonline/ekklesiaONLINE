# Baseline Snapshot Checklist (2026-03-30)

Purpose: lock a known-good baseline before redesign work.

## 1) Database Baseline

- [x] Translation tables are absent.
  - Check:
    - `SELECT COUNT(*) AS translation_tables_remaining FROM information_schema.tables WHERE table_schema='ekklesiaRD' AND table_name IN ('chart_account_translations','liturgical_entry_translations');`
  - Result: `0`

- [x] Developer rollback account artifacts are absent.
  - Checks:
    - `SELECT COUNT(*) AS developer_users FROM users WHERE username='developer';`
    - `SELECT COUNT(*) AS developer_identities FROM auth_identities WHERE secret='developer@ekklesiard.org';`
  - Results: `0`, `0`

- [x] Tier-1 migration versions are absent from migration history.
  - Check:
    - `SELECT version FROM migrations WHERE version IN ('2026_03_30_000001','2026_03_30_000002');`
  - Result: no rows returned

## 2) Code Baseline

- [x] No explicit references found for removed translation artifacts or temporary developer seeders in app/module/docs scan.
  - Search patterns:
    - `chart_of_accounts.manage|liturgical_source.manage|developer@ekklesiard.org|SystemDeveloperSeeder|LiturgicalTranslationsSeeder|chart_account_translations|liturgical_entry_translations`
  - Result: no matches

- [x] Route-level translation permission filters are not present in diocesan routes.
  - Search patterns:
    - `permission:chart_of_accounts.manage|permission:liturgical_source.manage`
  - Result: no matches

- [x] Key baseline files pass PHP syntax checks.
  - Files checked:
    - `app/Config/AuthGroups.php`
    - `app/Database/Seeds/UnifiedAppSeeder.php`
    - `Diocesan/Config/Routes.php`
    - `Diocesan/Controllers/ChartOfAccounts.php`
    - `Diocesan/Controllers/LiturgicalCalendar.php`
    - `app/Models/ChartOfAccountModel.php`
    - `app/Models/LiturgicalCalendarEntryModel.php`

## 3) File Fingerprints (SHA-256)

Use these hashes to detect drift before/after rethink implementation.

- `066d9c42d515fc71a73640b21fbf1ff3f7862e1d6e709233a7bbf79c1bd193a0`  `app/Config/AuthGroups.php`
- `7bd00999eaa4746183fb4992837f3a4f31a66ad08e66f5fbe53eff6770fa502d`  `app/Database/Seeds/UnifiedAppSeeder.php`
- `978492271323c6467ad90b25a80657d341b27f79a22856d4a0c266d8dc546ab9`  `Diocesan/Config/Routes.php`
- `f0d956472094d88093820224394fdd24e764c8e8421365463001f30e5d71de13`  `Diocesan/Controllers/ChartOfAccounts.php`
- `36a20c7696c28fd5718752153d887c36e06cddcc583a25f42c2226e454bf4902`  `Diocesan/Controllers/LiturgicalCalendar.php`
- `b2c8ad104876425885242573c5ea097b86108661246b227c2f4248e2f05dd819`  `app/Models/ChartOfAccountModel.php`
- `0212e8de829d49663496c2da69d1fca8eeb8abc22b6dfcf6899c06eff7b8256b`  `app/Models/LiturgicalCalendarEntryModel.php`

## 4) Workspace Note

- [x] No git repository detected at workspace root (`/Users/juanrosariodelacruz/Sites/ekklesiaRD`).
- Action for future snapshots:
  - If needed, initialize git or run snapshots from the actual repo root to track diffs over time.

## 5) Pre-Rethink Gate (run this before starting new implementation)

- [ ] Re-run database checks in Section 1.
- [ ] Re-run syntax checks in Section 2.
- [ ] Recompute hashes in Section 3 and compare.
- [ ] Confirm no unexpected edits in `app/Models/LiturgicalCalendarEntryModel.php` since this file was recently changed outside the prior flow.
