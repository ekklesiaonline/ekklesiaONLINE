# Chart of Accounts Guidelines

## Overview

The ekklesiaRD Chart of Accounts (COA) uses a standardized 4-digit numbering system designed for church and charitable organization fund accounting. This structure enables accurate financial reporting, proper fund segregation, and compliance with religious and nonprofit accounting standards.

## Account Structure

### Numbering Tiers

```ini
1000-1999: Assets (What the church owns)
2000-2999: Liabilities (What the church owes)
3000-3999: Net Assets/Fund Balances (Equity)
4000-4999: Income - Unrestricted (General operating revenue)
5000-5999: Income - Restricted/Designated (Funds for specific purposes)
6000-6999: Expenses - Outreach & Parish Missions (Money given away)
7000-7999: Expenses - Ministry & Programs (Church life and groups)
8000-8999: Expenses - Operations & Facilities (Building/Admin costs)
```

## Key Design Principles

### 1. Balance Sheet Accounts (1000–3999)

Track what the church owns (assets), owes (liabilities), and the net equity.

- **Assets (1000–1999)**: Current assets (cash, receivables), fixed assets (property, equipment), and contra accounts (accumulated depreciation).
- **Liabilities (2000–2999)**: Current liabilities (payables, payroll taxes) and long-term liabilities (mortgages, loans).
- **Net Assets (3000–3999)**: Unrestricted net assets, board-designated funds, and permanently restricted (endowment) balances.

### 2. Income/Revenue Accounts (4000–5999)

Separated by restriction status for proper fund accounting.

- **Unrestricted Income (4000–4999)**: Plate offerings, pledges, donations, rental income, fundraising proceeds, investment income—money that can be deployed for any general purpose.
- **Restricted/Designated Income (5000–5999)**: Special offerings (Flowers, Bishop's Mission, etc.), designated gifts (women's/youth groups), capital campaign gifts, and grants—money that must be spent on specified purposes.

### 3. Expense Accounts (6000–8999)

Organized by _functional_ categories to support mission-focused reporting.

- **Outreach Expenses (6000–6999)**: Diocesan assessments, mission work, theological education—money given away beyond the parish to support the larger church and community.
- **Ministry & Program Expenses (7000–7999)**: Clergy compensation, worship/music, formation (Sunday school, youth), fellowship—direct support of the congregation's life and spiritual mission.
- **Operations & Facilities (8000–8999)**: Utilities, maintenance, insurance, office supplies, professional fees, capital projects—the infrastructure cost of being open and functional.

## Account Number Allocation

Each tier is allocated space for detailed sub-categorization:

- **First Digit (1–8)**: Primary category
- **Second Digit (0–9)**: Sub-section (e.g., 1100–1199 = current assets, 1200–1299 = receivables, 1300–1399 = prepaid)
- **Third & Fourth Digits (00–99)**: Individual accounts

### Example Allocation

```ini
11xx = Current Assets
  1110 = Petty Cash
  1120 = Cash - Checking
  1130 = Cash - Savings
  1140 = Cash - Investment

12xx = Receivables
  1210 = Accounts Receivable

13xx = Prepaid/Other Current
  1310 = Prepaid Expenses

14xx = Fixed Assets
  1410 = Land
  1420 = Buildings
  1430 = Accumulated Depreciation - Buildings
  1440 = Furniture, Fixtures & Equipment
  1450 = Accumulated Depreciation - FF&E
```

## Complete Chart of Accounts

### BALANCE SHEET ACCOUNTS

#### Assets (1000–1999)

| Code               | Account Name                         | Type            | Description                                  |
| ------------------ | ------------------------------------ | --------------- | -------------------------------------------- |
| 1000               | ASSETS                               | Heading         |                                              |
| **Current Assets** |                                      |                 |                                              |
| 1110               | Petty Cash                           | Asset (Current) | Cash on hand for small purchases.            |
| 1120               | Cash - Checking                      | Asset (Current) | Main operating checking account.             |
| 1130               | Cash - Savings                       | Asset (Current) | Savings or money market accounts.            |
| 1140               | Cash - Investment Account            | Asset (Current) | Accounts for invested funds.                 |
| 1210               | Accounts Receivable                  | Asset (Current) | Pledges promised but not yet paid.           |
| 1310               | Prepaid Expenses                     | Asset (Current) | Insurance or other items paid in advance.    |
| **Fixed Assets**   |                                      |                 |                                              |
| 1410               | Land                                 | Asset (Fixed)   | Value of land owned.                         |
| 1420               | Buildings                            | Asset (Fixed)   | Value of buildings (church, vicarage, hall). |
| 1430               | Accumulated Depreciation - Buildings | Asset (Contra)  | Reduces building value to show age/use.      |
| 1440               | Furniture, Fixtures & Equipment      | Asset (Fixed)   | Pews, office furniture, computers, etc.      |
| 1450               | Accumulated Depreciation - FF&E      | Asset (Contra)  | Reduces FF&E value.                          |

#### Liabilities (2000–2999)

| Code                      | Account Name             | Type                  | Description                                 |
| ------------------------- | ------------------------ | --------------------- | ------------------------------------------- |
| 2000                      | LIABILITIES              | Heading               |                                             |
| **Current Liabilities**   |                          |                       |                                             |
| 2110                      | Accounts Payable         | Liability (Current)   | Money owed to vendors/suppliers.            |
| 2210                      | Payroll Liabilities      | Liability (Current)   | Taxes or benefits withheld from salaries.   |
| **Long-term Liabilities** |                          |                       |                                             |
| 2310                      | Notes Payable - Mortgage | Liability (Long-term) | Total amount owed on the building mortgage. |
| 2320                      | Notes Payable - Other    | Liability (Long-term) | Other loans payable.                        |

#### Net Assets/Fund Balances (3000–3999)

| Code | Account Name                        | Type      | Description                                                                           |
| ---- | ----------------------------------- | --------- | ------------------------------------------------------------------------------------- |
| 3000 | NET ASSETS                          | Heading   |                                                                                       |
| 3100 | Net Assets - Unrestricted           | Net Asset | Cumulative surplus from general operations.                                           |
| 3200 | Net Assets - Board Designated       | Net Asset | Surplus set aside by the vestry/council for a specific purpose (e.g., building fund). |
| 3300 | Net Assets - Permanently Restricted | Net Asset | Endowment funds where the principal cannot be spent.                                  |

### INCOME/REVENUE ACCOUNTS (4000–5999)

#### Unrestricted Income (4000–4999)

| Code | Account Name                | Type    | Description                                                |
| ---- | --------------------------- | ------- | ---------------------------------------------------------- |
| 4000 | UNRESTRICTED INCOME         | Heading | Income that can be used for any general purpose.           |
| 4110 | Plate & Loose Offerings     | Income  | General Sunday offerings, not pledged.                     |
| 4120 | Pledge & Tithe Revenue      | Income  | Fulfilled pledges and tithes from members.                 |
| 4130 | Pledge Revenue - Prior Year | Income  | Pledges paid that were promised for a prior year.          |
| 4210 | Rental Income - Vicarage    | Income  | Rent received from the vicarage if not occupied by clergy. |
| 4220 | Rental Income - Other       | Income  | Rent from halls or other properties.                       |
| 4310 | Fundraising Events          | Income  | Net income from dinners, auctions, etc.                    |
| 4320 | Donations - Unrestricted    | Income  | General, non-pledge donations.                             |
| 4330 | Sales Revenue               | Income  | Bookstore, bake sale, or thrift shop sales.                |
| 4410 | Investment Income           | Income  | Interest and dividends from unrestricted investments.      |
| 4510 | Miscellaneous Income        | Income  | Any other small, unrestricted income.                      |

#### Restricted/Designated Income (5000–5999)

| Code                         | Account Name                              | Type    | Description                                                       |
| ---------------------------- | ----------------------------------------- | ------- | ----------------------------------------------------------------- |
| 5000                         | RESTRICTED / DESIGNATED INCOME            | Heading | Income that must be used for a specific purpose.                  |
| **Special Offerings**        |                                           |         |                                                                   |
| 5110                         | Special Offerings - Flowers               | Income  | Altar flowers or specific flower offerings.                       |
| 5120                         | Special Offerings - Discretionary Fund    | Income  | For the Rector's/Vicar's discretionary use.                       |
| 5130                         | Special Offerings - UTO                   | Income  | United Thank Offering.                                            |
| 5140                         | Special Offerings - Good Friday           | Income  | Good Friday offering.                                             |
| 5150                         | Special Offerings - Bishop's Mission Fund | Income  | Designated to support diocesan missions.                          |
| 5160                         | Special Offerings - Bishop's Visit        | Income  | Designated for Bishop's visit or special event.                   |
| **Designated Gifts**         |                                           |         |                                                                   |
| 5210                         | Designated Gifts - Women's Group          | Income  | Income specifically for the women's group.                        |
| 5220                         | Designated Gifts - Men's Group            | Income  | Income specifically for the men's group.                          |
| 5230                         | Designated Gifts - Youth Group            | Income  | Income specifically for youth programs.                           |
| **Capital & Major Projects** |                                           |         |                                                                   |
| 5310                         | Capital Campaign Gifts                    | Income  | Gifts for a specific building project (e.g., Kitchen, Bathrooms). |
| 5320                         | Gifts for Major Repairs                   | Income  | Gifts designated for a specific repair project.                   |
| **Grants & External**        |                                           |         |                                                                   |
| 5410                         | Grants Received                           | Income  | Grants from the diocese or other bodies.                          |

### EXPENSE ACCOUNTS (6000–8999)

#### Outreach Expenses (6000–6999)

| Code | Account Name                       | Type    | Description                                                                  |
| ---- | ---------------------------------- | ------- | ---------------------------------------------------------------------------- |
| 6000 | OUTREACH EXPENSES                  | Heading | Money given away beyond the parish.                                          |
| 6110 | Diocesan Assessment / Quota        | Expense | Payment to the larger diocese.                                               |
| 6120 | Theological Education              | Expense | Support for seminaries or continuing education.                              |
| 6130 | Property Insurance (Diocesan Plan) | Expense | If paid through the diocese.                                                 |
| 6210 | Outreach - Local & Global          | Expense | Direct support to food banks, missions, missions partnerships.               |
| 6220 | Special Offering Remittances       | Expense | Sending collected special offerings (UTO, Good Friday, etc.) to the diocese. |

#### Ministry & Program Expenses (7000–7999)

| Code                            | Account Name                          | Type    | Description                                               |
| ------------------------------- | ------------------------------------- | ------- | --------------------------------------------------------- |
| 7000                            | MINISTRY & PROGRAM EXPENSES           | Heading | Money spent on church life and groups.                    |
| **Clergy**                      |                                       |         |                                                           |
| 7110                            | Clergy Salary & Housing               | Expense | Stipend, housing allowance, and cash compensation.        |
| 7120                            | Clergy Pension & Benefits             | Expense | Pension fund contributions and other benefits.            |
| 7130                            | Clergy Continuing Education           | Expense | Funds for clergy development and retreats.                |
| 7140                            | Clergy Travel & Mileage               | Expense | Reimbursement for pastoral visits, conference attendance. |
| **Worship & Music**             |                                       |         |                                                           |
| 7210                            | Worship & Music - Organist            | Expense | Organist/choir director stipend.                          |
| 7211                            | Worship & Music - Supplies            | Expense | Altar flowers, communion elements, candles.               |
| 7212                            | Worship & Music - Sacred Music        | Expense | Sheet music, licenses (CCLI/OneLicense).                  |
| **Formation & Education**       |                                       |         |                                                           |
| 7310                            | Christian Formation                   | Expense | Sunday School, youth group, adult education materials.    |
| **Group & Fellowship Ministry** |                                       |         |                                                           |
| 7320                            | Women's Group Ministry                | Expense | Expenses for the women's group programs.                  |
| 7330                            | Men's Group Ministry                  | Expense | Expenses for the men's group programs.                    |
| 7340                            | Fellowship & Hospitality              | Expense | Coffee hour, potlucks, congregational meals.              |
| **Communications**              |                                       |         |                                                           |
| 7410                            | Communications - Bulletins & Printing | Expense | Weekly bulletins, newsletters, printed materials.         |
| 7411                            | Communications - Postage & Web        | Expense | Website fees, mailing costs, email services.              |

#### Operations & Facilities (8000–8999)

| Code                              | Account Name                       | Type    | Description                                          |
| --------------------------------- | ---------------------------------- | ------- | ---------------------------------------------------- |
| 8000                              | OPERATIONS & FACILITIES            | Heading | The cost of keeping the church open and running.     |
| **Utilities**                     |                                    |         |                                                      |
| 8110                              | Utilities - Church                 | Expense | Electricity, water, gas, trash for church.           |
| 8111                              | Utilities - Vicarage               | Expense | Electricity, water, gas, trash for vicarage.         |
| 8112                              | Utilities - Telephone & Internet   | Expense | Phone, fax, internet services.                       |
| **Maintenance & Repairs**         |                                    |         |                                                      |
| 8210                              | Repairs & Maintenance - Minor      | Expense | Plumbing fixes, light bulbs, small repairs.          |
| 8211                              | Repairs & Maintenance - Grounds    | Expense | Lawn mowing, snow removal, gardening.                |
| 8212                              | Repairs & Maintenance - Janitorial | Expense | Cleaning supplies, cleaning service.                 |
| **Insurance**                     |                                    |         |                                                      |
| 8310                              | Building & Equipment Insurance     | Expense | If paid directly, not through diocese.               |
| 8311                              | Property Taxes                     | Expense | Property taxes if applicable.                        |
| **Office & Administration**       |                                    |         |                                                      |
| 8410                              | Office Supplies                    | Expense | General office paper, pens, toner.                   |
| 8411                              | Office Equipment & Furniture       | Expense | Purchase of computers, desks, chairs.                |
| **Professional & Administrative** |                                    |         |                                                      |
| 8510                              | Professional Fees                  | Expense | Accounting, legal, or consulting fees.               |
| 8511                              | Bank & Merchant Fees               | Expense | Monthly bank charges, credit card processing fees.   |
| **Capital Projects**              |                                    |         |                                                      |
| 8610                              | Major Repairs & Improvements       | Expense | Large, non-routine projects (new roof, HVAC system). |
| 8611                              | Capital Purchases                  | Expense | Buying land or buildings.                            |
| **Depreciation & Miscellaneous**  |                                    |         |                                                      |
| 8710                              | Depreciation Expense               | Expense | Non-cash expense to show asset aging.                |
| 8810                              | Miscellaneous Expense              | Expense | Any other small, uncategorized expenses.             |

## Implementation Notes

### Fund Accounting Separation

The system uses fund accounting principles:

- **General (Unrestricted) Fund**: Operates on accounts 4000–4999 and 8000–8999. Balances there support the church's general mission.
- **Specific Purpose (Designated) Fund**: Operates on accounts 5000–5999. Money in these accounts must be spent according to the donor's or council's intent.
- **Permanently Restricted Fund**: Operates on account 3300 (endowment). Principal is never spent; only investment income may be used.

### Accounts Receivable & Pledges

Track pledges in account 1210 (Accounts Receivable) and recognize them as income only when payment is received, not when pledged (cash basis). For accrual basis churches, adjust the recognition policy accordingly.

### Depreciation & Fixed Assets

- Record property and equipment at cost in accounts 1410–1450.
- Record annual depreciation against contra accounts (1430, 1450) and expense account 8710.
- Review depreciation schedules annually based on useful life (e.g., buildings 40 years, equipment 5–10 years).

### Budget Alignment

**Budgetable accounts** (allow_budget = 1) are limited to:

- 4000–4999 (Unrestricted Income)
- 5000–5999 (Restricted Income)
- 7000–7999 (Ministry Program Expenses)
- 8000–8999 (Operations Expenses)

**Non-budgetable accounts** (allow_budget = 0) typically include:

- Balance sheet accounts (1000–3999)
- Diocesan assessments and other pass-through expenses (6110)
- Depreciation and non-cash items (8710)

### Reporting & Financial Statements

#### Income Statement (Profit & Loss)

```ini
4000–4999: Total Unrestricted Revenue
5000–5999: Total Restricted Revenue
_____________________________________
Total Revenue

6000–6999: Outreach Expenses
7000–7999: Ministry & Program Expenses
8000–8999: Operations Expenses
_____________________________________
Total Expenses

Net Income (Loss)
```

#### Balance Sheet

```ini
Assets (1000–1999)
– Liabilities (2000–2999)
= Net Assets (3000–3999)
```

## Migration & Updates

When the CoA structure is updated:

1. Existing transactions continue to map to legacy account codes through a reconciliation table if needed.
2. All new transactions use the standardized 4-digit system.
3. Historical reporting can aggregate legacy codes into the new structure.
4. Church leadership should review and approve the new CoA structure before implementation.

## Questions & Customization

Churches may need to customize accounts for:

- **Multi-site operations**: Add location suffixes (e.g., 7110-A for Main Campus Clergy, 7110-B for Satellite).
- **Detailed sub-funds**: Create dedicated restricted funds for capital projects or significant ministries.
- **Grant-specific tracking**: Use designated restricted accounts tied to grant requirements.

Contact the system administrator or refer to this document for guidance on adding or modifying accounts.
