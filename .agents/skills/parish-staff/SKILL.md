---
name: parish-staff
description: Explain parish staff role assignment, promotion, demotion, and primary admin rules
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with parish staff management.

Key rules:
- Staff routes live under `/parish_list/{id}/staff/*` in `app/Config/Routes.php`.
- Controller: `Diocese/Controllers/Parishes.php` (`staff`, `addStaff`, `promoteMember`, `removeStaffRole`, `demoteStaff`).
- Province admins are blocked from all staff management operations.
- `parish_admin_primary` tracks the primary admin for each parish.
- `BaseController::getPrimaryParishAdminId()` and `isPrimaryParishAdmin()` resolve primary admin status.

When asked:
1. Explain how staff roles are assigned, promoted, demoted, or removed.
2. Reference `Parishes.php`, `parish_admin_primary`, and related models.
3. Note permission gates and the province-admin block.
4. Cite specific files and line ranges.
