---
name: ueid
description: >-
  Explain, look up, validate, sync, or refresh 8-digit Unique Episcopal Identifiers (UEID)
  and diocesan directory mappings in ekklesiaONLINE. Use when working with parish_ueid,
  docs/UEID.json or docs/UEID.csv, General Convention diocese mergers/creations,
  php spark directory:sync-ueid, parish consolidation, or distinguishing UEIDs from
  Church Pension Group (CPG) IDs and EINs.
---

# Unique Episcopal Identifier (UEID) Operations

Follow `.agents/rules/ueid.md` (or `.devin/rules/ueid.md`) and consult `docs/UEID_REFERENCE.md` for full background, General Convention lineage, and upstream data quirks.

## Key Sources

- **Reference & Rules**: `docs/UEID_REFERENCE.md`, `.agents/rules/ueid.md`
- **Canonical Data**: `docs/UEID.json` (primary), `docs/UEID.csv` (fallback) — 6,751 rows, 107 dioceses (legacy `docs/UEID.md` is retired)
- **Commands**: `app/Commands/SyncUeidDirectory.php` (`php spark directory:sync-ueid`), `app/Commands/VerifyDirectoryReadiness.php` (`php spark directory:verify-readiness`)
- **Cutover & Model**: `build/parish_hierarchy_transfer.sh`, `app/Models/ParishModel.php`, `app/Services/HierarchyService.php`, `Diocese/Views/Parishes/edit.php`
- **Tests**: `tests/database/UeidDirectorySyncTest.php`
- **External Lookup & Support**: `https://generalconvention.org/ueid-lookup/`, `https://reports.dfms.org`, `reports@episcopalchurch.org`, `pr@episcopalchurch.org`

---

## Playbook 1: Look Up & Validate a Congregation UEID

1. **Validate syntax**: Confirm `^\d{4}-\d{4}$` (8 digits in two 4-digit blocks with a hyphen, e.g., `7808-7863`). Treat the code as opaque; never infer diocese, province, or state from its digits.
2. **Disambiguate identifier type**:
   - **Parish UEID (`parish.parish_ueid`)**: General Convention / DFMS congregation ID used for Parochial Reports and `directory:sync-ueid`.
   - **CPG Employer / MAP ID**: Church Pension Group benefits/billing ID — never store in `parish_ueid`.
   - **IRS EIN (`parish.tax_id`)**: Tax identifier — stored separately in `parish.tax_id`.
   - **Diocesan-office UEID**: Reserved for diocesan facilities — never assign to a local parish.
3. **Search locally first**: Query `docs/UEID.csv` / `docs/UEID.json` or the `parish` table by `parish_ueid`, `Name`, `Diocese`, or `City`.
4. **Verify upstream**: Search `https://generalconvention.org/ueid-lookup/` (filtering by city or diocese) or contact the diocesan office / `reports@episcopalchurch.org`.

---

## Playbook 2: Sync the UEID Directory (`directory:sync-ueid`)

1. **Check parent hierarchy first**: Confirm real `province` rows and target `diocese` rows (with `alias_norm` populated) exist. Avoid `--default-province-id=1` in production unless immediately reconciling province assignments afterward.
2. **Run dry-run**:
   ```bash
   php spark directory:sync-ueid
   ```
3. **Inspect the dry-run report**: Read the newest `writable/reports/ueid_directory_sync_*.md`. Check `Errors`, `Dioceses Created`, `Parishes Updated`, and `Parishes Reactivated`.
4. **Apply safely on live databases**:
   ```bash
   php spark directory:sync-ueid --apply --preserve-inactive
   ```
   - Pass `--preserve-inactive` so closed/deactivated parishes stay `is_active = 0`.
   - Only pass `--deactivate-missing` after auditing the dry-run report to verify no intentional active `parish_ueid` records would be deactivated.
   - Never use `mysqldump --replace` on a live database (`build/parish_hierarchy_transfer.sh` is strictly for fresh empty-DB cutover before user/profile data exists).
5. **Verify readiness**:
   ```bash
   php spark directory:verify-readiness --strict
   ```

---

## Playbook 3: Refresh `docs/UEID.csv` & `docs/UEID.json` (Including GC Decisions)

When updating the shipped files from `https://generalconvention.org/ueid-lookup/` or applying a new General Convention diocesan decision:

1. **Preserve GC80 & GC81 curated dioceses** (do not revert to legacy TablePress names):
   - `Great Lakes` (merged `Eastern Michigan` + `Western Michigan`, GC81 C025)
   - `Wisconsin` (reunited `Eau Claire` + `Fond du Lac` + `Milwaukee`, GC81 D051)
   - `Navajoland` (elevated from `Navajo Missions`, GC81 C009)
   - `Hawaii` (includes Guam `1462-9869` & Saipan `9713-0538` formerly under `Micronesia`, GC81 B006)
   - `Texas` (reunited `North Texas`, GC80 C004; synthetic `2603-1393` must remain excluded)
   - `Churches in Europe` (mapped to `Convocation of Episcopal Churches in Europe`)
2. **Apply newer diocesan reunions** (such as `Bethlehem` + `Central Pennsylvania` → **Susquehanna**, effective Jan 1, 2026 under Canon I.10.6):
   - Create or rename the canonical `diocese` row (and `alias_norm`) in the database first.
   - Update only the `Diocese` column in **both** `docs/UEID.csv` and `docs/UEID.json`; keep every `ParishUEID` immutable.
3. **Verify file parity & format**: Confirm `docs/UEID.json` and `docs/UEID.csv` have identical columns (`ParishUEID,Diocese,Name,City,State,County`), identical row counts and values, zero duplicates, all rows matching `^\d{4}-\d{4}$`, and no `2603-1393`.
4. **Run parity & sync tests**:
   ```bash
   /opt/homebrew/bin/php vendor/bin/phpunit --no-coverage tests/database/UeidDirectorySyncTest.php
   ```

---

## Playbook 4: New, Merged, Closed, or Legacy No-UEID Parishes

- **Diocese merger / transfer**: Keep `parish.parish_ueid` unchanged; update `parish.diocese_id` via `directory:sync-ueid --apply --preserve-inactive`.
- **Closed / merged congregation**: Deactivate (`is_active = 0`) rather than deleting the row so financial, membership, and sacramental foreign keys remain intact.
- **Legacy no-UEID parish consolidation**: Follow the transactional reparenting + deactivation pattern documented in `docs/LA_TRANSFIGURACION_CONSOLIDATION_REPORT_2026-04-07.md` (backup first, check unique-key collisions, reassign all `parish_id` FKs in one transaction, set `is_active = 0` on the legacy row).
