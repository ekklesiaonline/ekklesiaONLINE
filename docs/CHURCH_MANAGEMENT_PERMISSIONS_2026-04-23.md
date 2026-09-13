# Church Management Permissions Rules (April 23, 2026)

## Overview

This document codifies the permission constraints for church editing, Tax ID field access, and staff management across all administrative roles in ekklesiaRD.

## Permission Matrix

### Church Editing & Staff Management

| Role | Church Profile (Name, Diocese) | Tax ID | Contact/Location | Staff Management | Notes |
| --- | --- | --- | --- | --- | --- |
| **System Admin** | Edit all | Edit all | Edit all | Full CRUD | Unrestricted access to all churches |
| **Diocesan Admin** | Read-only | Edit all diocesan churches | Edit all diocesan churches | Full CRUD (diocesan churches only) | Scoped to assigned diocese |
| **Church Admin** | Read-only | Edit own church only | Edit own church | Full CRUD (own church only) | Limited to operational church |
| **Province Admin** | Read-only all | Read-only all | Read-only all | **Blocked** (403) | Oversight role with no operational changes |

## Field-Level Rules

### Church Name & Diocese

- **Editable by:** System Admin only
- **Display for others:** Read-only (disabled input with background)
- **Location:** Congregation Identity section (`Diocesan/Views/Churches/edit.php`)

### Tax ID (EIN)

- **Editable by:**
  - System Admin (all churches)
  - Diocesan Admin (churches within their diocese only)
  - Church Admin (their own church only)
- **Display for Province Admin:** Read-only (no edit capability)
- **Location:** Congregation Identity section (`Diocesan/Views/Churches/edit.php`)
- **Implementation variable:** `$canEditTaxId`

### Contact & Location Fields

Email, phone, website, address, mailing address, city, state, zip, county, country:

- **Editable by:** All church admins (within scope)
- **Restriction:** Church Admin can only edit their own church

### Logo Upload

- **Editable by:** All authenticated church admins
- **Restriction:** Church Admin can only upload for their own church

## Staff Management Constraints

### Province Admin Prohibition

Province admins are **completely blocked** from any staff management operations:

- **Blocked methods:** `staff()`, `addStaff()`, `promoteMember()`, `removeStaffRole()`, `demoteStaff()`
- **Location:** `Diocesan/Controllers/Churches.php`
- **Error response:** 403 Forbidden
- **Error message key:** `Diocesan.provinceAdminStaffManagementDenied`
- **Message text:** *"Province administrators cannot manage church staff. You have read-only access to churches."*

### Province Admin Read-Only Access

Province admins retain read-only access to:

- Church list with member counts
- Church detail view (name, address, contact info)
- Staff roster (view only, no edit/delete)
- Full dashboard visibility to all diocesan churches in their province

### Province Admin CRUD Scope

Province admins are allowed to:

- ✅ CRUD diocesan admin accounts (separate interface, not via church edit page)
- ❌ Edit church data of any kind
- ❌ Manage church staff
- ❌ Modify roles or permissions

## Implementation Details

### Controller Logic

**In `Diocesan/Controllers/Churches.php::edit()` method:**

```php
$isSystemAdmin = $this->isSystemAdmin();
$isDiocesanAdmin = $this->isDiocesanAdmin();
$isChurchAdmin = $this->isChurchAdminOnly();
$operationalChurchId = (int) ($this->getOperationalChurchId() ?? 0);

$canEditTaxId = $isSystemAdmin || $isDiocesanAdmin || ($isChurchAdmin && $operationalChurchId === $churchId);
```

**In `Diocesan/Controllers/Churches.php::update()` method:**

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
    return redirect()->to('/churches')->with('error', lang('Diocesan.provinceAdminStaffManagementDenied'));
}
```

### View Logic

**In `Diocesan/Views/Churches/edit.php`:**

Tax ID field uses `$canEditTaxId` flag:

```php
<div class="col-md-6">
    <label <?= $canEditTaxId ? 'for="tax_id"' : '' ?> class="form-label"><?= lang('Churches.labelTaxId') ?></label>
    <?php if ($canEditTaxId): ?>
        <input type="text" class="form-control" id="tax_id" name="tax_id" value="<?= old('tax_id', $church['tax_id'] ?? '') ?>" 
            placeholder="<?= esc(lang('Churches.placeholderTaxIdExample')) ?>">
    <?php else: ?>
        <p class="border rounded p-2 bg-body-secondary mb-0" style="min-height: 2.375rem;">
            <?= esc($church['tax_id'] ?? '') !== '' ? esc($church['tax_id']) : '—' ?>
        </p>
    <?php endif; ?>
</div>
```

## Language Keys

Added to `app/Language/en/Diocesan.php`:

```php
'provinceAdminStaffManagementDenied' => 'Province administrators cannot manage church staff. You have read-only access to churches.',
```

## Testing Scenarios

### Scenario 1: Church Admin Edits Own Church Tax ID

- User: Church Admin for Church ID 5
- Action: Edit `/churches/edit/5`
- Expected: Tax ID field is editable
- Validation: `$canEditTaxId = true`

### Scenario 2: Church Admin Attempts to Edit Different Church Tax ID

- User: Church Admin for Church ID 5
- Action: Edit `/churches/edit/10`
- Expected: Access denied (fails `canManageChurch()` check first)
- HTTP Status: 302 redirect to `/churches`

### Scenario 3: Diocesan Admin Edits Church Tax ID in Their Diocese

- User: Diocesan Admin for Diocese 3
- Action: Edit `/churches/edit/42` (where church 42 is in diocese 3)
- Expected: Tax ID field is editable
- Validation: `$canEditTaxId = true`

### Scenario 4: Province Admin Attempts to Access Staff Management

- User: Province Admin
- Action: POST to `/churches/5/staff` → `addStaff()`
- Expected: Error message about read-only access
- HTTP Status: 302 redirect with error message
- Language Key: `Diocesan.provinceAdminStaffManagementDenied`

### Scenario 5: Province Admin Views Church Details

- User: Province Admin
- Action: GET `/churches/edit/5`
- Expected: All fields are read-only (no edit capability)
- Validation: `$isProvinceAdmin()` → View passes `isProvinceAdmin => true`

## Related Code Files

- `Diocesan/Controllers/Churches.php` – Permission logic and controller methods
- `Diocesan/Views/Churches/edit.php` – Field display and editability logic
- `app/Language/en/Diocesan.php` – Error message localization
- `.github/copilot-instructions.md` – Overall system architecture (section 5.1)

## Changelog

- **April 23, 2026** – Initial implementation of church permission constraints
  - Church Admin can now edit Tax ID for their own church
  - Diocesan Admin can edit Tax ID for all churches in their diocese
  - Province Admin blocked from all staff management with 403 Forbidden
  - Province Admin has read-only access to all church data
