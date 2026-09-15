# Parish Management Permissions Rules (April 23, 2026)

## Overview

This document codifies the permission constraints for parish editing, Tax ID field access, and staff management across all administrative roles in ekklesiaRD.

## Permission Matrix

### Parish Editing & Staff Management

| Role | Parish Profile (Name, Diocese) | Tax ID | Contact/Location | Staff Management | Notes |
| --- | --- | --- | --- | --- | --- |
| **System Admin** | Edit all | Edit all | Edit all | Full CRUD | Unrestricted access to all parishes |
| **Diocesan Admin** | Read-only | Edit all diocesan parishes | Edit all diocesan parishes | Full CRUD (diocesan parishes only) | Scoped to assigned diocese |
| **Parish Admin** | Read-only | Edit own parish only | Edit own parish | Full CRUD (own parish only) | Limited to operational parish |
| **Province Admin** | Read-only all | Read-only all | Read-only all | **Blocked** (403) | Oversight role with no operational changes |

## Field-Level Rules

### Parish Name & Diocese

- **Editable by:** System Admin only
- **Display for others:** Read-only (disabled input with background)
- **Location:** Congregation Identity section (`Diocesan/Views/Parishes/edit.php`)

### Tax ID (EIN)

- **Editable by:**
  - System Admin (all parishes)
  - Diocesan Admin (parishes within their diocese only)
  - Parish Admin (their own parish only)
- **Display for Province Admin:** Read-only (no edit capability)
- **Location:** Congregation Identity section (`Diocesan/Views/Parishes/edit.php`)
- **Implementation variable:** `$canEditTaxId`

### Contact & Location Fields

Email, phone, website, address, mailing address, city, state, zip, county, country:

- **Editable by:** All parish admins (within scope)
- **Restriction:** Parish Admin can only edit their own parish

### Logo Upload

- **Editable by:** All authenticated parish admins
- **Restriction:** Parish Admin can only upload for their own parish

## Staff Management Constraints

### Province Admin Prohibition

Province admins are **completely blocked** from any staff management operations:

- **Blocked methods:** `staff()`, `addStaff()`, `promoteMember()`, `removeStaffRole()`, `demoteStaff()`
- **Location:** `Diocesan/Controllers/Parishes.php`
- **Error response:** 403 Forbidden
- **Error message key:** `Diocesan.provinceAdminStaffManagementDenied`
- **Message text:** *"Province administrators cannot manage parish staff. You have read-only access to parishes."*

### Province Admin Read-Only Access

Province admins retain read-only access to:

- Parish list with member counts
- Parish detail view (name, address, contact info)
- Staff roster (view only, no edit/delete)
- Full dashboard visibility to all diocesan parishes in their province

### Province Admin CRUD Scope

Province admins are allowed to:

- ✅ CRUD diocesan admin accounts (separate interface, not via parish edit page)
- ❌ Edit parish data of any kind
- ❌ Manage parish staff
- ❌ Modify roles or permissions

## Implementation Details

### Controller Logic

**In `Diocesan/Controllers/Parishes.php::edit()` method:**

```php
$isSystemAdmin = $this->isSystemAdmin();
$isDiocesanAdmin = $this->isDiocesanAdmin();
$isParishAdmin = $this->isParishAdminOnly();
$operationalParishId = (int) ($this->getOperationalParishId() ?? 0);

$canEditTaxId = $isSystemAdmin || $isDiocesanAdmin || ($isParishAdmin && $operationalParishId === $parishId);
```

**In `Diocesan/Controllers/Parishes.php::update()` method:**

Tax ID validation and assignment only occur when `$canEditTaxId` is true:

```php
if ($canEditTaxId) {
    $rules = array_merge($rules, [
        'tax_id' => 'permit_empty|max_length[50]',
    ]);
    
    $data = array_merge($data, [
        'tax_id' => $this->request->getPost('tax_id'),
    ]);
}
```

**Province Admin Staff Block:**

Applied to these methods at entry point:

```php
if ($this->isProvinceAdmin()) {
    return redirect()->to('/parishes')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
}
```

### View Logic

**In `Diocesan/Views/Parishes/edit.php`:**

Tax ID field uses `$canEditTaxId` flag:

```php
<div class="col-md-6">
    <label <?= $canEditTaxId ? 'for="tax_id"' : '' ?> class="form-label"><?= lang('Parishes.labelTaxId') ?></label>
    <?php if ($canEditTaxId): ?>
        <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= old('tax_id', $parish['tax_id'] ?? '') ?>" 
            placeholder="<?= esc(lang('Parishes.placeholderTaxIdExample')) ?>">
    <?php else: ?>
        <p class="border rounded p-2 bg-body-secondary mb-0" style="min-height: 2.375rem;">
            <?= esc($parish['tax_id'] ?? '') !== '' ? esc($parish['tax_id']) : '—' ?>
        </p>
    <?php endif; ?>
</div>
```

## Language Keys

Added to `app/Language/en/Diocesan.php`:

```php
'provinceAdminStaffManagementDenied' => 'Province administrators cannot manage parish staff. You have read-only access to parishes.',
```

## Testing Scenarios

### Scenario 1: Parish Admin Edits Own Parish Tax ID

- User: Parish Admin for Parish ID 5
- Action: Edit `/parishes/edit/5`
- Expected: Tax ID field is editable
- Validation: `$canEditTaxId = true`

### Scenario 2: Parish Admin Attempts to Edit Different Parish Tax ID

- User: Parish Admin for Parish ID 5
- Action: Edit `/parishes/edit/10`
- Expected: Access denied (fails `canManageParish()` check first)
- HTTP Status: 302 redirect to `/parishes`

### Scenario 3: Diocesan Admin Edits Parish Tax ID in Their Diocese

- User: Diocesan Admin for Diocese 3
- Action: Edit `/parishes/edit/42` (where parish 42 is in diocese 3)
- Expected: Tax ID field is editable
- Validation: `$canEditTaxId = true`

### Scenario 4: Province Admin Attempts to Access Staff Management

- User: Province Admin
- Action: POST to `/parishes/5/staff` → `addStaff()`
- Expected: Error message about read-only access
- HTTP Status: 302 redirect with error message
- Language Key: `Diocesan.provinceAdminStaffManagementDenied`

### Scenario 5: Province Admin Views Parish Details

- User: Province Admin
- Action: GET `/parishes/edit/5`
- Expected: All fields are read-only (no edit capability)
- Validation: `$isProvinceAdmin()` → View passes `isProvinceAdmin => true`

## Related Code Files

- `Diocesan/Controllers/Parishes.php` – Permission logic and controller methods
- `Diocesan/Views/Parishes/edit.php` – Field display and editability logic
- `app/Language/en/Diocesan.php` – Error message localization
- `.github/copilot-instructions.md` – Overall system architecture (section 5.1)

## Changelog

- **April 23, 2026** – Initial implementation of parish permission constraints
  - Parish Admin can now edit Tax ID for their own parish
  - Diocesan Admin can edit Tax ID for all parishes in their diocese
  - Province Admin blocked from all staff management with 403 Forbidden
  - Province Admin has read-only access to all parish data
