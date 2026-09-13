# Episcopal Parochial Report — Reference & Field Mapping

**Source:** The Annual Report of Episcopal Congregations and Missions  
**Canons:** I.6, I.7, and I.17  
**Alias:** The Parochial Report

This document describes the structure, fields, and calculation rules of the TEC Parochial Report. It serves as the source-of-truth for mapping ekklesiaRD data to the report's required fields.

---

## Page 1 — Congregation Identity & Report Certification

### Congregation Information

| Field                                                         | Notes                                          |
| --------------------------------------------------------------|------------------------------------------------|
| Name of Congregation                                          | `churches.name`                                |
| Diocese                                                       | Diocese affiliation                            |
| Street Address 1 / 2                                          | `churches.address`                             |
| City / State / Zip + 4 / County / Country                     | Extend `churches` if not already stored        |
| Mailing Address 1 / 2 / City / State / Zip / County / Country | Separate mailing address if different          |
| Email Address                                                 | `churches.email`                               |
| Congregation's Web Address                                    | `churches.website`                             |
| Phone #                                                       | `churches.phone`                               |
| Federal Tax ID #                                              | New field — not currently in `churches` schema |

### Report Preparation

Two preparers must be recorded:

| Role                             | Fields                     |
| -------------------------------- |--------------------------- |
| Data & Demographics preparer     | Name, email, daytime phone |
| Stewardship & Financial preparer | Name, email, daytime phone |

### Certifications

Four separate certification blocks, each requiring: name (print), email, daytime phone, signature, and date.

| Block                             | Signer                 |
| --------------------------------- |------------------------|
| Clerk of the Vestry               | Date of vestry meeting |
| Rector / Vicar / Priest-in-Charge | —                      |
| Warden / Vestry Approval          | —                      |

### Parochial Report Completion

- Count of people who participated in completing the report.
- Free-text identification of their roles (Rector/Vicar, Wardens, Vestry Member, Treasurer, Staff Member, Volunteer). Vestry members who only voted to approve are excluded from this count.

---

## Page 2 — Attendance, Impact & Membership

### Attendance and Impact (Lines 1–8)

> Online viewership = unique live viewers summed across all platforms (Zoom + Facebook + YouTube, etc.). Does **not** include "viewed after" replays. Does **not** count households — count individual viewers.

#### Line 1 — Average Sunday Attendance In-Person (ASA In-Person)

```text
Total In-Person Annual Attendance ÷ Total Number of Sundays In-Person Worship Was Conducted = ASA (in-person)
```text

- Includes Saturday Evening services counted as Sunday.
- Maps to: `services` table (`service_type = 'Sunday Worship'`) + `attendance` table (`attended = 1`).
- **ekklesiaRD note:** §12 of the system rules prohibits bucket-level averaging for church-admin dashboard graphics. The formula above is the canonical Parochial Report calculation and applies here.

#### Line 2 — Average Sunday Attendance Online (ASA Online)

```text
Total Annual Livestream Attendance (all platforms) ÷ Total Number of Sunday Services Held = ASA (online)
```text

- Excludes "viewed after" totals — live viewers only.
- Currently not tracked in `services` or `attendance`. A `service_online_viewership` field or separate table would be needed.

#### Line 3 — Average Weekday Attendance In-Person (AWA In-Person)

```text
Total In-Person Attendance (weekdays, holy days, special annual feasts) ÷ Total Number of Weeks Services Were Held = AWA (in-person)
```text

- Maps to: `services` table (`service_type IN ('Weekday Service', 'Special', 'Other')`) + `attendance`.

#### Line 4 — Average Non-Live ("Viewed After") Views

- Estimated average unique viewers who watched the recorded service within one week of broadcast.
- Two fields: `Average 1-week (unique)` and `Estimated Average`.
- Not currently tracked. Requires a separate data entry or manual import from platform analytics.

#### Line 6 — Outreach Beneficiaries

- Estimated beneficiaries of outreach ministries **per week**.
- Not currently tracked in ekklesiaRD. New data entry field needed.

#### Line 7 — Average Non-Member Volunteers

- Average number of non-member volunteers for outreach ministries **per week**.
- Partial match: `volunteer_teams` / `team_members` can identify volunteers. Non-member status requires checking `is_member = 0` on the volunteer's affiliation.

#### Line 8 — Total Impact

```text
Total Impact = Lines 1 + 2 + 3 + 4 + 6 + 7
```text

---

### Communicants in Good Standing (Lines 9–11)

> **Definition (TEC Canon):** Baptized members who have received Holy Communion at least 3 times in the preceding year AND are faithful in corporate worship (unless prevented for good cause) AND in working, praying, and giving for the spread of the Kingdom of God.

| Line | Field | Age Range | Maps to |
| --- |---| --- |---|
| 9 | Adult communicants in good standing | Age 16 and over | `member_standing_summary.is_good_standing = 1` + `profile.birth_date` (age ≥ 16) |
| 10 | Youth communicants in good standing | Under age 16 | Same, age < 16 |
| 11 | Total (Line 9 + Line 10) | — | Sum |

**ekklesiaRD mapping note:** `member_standing_summary.is_good_standing` is the canonical field. The Parochial Report age split (16 / under 16) differs from the system's current `is_dependent` concept (under 18). A separate age-threshold query will be needed at report time.

The TEC definition of Communicant in Good Standing requires:
1. Baptized (→ `profile.baptism_date IS NOT NULL`)
2. Received Communion ≥ 3 times in the year (→ `attendance.communicant = 1`, count ≥ 3 in year)
3. Faithful in corporate worship (→ modeled via good standing)
4. Working, praying, giving (→ partly modeled via `donations`, partly qualitative)

---

### Active Members and Participants (Lines 12–13)

| Line | Field | Description |
| --- |---| --- |
| 12 | Total Active Members (other) | Regularly participate, meet parish membership standard, but **do not** meet Communicant in Good Standing threshold. Includes non-Episcopalians and non-Christians who are parish members. Maps to `is_member = 1` AND `is_good_standing = 0`. |
| 13 | Total Estimated Active Participants (non-members) | Participate in church life but meet neither the Communicant threshold nor the membership standard. Maps to `is_member = 0`, `membership_status IN ('visitor', 'regular_attender', 'pending')`. |

---

## Page 3 — Easter/Christmas, Clergy & Staff, Sacraments

### Easter and Christmas Attendance (Lines 14–15)

- In-person only. Online participation excluded.

| Line | Field |
| --- |---|
| 14 | Total In-Person Easter Sunday Attendance (including Easter Vigil) |
| 15 | Total In-Person Christmas Eve and Christmas Day Attendance |

Maps to: `services` filtered by `date` on Easter and Christmas dates + `attendance` table.

---

### Clergy and Staff (Line 16)

| Category | Subtypes |
| --- |---|
| Clergy | Full Time, Part Time, Non-Stipendiary |
| Lay Staff | Full Time, Part Time, Unpaid/Non-Stipendiary |

Totals required:
- Total Clergy Staff (with breakdown: Deacons count, Priests count)
- Total Lay Staff
- Total Staff

Not currently tracked in ekklesiaRD — would require a `staff_roster` or extension of `leadership_positions`.

---

### Primary Worship Leadership (Line 17)

Select one:

- Full Time Priest
- Short Term Supply
- Part Time Priest
- Long Term Supply
- Lay Person
- Deacon

---

### Unusual Clergy Situation (Line 18)

Select all that apply:

- Long Term Supply or Interim
- Call to Common Ministry
- Clergy from elsewhere in The Anglican Communion

---

### Sacraments & Services (Line 19)

Numeric count for the reporting year. Source: parish register.

| Sacrament / Service | Count type |
| --- |---|
| Baptisms | # of individuals |
| Confirmations | # of individuals |
| Receptions | # of individuals |
| Marriages | # of services |
| Burials | # of services |
| Daily Office Services | # of services |
| Eucharists | # of services |

Maps to: `services` table + `service_sacramental_counts` (if implemented). `ServiceSacramentalCountModel` already references `baptism_child` — this is the right extension point.

---

### Services, Programs & Initiatives (Line 20)

Checkbox selection. Current coverage in ekklesiaRD vs. required:

| Program | Tracked? |
| --- |---|
| Sunday Morning Eucharist | Via `services.service_type` |
| Streamed Service | Not tracked |
| Children's Choir | Not tracked |
| Ecumenical Collaboration | Not tracked |
| Sunday Morning Prayer | Via `services` |
| Interactive Virtual Service | Not tracked |
| Sunday School | Not tracked |
| Virtual Formation | Not tracked |
| Other Weekend Eucharist | Via `services` |
| Paid Musicians | Not tracked |
| Sunday Childcare | Not tracked |
| In Person Formation | Not tracked |
| Weekday Eucharist | Via `services` |
| Volunteer Musicians | Partial — `volunteer_teams` |
| Bible Study | Not tracked |
| Campus Ministry | Not tracked |
| Weekday Morning/Noonday/Evening Prayer | Via `services` |
| Adult Choir | Not tracked |
| Outreach Ministries | Partial — `volunteer_teams` |
| Other (free text) | — |

---

### Subsidiary / Associated Organizations (Line 21)

Checkbox: Preschool or Nursery, K-8 School, 9-12 School, Outreach Ministry, Other.

---

### Demographics (Lines 22–25)

| Line | Field | Notes |
| --- |---| --- |
| 22 | Languages of worship offered | Checkboxes: English, Spanish, French, Haitian Creole, Mandarin, Tagalog, ASL, Other |
| 23 | Simultaneous multi-language service | Boolean checkbox |
| 24 | % White/Caucasian/European descent | Scale or numeric percentage |
| 25 | Estimated average age of congregation | Scale or numeric |

Lines 24–25 are not currently tracked in ekklesiaRD. `birth_date` is stored in `profile` so average age is computable. Ethnicity/race is not stored and would require a new optional field.

---

## Page 4 — Giving Information

### Stewardship (Lines 1–2)

> Count each distinct pledge. One family pledge = 1. Four individual pledges by the same family = 4.

| Line | Field | Currency |
| --- |---| --- |
| 1 | Number of confirmed pledges to the general/operating fund | USD + Other |
| 2 | Monetary amount of confirmed pledges | USD + Other |

Maps to: `annual_pledges` table (if implemented). Not explicitly tracked in current `donations` schema.

---

### Revenue (Lines 3–5)

| Line | Field | Description |
| --- |---| --- |
| 3 | Plate, pledge & other unrestricted contributions | `donations` where `category IN ('tithe','offering')` and no restriction |
| 4 | Contributions restricted by contributors | `donations` where `category` indicates a restricted purpose (e.g., building fund, missions) |
| 5 | All other revenue | Grants, fundraising, rental revenue, bank interest. Excludes investment gains/losses. |
| A | Total Revenue (3+4+5) | Sum |

---

### Expenses (Lines 6–7)

| Line | Field | Description |
| --- |---| --- |
| 6 | Non-capital expenses | Operations, salaries, utilities, insurance, diocesan assessments, programming. Maps to `expenses` table. |
| 7 | Capital expenses | Property, buildings, equipment purchases and major improvements. New `category` value needed. |
| B | Total Expenses (6+7) | Sum |

---

### Year-End Balances (Line 8)

| Line | Field |
| --- |---|
| 8 | All cash, checking & savings balances as of Dec 31 | Total of all cash accounts under the congregation's Federal Tax ID. |

---

### Supplemental Year-End Information (Lines 9–15)

#### Line 9 — Endowment & Investment Accounts

| Sub-line | Field |
| --- |---|
| 9a | Endowment (restricted), Endowment (unrestricted), Total Endowment; Non-endowed Investments (restricted/unrestricted), Total Investments |
| 9b | Percentage draw (amount withdrawn ÷ prior year-end market value) |
| 9c | Dollar amount drawn for use |
| 9d | Net gain/loss (dividends + interest + realized gains + unrealized gains − fees) |

**Example:** Year-end market value = $100,000. Withdrawn = $5,000. Draw % = 5%. Net gain = dividends $500 + interest $250 + realized gains $1,000 + unrealized gains $1,500 − fees $100 = **$3,150**.

#### Line 10 — Outstanding Debt

- Yes/No + amount + purpose description.
- Includes credit card debt, bank loans, diocesan debt, and debt to any person or entity.

#### Line 11 — Property Ownership

- Does the congregation own buildings and/or land?
- Includes property held in trust for the diocese.

#### Line 12 — Rental / Lease to Outside Groups

- Yes/No.
- 12a: Revenue from renting/leasing.
- 12b: Direct expenses incurred from rental activities.

#### Line 13 — Carbon Footprint (Optional)

- Free text. Mandated by Resolution 2022-D064. Response is optional.

#### Line 14 — Best Exemplifying Program (Optional)

- Free text. One program or initiative that best exemplifies the congregation.

#### Line 15 — Hope for the Future (Optional)

- Free text. One program or initiative representing hope for the congregation or the greater Episcopal Church.

---

## ekklesiaRD Data Gap Summary

The following Parochial Report fields have **no current mapping** in ekklesiaRD and would require new schema additions or manual data entry:

| Report Field | Gap Type | Suggested Resolution |
| --- |---| --- |
| Federal Tax ID | Missing field | Add `churches.federal_tax_id VARCHAR(20)` |
| Online viewership (Line 2) | No tracking | Add `service_online_viewers INT` to `services` or a `service_streaming_stats` table |
| "Viewed after" (Line 4) | No tracking | Same table as above, separate column |
| Outreach beneficiaries / week (Line 6) | No tracking | Add to `volunteer_teams` or new `outreach_metrics` table |
| Clergy & Staff roster (Line 16) | Not modeled | Extend `leadership_positions` with `employment_type` and `role_category` |
| Primary worship leader type (Line 17) | Not modeled | `churches` setting or `services.lead_type` |
| Unusual clergy situation (Line 18) | Not modeled | Boolean flags on `churches` or `leadership_positions` |
| Sacramental counts (Line 19) | Partial — `ServiceSacramentalCountModel` exists | Confirm `baptism_adult`, `confirmation`, `reception`, `marriage`, `burial`, `daily_office`, `eucharist` columns |
| Programs checklist (Line 20) | Not modeled | `church_programs` junction table or JSON column |
| Subsidiary organizations (Line 21) | Not modeled | `church_organizations` table |
| Languages offered (Line 22) | Not modeled | `church_languages` junction table |
| Simultaneous multi-language (Line 23) | Not modeled | Boolean on `churches` |
| Racial/ethnic composition (Line 24) | Not stored | Optional `churches.pct_white` or a demographics survey table |
| Average congregation age (Line 25) | Computable | Derived from `profile.birth_date` — no schema change needed |
| Pledge tracking (Lines 1–2) | `annual_pledges` table exists | Verify it covers operating-fund vs. restricted distinction |
| Restricted vs. unrestricted donations (Lines 3–4) | `donations.category` is coarse | Add `is_restricted BOOLEAN` + `restriction_purpose VARCHAR` to `donations` |
| Capital vs. non-capital expenses (Line 7) | `expenses.category` is free text | Add `is_capital BOOLEAN` to `expenses` |
| Year-end cash balance (Line 8) | Not tracked | New `church_financial_summary` table or manual entry |
| Endowment / investment detail (Line 9) | Not tracked | New `church_investments` table |
| Outstanding debt (Line 10) | Not tracked | New `church_debt` table or fields on `church_financial_summary` |
| Property ownership (Line 11) | Not tracked | Boolean on `churches` |
| Rental income/expenses (Line 12) | Partial — `expenses` and `donations` exist | Add `rental_income` and `rental_expense` fields or filter by category |
