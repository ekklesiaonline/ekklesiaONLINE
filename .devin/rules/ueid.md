# Unique Episcopal Identifier (UEID)

## Format & Identifier Boundaries

- Every Episcopal congregation UEID (`parish.parish_ueid`) is an **8-digit numeric code** formatted as `XXXX-XXXX` (`^\d{4}-\d{4}$`).
- **UEIDs are opaque**: never parse digits or prefixes to infer province, diocese, state, or congregation type. Always resolve hierarchy through `parish.diocese_id` → `diocese.province_id` → `province.id`.
- **Authority separation**: UEIDs are assigned by the **Executive Office of the General Convention (DFMS)** (`https://generalconvention.org/ueid-lookup/`). Never store **Church Pension Group (CPG)** employer/MAP IDs, **IRS EINs** (`parish.tax_id`), or **diocesan-office UEIDs** in `parish.parish_ueid`.
- `parish.parish_ueid` is `UNIQUE` (`idx_parish_parish_ueid`) and **read-only** in the parish edit UI (`Diocese/Views/Parishes/edit.php`); never expose it as an editable field for parish/diocesan admins.

## Immutability & Sync Key Invariant

- `parish.parish_ueid` is the stable external sync key and stays with the congregation across diocesan mergers, reunions, or renames; only `parish.diocese_id` updates.
- Never use `mysqldump --replace` on a live database (`REPLACE INTO` deletes/re-inserts conflicting `parish_ueid` rows and orphans downstream foreign keys). Use `build/parish_hierarchy_transfer.sh` only on a fresh empty DB (Path A), and `php spark directory:sync-ueid --apply --preserve-inactive` for live updates (Path B).
- Never hard-delete closed or merged parishes with historical data; deactivate them (`is_active = 0`).

## Canonical Dataset & Parity

- `docs/UEID.json` (primary) and `docs/UEID.csv` (fallback) are the **only canonical shipped UEID datasets** (6,751 parishes across 107 dioceses) and **must remain 100% row- and field-identical**, verified by `tests/database/UeidDirectorySyncTest::testShippedUeidJsonParsesAndMatchesCsv()`.
- `docs/UEID.md` and `php spark parish:merge-ueid` are **legacy**; never use them for live directory syncs, and never reintroduce the removed synthetic test row `2603-1393`.

## General Convention Diocesan Decisions

- Preserve all curated General Convention updates when refreshing or validating UEID data:
  - **GC81 (2024)**: `Eastern Michigan` + `Western Michigan` → `Great Lakes` (C025); `Eau Claire` + `Fond du Lac` + `Milwaukee` → `Wisconsin` (D051); `Navajo Missions` → `Navajoland` (C009); `Micronesia` (Guam & Saipan) → `Hawaii` (B006).
  - **GC80 (2022)**: `North Texas` → `Texas` (C004).
  - **Europe alias**: `Parishes in Europe` → `Churches in Europe` (mapped to `Convocation of Episcopal Churches in Europe` by `SyncUeidDirectory::loadDioceseMap()`).
- When applying newer diocesan reunions (e.g., `Bethlehem` + `Central Pennsylvania` → **Diocese of the Susquehanna**, effective Jan 1, 2026), align target `diocese` / `alias_norm` rows first, update `Diocese` in both `docs/UEID.csv` and `docs/UEID.json` while keeping every `ParishUEID` unchanged, and sync via `directory:sync-ueid`.

## Sync & Readiness Gates

- Always dry-run `php spark directory:sync-ueid` and inspect `writable/reports/ueid_directory_sync_*.md` before `--apply`.
- Pass `--preserve-inactive` on live databases so intentionally deactivated parishes stay inactive.
- Never rely on `--default-province-id=1` in production without immediately reconciling newly created dioceses to their true provinces.
- Confirm `php spark directory:verify-readiness --strict` passes before go-live.

## References

- Canonical documentation: `docs/UEID_REFERENCE.md`
- Skill runbooks: `.agents/skills/ueid/SKILL.md` (or `.devin/skills/ueid/SKILL.md`)
- Sync command & tests: `app/Commands/SyncUeidDirectory.php`, `tests/database/UeidDirectorySyncTest.php`
