# EkklesiaRD User Manual

## 1. Purpose

EkklesiaRD is a multi-level Parish Management System (ChMS) for:

- Province oversight
- Diocese administration
- Parish-level operations
- Ministry team workflows

The system is role-driven. Each user sees only the modules and data allowed by permissions.

## 2. Access and Login

1. Open the system URL provided by your administrator.
2. Sign in with your email and password.
3. If your account is pending review, wait for diocesan approval.
4. After approval, you are redirected to your role dashboard.

## 3. Role-Based Dashboards

### Province Administrator

Primary dashboard: Province Dashboard.
Main purpose:

- View network-wide summaries across dioceses
- Filter by diocese and date period
- Create dioceses
- Assign diocesan admins

Typical widgets:

- Total dioceses and parishes
- Total people and attendance
- Attendance period averages (Today, Week, Month, Year, Sundays, Weekdays)

### Diocesan Administrator

Primary dashboard: Diocesan Dashboard.
Main purpose:

- Oversee all parishes in one diocese
- Review and moderate new registrations
- Open diocesan finance and system overview pages

Typical widgets:

- Parishes in scope
- People/members in scope
- Attendance averages
- Pending registrations with Approve, Dismiss, Ban, Delete actions

### Parish-Level Roles (Parish Admin, Pastor, Treasurer, Volunteer Coordinator, Member)

Primary dashboard: Parish Dashboard.
Main purpose:

- Daily parish operations and member care

Typical widgets:

- Total people, total members, active members
- Good standing and leadership counts
- Pending follow-ups
- Monthly financial summary (income, expenses, net)
- Recent services and recent visitors

## 4. Main Navigation Menu

The sidebar is permission-aware. Common menu items include:

- Dashboard
- Members or Staff
- Families
- Services
- Attendance
- Visitors
- Leadership
- Good Standing
- Follow-ups
- Finance:
  - Donations
  - Expenses
  - Budgets
  - Annual Pledges
- Volunteers
- Volunteer Schedules
- Events
- Worship Planning
- Reports
- Consistency Report
- Parishes
- Profile
- Logout

Note: Some roles may see fewer items.

## 5. Core Workflows

### 5.1 Member Management

Use Members to:

- Search and view member records
- Create new members (if permitted)
- Edit profile and status
- Export data (if permitted)

Recommended flow:

1. Go to Members.
2. Search by name/email/phone.
3. Open the record.
4. Update required fields.
5. Save and verify changes.

### 5.2 Family Management

Use Families to:

- Create family households
- Assign head of household
- Add/remove family members

Recommended flow:

1. Open Families.
2. Create family with name and address.
3. Add members and set relationships.
4. Save.

### 5.3 Services and Attendance

Use Services to create liturgical events.
Use Attendance to mark individual presence and communicants.

Recommended flow:

1. Create service in Services.
2. Open Attendance for that service.
3. Mark attendees.
4. Run attendance reports.

### 5.4 Visitor Follow-Up and Conversion

Use Visitors and Follow-ups to track visitors.
Use the visitor promote action to convert eligible visitors into members.

Recommended flow:

1. Register visitor.
2. Assign follow-up task.
3. Complete follow-up.
4. Convert visitor to member when appropriate.

### 5.5 Leadership and Good Standing

Use Leadership to assign or manage leadership positions.
Use Good Standing to review status and run refresh logic.

Recommended flow:

1. Open Leadership and update assignments.
2. Open Good Standing.
3. Run Refresh.
4. Review updated standing outcomes.

### 5.6 Finance Operations

Finance module includes Donations, Expenses, Budgets, and Annual Pledges.

Recommended flow:

1. Record donations.
2. Record expenses.
3. Maintain budgets.
4. Manage annual pledges.
5. Review reports and net position.

### 5.7 Volunteer and Event Operations

Use Volunteers for teams and assignments.
Use Volunteer Schedules to plan service responsibilities.
Use Events for event lifecycle and check-in.

Recommended flow:

1. Create or update teams.
2. Assign members.
3. Build schedules.
4. Register and check in event attendees.

## 6. Diocesan Registration Moderation Workflow

For diocesan admins:

1. Open Diocesan Dashboard.
2. Review New User Registrations Pending Review.
3. Select role.
4. Select parish assignment when needed.
5. Choose action:
   - Approve
   - Dismiss
   - Ban
   - Delete

Best practice:

- Approve only after verifying profile data and intended parish scope.

## 7. Province Oversight Workflow

For province admins:

1. Open Province Dashboard.
2. Select diocese scope (or All Dioceses).
3. Select period filter.
4. Review attendance and membership summaries.
5. Create dioceses or assign diocesan admins as needed.

## 8. Finance Dashboard Workflow (Diocesan)

1. Open Diocesan Finance Dashboard.
2. Choose parish scope (All Parishes or one parish).
3. Choose period (today/week/month/year/custom).
4. Review total income, total expenses, net.
5. Analyze by-parish comparisons and monthly trends.

## 9. Reports and Data Quality

### Reports

Use Reports for operational and financial summaries depending on your role.

### Consistency Report

Admin roles can use Consistency Report to spot data integrity issues and export CSV.

## 10. Profile and Account

Use Profile to:

- Update personal details
- Complete missing profile data
- Maintain contact information

If profile is incomplete, complete it before full workflow access.

## 11. Permissions and Visibility Rules

- Permissions define what actions are allowed.
- Parish-level users are scoped to their parish.
- Diocesan admins are scoped to their diocese.
- Province admins can see province-wide summaries but not unrestricted parish CRUD/finance unless explicitly granted.

## 12. Troubleshooting

### I cannot see a menu item

Cause: missing permission or role mismatch.
Action: contact an administrator to verify role and permission assignment.

### I am redirected away from a page

Cause: scope or permission restriction.
Action: confirm your assigned parish/diocese and role.

### Dashboard is empty

Cause: no records in selected scope/period.
Action: adjust date filter and verify data entry in source modules.

### Registration is pending

Cause: diocesan moderation not completed.
Action: request diocesan admin review.

## 13. Recommended Operating Routine

Daily:

- Check dashboard cards
- Process follow-ups
- Record attendance and finance entries

Weekly:

- Review conversions and good standing
- Review volunteer schedules and events

Monthly:

- Reconcile donations and expenses
- Validate budgets and pledges
- Export reports for leadership review

## 14. Screenshot Guide (How to Capture)

No screenshots are bundled in the repository currently.

To build a screenshot appendix for this manual:

1. Log in as each role.
2. Capture:
   - Province Dashboard
   - Diocesan Dashboard
   - Parish Dashboard
   - Finance Dashboard
   - Members, Visitors, Good Standing, Pledges, Volunteers
3. Save files using this naming pattern:
   - screenshot-province-dashboard.png
   - screenshot-diocesan-dashboard.png
   - screenshot-parish-dashboard.png
   - screenshot-finance-dashboard.png
4. Add them under docs/images and reference them in this manual.

## 15. Quick Start by Role

### Province Admin

- Open Province Dashboard
- Set diocese/period filters
- Review province trends
- Create dioceses and assign diocesan admins

### Diocesan Admin

- Open Diocesan Dashboard
- Moderate pending registrations
- Manage parishes and diocesan tools
- Use finance and system overview

### Parish Admin

- Manage members, families, services, attendance, visitors, finance, volunteers
- Monitor consistency and reports

### Pastor

- Focus on members, attendance, follow-ups, events, worship planning

### Treasurer

- Focus on donations, expenses, budgets, pledges, reports

### Volunteer Coordinator

- Focus on teams, assignments, schedules, event operations

### Member

- Maintain profile and use permitted self-service features

---
Manual version: 1.0 (English)
Last updated: 2026-03-26
