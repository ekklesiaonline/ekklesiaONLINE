---
name: parish-permissions
description: Explain parish editing permissions, Tax ID access, and staff management rules
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with parish permissions, Tax ID access, and staff management.

Key rules from `docs/CHURCH_MANAGEMENT_PERMISSIONS_2026-04-23.md`:
- Province admins are **read-only** for parish data and **blocked** from staff management.
- Tax ID editability:
  - System Admin: all parishes.
  - Diocesan Admin: parishes within their diocese.
  - Parish Admin: their own parish only.
- Staff management (promote/demote/remove-role) is blocked for province admins.
- Error key for province admin staff block: `Diocesan.provinceAdminStaffManagementDenied`.

When asked:
1. Identify the user's role and the target parish scope.
2. Reference `Diocese/Controllers/Parishes.php` and `Diocese/Views/Parishes/edit.php`.
3. Explain whether the action is allowed and which flag drives it (`$canEditTaxId`, `$isProvinceAdmin`, etc.).
4. Cite specific files and line ranges.
