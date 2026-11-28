# Unique Episcopal Identifier (UEID) Reference

## 1. Overview & Authority

The **Unique Episcopal Identifier (UEID)** is the unique **8-digit numeric code** assigned to every congregation (parish, mission, cathedral, chapel, or worshiping institution) within The Episcopal Church (TEC).

- **Issuing & Maintaining Authority**: Executive Office of the General Convention (Domestic and Foreign Missionary Society / DFMS, Office of Research & Statistics).
- **Standard Format**: Two 4-digit blocks joined by a hyphen: `XXXX-XXXX` (regex: `^\d{4}-\d{4}$`).
- **Official Lookup Directory**: [General Convention UEID Lookup](https://generalconvention.org/ueid-lookup/) — published as a searchable TablePress directory with six columns: `ParishUEID`, `Diocese`, `Name`, `City`, `State`, `County`.
- **Official General Convention Support**: `reports@episcopalchurch.org` and `pr@episcopalchurch.org` (or the relevant diocesan office / Secretary of Convention).

### Official Churchwide Uses

- **Annual Parochial Report (`https://reports.dfms.org`)**: Mandated under Canons I.6, I.7, and I.17. Historically congregations authenticated using their UEID and a PIN; modern portals use email-based accounts still bound to the congregation's UEID.
- **Episcopal Parish Register App (`https://parishregister.episcopalchurch.org/`)**: Syncs digital service registers with annual Parochial Report submissions via UEID.
- **Diocesan-Level UEIDs (Do Not Assign to Parishes)**: Separate UEIDs exist strictly for diocesan offices tracking worship or events at diocesan-level facilities. Never assign a diocesan-office UEID to a local parish record in `parish.parish_ueid`.

---

## 2. Distinction from Church Pension Group (CPG) & Tax Identifiers

**Church Pension Group (CPG)** (`https://www.cpg.org`) provides pension, health, and property/casualty insurance for clergy and lay employees, serves as the official **Recorder of Ordinations**, operates **My Admin Portal (MAP)** for employer/benefits administration, and publishes *The Episcopal Church Annual* (**The Red Book**) and the *Episcopal Clerical Directory* (**ECD / ECDPlus**) through Church Publishing Incorporated.

While CPG coordinates churchwide institutional data across the Episcopal ecosystem, CPG uses its own institutional/employer account numbers for pension and insurance billing. **Never conflate** a congregation's General Convention UEID with a CPG account number or an IRS Employer Identification Number (EIN).

| Identifier | Stored In (`ekklesiaONLINE`) | Format / Example | Authority | Purpose |
| --- | --- | --- | --- | --- |
| **Parish UEID** | `parish.parish_ueid` (`VARCHAR(32)`, `UNIQUE`) | `XXXX-XXXX` (e.g., `7808-7863`) | Executive Office of the General Convention (DFMS) | Canonical congregation key across TEC, Parochial Reports, and `directory:sync-ueid` |
| **CPG Employer / MAP ID** | *Not stored in `parish_ueid`* | CPG-assigned institutional ID | Church Pension Group (CPG) | Pension/medical/insurance billing, MAP benefits administration, *Red Book* / ECD cross-referencing |
| **IRS Tax ID (EIN)** | `parish.tax_id` (`VARCHAR(50)`) | `XX-XXXXXXX` | IRS (restricted edit per diocese/admin tier) | Tax-deductible giving receipts, financial governance, Parochial Report p. 1 |
| **Internal Primary Key** | `parish.id` (`INT UNSIGNED`) | Auto-increment integer | `ekklesiaONLINE` MySQL DB | Internal foreign-key target across all tenant-scoped tables |
| **Public Token** | `parish.public_token` (`VARCHAR(64)`, `UNIQUE`) | 64-char hex (`bin2hex(random_bytes(32))`) | `ekklesiaONLINE` (`SyncUeidDirectory`) | Opaque public URL/QR identifier for connect cards and public forms |

---

## 3. Format, Opacity & Immutability Rules

1. **8-Digit Hyphenated Format**: Every valid UEID matches `^\d{4}-\d{4}$`. Across the 6,751 shipped records in `docs/UEID.csv` and `docs/UEID.json`, values span `1001-0601` through `9998-3645` with zero duplicates and zero leading-zero prefixes, distributed uniformly across first digits `1`–`9`.
2. **Opaque / Non-Semantic Digits**: The digits of a UEID do **not** encode province, diocese, state, or congregation type. Never parse prefixes or substrings of `parish_ueid` to infer geography or diocesan membership; always resolve parent hierarchy via `parish.diocese_id` → `diocese.province_id` → `province.id`.
3. **Immutability Across Diocesan Changes**: When dioceses merge, reunify, split, or rename, a congregation **retains its existing 8-digit UEID**; only its `Diocese` / `parish.diocese_id` changes.
4. **Read-Only in Parish Edit UI**: In `Diocese/Views/Parishes/edit.php`, `parish_ueid` is rendered as static read-only text (never an editable form field). `App\Models\ParishModel` allows `parish_ueid` search in `paginateWithMemberCount()` and validates `permit_empty|max_length[32]`.
5. **New & Closed Congregations**:
   - Newly planted or admitted congregations receive a new UEID from the Executive Office of the General Convention.
   - Closed or merged congregations are reported by the diocese in Part I of the Diocesan Report ("New and Closed Congregations") and deactivated in `ekklesiaONLINE` (`parish.is_active = 0`) rather than hard-deleted, preserving historical `parish_id` foreign keys.

---

## 4. General Convention Diocesan Decisions & Curated Dataset Lineage

The raw legacy TablePress export (`docs/UEID.md`) contained **6,752 rows across 112 dioceses** (including historical diocesan names and a synthetic test entry `2603-1393`). `ekklesiaONLINE`'s canonical dataset (`docs/UEID.json` and `docs/UEID.csv`) has been curated to **6,751 rows across 106 dioceses** reflecting General Convention and diocesan union decisions:

### Applied General Convention & Diocesan Decisions (Reflected in `docs/UEID.csv` & `docs/UEID.json`)

| Decision / Event | Legacy Name(s) in `docs/UEID.md` | Canonical Name in `docs/UEID.csv` & `docs/UEID.json` | Row Count | Notes |
| --- | --- | --- | ---: | --- |
| **81st GC (2024) — Resolution C025** (Juncture) | `Eastern Michigan` + `Western Michigan` | `Great Lakes` | 97 | Merger forming the Episcopal Diocese of the Great Lakes |
| **81st GC (2024) — Resolution D051** (Reunion) | `Eau Claire` + `Fond du Lac` + `Milwaukee` | `Wisconsin` | 99 | Reunion of the three Wisconsin dioceses into the Episcopal Diocese of Wisconsin |
| **81st GC (2024) — Resolution C009** (Elevation) | `Navajo Missions` | `Navajoland` | 10 | Elevated from area mission to Missionary Diocese of Navajoland |
| **81st GC (2024) — Resolution B006** (Expansion) | `Micronesia` (Guam & Saipan, 2 rows: `1462-9869`, `9713-0538`) | `Hawaii` | 37 | Dissolved separate Micronesia mission; absorbed into the Diocese of Hawaii |
| **Canon I.10.6 Reunion (eff. Jan 1, 2026)** | `Bethlehem` (56) + `Central Pennsylvania` (61) | `Susquehanna` | 117 | Reunited as the Episcopal Diocese of the Susquehanna (Joint Convention Oct 17–18, 2025) |
| **80th GC (2022) — Resolution C004** (Reunion) | `North Texas` (incl. bogus test row `2603-1393`) | `Texas` | 165 | Reunited into Diocese of Texas; `2603-1393` (`"Not a real parish"`) removed |
| **Naming Alignment** | `Parishes in Europe` | `Churches in Europe` | 20 | Mapped to `Convocation of Episcopal Churches in Europe` in `SyncUeidDirectory::loadDioceseMap()` |

### Applying Future Diocesan Reunions or Mergers

When future General Conventions or diocesan agreements of union merge or rename dioceses:
1. Ensure the target `diocese` row (and `diocese.alias_norm`) exists under the proper `province_id` in the database, reparent any `parish.diocese_id` and active references inside a single transaction, and clean up dissolved zero-parish `diocese` rows (along with any orphaned `diocese_setting` rows).
2. Update the `Diocese` column for the affected congregations in **both** `docs/UEID.csv` and `docs/UEID.json` while keeping every `ParishUEID` untouched.
3. Run `php spark directory:sync-ueid` (dry-run), verify `writable/reports/ueid_directory_sync_*.md` shows zero errors, then apply with `php spark directory:sync-ueid --apply --preserve-inactive` and confirm `php spark directory:verify-readiness --strict`.

---

## 5. Shipped Files, Database Schema & Known Upstream Data Quirks

### Shipped Repository Files

- **`docs/UEID.json`** (primary) and **`docs/UEID.csv`** (fallback): Canonical 6,751-row, 106-diocese directory (`ParishUEID`, `Diocese`, `Name`, `City`, `State`, `County`). Both files **must remain 100% row- and field-identical**, enforced by `tests/database/UeidDirectorySyncTest::testShippedUeidJsonParsesAndMatchesCsv()`.
- **`docs/UEID.md`**: Legacy raw TablePress HTML snapshot (6,752 rows, 112 dioceses, contains bogus row `2603-1393`). Used only by the legacy `php spark parish:merge-ueid` command (`app/Commands/MergeUeidParishes.php`). **Do not use for production syncs or new features.**

### Database Schema (`app/Database/canonical_schema_tables.sql`)

- `province`: `id`, `province_number` (`VARCHAR(10) UNIQUE NOT NULL`), `name`, `sort_order`.
- `diocese`: `id`, `province_id` (`NOT NULL`, FK → `province.id` `ON DELETE RESTRICT ON UPDATE CASCADE`), `name`, `alias_norm` (`VARCHAR(255) NULL`, indexed).
  - Note: `diocese_alias` is an optional legacy lookup table not created by baseline migrations and not exported by `build/parish_hierarchy_transfer.sh`. `SyncUeidDirectory` reads `diocese.name`, `diocese.alias_norm`, and (if present) `diocese_alias`.
- `parish`: `id`, `name`, `parish_ueid` (`VARCHAR(32) NULL`, `UNIQUE KEY idx_parish_parish_ueid`), `is_active` (`TINYINT(1) DEFAULT 1`), `diocese_id` (FK → `diocese.id` `ON DELETE RESTRICT ON UPDATE CASCADE`), `public_token` (`VARCHAR(64) NULL`, `UNIQUE KEY uniq_parish_public_token`), `city`, `state`, `county`, `deleted_at`.

### Known Upstream Data Quirks in `docs/UEID.csv` / `docs/UEID.json`

Do not treat these upstream TablePress artifacts as sync bugs unless intentionally curating address fields:

- **1 row with empty `City`**: `8969-3071` (`Virgin Islands`, `St Paul's Episcopal Church`, `State = ""`, `County = "Tortola"`).
- **Empty `State` (447 rows) / `County` (346 rows)**: Normal for non-US jurisdictions in Provinces II, VIII, and IX (e.g., `Haiti`, `Honduras`, `Dominican Republic`, `Cuba`, `Taiwan`, `Churches in Europe`).
- **9 `Ecuador-Litoral` rows with `State = "Guam"`**: Upstream TablePress code collision (Guayas / `GU` mapped to `"Guam"` in the General Convention directory for 9 Guayaquil/Duran congregations).

---

## 6. Operational Workflows

### A. Syncing UEID Directory to the Database (`directory:sync-ueid`)

`App\Commands\SyncUeidDirectory` (`php spark directory:sync-ueid`) uses `ParishUEID` as the stable upsert key:

```bash
# 1. Always dry-run first (defaults to docs/UEID.json, falling back to docs/UEID.csv)
php spark directory:sync-ueid

# 2. Inspect the generated report
ls -lt writable/reports/ueid_directory_sync_*.md | head -n 1

# 3. Apply safely on an existing database (preserves intentionally deactivated parishes)
php spark directory:sync-ueid --apply --preserve-inactive

# 4. Verify hierarchy readiness (go-live strict gate)
php spark directory:verify-readiness --strict
```

**Important operational rules**:
- Never pass `--default-province-id=1` in production unless you immediately reconcile newly created dioceses to their true provinces (`Provinces I–IX`).
- Only pass `--deactivate-missing` after reviewing the dry-run report to ensure intentional non-UEID or custom parishes will not be unexpectedly deactivated (the flag only targets rows where `parish_ueid IS NOT NULL AND parish_ueid != ''`).
- Never use `mysqldump --replace` against a live database: `REPLACE INTO` deletes and re-inserts conflicting `parish_ueid` rows, changing `parish.id` or failing foreign-key checks. Use `build/parish_hierarchy_transfer.sh` only for fresh empty-destination cutovers (Path A in `docs/HOSTGATOR_DEPLOY.md` §4.1), and `directory:sync-ueid --apply` for live updates (Path B).

### B. Refreshing `docs/UEID.csv` & `docs/UEID.json` from Upstream

1. Export updated records from `https://generalconvention.org/ueid-lookup/`.
2. Validate every `ParishUEID` matches `^\d{4}-\d{4}$` with zero duplicates, and verify synthetic row `2603-1393` is absent.
3. Re-apply or confirm General Convention diocesan consolidations (`Great Lakes`, `Wisconsin`, `Navajoland`, `Hawaii` with Guam/Saipan, `Texas` with North Texas, `Churches in Europe`, plus any newly effective reunions such as `Susquehanna`).
4. Regenerate `docs/UEID.json` and `docs/UEID.csv` so both are byte-clean, UTF-8, and structurally identical.
5. Run the test suite:
   ```bash
   vendor/bin/phpunit --no-coverage tests/database/UeidDirectorySyncTest.php
   ```

### C. Consolidating a Legacy No-UEID Parish into a Canonical UEID Parish

When a parish was originally created manually without a `parish_ueid` and a canonical UEID row also exists:
1. Back up the database (`sh build/backup_database.sh`).
2. Check for unique-key collisions (`annual_pledges`, `parish_budgets`, `funds`, `parish_affiliation`).
3. Reassign all parish-scoped foreign keys to the canonical UEID `parish.id` inside a single SQL transaction, and deactivate (`is_active = 0`) the legacy row rather than hard-deleting it.
4. Reference precedent: `docs/LA_TRANSFIGURACION_CONSOLIDATION_REPORT_2026-04-07.md`.
