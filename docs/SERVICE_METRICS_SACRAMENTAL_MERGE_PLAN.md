# Service Metrics + Sacramental Counts Merge Plan

**Date:** 2026-04-20  
**Status:** Proposal — pending decision on three design choices (§3)

---

## 1. The Problem

Two satellite tables sit in a one-to-one relationship with `services.id`:

| Table                            | PK           | Purpose                                           |
|----------------------------------|--------------|---------------------------------------------------|
| `service_metrics`                | `service_id` | Operational analytics + classification metadata   |
| `service_sacramental_counts`     | `service_id` | Sacramental event counts for TEC Parochial Report |

A third change (migration `2026_04_20_000006`) also added two Parochial-Report viewer columns **directly onto the `services` table**:

- `services.online_live_viewers`
- `services.online_recorded_views`

All three destinations ultimately feed the same annual TEC Parochial Report. This forces the Services controller to do three separate upserts per save and forces the data-entry UI to split conceptually related fields across two sections.

---

## 2. Merge Strategy

### 2.1 Column inventory

**`service_sacramental_counts` columns → move to `service_metrics`**

| Column                    | Type     | Can be derived?                        | Action                   |
|---------------------------|----------|----------------------------------------|--------------------------|
| `baptism_adult`           | SMALLINT | **No** — no source field to count from | Add to `service_metrics` |
| `baptism_child`           | SMALLINT | **No**                                 | Add to `service_metrics` |
| `confirmation_adult`      | SMALLINT | **No**                                 | Add to `service_metrics` |
| `confirmation_teen`       | SMALLINT | **No**                                 | Add to `service_metrics` |
| `reception_count`         | SMALLINT | **No**                                 | Add to `service_metrics` |
| `marriage_count`          | SMALLINT | **No**                                 | Add to `service_metrics` |
| `burial_count`            | SMALLINT | **No**                                 | Add to `service_metrics` |
| `eucharist_count`         | SMALLINT | **Yes** — see §3.1                     | Design choice needed     |
| `daily_office_count`      | SMALLINT | **Yes** — see §3.2                     | Design choice needed     |

**`services` table viewer columns (from migration 000006) → move to `service_metrics`**

| Column                    | Currently on  | Move to           | Why                                                                                                      |
|---------------------------|---------------|-------------------|----------------------------------------------------------------------------------------------------------|
| `online_live_viewers`     | `services`    | `service_metrics` | All non-core service data lives in `service_metrics`; `services` should be core liturgical metadata only |
| `online_recorded_views`   | `services`    | `service_metrics` | Same reason                                                                                              |

These remain distinct from the existing operational fields:

| Existing `service_metrics` field  | Purpose                                                           | Keep?                      |
|-----------------------------------|------------------------------------------------------------------ |----------------------------|
| `online_live_views`               | Raw platform-reported view count (may double-count same viewer)   | **Yes** — different number |
| `online_recorded_views_7d`        | 7-day rolling window of recording plays                           | **Yes** — different number |
| `online_peak_viewers`             | Max concurrent viewers at any moment                              | **Yes** — different number |

The new `online_live_viewers` and `online_recorded_views` are the *administrator-curated, officially submitted* counts (unique households, deduplicated) used in the annual return — they will coexist with the raw analytics fields.

### 2.2 Target schema for `service_metrics` after merge

```js
service_metrics
├── service_id               PK / FK → services.id
├── format_of_service        ENUM (In-Person|Hybrid|Online)
├── attendance_classification ENUM (Regular|Special)
├── service_family           ENUM (holy_communion|daily_office|other)   ← ENUM expanded (§3.2)
├── service_schedule         ENUM (sunday|weekday|private)
├── reported_present_count   INT
├── reported_communion_count INT
│
├── — Sacramental counts (moved from service_sacramental_counts) —
├── baptism_adult            SMALLINT
├── baptism_child            SMALLINT
├── confirmation_adult       SMALLINT
├── confirmation_teen        SMALLINT
├── reception_count          SMALLINT
├── marriage_count           SMALLINT
├── burial_count             SMALLINT
├── eucharist_count          SMALLINT   ← if design choice A (§3.1)
├── daily_office_count       SMALLINT   ← if design choice A (§3.2)
│
├── — Online analytics (existing) —
├── online_live_views        INT   (raw platform count)
├── online_recorded_views_7d INT   (7-day rolling)
├── online_peak_viewers      INT   (peak concurrent)
│
├── — Online Parochial Report (moved from services table) —
├── online_live_viewers      INT   (official curated unique count)
├── online_recorded_views    INT   (official curated recorded count)
│
├── languages_json           JSON
├── place_labels_json        JSON
├── created_at / updated_at
```

`service_sacramental_counts` table → **dropped**.  
`services.online_live_viewers` and `services.online_recorded_views` → **dropped**.

---

## 3. Design Choices Needed

These three decisions change the scope of the migration. Pick one option for each and record the decision before implementation begins.

### 3.1 `eucharist_count` — store vs. derive

**Option A — Store explicitly (recommended)**  
Keep `eucharist_count` as a writable field in `service_metrics`. Reason: a single liturgical event could contain multiple celebrations (e.g., a Cathedral Chrismal with two Eucharists), which cannot be inferred by simply counting rows with `service_family = 'holy_communion'`. The count-per-row stays at 1 unless the administrator says otherwise.

**Option B — Derive at report time**  
Remove `eucharist_count` column. The annual Parochial Report query counts `service_metrics` rows where `service_family = 'holy_communion'` within the year. Simpler schema; loses accuracy for multi-celebration events.

**Recommendation: Option A.** The field stays in `service_metrics`, defaulting to `1` when `service_family = 'holy_communion'` (pre-fill in the UI).

---

### 3.2 `daily_office_count` — store vs. expand ENUM

**Option A — Store explicitly (recommended)**  
Keep `daily_office_count` as a writable field in `service_metrics`. Expand `service_family` ENUM to include `'daily_office'` alongside `'holy_communion'` and `'other'`. The count field covers morning-and-evening-prayer services held in a single calendar event.

**Option B — Derive from expanded ENUM only**  
Add `'daily_office'` to `service_family` ENUM and count rows at report time. Works for single-office entries; loses accuracy if Morning + Evening Prayer are logged as one service row.

**Recommendation: Option A** — same rationale as §3.1.

---

### 3.3 `services.online_live_viewers` / `services.online_recorded_views` — rollback vs. rename

Migration `2026_04_20_000006` added these two columns to the `services` table. They should be moved to `service_metrics` (where all other per-service analytics live).

**Option A — Rollback 000006, add the columns inside the merge migration (recommended)**  
Create a single migration that:

1. Drops `services.online_live_viewers` and `services.online_recorded_views`.
2. Adds `online_live_viewers` and `online_recorded_views` to `service_metrics`.
3. Copies `service_sacramental_counts` data into `service_metrics`.
4. Drops `service_sacramental_counts`.

**Option B — Leave on `services` table**  
Keep the viewer counts on `services` (accepted column on a core table) and only merge `service_sacramental_counts` into `service_metrics`. Less schema movement, but `services` grows with Parochial-Report-only fields.

**Recommendation: Option A.** `services` should stay as core liturgical metadata (date, type, preacher, celebrant). All analytical/report-collection data belongs in `service_metrics`.

---

## 4. Migration Plan

Assuming all three Option A decisions above.

### Step 1 — New migration: `2026_04_20_000008_MergeSacramentalIntoServiceMetrics`

```php
public function up(): void
{
    // 1. Expand service_family ENUM to include 'daily_office'
    $this->db->query(
        "ALTER TABLE service_metrics
            MODIFY COLUMN service_family ENUM('holy_communion','daily_office','other') NULL"
    );

    // 2. Add sacramental count columns to service_metrics
    $this->forge->addColumn('service_metrics', [
        'baptism_adult'        => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'baptism_child'        => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'confirmation_adult'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'confirmation_teen'    => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'reception_count'      => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'marriage_count'       => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'burial_count'         => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'eucharist_count'      => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'daily_office_count'   => ['type' => 'SMALLINT', 'constraint' => 5, 'unsigned' => true, 'null' => true, 'default' => null],
        'online_live_viewers'  => ['type' => 'INT',      'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
        'online_recorded_views'=> ['type' => 'INT',      'constraint' => 11, 'unsigned' => true, 'null' => true, 'default' => null],
    ]);

    // 3. Copy existing sacramental data into service_metrics (upsert via INSERT…ON DUPLICATE KEY)
    $this->db->query("
        INSERT INTO service_metrics
            (service_id, baptism_adult, baptism_child, confirmation_adult, confirmation_teen,
             reception_count, marriage_count, burial_count, eucharist_count, daily_office_count,
             created_at, updated_at)
        SELECT
            service_id, baptism_adult, baptism_child, confirmation_adult, confirmation_teen,
            reception_count, marriage_count, burial_count, eucharist_count, daily_office_count,
            NOW(), NOW()
        FROM service_sacramental_counts
        ON DUPLICATE KEY UPDATE
            baptism_adult      = VALUES(baptism_adult),
            baptism_child      = VALUES(baptism_child),
            confirmation_adult = VALUES(confirmation_adult),
            confirmation_teen  = VALUES(confirmation_teen),
            reception_count    = VALUES(reception_count),
            marriage_count     = VALUES(marriage_count),
            burial_count       = VALUES(burial_count),
            eucharist_count    = VALUES(eucharist_count),
            daily_office_count = VALUES(daily_office_count),
            updated_at         = NOW()
    ");

    // 4. Copy online viewer counts from services table into service_metrics
    $this->db->query("
        INSERT INTO service_metrics (service_id, online_live_viewers, online_recorded_views, created_at, updated_at)
        SELECT id, online_live_viewers, online_recorded_views, NOW(), NOW()
        FROM services
        WHERE online_live_viewers IS NOT NULL OR online_recorded_views IS NOT NULL
        ON DUPLICATE KEY UPDATE
            online_live_viewers   = VALUES(online_live_viewers),
            online_recorded_views = VALUES(online_recorded_views),
            updated_at            = NOW()
    ");

    // 5. Drop viewer columns from services
    $this->db->query("ALTER TABLE services DROP COLUMN IF EXISTS online_live_viewers");
    $this->db->query("ALTER TABLE services DROP COLUMN IF EXISTS online_recorded_views");

    // 6. Drop the now-redundant sacramental counts table
    $this->forge->dropTable('service_sacramental_counts', true);
}

public function down(): void
{
    // Restore service_sacramental_counts
    // (abbreviated — full down() would recreate the table and reverse-copy data)
    $this->forge->createTable('service_sacramental_counts');

    // Restore services viewer columns
    $this->db->query(
        "ALTER TABLE services
            ADD COLUMN online_live_viewers   INT UNSIGNED NULL,
            ADD COLUMN online_recorded_views INT UNSIGNED NULL"
    );

    // Revert service_family ENUM
    $this->db->query(
        "ALTER TABLE service_metrics
            MODIFY COLUMN service_family ENUM('holy_communion','other') NULL"
    );

    // Drop merged columns from service_metrics
    $this->forge->dropColumn('service_metrics', [
        'baptism_adult','baptism_child','confirmation_adult','confirmation_teen',
        'reception_count','marriage_count','burial_count','eucharist_count',
        'daily_office_count','online_live_viewers','online_recorded_views',
    ]);
}
```

---

## 5. Code Changes Required After Migration

### 5.1 Models

**Remove:**

- `app/Models/ServiceSacramentalCountModel.php` — delete the file

**Update `ServiceMetricsModel::$allowedFields`** — add all 11 new columns:

```php
'baptism_adult', 'baptism_child', 'confirmation_adult', 'confirmation_teen',
'reception_count', 'marriage_count', 'burial_count', 'eucharist_count', 'daily_office_count',
'online_live_viewers', 'online_recorded_views',
```

**Update `ServiceModel::$allowedFields`** — remove:

```php
'online_live_viewers', 'online_recorded_views',  // ← remove these two
```

### 5.2 Controller: `Worship/Controllers/Services.php`

- Remove the `$serviceSacramentalCountModel` property and its instantiation.
- Remove the `service_sacramental_counts` block in `upsertRelatedServiceData()`.
- Move the 9 sacramental fields and 2 viewer fields into the existing `$metricsData` array inside the `service_metrics` block.
- Remove `getCoreServiceData()` references to `online_live_viewers` and `online_recorded_views` (they no longer go to the `services` row).

### 5.3 Views

- `Worship/Views/Services/create.php` and `edit.php` — the field inputs themselves do not change; only the grouping/section headers in the UI can be updated to no longer distinguish "Service Metrics" vs. "Sacramental Counts" (they are now one logical group).

### 5.4 Parochial Report queries

After the merge, the annual aggregation query simplifies to a single table join:

```sql
-- Example: Parochial Report Line 19 for church 5, year 2026
SELECT
    SUM(sm.baptism_adult)      AS total_baptisms_adult,
    SUM(sm.baptism_child)      AS total_baptisms_child,
    SUM(sm.confirmation_adult) AS total_confirmations_adult,
    SUM(sm.confirmation_teen)  AS total_confirmations_teen,
    SUM(sm.reception_count)    AS total_receptions,
    SUM(sm.marriage_count)     AS total_marriages,
    SUM(sm.burial_count)       AS total_burials,
    SUM(sm.eucharist_count)    AS total_eucharistic_services,
    SUM(sm.daily_office_count) AS total_daily_offices,
    SUM(sm.online_live_viewers)   AS total_online_live_viewers,
    SUM(sm.online_recorded_views) AS total_online_recorded_views
FROM service_metrics sm
INNER JOIN services s ON s.id = sm.service_id
WHERE s.church_id = 5
  AND YEAR(s.date) = 2026;
```

---

## 6. Risk Register

| Risk                                                                              | Severity  | Mitigation                                                                                                                                                                                                   |
|-----------------------------------------------------------------------------------|-----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Existing `service_sacramental_counts` rows with no matching `service_metrics` row | Medium    | The INSERT…ON DUPLICATE KEY in Step 3 inserts a new `service_metrics` row if none exists; the FK to `services` is satisfied because `service_sacramental_counts.service_id` already references `services.id` |
| `services.online_live_viewers` data exists before migration runs                  | Low       | Step 4 copies them before the DROP in Step 5                                                                                                                                                                 |
| `ServiceSacramentalCountModel` still imported in other controllers                | Low       | Run `grep -r "ServiceSacramentalCountModel" .` before executing; only `Worship/Controllers/Services.php` currently references it                                                                             |
| `service_family` ENUM change breaks existing MySQL strict-mode rows               | None      | Adding a value to an ENUM is non-destructive; existing rows are unaffected                                                                                                                                   |

---

## 7. Summary of Decisions Required

| #   | Question                                                                        | Recommendation                                 |
|-----|---------------------------------------------------------------------------------|------------------------------------------------|
| 3.1 | Store `eucharist_count` or derive from `service_family`?                        | **Store** (allows multi-celebration events)    |
| 3.2 | Store `daily_office_count` or derive after ENUM expansion?                      | **Store** (allows morning+evening in one row)  |
| 3.3 | Move `services.online_live_viewers/online_recorded_views` to `service_metrics`? | **Yes — move** (`services` stays clean)        |

Reply with your decisions and implementation can begin immediately.
