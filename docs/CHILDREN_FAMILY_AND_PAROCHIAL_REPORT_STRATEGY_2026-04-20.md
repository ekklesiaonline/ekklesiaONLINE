# Children, Family Registration & Parochial Report Strategy

**Date:** April 20, 2026  
**Scope:** ekklesiaRD codebase analysis combined with TEC Parochial Report field requirements  
**Status:** Strategy plus implementation context. The parochial report UI, controller mapping, and report-year persistence were updated on April 21, 2026 to follow `docs/EPISCOPAL_PAROCHIAL_REPORT_FOR_REFERENCE.md` as the authoritative field contract.

## Implementation Update — April 21, 2026

- The parochial report runtime now uses the Episcopal reference document as the canonical field set.
- `Worship/Controllers/ParochialReport.php` now computes autofill values from the modeled data sources instead of treating the form as a mostly manual worksheet.
- `Worship/Views/ParochialReport/index.php` now mirrors the page-by-page reference structure, including explanation text and examples for operators.
- `church_financial_summary` was expanded to persist report-year metadata such as mailing details, certifications, manual estimates, checklist responses, and narratives.
- Remaining strategic items in this document still apply where they concern adjacent family and dependent modeling work outside the parochial-report screen itself.

---

## 1. The Core Problem

### 1.1 Operational scenario

A family arrives at a parish. The parents are not members — they have not yet progressed through the pipeline — but their children attend regularly and are being baptized or confirmed. The church needs to:

1. Record the children as **regular attenders** under a family unit.
2. Designate a **head of household** for that family.
3. Eventually move the children through the sacramental track (baptism → confirmation → active member).
4. Track all of this in ways that satisfy the **TEC Parochial Report** (Canons I.6, I.7, I.17).

### 1.2 The membership pipeline (existing)

```text
First-Time Guest → Visitor → Regular Attender → Pending → Active Member
    → Member in Good Standing → Leadership Position
```

This pipeline is modeled for **adults making a personal covenant commitment**. It does not distinguish between:

- A child who is a regular attender (baptized infant, attending Sunday school)
- An adult who is a regular attender (parents who come but have not committed)
- A child who has been confirmed and is now an active covenant member

### 1.3 The parochial report age threshold (critical)

The TEC Parochial Report uses **age 16** — not 18 — as the adult/youth split:

| Line | Description | Age |
| --- | --- | --- |
| Line 9 | Adult communicants in good standing | 16 and over |
| Line 10 | Youth communicants in good standing | Under 16 |

A baptized child under 16 who receives communion regularly **can and should** appear on Line 10 as a youth communicant in good standing. They are not excluded from the pipeline — they occupy a different reporting bucket.

Children aged 16–17 fall into the **adult** communicant bucket (Line 9), which means a confirmed 16-year-old is reported the same way as a 40-year-old covenant member.

This reframes the child problem: it is not that children are outside the system — it is that the system lacks:

- An **age-derived flag** to split adult vs. youth reporting
- A **sacramental track** (baptism date → confirmation date → transition to `active`)
- A way to create a **family without requiring the head to already be an `is_member=1` person**

---

## 2. Current Codebase State

### 2.1 Family creation — head-of-household constraint

**File:** `MemberPortal/Controllers/Families.php` — `create()`, `store()`, `edit()`, `update()`

The head-of-household dropdown is populated by:

```php
$members = $this->getMemberChurchAffiliationModel()->getParishMembers((int) $churchId);
// membersOnly defaults to true → only is_member = 1 people appear
```

The server-side validation check (`isChurchAffiliatedMember`) only requires an **active affiliation row** — it does not require `is_member = 1`. The constraint is entirely in the dropdown population, not in the business rule.

`head_of_household_id` is nullable in the database and in the controller. A family can be saved without any head.

**Consequence:** A parent who is a `regular_attender` or `visitor` (not yet `is_member=1`) is invisible in the head-of-household dropdown. The admin cannot assign them as head even though the server would accept it.

### 2.2 Non-portal member provisioning — `is_child` is ignored

**File:** `app/Services/NonPortalMemberProvisioningService.php` — `create()` line 65

The controller (`Families::addMemberNonPortal()`) validates and passes `is_child` in the payload. The provisioning service ignores it:

```php
$profileData = [
    // ...
    'membership_status' => 'active',   // hardcoded — ignores is_child
];
```

And for `is_member` (line ~108):

```php
$isMemberValue = isset($payload['is_member']) ? (int)(bool)$payload['is_member'] : 1;
```

Unless the **caller** explicitly passes `is_member=0`, a child created via the non-portal flow gets `membership_status='active'` and `is_member=1` — they appear as full covenant members, inflate Line 9 adult communicants, and bypass the sacramental track entirely.

### 2.3 Sacramental tracking — partially implemented

`ServiceSacramentalCountModel` already tracks per-service:

| Column | Maps to Parochial Report |
| --- | --- |
| `baptism_adult` | Line 19 — Baptisms (adult) |
| `baptism_child` | Line 19 — Baptisms (child/infant) |
| `confirmation_adult` | Line 19 — Confirmations (adult) |
| `confirmation_teen` | Line 19 — Confirmations (teen) |
| `reception_count` | Line 19 — Receptions |

Missing from `service_sacramental_counts`:

- `marriage_count`
- `burial_count`
- `eucharist_count` (tracked separately at service level? needs verification)
- `daily_office_count`

`profile` already stores `baptism_date`, `confirmation_date`, and `is_confirmed`. These are the right fields to drive the sacramental track — they just are not used programmatically.

### 2.4 Age computation — available but unused

`profile.birth_date` is stored and encrypted (via `DataEncryption`). No code computes a person's age or compares it against any threshold. The Parochial Report requires two age-split queries (under 16 / 16 and over), and the children strategy requires a minor-detection rule (under 18).

### 2.5 What `is_member = 1` means today

Per §16 of the system rules:

> `is_member = 1` iff `membership_status IN ('active', 'inactive')`

A baptized child who is a regular attender should have `is_member = 0` and `membership_status = 'regular_attender'`. Upon confirmation, they should transition to `membership_status = 'active'`, `is_member = 1`.

The pipeline transition at confirmation is currently **entirely manual** — no trigger exists.

---

## 3. Parochial Report — Full Field Mapping vs. ekklesiaRD

### 3.1 Page 1 — Congregation Identity & Certification

| Report Field | ekklesiaRD Mapping | Gap |
| --- | --- | --- |
| Name of Congregation | `churches.name` | None |
| Diocese | `dioceses` via FK | None |
| Street Address 1/2, City, State, Zip, County, Country | `churches.address` (single text field) | Address is not structured — split into `city`, `state`, `zip`, `county`, `country` columns needed |
| Mailing Address (separate) | Not stored | New fields: `mailing_address`, `mailing_city`, `mailing_state`, `mailing_zip`, `mailing_country` |
| Email | `churches.email` | None |
| Website | `churches.website` | None |
| Phone | `churches.phone` | None |
| Federal Tax ID | Not stored | New field: `churches.federal_tax_id VARCHAR(20)` |
| Report preparers (2 roles) | Not modeled | New: `parochial_report_meta` table or JSON field on `churches` |
| Four certification blocks | Not modeled | Same — part of `parochial_report_meta` or a dedicated `parochial_report_certifications` table |
| Completion participant count/roles | Not modeled | Same |

### 3.2 Page 2 — Attendance, Impact, and Membership

#### Attendance lines (1–8)

| Line | Formula | ekklesiaRD Source | Gap |
| --- | --- | --- | --- |
| 1 — ASA In-Person | Total in-person attendance ÷ Sundays in-person worship held | `services` (Sunday Worship) + `attendance.attended = 1` | Computable now. See §12 constraint: use raw per-service values, no bucket averaging. |
| 2 — ASA Online | Total live viewership ÷ Sundays online held | Not tracked | New field: `services.online_live_viewers INT` |
| 3 — AWA In-Person | Total weekday attendance ÷ weeks held | `services` (Weekday/Special/Other) + `attendance` | Computable now. |
| 4 — Viewed-After average | Unique non-live views within 1 week of broadcast | Not tracked | New field: `services.online_recorded_views INT` |
| 6 — Outreach beneficiaries/week | Estimated weekly outreach reach | Not tracked | New: `outreach_metrics` table or weekly input field |
| 7 — Non-member volunteers/week | Avg non-member volunteers for outreach | Partial — `team_members` + `is_member=0` | Requires volunteer context tagging (outreach vs. other ministry) |
| 8 — Total Impact | Lines 1+2+3+4+6+7 | Derived | Computable once gaps above are filled |

#### Communicants in Good Standing (Lines 9–11)

The TEC definition requires all four criteria to be true:

1. **Baptized** → `profile.baptism_date IS NOT NULL`
2. **Received Communion ≥ 3 times in reporting year** → `COUNT(attendance rows WHERE communicant=1 AND service date IN report year) >= 3`
3. **Faithful in corporate worship** → modeled as `is_good_standing = 1` in `member_standing_summary`
4. **Working, praying, giving** → partially modeled via `donations`; qualitative remainder

| Line | Segment | Age filter | ekklesiaRD Query |
| --- | --- | --- | --- |
| 9 | Adult communicants in good standing | `TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) >= 16` | `member_standing_summary.is_good_standing=1` + baptized + communion ≥ 3 + age ≥ 16 |
| 10 | Youth communicants in good standing | `TIMESTAMPDIFF(YEAR, p.birth_date, CURDATE()) < 16` | Same criteria, age < 16 |
| 11 | Total | — | Line 9 + Line 10 |

**Note:** `birth_date` is encrypted at rest via `DataEncryption`. The age comparison must decrypt the field or use a stored plaintext age-year column for efficient querying. For reporting purposes, a dedicated report query that decrypts in application layer (not SQL) is the safest approach.

**Children implication:** A baptized 12-year-old who attends Sunday services and receives communion at least 3 times per year **belongs on Line 10**. They are a youth communicant in good standing. They do not need `is_member=1` for this — `member_standing_summary.is_good_standing=1` is the only required status flag.

#### Active Members and Participants (Lines 12–13)

| Line | Description | ekklesiaRD Mapping |
| --- | --- | --- |
| 12 | Active Members (other) — meet membership standard but not Communicant threshold | `is_member=1` AND `is_good_standing=0` (or `is_good_standing` unknown). Includes non-Episcopalians who are parish members. |
| 13 | Active Participants (non-members) — participate but meet neither threshold | `is_member=0` AND `membership_status IN ('visitor', 'regular_attender', 'pending')` AND attended at least once in the report year |

**Children implication:** Children who are regular attenders but not yet confirmed appear on Line 13 (active non-member participants) until they are confirmed, at which point they move to Line 9 or 10.

### 3.3 Page 3 — Easter/Christmas, Clergy, Sacraments

#### Lines 14–15 — Easter and Christmas Attendance

- In-person only.
- Query: `services` filtered by `date` = Easter Sunday/Vigil date or Christmas Eve/Day, joined to `attendance` count.
- Easter date is computed (not a fixed calendar date) — the report module will need a `computeEasterDate(int $year): string` helper.

#### Line 16 — Clergy and Staff

Not currently tracked in ekklesiaRD. `leadership_positions` tracks roles but not employment type or compensation status.

**Schema addition needed:**

```sql
ALTER TABLE leadership_positions
    ADD COLUMN employment_type ENUM('full_time','part_time','non_stipendiary','unpaid') NULL,
    ADD COLUMN role_category ENUM('clergy','lay_staff') NULL,
    ADD COLUMN clergy_order ENUM('bishop','priest','deacon') NULL;
```

#### Lines 17–18 — Clergy Situation

New fields on `churches`:

```sql
ALTER TABLE churches
    ADD COLUMN primary_worship_leader_type ENUM(
        'full_time_priest','part_time_priest','short_term_supply',
        'long_term_supply','lay_person','deacon'
    ) NULL,
    ADD COLUMN clergy_situation_flags JSON NULL;
    -- e.g. {"long_term_supply":true,"common_ministry":false,"anglican_communion":false}
```

#### Line 19 — Sacramental Counts

`ServiceSacramentalCountModel` already covers:

| Column | Maps to |
| --- | --- |
| `baptism_adult` | Baptisms (adult) |
| `baptism_child` | Baptisms (child/infant) |
| `confirmation_adult` | Confirmations (adult) |
| `confirmation_teen` | Confirmations (teen) |
| `reception_count` | Receptions |

**Missing columns** (migration needed):

```sql
ALTER TABLE service_sacramental_counts
    ADD COLUMN marriage_count    SMALLINT UNSIGNED DEFAULT 0,
    ADD COLUMN burial_count      SMALLINT UNSIGNED DEFAULT 0,
    ADD COLUMN eucharist_count   SMALLINT UNSIGNED DEFAULT 0,
    ADD COLUMN daily_office_count SMALLINT UNSIGNED DEFAULT 0;
```

The Parochial Report aggregates all services over the full year:

```sql
SELECT
    SUM(baptism_adult + baptism_child) AS total_baptisms,
    SUM(confirmation_adult + confirmation_teen) AS total_confirmations,
    SUM(reception_count) AS total_receptions,
    SUM(marriage_count) AS total_marriages,
    SUM(burial_count) AS total_burials,
    SUM(eucharist_count) AS total_eucharists,
    SUM(daily_office_count) AS total_daily_offices
FROM service_sacramental_counts ssc
INNER JOIN services s ON s.id = ssc.service_id
WHERE s.church_id = :churchId
  AND YEAR(s.date) = :reportYear;
```

**Children implication:** `baptism_child` is already in the model. When a child is baptized, the admin logs the count here AND (optionally) records `profile.baptism_date` on the child's profile. Both are needed — the aggregate for the report, the individual date for the sacramental track.

#### Line 20 — Programs & Services Checklist

Not modeled. Recommended new table:

```sql
CREATE TABLE church_programs (
    church_id   INT UNSIGNED NOT NULL,
    program_key VARCHAR(60)  NOT NULL,  -- e.g. 'sunday_eucharist', 'streamed_service', 'sunday_school'
    active      TINYINT(1)   DEFAULT 1,
    PRIMARY KEY (church_id, program_key),
    FOREIGN KEY (church_id) REFERENCES churches(id)
);
```

Valid `program_key` values (from Line 20): `sunday_eucharist`, `streamed_service`, `childrens_choir`, `ecumenical_collaboration`, `sunday_morning_prayer`, `interactive_virtual`, `sunday_school`, `virtual_formation`, `other_weekend_eucharist`, `paid_musicians`, `sunday_childcare`, `in_person_formation`, `weekday_eucharist`, `volunteer_musicians`, `bible_study`, `campus_ministry`, `weekday_prayer`, `adult_choir`, `outreach_ministries`.

**Children implication:** `sunday_school` and `sunday_childcare` are two of these program keys. If the church has these programs, children under 18 have a formal place in the church's reported ministry structure.

#### Lines 22–25 — Demographics

| Line | Field | Gap |
| --- | --- | --- |
| 22 | Languages of worship | New table: `church_languages (church_id, language_code)` |
| 23 | Simultaneous multi-language service | New boolean: `churches.has_multilingual_service TINYINT(1)` |
| 24 | % White/Caucasian | Not stored — optional survey field; no schema change mandated |
| 25 | Average age of congregation | **Computable** from `profile.birth_date` — no schema change needed |

Average age computation (application-layer, because `birth_date` is encrypted):

```php
$profile = $db->table('profile')->select('birth_date')->where('church_id', $churchId)->get()->getResultArray();
$ages = array_filter(array_map(static function(array $row): ?int {
    $decrypted = DataEncryption::decrypt($row['birth_date'] ?? null);
    if (!$decrypted) { return null; }
    return (int) (new \DateTime($decrypted))->diff(new \DateTime())->y;
}, $profile));
$averageAge = count($ages) ? array_sum($ages) / count($ages) : null;
```

### 3.4 Page 4 — Giving, Expenses, and Year-End

#### Stewardship (Lines 1–2)

| Field | ekklesiaRD Mapping | Gap |
| --- | --- | --- |
| Number of confirmed pledges | `annual_pledges` (if present) | Verify `annual_pledges` table covers operating-fund pledges distinctly from restricted pledges |
| Total dollar amount of pledges | `annual_pledges.amount` | Same |

#### Revenue (Lines 3–5)

Current `donations.category` values: `tithe`, `offering`, `mission`, `building fund`, `other`.

| Report Line | Mapping | Gap |
| --- | --- | --- |
| Line 3 — Unrestricted contributions | `donations WHERE category IN ('tithe','offering')` AND no restriction | No `is_restricted` flag — category alone is insufficient; `building fund` and `mission` could be donor-restricted |
| Line 4 — Restricted contributions | Needs explicit restriction flag | Add `is_restricted TINYINT(1) DEFAULT 0` and `restriction_purpose VARCHAR(255)` to `donations` |
| Line 5 — All other revenue | Grants, fundraising, rental, interest | No separate revenue categories exist; `expenses` and `donations` tables do not model these |

**Schema addition for `donations`:**

```sql
ALTER TABLE donations
    ADD COLUMN is_restricted     TINYINT(1)    DEFAULT 0,
    ADD COLUMN restriction_purpose VARCHAR(255) NULL;
```

#### Expenses (Lines 6–7)

| Report Line | Mapping | Gap |
| --- | --- | --- |
| Line 6 — Non-capital expenses | `expenses` — all current rows | Current `category` is free text — cannot reliably distinguish capital vs. non-capital |
| Line 7 — Capital expenses | Subset of `expenses` | Add `is_capital TINYINT(1) DEFAULT 0` to `expenses` |

**Schema addition:**

```sql
ALTER TABLE expenses
    ADD COLUMN is_capital TINYINT(1) DEFAULT 0;
```

#### Year-End (Lines 8–12)

None of these are tracked in ekklesiaRD. A dedicated table is needed:

```sql
CREATE TABLE church_financial_summary (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    church_id        INT UNSIGNED NOT NULL,
    report_year      YEAR         NOT NULL,
    cash_balance     DECIMAL(14,2) DEFAULT NULL,
    endowment_restricted   DECIMAL(14,2) DEFAULT NULL,
    endowment_unrestricted DECIMAL(14,2) DEFAULT NULL,
    investment_restricted  DECIMAL(14,2) DEFAULT NULL,
    investment_unrestricted DECIMAL(14,2) DEFAULT NULL,
    pct_draw         DECIMAL(5,4)  DEFAULT NULL,
    amount_drawn     DECIMAL(14,2) DEFAULT NULL,
    investment_net_gain_loss DECIMAL(14,2) DEFAULT NULL,
    has_debt         TINYINT(1)    DEFAULT 0,
    debt_amount      DECIMAL(14,2) DEFAULT NULL,
    debt_description TEXT          DEFAULT NULL,
    owns_property    TINYINT(1)    DEFAULT 0,
    rents_to_outside TINYINT(1)    DEFAULT 0,
    rental_income    DECIMAL(14,2) DEFAULT NULL,
    rental_expense   DECIMAL(14,2) DEFAULT NULL,
    carbon_footprint_notes TEXT    DEFAULT NULL,
    exemplary_program_text TEXT    DEFAULT NULL,
    future_hope_text       TEXT    DEFAULT NULL,
    created_at       DATETIME      DEFAULT NULL,
    updated_at       DATETIME      DEFAULT NULL,
    UNIQUE KEY uk_church_year (church_id, report_year),
    FOREIGN KEY (church_id) REFERENCES churches(id)
);
```

---

## 4. Children Under 18 — Recommended Implementation

### 4.1 The three friction points (codebase-specific)

| # | Problem | Root cause |
| --- | --- | --- |
| F1 | Parent who is `regular_attender` cannot be set as head of household | `getParishMembers(membersOnly: true)` excludes `is_member=0` people from the dropdown |
| F2 | Child created via non-portal flow gets `membership_status='active'`, `is_member=1` | `NonPortalMemberProvisioningService::create()` line 65 hardcodes `'active'` and ignores `is_child` payload flag |
| F3 | No age-based split for Parochial Report lines 9 vs. 10 | `birth_date` is stored but never computed |

### 4.2 Phase 1 — Minimal fixes (no schema change, no new concept)

#### Fix F1 — Expand the head-of-household dropdown

In `MemberPortal/Controllers/Families.php`, `create()` and `edit()`, change:

```php
// Before
$members = $this->getMemberChurchAffiliationModel()->getParishMembers((int) $churchId);

// After
$members = $this->getMemberChurchAffiliationModel()->getParishMembers((int) $churchId, null, false);
// membersOnly=false → all active affiliated people appear, not just is_member=1
```

The server-side eligibility check (`isChurchAffiliatedMember`) already accepts anyone with an active affiliation — no change needed there.

#### Fix F2 — Honor `is_child` in the provisioning service

In `NonPortalMemberProvisioningService::create()`, replace the hardcoded defaults:

```php
// Before
'membership_status' => 'active',

// After
$isChild = filter_var($payload['is_child'] ?? false, FILTER_VALIDATE_BOOLEAN);
$defaultStatus = $isChild ? 'regular_attender' : 'active';
$profileData['membership_status'] = $defaultStatus;
// ...and for the affiliation:
$affiliationData['is_member'] = $isChild ? 0 : ($payload['is_member'] ? 1 : 1);
```

This ensures children arrive as `regular_attender`, `is_member=0` — the same status as a visiting adult who has not yet committed. Their sacramental progression (baptism → confirmation → `active`) drives the upgrade, not the provisioning default.

#### Fix F3 — The operational workflow after the two fixes

1. Admin creates the family. Head-of-household left blank (nullable — valid).
2. Admin adds parent via **Add Non-Portal Member**: `is_child=0`, `is_member=0` → parent gets `membership_status='regular_attender'`.
3. Admin adds children via same flow: `is_child=1` → `regular_attender`, `is_member=0`, `relationship='Child'`.
4. Admin edits family, assigns parent as head — parent now appears in dropdown.
5. Parent enters the normal adult pipeline at their own pace.
6. When a child is baptized: admin records `profile.baptism_date` on the child's profile and logs `baptism_child++` in `service_sacramental_counts`.
7. When a child is confirmed (typically age 14–17): admin sets `profile.is_confirmed=1`, `profile.confirmation_date`, and transitions `membership_status='active'`, `is_member=1`.

### 4.3 Phase 2 — `is_dependent` flag (schema extension, next sprint)

Add `is_dependent TINYINT(1) DEFAULT 0` to both `profile` and `member_church_affiliations`.

**Rules:**

- Set `is_dependent=1` when `is_child=true` at provisioning time.
- Auto-derived in `ChurchPeopleRegistryService` sync: anyone with `TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 18` AND decrypted `birth_date` known → `is_dependent=1`.
- `is_dependent=1` people are excluded from the adult follow-up pipeline (no automated emails to a 7-year-old).
- `is_dependent=1` cannot be appointed to leadership positions (already blocked by `canBeAppointed()` via `is_member=0` gate).
- Head-of-household constraint: require `is_dependent=0` (i.e., a legal adult).
- Parochial Report age split: use `is_dependent=0` + `birth_date` computed age for Lines 9/10 filter.

### 4.4 Phase 3 — Sacramental track automation (future sprint)

When `profile.is_confirmed` flips to `1`:

- Automatically set `membership_status='active'`, `is_member=1` in both `profile` and `member_church_affiliations`.
- Log a `member.confirmation_transition` audit entry.
- Remove `is_dependent=1` if person is now 16+ (or leave it and use age solely for Parochial Report bucket).

This makes the confirmation event the programmatic trigger for covenant membership — matching the Anglican/Episcopal theological model precisely.

---

## 5. Full Gap Summary — ekklesiaRD vs. Parochial Report

### Schema additions required

| Field / Table | Migration | Priority |
| --- | --- | --- |
| `churches.federal_tax_id` | `ALTER TABLE churches ADD COLUMN federal_tax_id VARCHAR(20) NULL` | Medium |
| `churches.address_city`, `address_state`, `address_zip`, `address_county`, `address_country` | Structured address fields | Low |
| `churches.mailing_*` | Mailing address block | Low |
| `churches.primary_worship_leader_type` ENUM | See §3.3 | Low |
| `churches.clergy_situation_flags` JSON | See §3.3 | Low |
| `churches.has_multilingual_service` | Boolean | Low |
| `services.online_live_viewers` INT | ASA online (Line 2) | High |
| `services.online_recorded_views` INT | Viewed-after (Line 4) | Medium |
| `leadership_positions.employment_type`, `role_category`, `clergy_order` | Clergy/staff roster (Line 16) | Medium |
| `service_sacramental_counts` — 4 new columns | `marriage_count`, `burial_count`, `eucharist_count`, `daily_office_count` | High |
| `church_programs` junction table | Programs checklist (Line 20) | Medium |
| `church_languages` junction table | Languages (Line 22) | Low |
| `donations.is_restricted`, `restriction_purpose` | Revenue split Lines 3–4 | High |
| `expenses.is_capital` | Capital vs. non-capital (Line 7) | High |
| `church_financial_summary` table | Year-end balances, endowments, debt, property, rental (Lines 8–12) | High |
| `profile.is_dependent` | Children/dependent flag (Phase 2) | High |
| `member_church_affiliations.is_dependent` | Same, affiliation scope | High |

### Data computable from existing schema (no migration needed)

| Parochial Report Field | Query approach |
| --- | --- |
| ASA In-Person (Line 1) | `services` (Sunday Worship) + `COUNT(attendance WHERE attended=1)` ÷ Sunday count |
| AWA In-Person (Line 3) | Same, `service_type IN ('Weekday Service','Special','Other')` ÷ week count |
| Easter/Christmas attendance (Lines 14–15) | Filter `services.date` by computed Easter date and Dec 24–25 |
| Adult communicants in good standing (Line 9) | `member_standing_summary.is_good_standing=1` + `profile.baptism_date IS NOT NULL` + communion ≥ 3 + `birth_date` decrypted age ≥ 16 |
| Youth communicants in good standing (Line 10) | Same, age < 16 |
| Active Members (Line 12) | `is_member=1` AND `is_good_standing=0` |
| Active Participants (Line 13) | `is_member=0` AND `membership_status IN ('visitor','regular_attender','pending')` |
| Sacramental counts (Line 19, partial) | `SUM(service_sacramental_counts.*) GROUP BY church_id, YEAR(service.date)` |
| Average age of congregation (Line 25) | Application-layer: decrypt `birth_date`, compute mean age |
| Baptism count on child profile | `COUNT(profile WHERE baptism_date IS NOT NULL AND church_id=:id)` |

### Not trackable without new data entry (manual or external)

| Report Field | Notes |
| --- | --- |
| Online viewership (Lines 2, 4) | Platform data (YouTube/Facebook/Zoom analytics) — requires manual entry or API import |
| Outreach beneficiaries/week (Line 6) | Entirely qualitative — requires weekly input screen |
| Non-member volunteer count/week (Line 7) | Partial derivation possible; outreach-specific tagging needed |
| % Racial/ethnic composition (Line 24) | Optional; sensitive — if collected, requires explicit consent workflow |
| Report certification signatories | Manual entry for the report year |
| Carbon footprint narrative (Line 13) | Free text — annual entry |

---

## 6. Recommended Implementation Order

| Sprint | Work | Impact |
| --- | --- | --- |
| **Now** | Fix F1 (dropdown) + Fix F2 (`is_child` in provisioning) | Unblocks all families with non-member parents immediately |
| **Sprint 1** | Add `service_sacramental_counts` missing columns + `donations.is_restricted` + `expenses.is_capital` | Enables correct financial and sacramental report data |
| **Sprint 2** | Add `services.online_live_viewers` + `services.online_recorded_views` + `church_financial_summary` table | Completes attendance and financial year-end blocks |
| **Sprint 3** | Add `profile.is_dependent` + honor it in `ChurchPeopleRegistryService` sync + exclude dependents from follow-up pipeline | Correct demographic segmentation; clean Parochial Report age buckets |
| **Sprint 4** | Build the Parochial Report generation UI (per-church, per-year) using the above data | The actual report output |
| **Future** | Sacramental track automation (confirmation triggers `active` transition) + `church_programs` checklist + `church_languages` | Completes the full TEC reporting surface |
