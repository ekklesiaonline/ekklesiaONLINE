# Legacy Rename Enforcement and Cleanup Plan (Step 2)

Date: 2026-05-10
Scope: Legacy-Renamed table group
Owner: Release engineering + backend maintainers

## Objective

Enforce and verify completion of legacy church-to-parish schema renames in production, then schedule removal of temporary compatibility code after 1-2 stable releases.

## Verified in this environment

1. Migration enforcement run completed:
    - Command: `php spark migrate --all`
    - Result: `Migrations complete.`
2. Critical rename/normalization migrations are marked as migrated:
    - `2026_05_09_000007 StandardizeParishIdAcrossAllTables`
    - `2026_05_09_000008 NormalizeChurchScopeEnumsToParish`
    - `2026_05_10_000001 StrictRenameLegacyChurchSchemaObjects`

## Legacy-Renamed table map

- `churches` -> `parish`
- `church_settings` -> `parish_setting`
- `church_integrations` -> `parish_integration`
- `church_provider_credentials` -> `parish_provider_credential`
- `church_role_audit_logs` -> `parish_role_audit_log`
- `church_budgets` -> `parish_budgets`
- `church_people` -> `parish_people`
- `church_report_storage` -> `parish_report_storage`
- `church_financial_summary` -> `parish_report_storage` (older env fallback path)
- `member_church_affiliations` -> `parish_affiliation` (standardization lineage)

## Production enforcement runbook (release gate)

1. Pre-deploy:
    - Run `php spark migrate:status --all`.
    - Confirm the three critical migrations above are listed as migrated.
2. Deploy phase:
    - Run `php spark migrate --all`.
    - Re-run `php spark migrate:status --all` and archive output in release evidence.
3. Post-deploy DB verification (via DBA SQL client):
    - Confirm target tables exist: `parish`, `parish_setting`, `parish_integration`, `parish_provider_credential`, `parish_role_audit_log`, `parish_budgets`, `parish_people`, `parish_report_storage`.
    - Confirm legacy names are absent (except historical backup artifacts outside app schema policy).
4. Smoke verification:
    - Run `sh build/e2e_module_smoke.sh`.
    - Run `BASE_URL=<target> sh build/e2e_admin_levels_smoke.sh`.

## Compatibility cleanup schedule

### Release N (current launch)

- Keep compatibility safeguards in place.
- Keep defensive alias handling where required for mixed-state environments.
- Capture production telemetry and DB evidence for two stable release cycles.

### Release N+1

- Add warnings/log markers when legacy fallback paths are exercised.
- Confirm zero operational reliance on legacy names in logs and support incidents.

### Release N+2 (or after 1-2 stable releases)

- Remove legacy compatibility code paths and migration-era aliases.
- Remove stale command/code references to retired artifacts where safe.
- Re-run full migration + smoke + permission boundary test suite.

## Candidate cleanup targets (post-stability)

1. Legacy fallback logic in models/services that still probes old table names.
2. Any stale comments/docs that still describe `church_*` as canonical names.
3. Retired follow-up migration helper command if no legacy data migration remains operationally required.

## Exit criteria for Step 2 complete

- Rename migrations enforced and verified in release evidence.
- Production runbook includes explicit rename checks.
- Cleanup schedule committed with N, N+1, N+2 milestones.
