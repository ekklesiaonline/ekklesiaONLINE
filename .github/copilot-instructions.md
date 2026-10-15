# ekklesiaRD – Multi-Parish Management System (ChMS)

## 1. Project Overview

ekklesiaRD is a multi‑parish web application designed to streamline administrative tasks for parishes, dioceses and provincial networks in the Episcopal Church.
A system admin (Presiding Bishop, or staff from the central office or 815 ) can view aggregated data across all parishes, while each parish has its own administrators (clergy, vestry members[Senior and Junior Guardians, clerk, treasurer], ministry leaders) who manage only their parish’s data. Diocesan bishop and bishops’ staff have read-only access to all parishes in their diocese. The system supports member management, service attendance tracking, financial record-keeping, volunteer coordination, and visitor follow-up.

Built on CodeIgniter 4.7 with Shield 1.2 for authentication and authorization, and MySQL 8.0, the system uses a modular architecture.
User account data (login credentials) is kept separate from extended profile information to allow flexible user management.
The application supports family grouping, detailed liturgical service tracking, real-time interactions via AJAX, and a clean, responsive interface.

Target Users: Parish administrators, clergy, treasurers, ministry leaders, volunteers, and members (each with appropriate permissions).

## 2. Core Features & Modules

### 2.1 Multi‑Parish & User Management

Multi-Parish support

- A parishes table stores each parish’s details.
- Every record that belongs to a parish (members, services, donations, etc.) includes a parish_id foreign key.
- Super admin can access all parishes; parish admins are restricted to their own parish.
- Authentication (via Shield)
- Registration, login, password reset, email verification – using Shield’s users table (email, password, etc.).

User Profiles

- Extended information stored in a separate profiles table (one‑to‑one with users): first name, middle name, last name, phone, address, social media links, birth date, baptism date, spiritual gifts, etc.

Membership Status

- Canonical lifecycle on `parish_affiliation.membership_status`: `visitor → regular_attender → member`, with `inactive`/`transferred`/`deceased` exits. All transitions go through `MembershipPathwayService`. See `docs/VISITOR_LIFECYCLE.md`.

Family Grouping

- Families are created (e.g., “Smith Family”) and members are linked via a family_members table. Each family can have a designated head of household.

Roles & Permissions

- Granular access control using Shield’s permission system. Predefined roles: Super Admin, Parish Admin, Clergy, Treasurer, Volunteer Coordinator, Member. Permissions are scoped per parish (e.g., members.view may be restricted to a specific parish).

CRUD Operations

- Add, edit, view, deactivate members with AJAX‑powered modals for a smooth user experience.

### 2.2 Service & Attendance Tracking

Service Log

- Record each service with key liturgical details: date, liturgical day, service type (Sunday Worship, Weekday Service, Special, etc.), place, time, preacher, celebrant, notes.
  Note: Total attendance and communicant counts are derived from individual attendance records, not stored redundantly.

Attendance Recording

- Mark individual member attendance for each service via a member search. Optionally record whether they took communion.

Real‑time Check‑in

- AJAX‑powered interface for quick check‑in during services.

Attendance Statistics

- Visual charts (weekly/monthly trends, average attendance) using Chart.js, updated dynamically.
  Export Reports
- Generate PDF/Excel summaries of attendance.

### 2.3 Parish Accounting

Income Management

- Record tithes, offerings, and other donations. Donations can be linked to members for automated giving statements.

Expense Tracking

- Categorize expenses (utilities, salaries, missions) and track approvals.

Financial Reports

- Income vs. expense summaries, balance sheets, donor statements (with export options).

Budget Management

- Set annual/monthly budgets and compare against actuals.

### 2.4 Leadership & Volunteer Management

Volunteer Teams

- Create teams (worship, ushers, children’s ministry) and assign members.

Scheduling

- Assign volunteers to service rosters; view assignments in a calendar.

Availability & Skills

- Track member availability and skills to optimise scheduling.

Communication

- Send email/SMS reminders for upcoming duties (integrated with an email service).

### 2.5 First‑Time Visitor Follow‑up

Visitor Check‑in

- Simple form to capture visitor details (name, contact, visit date, service attended).

Automated Follow‑up

- Configurable workflow – send a thank‑you email, assign a greeter for a follow‑up call, and track interactions.

Follow‑up Tasks

- Create and assign tasks to leaders/volunteers; mark tasks as completed.

### 2.6 Dashboard & Real‑time AJAX Features

Dashboard Widgets

- Summaries of member counts (by status, by family), attendance trends, recent donations, pending follow‑ups, and financial snapshots.

AJAX Updates

- Refresh statistics without page reload after actions like marking attendance or adding a member.

Live Search & Filters

- Instant search for members, transactions, volunteers, etc.

## 3. Technology Stack

Backend: PHP 8.1+, CodeIgniter 4.7
Authentication: CodeIgniter Shield 1.2
Database: MySQL 8.0
Frontend: Bootstrap 5 (responsive UI), jQuery (or vanilla JS) for AJAX, Chart.js for charts
Additional Libraries: Dompdf (PDF), PhpSpreadsheet (Excel), CodeIgniter Settings (configuration management)

## 4. Database Schema (Key Tables & Pending Tables)

Shield provides default tables: users, auth_identities, auth_logins, auth_permissions, auth_roles, auth_groups_users.
We extend with the following tables (all include parish_id for multi‑tenancy unless noted otherwise):

### 4.0 Pending/Planned Tables (Not Yet Used in Code)

| Table Name               | Status   | Notes                               |
| ------------------------ | -------- | ----------------------------------- |
| bank_account             | Not used | Needed for finance/bank integration |
| bank_reconciliation      | Not used | Needed for bank conciliation        |
| bank_transaction         | Not used | Needed for bank conciliation        |
| bank_reconciliation_item | Not used | Needed for bank conciliation        |
| small_groups             | Not used | Needed for small group ministry     |
| group_membership         | Not used | Needed for small group ministry     |
| ...                      | ...      | ...                                 |

See the implementation plan below for details.

## 17. Pending/Planned Features & Implementation Plan

### 17.1 Finance: Bank Accounts & Reconciliation

- **Schema present:** `bank_account`, `bank_transaction`, `bank_reconciliation`, `bank_reconciliation_item`
- **Missing:** Models, controllers, UI, permissions, integration with donations/expenses
- **Plan:**
    1. Create models for each table (BankAccountModel, BankTransactionModel, etc.)
    2. Build CRUD controllers and admin UI for bank accounts
    3. Implement reconciliation workflow (import, match, mark cleared)
    4. Integrate with donations/expenses for auto-matching
    5. Add permissions: `finance.bank_accounts`, `finance.reconcile`

### 17.2 Small Groups Module

- **Schema present:** `small_groups`, `group_membership`
- **Missing:** Models, controllers, UI, group assignment logic
- **Plan:**
    1. Create models/controllers for small groups and memberships
    2. Add group assignment UI to member profile
    3. Integrate with member standing (Stage 2 completion)
    4. Add permissions: `groups.manage`, `groups.assign`

### 17.3 Bulk Messaging

- **Schema:** Not present (needs design)
- **Missing:** Messaging module, queue, UI, permissions
- **Plan:**
    1. Design schema for message queue, delivery logs
    2. Build messaging service (email/SMS)
    3. Add UI for composing and sending bulk messages
    4. Add permissions: `messaging.send_bulk`, `messaging.view_logs`

### 17.4 Visitor Follow-up Enhancements

- **Superseded:** the `guests`/`guest_visits`/`guest_followup_log`/`follow_up_tasks` schema era is retired. Visitor follow-up runs on `person_visit` + `followup_*` tables over `parish_affiliation` visitor/regular_attender rows.
- **Done:** single `POST /visitors/promote/{personId}` conversion flow (mark member / link user / create account) replaced the flashdata pre-fill handoff; `regular_attender` is a managed intermediate stage.

### 17.5 Other Gaps

- **Member Pathway Tracking:** Add `pathway_stage` to profiles
- **Leadership Appointment Gate:** Enforce good standing at appointment
- **Exports:** Implement PDF/Excel exports for giving statements, directories

See also: docs/MIGRATIONS_AND_SEEDERS.md for migration status.

### 4.1 Multi‑Parish Foundation

**parishes**

- id (INT, PK)
- name (VARCHAR)
- address (TEXT)
- phone (VARCHAR)
- email (VARCHAR)
- website (VARCHAR)
- created_at, updated_at

### 4.2 Profiles

**profiles**

- id (INT, PK)
- user_id (INT, UNSIGNED, UNIQUE, FK to users.id)
- parish_id (INT, UNSIGNED, FK to parishes.id) // which parish the user belongs to
- first_name, middle_name, last_name (VARCHAR, nullable)
- phone (VARCHAR, nullable)
- address, city, state, zip, country (nullable)
- social_media (JSON, nullable)
- birth_date, baptism_date (DATE, nullable)
- membership_status (ENUM: active, inactive, visitor, pending, regular_attender) DEFAULT 'regular_attender'
- spiritual_gifts (TEXT, nullable)
- created_at, updated_at

### 4.3 Families

**families**

- id (INT, PK)
- parish_id (INT, UNSIGNED, FK to parishes.id)
- family_name (VARCHAR)
- head_of_household_id (INT, UNSIGNED, FK to users.id, nullable) // head can be NULL initially
- address (TEXT, nullable)
- created_at, updated_at

### 4.4 Family Members

**family_members**

- id (INT, PK)
- family_id (INT, UNSIGNED, FK to families.id)
- user_id (INT, UNSIGNED, FK to users.id)
- relationship (VARCHAR, e.g., "Head", "Spouse", "Child", nullable)
- created_at

### 4.5 Services

**services**

- id (INT, PK)
- parish_id (INT, UNSIGNED, FK to parishes.id)
- date (DATE)
- liturgical_day (VARCHAR, nullable)
- service_type (ENUM: 'Sunday Worship','Weekday Service','Special','Other')
- place (VARCHAR, nullable)
- time (TIME, nullable)
- preacher (VARCHAR, nullable)
- celebrant (VARCHAR, nullable)
- notes (TEXT, nullable)
- created_at, updated_at
  Note: Attendance counts are derived from the attendance table; they are not stored here.

### 4.6 Attendance (Individual)

**attendance**

- id (INT, PK)
- service_id (INT, UNSIGNED, FK to services.id)
- user_id (INT, UNSIGNED, FK to users.id)
- attended (BOOLEAN, DEFAULT false)
- communicant (BOOLEAN, DEFAULT false) // whether they took communion
- created_at

### 4.7 Visitors

**Retired:** the standalone `guests` table was dropped. Visitors are `person` rows with `parish_affiliation.membership_status = 'visitor'`; visit history lives on `person_visit` (`visit_date`, `visit_type` first/return, `service_id`, `attended_event`). The affiliation row carries `followed_up` and `converted_to_member_at`/`converted_by_user_id` markers. See `docs/VISITOR_LIFECYCLE.md`.

### 4.8 Follow‑up Log

**followup_log** (replaces the retired `follow_up_tasks` / `guest_followup_log`)

- id (INT, PK)
- person_id, parish_id (INT)
- action_id (INT, FK to followup_action.id, nullable)
- assigned_to (INT, FK to users.id, nullable)
- scheduled_date (DATE)
- executed_at (DATETIME, nullable)
- status ('pending','sent','failed','skipped')
- notes, response_metadata
- created_at, updated_at

### 4.9 Donations

**donations**

- id (INT, PK)
- parish_id (INT, UNSIGNED, FK to parishes.id)
- user_id (INT, UNSIGNED, FK to users.id, nullable) // null for anonymous/non‑member
- amount (DECIMAL(10,2))
- date (DATE)
- category (ENUM: 'tithe','offering','mission','building fund','other')
- payment_method (VARCHAR, nullable)
- notes (TEXT, nullable)
- created_at, updated_at

### 4.10 Expenses

**expenses**

- id (INT, PK)
- parish_id (INT, UNSIGNED, FK to parishes.id)
- description (VARCHAR)
- amount (DECIMAL(10,2))
- category (VARCHAR) // e.g., utilities, salaries, maintenance
- date (DATE)
- approved_by (INT, UNSIGNED, FK to users.id, nullable)
- notes (TEXT, nullable)
- created_at, updated_at

### 4.11 Volunteer Teams

**volunteer_teams**

- id (INT, PK)
- parish_id (INT, UNSIGNED, FK to parishes.id)
- name (VARCHAR)
- description (TEXT, nullable)
- leader_id (INT, UNSIGNED, FK to users.id, nullable)
- created_at, updated_at

### 4.12 Team Members

**team_members**

- id (INT, PK)
- team_id (INT, UNSIGNED, FK to volunteer_teams.id)
- user_id (INT, UNSIGNED, FK to users.id)
- role (VARCHAR, nullable) // e.g., "coordinator", "member"
- created_at

### 4.13 Volunteer Schedule

**volunteer_schedule**

- id (INT, PK)
- team_id (INT, UNSIGNED, FK to volunteer_teams.id)
- service_id (INT, UNSIGNED, FK to services.id, nullable) // optional link to a specific service
- user_id (INT, UNSIGNED, FK to users.id)
- date (DATE)
- time_slot (VARCHAR, nullable) // e.g., "morning", "afternoon"
- status (ENUM: 'scheduled','confirmed','cancelled') DEFAULT 'scheduled'
- created_at, updated_at

## 5. Authentication & Authorization with Shield

Shield provides a robust foundation for authentication and role‑based permissions. We will:

Extend the User Model
Add a parish_id field to the users table (or use a separate user_parish table if users can belong to multiple parishes – here we assume one parish per user for simplicity).

Alternatively, keep the parish_id in the profiles table and retrieve it when needed.
Define Permissions

Examples:

members.view – view member list
members.edit – edit member details
attendance.mark – record attendance
finance.view – view financial reports
finance.manage – add/edit donations/expenses
volunteers.assign – manage volunteer teams and schedules
followup.manage – manage visitor follow‑ups
admin.province – super admin (access all parishes)

Define Roles

- Super Admin – all permissions, can switch between parishes.
- Parish Admin – full access to one parish’s data (except super admin functions).
- Clergy – can view members, mark attendance, view financial summaries.
- Treasurer – manage finances (donations, expenses, reports).
- Volunteer Coordinator – manage teams and schedules.
- Member – view own profile, view family, possibly mark own attendance (if allowed).

Scoping Permissions per Parish

- In controllers, after authentication, check if the user has the required permission and if the requested record belongs to the user’s parish (or if user is super admin). This can be done with a global scope or manually in queries.

Middleware

- Use Shield’s session and permission filters to protect routes. Example route group:

    ```php
    $routes->group('members', ['filter' => 'permission:members.view'], function($routes) {
    $routes->get('/', 'MemberController::index');
    $routes->post('add', 'MemberController::add', ['filter' => 'permission:members.edit']);
    });
    ```

## 6. AJAX Integration Details

- Member Search – Type‑ahead when marking attendance or assigning tasks.
- Inline Editing – Update member status or role without page reload.
- Attendance Marking – Submit via AJAX; dashboard widgets update automatically.
- Dashboard Charts – Fetch updated data periodically or after key actions.
- Implementation – Use jQuery’s $.ajax or the Fetch API. Controllers return JSON responses.

### 6.1 Native Fetch CSRF Pattern (Mandatory)

All native `fetch()` POST calls **must** use the following pattern. Do **not** use `Content-Type: application/json` — CI4’s CSRF filter only sees the token when it is sent as a request header matching the CSRF header name (read from the `csrf-header` meta tag).

```javascript
const csrfMeta = document.querySelector('meta[name="csrf-token"]'); // ← name is "csrf-token" (lowercase)
const csrfHeader =
	document.querySelector('meta[name="csrf-header"]')?.content ??
	"X-CSRF-TOKEN";

const headers = {
	"X-Requested-With": "XMLHttpRequest",
	"Content-Type": "application/x-www-form-urlencoded",
};
if (csrfMeta) {
	headers[csrfHeader] = csrfMeta.content;
}

fetch(url, {
	method: "POST",
	credentials: "same-origin",
	headers,
	body: new URLSearchParams({ field: value }).toString(),
})
	.then(function (r) {
		// Refresh CSRF token from response header (CI4 regenerates it on each request).
		const newToken = r.headers.get(csrfHeader);
		if (newToken && csrfMeta) {
			csrfMeta.content = newToken;
		}
		return r.json();
	})
	.then(function (data) {
		/* handle data.success */
	})
	.catch(function () {
		showAppToast(errFallback, "danger");
	});
```

**Critical rules:**

- The meta tag name in the layout is `csrf-token` (all lowercase). Never use `X-CSRF-TOKEN` as the `querySelector` selector — that returns `null` and sends an empty token, causing a CI4 403 "The action you requested is not allowed."
- Always keep a reference to the `csrfMeta` **element** (`const csrfMeta = document.querySelector('meta[name="csrf-token"]')`) and read `csrfMeta.content` each time you build headers. Do not cache the token value in a separate variable — CI4 regenerates the token after every successful POST, and a cached value causes the next request to fail with "The action you requested is not allowed." Refresh `csrfMeta.content` from the response header inside `.then()`.
- Send body fields as `URLSearchParams` (form-encoded), not `JSON.stringify`. Controller reads via `$this->request->getPost('field')`.
- Reference implementation: `Clergy/Views/Visitors/followup_manual.php` `ajaxPost()` helper.

## 7. Security Considerations

- Input Validation – CI4’s validation library on all forms.
- CSRF Protection – Enabled by default; the CSRF token is injected via `<meta name="csrf-token">` in every layout. Always use the fetch pattern in §6.1 for native fetch calls.
- Authorization – Shield’s permission checks on every sensitive route, plus parish‑scoping.
- Data Encryption – Encrypt sensitive data (e.g., donation amounts could be encrypted at rest if required).
- HTTPS – Enforced in production.
- SQL Injection Prevention – Use Query Builder or prepared statements.

## 8. User Interface & Experience

- Responsive Design – Bootstrap 5 ensures mobile‑friendliness.
- Intuitive Navigation – Sidebar menu grouped by modules, with parish selector for super admin.
- Modal Forms – Quick add/edit without leaving the page.
- Toast Notifications – Non‑intrusive success/error messages after AJAX actions.

### 8.1 UI Consistency Rules (Mandatory)

- Do not duplicate page titles inside content views when the top header bar already displays the page title (`page_title` from `getCommonViewData()`).
- For form/detail pages, use a standardized top action row and place actions there without a repeated local `<h1>/<h2>/<h4>` page title.
- Standard top action row pattern: `d-flex justify-content-start align-items-center mb-3`.
- Keep primary navigation/back/create actions in that top row; avoid right-aligned one-off wrappers like `text-end` or `justify-content-end` unless there is a specific approved UX exception.
- Button appearance must be outline-first across modules for consistency. Prefer Bootstrap outline variants (`btn-outline-*`) and avoid introducing custom filled-only button treatments.
- **Badge appearance must also be outline-first.** All status/label badges must use `border border-{color} text-{color} fw-normal` instead of `bg-{color}`. Never use filled `bg-*` badge variants — they do not adapt to dark mode and violate the outline-first rule.
    - Static example: `<span class="badge border border-success text-success fw-normal">Active</span>`
    - Dynamic color example — extract the color to a PHP variable first, then reference it twice:
        ```php
        <?php $statusColor = $isActive ? 'success' : 'secondary'; ?>
        <span class="badge border border-<?= $statusColor ?> text-<?= $statusColor ?> fw-normal">
        ```
    - Do **not** inline complex ternaries directly into both `border-{color}` and `text-{color}` positions — extract to a variable for readability.
    - JS-side badge updates must also use the outline class string, e.g. `badge border border-success text-success fw-normal`.
- **Table headers (`<thead>`) must never carry hardcoded color-variant classes** (`table-light`, `table-dark`, or any Bootstrap color variant). Leave `<thead>` unstyled so Bootstrap's theme tokens (`data-bs-theme`) apply automatically in both light and dark mode.
- Deletion UX must use split modals: one dedicated single-item delete modal (show item identity + irreversible warning + required reason), and a separate bulk-delete modal for multi-select (show selected count + irreversible warning + required reason).
- If a bulk action is triggered with exactly one selected row, route to the single-item delete modal instead of the bulk-delete modal.
- For in-table profile/detail links (for example Name columns), links must keep surrounding typography (`font-size`/`font-weight` inherit), use no underline at rest, and be differentiated primarily by color; hover underline is allowed.
- When touching legacy views, opportunistically normalize them to the same header/action/button consistency rules as part of the change.

### 8.2 Shared View Partials (Mandatory)

When the same modal or form block appears in two or more views, extract it into a named partial and include it with `$this->include()`. Do **not** duplicate inline HTML across views.

**Naming convention:** prefix the file with `_` (e.g., `_non_portal_modal.php`). Store it alongside the primary view it belongs to conceptually (e.g., `Clergy/Views/Members/_non_portal_modal.php`).

**Passing context to a partial:** use the `view()` helper with an explicit data array — do **not** use `$this->include()` for partials that need custom variables. `$this->include()` only passes `$this->data` (controller-injected data), not PHP variables set locally inside a view file.

```php
// Caller (Members/index.php)
<?= view('Clergy\Views\Members/_non_portal_modal', [
    'modalId'     => 'createStandaloneNonPortalModal',
    'fieldPrefix' => 'nm',
    'formAction'  => base_url('members/store-non-portal'),
]) ?>

// Caller (People/index.php)
<?= view('Clergy\Views\Members/_non_portal_modal', [
    'modalId'     => 'createNonPortalPersonModal',
    'fieldPrefix' => 'np',
    'formAction'  => base_url('people/store-non-portal'),
]) ?>
```

**Partial contract:** document required variables at the top of the partial file in a PHP comment block listing each variable name and its purpose.

**Existing partials:**

| Partial file                                 | Used by                                 | Variables                                 |
| -------------------------------------------- | --------------------------------------- | ----------------------------------------- |
| `Clergy/Views/Events/_form.php`              | `Events/create.php`, `Events/edit.php`  | _(form fields)_                           |
| `Clergy/Views/Members/_non_portal_modal.php` | `Members/index.php`, `People/index.php` | `$modalId`, `$fieldPrefix`, `$formAction` |

### 8.3 Collapsible Filter Panel (Mandatory)

Every list/index view that exposes search or filter controls **must** use the collapsible filter panel pattern. Never place filter forms directly inside a plain `<div class="card-body">` without a collapsible wrapper.

**Rules:**

- Compute a `$xyzFiltersActive` boolean **before** the card markup. It must be `true` when any meaningful filter (search text, status, date range, etc.) differs from its default/empty state. Year-only selectors that are always present do not count as "active" unless the user has changed them from the default.
- When `$xyzFiltersActive` is `true`, render `aria-expanded="true"` and add the `show` class so the panel opens automatically on page load.
- When `$xyzFiltersActive` is `false`, render `aria-expanded="false"` and omit `show` so the panel starts collapsed.
- All form controls inside the panel must use the `-sm` size variants: `form-control-sm`, `form-select-sm`.
- The Apply button must be `btn btn-sm btn-primary flex-fill` with a `bi-funnel` or `bi-search` icon. The Clear/Reset link must be `btn btn-sm btn-outline-secondary flex-fill`. Both must sit side-by-side in a `d-flex gap-2` column.
- The Clear link must preserve any non-filter state (e.g., a year parameter) that should not be wiped on reset.
- Use `lang('Services.apply')` for Apply and `lang('App.clear')` for Clear — do **not** invent new lang keys for these two labels.
- Do **not** use a full-width `w-100` on either button — use `flex-fill` instead so they share the column evenly.

**Standard structure:**

```php
<?php $xyzFiltersActive = trim((string) ($search ?? '')) !== '' || trim((string) ($status ?? '')) !== ''; ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-end filter-panel-header">
        <button class="btn btn-outline-secondary btn-sm" type="button"
            data-bs-toggle="collapse"
            data-bs-target="#xyzFilterPanel"
            aria-expanded="<?= $xyzFiltersActive ? 'true' : 'false' ?>">
            <i class="bi bi-funnel"></i> <?= lang('App.filter') ?>
        </button>
    </div>
    <div id="xyzFilterPanel" class="collapse <?= $xyzFiltersActive ? 'show' : '' ?>">
        <div class="card-body border-top">
            <form method="get" action="<?= base_url('route') ?>" class="row g-2 align-items-end">
                <!-- filter fields using form-control-sm / form-select-sm -->
                <div class="col-md-N d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">
                        <i class="bi bi-funnel"></i> <?= lang('Services.apply') ?>
                    </button>
                    <a href="<?= base_url('route') ?>" class="btn btn-sm btn-outline-secondary flex-fill"><?= lang('App.clear') ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
```

**Views using this pattern (reference implementations):**

| View file                                       | Panel ID                | Active condition                                                 |
| ----------------------------------------------- | ----------------------- | ---------------------------------------------------------------- |
| `Clergy/Views/People/index.php`                 | `#peopleFilterPanel`    | search / status / family                                         |
| `MemberPortal/Views/Admin/audit_logs.php`       | `#auditLogFilterPanel`  | parish / action / start_date / end_date                          |
| `Clergy/Views/Visitors/index.php`             | `#visitorFilterPanel`   | search / lifecycle / followup_stage / source                     |
| `Clergy/Views/Reports/roi_second_visit.php`     | `#roiFilterPanel`       | from_date / to_date (collapsed by default — no active detection) |
| `Clergy/Views/Events/index.php`                 | `#eventsFilterPanel`    | search / status                                                  |
| `MemberPortal/Views/Pledges/index.php`          | `#pledgeFilterPanel`    | search / per_page ≠ 25                                           |

### 8.4 Row Actions Dropdown (Mandatory for 3+ actions)

When a list row (table `<tr>` or `list-group-item`) would expose **3 or more action buttons**, replace them with a single `⋮` indicator that opens a Bootstrap **dropdown menu**. This keeps rows scannable and only surfaces actions when the user asks for them.

**Rules:**

- Trigger: `<button>` with `data-bs-toggle="dropdown"` and `bi-three-dots-vertical` icon. Use `btn-outline-secondary btn-sm`.
- Menu: `<ul class="dropdown-menu dropdown-menu-end shadow-sm">` — anchored to the right edge so it never clips off-screen.
- Each item is a `<button class="dropdown-item">` (not an `<a>`) so it works without a URL.
- Destructive actions (Delete, Mark Failed, Deactivate) must be placed **last**, separated from the rest by a `<li><hr class="dropdown-divider"></li>`, and carry `text-danger` on the item.
- When there is only **1 action** (e.g. a call-task "Complete" button), keep it as a plain button — a dropdown for a single item adds no value.
- All existing `data-bs-toggle="modal"` / `data-*` attributes move from the old buttons to the `dropdown-item` buttons unchanged; modal JS handlers require no modification.
- `title` attribute on the trigger should use `lang('App.colActions')`.

**Standard structure:**

```html
<div class="dropdown">
	<button
		class="btn btn-sm btn-outline-secondary"
		type="button"
		data-bs-toggle="dropdown"
		aria-expanded="false"
		title="<?= esc(lang('App.colActions')) ?>">
		<i class="bi bi-three-dots-vertical"></i>
	</button>
	<ul class="dropdown-menu dropdown-menu-end shadow-sm">
		<li>
			<button
				class="dropdown-item"
				data-bs-toggle="modal"
				data-bs-target="#editModal"
				data-id="<?= $row['id'] ?>">
				<i class="bi bi-pencil me-2"></i
				><?= lang('App.edit') ?>
			</button>
		</li>
		<!-- more items … -->
		<li><hr class="dropdown-divider" /></li>
		<li>
			<button
				class="dropdown-item text-danger"
				data-bs-toggle="modal"
				data-bs-target="#deleteModal"
				data-id="<?= $row['id'] ?>">
				<i class="bi bi-trash3 me-2"></i
				><?= lang('App.delete') ?>
			</button>
		</li>
	</ul>
</div>
```

**Views where this pattern is already applied:**

| View file                                 | Actions in dropdown                                   |
| ----------------------------------------- | ----------------------------------------------------- |
| `Clergy/Views/Visitors/followup_manual.php` | Mark Done, Reschedule, Skip, Print Label, Mark Failed |

**Views recommended for migration (3+ inline buttons per row):**

| View file                                     | Current inline actions                              |
| --------------------------------------------- | --------------------------------------------------- |
| `Worship/Views/WorshipPlanning/songs.php`     | Preview Lyrics, Edit, Deactivate/Reactivate, Delete |
| `Diocesan/Views/Parishes/index.php`           | Switch, Edit, Staff, Delete                         |
| `Clergy/Views/FollowupWorkflow/sequences.php` | View Actions, Toggle Active, Delete                 |
| `Clergy/Views/FollowupWorkflow/templates.php` | Preview, Edit, Delete                               |
| `Clergy/Views/Visitors/index.php`               | View, Edit, Delete                                  |

## 9. Deployment & Maintenance

- Version Control – Git (e.g., GitHub, GitLab).
- Environment Configuration – .env file for database credentials, etc.
- Backup Strategy – Automated daily MySQL backups.
- Documentation – User manual and technical documentation (including database ERD).

## 10. Database Migrations (Examples)

All migrations follow the patterns shown above. Here is an example for the parishes table:

```php
// app/Database/Migrations/2025_01_01_000001_create_parishes.php

<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateParishes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'address' => ['type' => 'TEXT', 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'website' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('parishes');
    }

    public function down()
    {
        $this->forge->dropTable('parishes');
    }
}
```

Similarly, adjust the previously shown migrations to include parish_id where needed.

## 11. Setting Up Permissions and Roles with Shield

In this project, Shield role assignments are stored in:

- auth_groups_users – users assigned to groups (group stored as VARCHAR in `group` column).

Note: This schema does not include `auth_groups` or `auth_groups_permissions`, so application queries should not join those tables.

Step‑by‑step setup:

Define permissions in a seeder:

```php
$authorize = service('authorization');
$authorize->createPermission('members.view', 'View member list');
$authorize->createPermission('members.edit', 'Edit member details');
// ... etc.
```

Create groups (roles) and assign permissions:

```php
$authorize->createGroup('provinceadmin', 'Super Administrator');
$authorize->addPermissionToGroup('members.view', 'provinceadmin');
$authorize->addPermissionToGroup('members.edit', 'provinceadmin');
// ... assign all permissions to provinceadmin

$authorize->createGroup('clergy', 'Clergy');
$authorize->addPermissionToGroup('members.view', 'clergy');
$authorize->addPermissionToGroup('attendance.mark', 'clergy');
// ...
```

Assign users to groups (e.g., when creating a user or via admin UI).

Protect routes using the permission filter:

```php
$routes->group('', ['filter' => 'session'], function($routes) {
    $routes->get('members', 'Members::index', ['filter' => 'permission:members.view']);
});
```

Enforce parish scoping in controllers:

```php
// In Members controller
public function index()
{
    $parishId = auth()->user()->parish_id;  // from profiles or user table
    if (auth()->user()->can('admin.province')) {
        // super admin may see all or choose parish
        $parishId = $this->request->getGet('parish_id') ?? $parishId;
    }
    $members = $this->profileModel->where('parish_id', $parishId)->findAll();
    // ...
}
```

## 12. Sunday Attendance Graphics Constraint (Parish Admin Scope)

Hard rule for parish admin dashboard graphics that draw Sunday attendance (for example, `ekklesiard.local/dashboard` in non-diocesan scope):

- Use raw values from `service_metrics` rows joined to `services`.
- Do not apply bucket-level averaging or derived ASA/ASC calculations for these graphics.
- If multiple Sunday services exist on the same date, preserve separate service points in chart series.
- Keep member-level attendance (`attendance` table) decoupled from parish Sunday graphics data retrieval.

Exception:

- Calculated KPI cards or reports that are explicitly ASA/ASC may continue using calculated values.

## 13. Member Dedup, Merge, and Hard-Delete Module

### 13.1 Module Location

| File                                               | Purpose                                                                |
| -------------------------------------------------- | ---------------------------------------------------------------------- |
| `app/Services/MemberMergeService.php`              | All business logic: duplicate detection, merge, hard-delete            |
| `Clergy/Controllers/MemberMerge.php`               | HTTP controller (index, preview, merge, confirmHardDelete, hardDelete) |
| `Clergy/Views/MemberMerge/index.php`               | Duplicate pairs list                                                   |
| `Clergy/Views/MemberMerge/preview.php`             | Side-by-side field comparison + merge form                             |
| `Clergy/Views/MemberMerge/hard_delete_confirm.php` | Three-gate confirmation page                                           |
| Routes in `Clergy/Config/Routes.php`               | `members/dedup/**` group, permission `members.delete`                  |

### 13.2 Duplicate Detection

`MemberMergeService::findDuplicatesForParish(int $parishId)` returns pairs where, within the same `parish_id`:

- `LOWER(TRIM(first_name))` + `LOWER(TRIM(last_name))` match, **OR**
- Non-empty `phone` values match (after decryption via `DataEncryption::decrypt()`).

The query uses a self-join with `p1.id < p2.id` to avoid symmetric pairs.

### 13.3 Merge Flow

1. Admin opens `/members/dedup` → sees all duplicate pairs sorted **oldest first** (by `p1.created_at ASC`). **Person A is always the older/lower-id record** and is pre-selected as canonical. The ↔ swap button allows reversal.
2. Clicks **Review & Merge** → navigates to `/members/dedup/preview?canonical=A&duplicate=B`.
3. Preview page shows both profiles side by side; differing fields have a radio to pick which value to carry forward onto the canonical.
4. Admin fills in a merge reason (min 8 chars) and clicks **Execute Merge** (AJAX POST to `/members/dedup/merge`).

Inside `MemberMergeService::merge()` — single transaction:

- **Phase 1**: `reassignUserReferences($duplicateUserId, $canonicalUserId)` — reassigns all transactional FK rows from duplicate → canonical (with dedup guards on `family_members`, `team_members`, `parish_affiliation`).
- **Phase 2**: Apply `$fieldOverrides` to the canonical `profiles` row.
- **Phase 3**: `performHardDelete($duplicateUserId)` — delete all remaining data for the duplicate.

### 13.4 Hard-Delete Flow

Accessible from:

- `/members/view/:id` → "Permanently Delete" button (requires `members.delete`)
- `/members/dedup` as the final phase of a merge

Three security gates enforced in `MemberMerge::hardDelete()` **before** calling the service:

1. **Name confirmation** — caller must POST `confirm_name` matching `strtolower(first_name . ' ' . last_name)`.
2. **Reason** — `delete_reason` must be ≥ 10 characters.
3. **Acknowledgment checkbox** — `acknowledge_irreversible = 1` must be present.

All three gates are also validated client-side (submit button stays disabled until all pass).

The audit log entry `member.hard_delete` is written **before** the service call while the profile still exists.

### 13.5 FK Resolution Table

| Strategy                                          | Tables                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| ------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Reassign to canonical** (merge only)            | `attendance`, `annual_pledges`, `member_discipline`, `member_standing_summary`, `event_registrations`, `donations`, `family_members`, `team_members`, `volunteer_schedule`, `leadership_positions`, `parish_affiliation`, `followup_log`, `call_task`, `parish_role_audit_logs` (both columns), `families.head_of_household_id`, `volunteer_teams.leader_id`, `expenses.approved_by`, `person_visit`, `parish_budgets.*`, `audit_logs.user_id`, `user_registration_requests.reviewed_by`, `leadership_positions.appointed_by_user_id` |
| **NULL** (hard-delete, preserve surrounding rows) | `donations.user_id`, `families.head_of_household_id`, `volunteer_teams.leader_id`, `expenses.approved_by`, `event_registrations.user_id`, `person_visit`, `parish_budgets.*`, `audit_logs.user_id`, `user_registration_requests.reviewed_by`, `leadership_positions.appointed_by_user_id`                                                                                                                                                                                                                                           |
| **Delete rows**                                   | `parish_role_audit_logs` (NOT NULL, cannot be NULLed), `attendance`, `annual_pledges`, `family_members`, `team_members`, `volunteer_schedule`, `leadership_positions`, `parish_affiliation`, `member_discipline`, `member_standing_summary`, `system_admin_primary`, `user_registration_requests (user_id)`, `parish_people`, `auth_identities`, `auth_groups_users`, `auth_permissions_users`, `auth_remember_tokens`, `user_sessions`, `profiles`, `users`                                         |

### 13.6 Portal Revocation Rule (critical invariant)

**Never set `users.active = 0` as part of portal revocation or merge/delete.**

`syncParish()` in `ParishPeopleRegistryService` filters on `u.active = 1`. Setting `users.active = 0` silently removes the person from the `parish_people` snapshot and makes them invisible on `/people`.

The correct revocation is: **delete the `auth_identities` row only** (type `email_password`). Shield requires that row to authenticate — login is impossible without it, and the member remains correctly visible.

If you find `users.active = 0` rows that have active `parish_affiliation` and no `auth_identities`, restore them with:

```sql
UPDATE users u
LEFT JOIN auth_identities ai ON ai.user_id = u.id AND ai.type = 'email_password'
INNER JOIN parish_affiliation mca ON mca.linked_user_id = u.id AND mca.deleted_at IS NULL
SET u.active = 1
WHERE u.active = 0 AND ai.id IS NULL;
```

Then run `php spark parish-people:sync`.

### 13.7 FK Column Precision Rule (merge/hard-delete)

When deleting or reassigning user references, **do not assume every table uses `user_id`**.

Critical mapping in this module:

- `followup_log` references users via **`assigned_to`** (NOT `user_id`).
- During merge: reassign `assigned_to` to the canonical user.
- During hard-delete: delete `followup_log` rows by `assigned_to = :userId`.

Implementation rule:

- For repeated table/column mutations, prefer tuple lists like `[['table','column'], ...]`.
- Avoid associative arrays where duplicate table keys can silently overwrite earlier mappings.

### 13.8 Dedup UI Messaging & Execution Rules

For dedup merge and hard-delete pages (`Clergy/Views/MemberMerge/preview.php`, `Clergy/Views/MemberMerge/hard_delete_confirm.php`):

- Use Bootstrap modals for destructive action confirmation.
- Use shared `showAppToast(message, level)` for user feedback (`primary`, `warning`, `danger`).
- Do not use blocking browser dialogs (`alert()`, `confirm()`) for normal control flow.
- Show loading state on execute buttons during async requests.
- On success: show toast, then redirect with a short delay.
- On failure/network error: show toast and fully restore button state.
- Add graceful fallback when Bootstrap modal runtime is unavailable: execute path must still run (no dead click).

## 14. UI Messaging & Confirmation Standards (System-wide)

All modules share a unified modal/toast system. **Never use `window.alert()`, `window.confirm()`, or `window.prompt()` in view files.**

### 14.1 Global Helpers (available in every layout)

| Helper                                                  | Signature                                         | Use for                                                         |
| ------------------------------------------------------- | ------------------------------------------------- | --------------------------------------------------------------- |
| `showAppToast(msg, level)`                              | `level`: `'primary'` \| `'warning'` \| `'danger'` | Non-blocking feedback: success, validation warnings, errors     |
| `showAppConfirm(title, body, onConfirm, confirmLabel?)` | `onConfirm`: callback executed on OK              | Destructive or irreversible AJAX actions requiring confirmation |

Both are injected by every module layout (`app/Views/layouts/main.php`, `Clergy/Views/layouts/main.php`, `Worship/…`, `Diocesan/…`, `Province/…`, `Treasurer/…`, `Volunteer/…`). MemberPortal views extend `app/Views/layouts/main.php`.

`showAppConfirm` uses `#appConfirmModal` (Bootstrap modal). Fallback to `window.confirm()` runs only when Bootstrap is not yet initialised (e.g., during layout parse phase) — this is intentional and the only sanctioned use of `window.confirm`.

### 14.2 Replacement Patterns

**Pattern A — AJAX delete function:**

```javascript
// ✅ Correct
function deleteX(id) {
    showAppConfirm(
        '<?= esc(lang('App.confirm'), 'js') ?>',
        '<?= esc(lang('X.confirmDelete'), 'js') ?>',
        function() {
            fetch(/* ... */)
                .then(response => response.json())
                .then(data => {
                    if (data.success) { location.reload(); }
                    else { showAppToast(contractErrorMessage(data, '…'), 'danger'); }
                })
                .catch(() => showAppToast('…', 'danger'));
        }
    );
}
```

**Pattern B — Form with confirmation (replaces `onsubmit="return confirm()"`):**

```html
<!-- ✅ Correct: data-confirm-* intercepted by layout delegation handler -->
<form
	action="…"
	method="post"
	class="d-inline"
	data-confirm-title="<?= esc(lang('App.confirm'), 'attr') ?>"
	data-confirm-body="<?= esc(lang('X.confirmDelete'), 'attr') ?>">
	<?= csrf_field() ?>
	<button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
</form>
```

**Pattern C — Button with `onclick="return confirm()"` (move to form):**

Remove `onclick` from the button and add `data-confirm-title`/`data-confirm-body` to the enclosing `<form>` instead.

**Pattern D — Modal fallback path that used `prompt()` for reason input:**

```javascript
// ✅ Correct: show a warning toast and abort — never use prompt()
if (!modal || !reasonInput) {
    showAppToast('<?= esc(lang('App.errorGeneric'), 'js') ?>', 'warning');
    return;
}
```

**Pattern E — Validation guard (replaces `alert()` for missing selection, short reason, etc.):**

```javascript
// ✅ Correct
if (selected.length === 0) {
    showAppToast('<?= esc(lang('Members.bulkDeleteSelectAtLeastOne'), 'js') ?>', 'warning');
    return;
}
```

### 14.3 Toast Levels

| Level       | When to use                                      |
| ----------- | ------------------------------------------------ |
| `'primary'` | Success confirmation                             |
| `'warning'` | Validation error, missing input, empty selection |
| `'danger'`  | Server error, network error, AJAX failure        |

### 14.4 `#appConfirmModal` Structure

Present in every layout, immediately after `<div id="appToastContainer">`:

```html
<div
	class="modal fade"
	id="appConfirmModal"
	tabindex="-1"
	aria-labelledby="appConfirmModalLabel"
	aria-modal="true"
	role="dialog">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="appConfirmModalLabel"></h5>
				<button
					type="button"
					class="btn-close"
					data-bs-dismiss="modal"></button>
			</div>
			<div class="modal-body" id="appConfirmModalBody"></div>
			<div class="modal-footer">
				<button
					type="button"
					class="btn btn-outline-secondary"
					data-bs-dismiss="modal">
					Cancel
				</button>
				<button
					type="button"
					class="btn btn-danger"
					id="appConfirmModalOkBtn">
					Confirm
				</button>
			</div>
		</div>
	</div>
</div>
```

The OK button is **clone-replaced** on each `showAppConfirm()` call to prevent listener accumulation.

### 14.5 Form Delegation Handler

All layouts contain a capture-phase `document.addEventListener('submit', …)` that intercepts any `<form>` with a `data-confirm-title` attribute, calls `showAppConfirm`, and only calls `form.submit()` inside the callback. No manual wiring needed per form.

## 15. Visitor Follow-up Workflow System

Canonical reference: `docs/FOLLOWUP_WORKFLOW_SYSTEM.md`. Lifecycle: `docs/VISITOR_LIFECYCLE.md`.

### 15.1 Module Location

| File                                               | Purpose                                                                                          |
| -------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `app/Commands/SendFollowups.php`                   | `php spark followup:send` Spark command — dispatches due log entries                             |
| `app/Commands/BackfillFollowups.php`               | `php spark followup:backfill` — reconciles visitors missing scheduled rows                       |
| `app/Services/FollowupDispatchService.php`         | Channel-specific dispatch logic (email/text/call/mail/gift)                                      |
| `app/Services/VisitorFollowupService.php`          | Read-side queries: progress badges, next pending, timeline, dashboard aggregates                 |
| `app/Services/SmsService.php`                      | Twilio or log-only SMS provider abstraction                                                      |
| `app/Models/FollowupSequenceModel.php`             | Sequence CRUD; `getActiveForParish(int $parishId)` — table `followup_sequence`                   |
| `app/Models/FollowupActionModel.php`               | Action CRUD; `getBySequenceWithTemplates()` — table `followup_action`                            |
| `app/Models/FollowupTemplateModel.php`             | Template CRUD — table `followup_template`                                                        |
| `app/Models/PersonVisitModel.php`                  | Visit recording; `afterInsert` hook schedules follow-ups on `visit_type='first'`                 |
| `app/Models/FollowupLogModel.php`                  | Log CRUD; `scheduleActiveSequencesForPerson()`; `getPendingDue()`; `getTimelineForPerson()`      |
| `app/Models/CallTaskModel.php`                     | Manual call task CRUD; `getPendingForParish()`                                                   |
| `Clergy/Controllers/Visitors.php`                  | Visitor CRUD + followup AJAX endpoints + `pendingCallsApi()` navbar badge                        |
| `Clergy/Controllers/FollowupWorkflow.php`          | Sequence / action / template admin; call-task queue; completion report                           |
| `Clergy/Config/Routes.php`                         | `/visitors/*` + `/followup-workflow/*` routes                                                    |

### 15.2 Data Flow

When a `person_visit` row with `visit_type='first'` is inserted, `PersonVisitModel::scheduleFollowups()` (afterInsert hook — the single scheduling point) calls `FollowupLogModel::scheduleActiveSequencesForPerson()`, which creates one `followup_log` row per action in every active sequence for the parish (`scheduled_date = visit_date + action.day_offset`). Callers must not schedule explicitly — the hook dedupes by `action_id`.

The cron command picks up due rows:

```text
[cron @ 9 AM] php spark followup:send
      │
      ▼
FollowupLogModel::getPendingDue($today)
      │
      ▼
FollowupDispatchService::dispatch($logRow, $template, $person)
  ├─ email → CI4 SMTP email service (scoped via EmailScopeService)
  ├─ text  → SmsService → Twilio (or log_only stub) via sms_queue
  ├─ call  → inserts call_task row (log_id always set) for volunteer queue
  ├─ mail  → Dompdf PDF written to writable/mail_queue/
  └─ gift  → metadata flag (manual task for admin)
```

### 15.3 Default 6-Week Sequence

Seeded by `SixWeekFollowupSeeder` as a system-level default (`parish_id = NULL` — visible to all parishes). Parishes may override via `/followup-workflow/sequences/create`.

| Week | Day offset | Channel | Automated      | Purpose                          |
| ---- | ---------- | ------- | -------------- | -------------------------------- |
| 1    | 2          | email   | Yes            | Welcome email                    |
| 1    | 5          | call    | No (volunteer) | Personal welcome call            |
| 2    | 10         | email   | Yes            | Small-group / ministry invite    |
| 3    | 16         | text    | Yes            | Friendly check-in SMS            |
| 3    | 18         | call    | No (volunteer) | Prayer & connection call         |
| 4    | 25         | gift    | No (volunteer) | Welcome gift delivery            |
| 5    | 33         | email   | Yes            | Membership / next-steps invite   |
| 6    | 40         | call    | No (volunteer) | Final membership invitation call |

### 15.4 Template Variables

Replaced at dispatch time in both `body_text` and `body_html`:

| Tag             | Source                                                                        |
| --------------- | ----------------------------------------------------------------------------- |
| `{first_name}`  | `person.first_name`                                                           |
| `{last_name}`   | `person.last_name`                                                            |
| `{parish_name}` | `parish.name` for the visitor's parish                                        |
| `{visit_date}`  | Scheduled date of the log entry                                               |
| `{clergy_name}` | Blank by default; extend `FollowupDispatchService::buildVarMap()` to populate |
| `{phone}`       | Decrypted `person.phone`                                                      |

### 15.5 Permissions

| Permission                  | Roles                                         | Scope                                                                  |
| --------------------------- | --------------------------------------------- | ---------------------------------------------------------------------- |
| `followup.manage`           | `parishadmin`, `clergy`                       | CRUD sequences/actions/templates; trigger actions; complete call tasks |
| `followup.view`             | `parishadmin`, `clergy`, `followup_volunteer` | Read-only: sequences, templates, call queue, timeline                  |
| `visitor.edit`              | `parishadmin`, `clergy`                       | Promote/demote attender and promote-to-member endpoints                |

### 15.6 Cron Setup

```bash
# Dispatch all due followup actions at 9 AM daily
0 9 * * * php /path/to/spark followup:send >> /path/to/writable/logs/followup.log 2>&1

# Scope to a single parish
0 9 * * * php /path/to/spark followup:send --parish 5

# Dry-run (no DB writes)
php spark followup:send --dry-run
```

### 15.7 Visitor-to-Member Conversion Rule

`POST /visitors/promote/{personId}` → `PersonConversionService::convertToMember()` (mark member / link user / create account modes). In one transaction it:

1. Transitions `parish_affiliation` to `member` via `MembershipPathwayService` (writes `person_status_history`, syncs `pathway_stage`).
2. Links an existing user or creates a portal account (user + `email_password` identity + `member` group + profile).
3. Marks pending `followup_log` rows `skipped` and stamps `followed_up = 1`, `converted_to_member_at`, `converted_by_user_id`.
4. Redirects to `/people/view/{profile_id}`.

Never update `membership_status` or conversion markers directly — always go through the pathway/conversion services. `POST /visitors/revert-conversion/{personId}` reverses it.

### 15.8 Legacy System — Fully Retired (April 2026)

The legacy follow-up system has been **hard-retired**. The following no longer exist:

- `follow_up_tasks` table — **dropped** (data migrated to `call_tasks`)
- `Clergy/Controllers/FollowUp.php` — **deleted**
- `Clergy/Views/FollowUp/` — **deleted**
- `app/Models/FollowUpTaskModel.php` — **deleted**
- Legacy routes (`followup/*`) — **removed** (old URLs redirect to `/followup-workflow/call-tasks`)
- `followup.tasks` permission — still present in `auth_permissions` for backward-compat but grants nothing useful

**All follow-up functionality uses the current system only:**

- `followup_log` + `call_task` tables
- `FollowupWorkflow` controller (`/followup-workflow/*`)
- `followup.view` / `followup.manage` permissions

Do **not** reference `follow_up_tasks`, `FollowUpTaskModel`, or `FollowUp` controller anywhere in new code.

### 15.9 Email & SMS Configuration

Email is configured via `.env` SMTP keys (`email.SMTPHost`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPPort`, `email.protocol = smtp`). `Config/Email.php` defaults: `$mailType = 'html'`, `$validate = true`.

SMS defaults to `log_only` (no real sends). To activate Twilio, set in `.env`:

```
sms.provider     = twilio
sms.twilio_sid   = ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
sms.twilio_token = xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
sms.twilio_from  = +1XXXXXXXXXX
```

Never commit real credentials to version control.

## 16. Membership Status Rules (Mandatory)

### 16.1 Canonical lifecycle

`parish_affiliation.membership_status` is the only stored lifecycle status (the retired `profiles.membership_status` mirror and the `parish_affiliation` table no longer exist):

```
visitor → regular_attender → member     (pathway)
inactive | transferred | deceased       (exits)
```

Default for new affiliations: `visitor`. `member` requires an explicit operator choice or a `MembershipPathwayService` transition — never write statuses outside `MembershipPathwayService`.

### 16.2 Semantic mapping

| Status             | Meaning                                              | `is_member` (on register) |
| ------------------ | ---------------------------------------------------- | ------------------------- |
| `visitor`          | First-time or occasional visitor                     | 0                         |
| `regular_attender` | Returns regularly; managed intermediate step         | 0                         |
| `member`           | Active covenant member                               | **1**                     |
| `inactive`         | Member not meeting standing; still on the register   | **1**                     |
| `transferred`/`deceased` | Terminal exits                                 | 0                         |

`is_member` is a MySQL generated column: `status IN ('member','inactive')`. `MembershipStatusService::isOnRegister()` vs `isMember()` (strict active member) — do not conflate. Legacy aliases normalize via `MembershipStatusService`: `'guest'` → `visitor`, `'active'` → `member`.

### 16.3 Write-path choke points

- `MembershipPathwayService::transition()` / `transitionForPerson()` — canonical status moves; writes `person_status_history`, syncs `pathway_stage`.
- `PersonConversionService::convertToMember()` — visitor→member promotion (`POST /visitors/promote/{personId}`).
- `MemberParishAffiliationModel` — affiliation writes; never write `affiliation_type`, `canonical_status`, or `is_good_standing` on the affiliation row (dropped).

### 16.4 Language keys

Status labels: `App.statusVisitor`, `App.statusRegular_attender` (note underscore — `ucfirst('regular_attender')` = `'Regular_attender'`), `App.statusMember`, `App.statusInactive`.

## 17. Post-Login Redirect Guard (Mandatory)

### Problem

The navbar pending-calls badge polls `GET /api/followup/pending-calls` every 30 seconds. When the session expires mid-poll, Shield's session filter intercepts the unauthenticated XHR, stores that URL in `beforeLoginUrl` tempdata, and redirects to login. After successful login, `loginRedirect()` restores `beforeLoginUrl` — sending the browser to the JSON endpoint instead of the dashboard, displaying raw JSON to the user.

### Rule

`app/Config/Auth.php::loginRedirect()` **must** discard any `beforeLoginUrl` whose path starts with `/api/` or ends with `.json` before returning the redirect URL. The current implementation:

```php
public function loginRedirect(): string
{
    $session   = session();
    $beforeUrl = $session->getTempdata('beforeLoginUrl');

    // Discard AJAX/API URLs — the navbar poller fires while the session
    // expires and the browser must not land on a JSON endpoint after login.
    if ($beforeUrl !== null) {
        $path = (string) parse_url((string) $beforeUrl, PHP_URL_PATH);
        if (str_starts_with($path, '/api/') || str_ends_with($path, '.json')) {
            $beforeUrl = null;
        }
    }

    $url = $beforeUrl ?? setting('Auth.redirects')['login'];
    return $this->getUrl($url);
}
```

**Extend the path-prefix list** (`/api/`, `.json`) whenever new JSON-only endpoints are added that could be polled by background JS.
