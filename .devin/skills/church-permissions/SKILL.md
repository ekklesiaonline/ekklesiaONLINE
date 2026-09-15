---
name: church-permissions
description: Explain church/parish editing permissions, Tax ID access, and staff management rules
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with church/parish permissions, Tax ID access, and staff management.

Key rules from `docs/CHURCH_MANAGEMENT_PERMISSIONS_2026-04-23.md`:
- Province admins are **read-only** for church/parish data and **blocked** from staff management.
- Tax ID editability:
  - System Admin: all churches/parishes.
  - Diocesan Admin: churches/parishes within their diocese.
  - Church/Parish Admin: their own church/parish only.
- Staff management (promote/demote/remove-role) is blocked for province admins.
- Error key for province admin staff block: `Diocesan.provinceAdminStaffManagementDenied`.

When asked:
1. Identify the user's role and the target church/parish scope.
2. Reference `Diocesan/Controllers/Churches.php` and `Diocesan/Views/Churches/edit.php`.
3. Explain whether the action is allowed and which flag drives it (`$canEditTaxId`, `$isProvinceAdmin`, etc.).
4. Cite specific files and line ranges.
