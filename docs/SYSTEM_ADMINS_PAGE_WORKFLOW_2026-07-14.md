# System Admins Page — Workflow & File Report

**Route:** `GET /system/system-admins`  
**Date:** 2026-07-14

---

## 1. Request Lifecycle (High-Level)

```
Browser GET /system/system-admins
  │
  ├─► Filter chain (before)
  │     forcehttps → pagecache → session → csrf → localeSet
  │     → registrationGate → sessionTracking
  │     → forcePasswordReset → deviceVerification
  │     → permission:system.admin
  │
  ├─► SystemAdmin::listSystemAdmins()
  │     ├─ requirePermission('system.admin')   [double-check]
  │     ├─ isSystemAdmin()                     [group check]
  │     ├─ resolveExistingProfileTable()
  │     ├─ getPermissionsHierarchy()
  │     ├─ getPermissionsForGroups(['systemadmin'])
  │     ├─ DB query  ──► auth_groups_users
  │     │               auth_identities
  │     │               profile / profiles
  │     │               system_admin_primary
  │     ├─ DataEncryption::decrypt()           [phone numbers]
  │     ├─ getPrimarySystemAdminId()
  │     └─ view('System/system_admins', $data)
  │
  ├─► View render
  │     layouts/main.php  (layout shell)
  │       └─ section('content')
  │            system_admins.php
  │              ├─ [per-row] admin_actions.php        (partial)
  │              ├─ admin_password_create.php           (partial, inside Create modal)
  │              └─ reset_password_modal.php            (partial)
  │                   └─ admin_password_create.php      (partial, inside Reset modal)
  │
  └─► Filter chain (after)
        secureheaders → requestMonitoring → pagecache
        → performance → toolbar
```

---

## 2. Files Involved

### 2.1 Routing

| File          | Path                    | Key lines |
|---------------|-------------------------|-----------|
| Routes config | `app/Config/Routes.php` | 53, 70–95 |

**Relevant route definitions (`app/Config/Routes.php`):**

```php
// Line 53 — outer session group
$routes->group('', ['filter' => 'session'], function ($routes) {

    // Line 70 — system sub-group, permission filter
    $routes->group('system', ['filter' => 'permission:system.admin'], function ($routes) {

        // Line 91 — the target route
        $routes->get('system-admins', '\App\Controllers\SystemAdmin::listSystemAdmins');

        // Related action routes consumed by buttons/forms on this page
        $routes->post('admins/store',                   '\App\Controllers\SystemAdmin::storeAdminUser');
        $routes->post('admins/update/(:num)',            '\App\Controllers\SystemAdmin::updateAdminUser/$1');
        $routes->post('admins/delete/(:num)',            '\App\Controllers\SystemAdmin::deleteAdminUser/$1');
        $routes->post('admins/transfer-primary/(:num)', '\App\Controllers\SystemAdmin::transferPrimarySystemAdmin/$1');
        $routes->post('admins/reset-password/(:num)',   '\App\Controllers\SystemAdmin::resetPassword/$1');
    });
});
```

---

### 2.2 Filters / Middleware

| Filter alias             | Class                                  | Trigger                      |
|--------------------------|----------------------------------------|------------------------------|
| `session`                | CI4 built-in                           | Outer route group (line 53)  |
| `permission:system.admin`| `App\Filters\PermissionFilter`         | System route group (line 70) |
| `csrf`                   | CI4 built-in                           | Global before                |
| `localeSet`              | `App\Filters\LocaleSetFilter`          | Global before                |
| `registrationGate`       | `App\Filters\RegistrationGateFilter`   | Global before                |
| `sessionTracking`        | `App\Filters\SessionTrackingFilter`    | Global before                |
| `forcePasswordReset`     | `App\Filters\ForcePasswordResetFilter` | Global before                |
| `deviceVerification`     | `App\Filters\DeviceVerificationFilter` | Global before                |
| `forcehttps`             | CI4 built-in                           | Required before              |
| `pagecache`              | CI4 built-in                           | Required before + after      |
| `secureheaders`          | `App\Filters\SecureHeadersFilter`      | Global after                 |
| `requestMonitoring`      | `App\Filters\RequestMonitoringFilter`  | Global after                 |
| `performance`            | CI4 built-in                           | Required after               |
| `toolbar`                | CI4 built-in                           | Required after               |

**Custom PermissionFilter (`app/Filters/PermissionFilter.php`):**

```php
class PermissionFilter extends ShieldPermissionFilter
{
    protected function isAuthorized(array $arguments): bool
    {
        $user = auth()->user();
        if ($user === null) return false;

        foreach ($arguments as $permission) {
            if ($user->can($permission)) return true;

            // Group-based shortcuts (no explicit permission record required)
            if ($permission === 'system.admin'   && $user->inGroup('systemadmin'))   return true;
            if ($permission === 'province.admin'  && $user->inGroup('provinceadmin')) return true;
            if ($permission === 'diocese.admin'   && $user->inGroup('dioceseadmin'))  return true;
        }
        return false;
    }
}
```

---

### 2.3 Controller

| File          | `app/Controllers/SystemAdmin.php` |
|---------------|-----------------------------------|
| Method        | `listSystemAdmins()` — lines 1209–1273 |
| Parent class  | `App\Controllers\BaseController` |

**`listSystemAdmins()` — full body:**

```php
public function listSystemAdmins()
{
    // 1. Permission guard (logs denied attempts via audit)
    if ($redirect = $this->requirePermission('system.admin')) {
        return $redirect;
    }

    // 2. Group guard — must be in `systemadmin` group
    if (!$this->isSystemAdmin()) {
        return $this->forbiddenResponse(lang('App.errorForbidden'));
    }

    $db = db_connect();
    $profileTable = $this->resolveExistingProfileTable($db); // 'profile' or 'profiles'

    $permissionsHierarchy      = $this->getPermissionsHierarchy();
    $systemAdminPermissions    = $this->getPermissionsForGroups(['systemadmin']);

    // 3. Early return when profile table is missing (fresh install)
    if ($profileTable === null) {
        return view('System/system_admins', array_merge(
            $this->getCommonViewData(lang('System.sectionSystemAdmins')),
            [
                'admins'                  => [],
                'systemAdminPermissions'  => $systemAdminPermissions,
                'permissionsHierarchy'    => $permissionsHierarchy,
                'primary_system_admin_id' => $this->getPrimarySystemAdminId(),
                'current_user_id'         => (int) (auth()->id() ?? 0),
            ]
        ));
    }

    // 4. Build query
    $query = $db->table('auth_groups_users agu')
        ->join($profileTable . ' p',    'p.user_id = agu.user_id', 'left')
        ->join('auth_identities ai',    'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
        ->where('agu.group', 'systemadmin')
        ->orderBy('p.last_name',  'ASC')
        ->orderBy('p.first_name', 'ASC');

    if ($this->tableExistsSafe($db, 'system_admin_primary')) {
        $query->select(
            'agu.user_id, ai.secret AS email, p.first_name, p.last_name, p.phone,
             CASE WHEN sap.user_id IS NULL THEN 0 ELSE 1 END AS is_primary', false
        )->join('system_admin_primary sap', 'sap.id = 1 AND sap.user_id = agu.user_id', 'left');
    } else {
        $query->select('agu.user_id, ai.secret AS email, p.first_name, p.last_name, p.phone, 0 AS is_primary', false);
    }

    $admins = $query->get()->getResultArray();

    // 5. Decrypt phone numbers (stored encrypted at rest)
    $admins = array_map(function (array $row): array {
        if (!empty($row['phone'])) {
            $row['phone'] = DataEncryption::decrypt((string) $row['phone']) ?? '';
        }
        return $row;
    }, $admins);

    // 6. Render view
    return view('System/system_admins', array_merge(
        $this->getCommonViewData(lang('System.sectionSystemAdmins')),
        [
            'admins'                  => $admins,
            'systemAdminPermissions'  => $systemAdminPermissions,
            'permissionsHierarchy'    => $permissionsHierarchy,
            'primary_system_admin_id' => $this->getPrimarySystemAdminId(),
            'current_user_id'         => (int) (auth()->id() ?? 0),
        ]
    ));
}
```

**Supporting private methods in `SystemAdmin.php`:**

```php
// Lines 1035–1051
private function getPrimarySystemAdminId(): ?int
{
    $db = db_connect();
    if (!$this->tableExistsSafe($db, 'system_admin_primary')) return null;

    $row = $db->table('system_admin_primary')
        ->select('user_id')->where('id', 1)->get()->getRowArray();

    $userId = (int) ($row['user_id'] ?? 0);
    return $userId > 0 ? $userId : null;
}

// Lines 1063–1071
private function tableExistsSafe($db, string $table): bool
{
    try {
        $db->query(sprintf('SELECT 1 FROM %s LIMIT 1', $table));
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// Lines 1073–1093
private function resolveExistingProfileTable($db): ?string
{
    // Tries prefixed and un-prefixed variants: 'profile', 'profiles'
    // Returns first table name that exists with fields, or null.
}
```

**Supporting methods in `BaseController.php`:**

```php
// Line 152
protected function isSystemAdmin(): bool
{
    return (bool) ($this->user && $this->user->inGroup('systemadmin'));
}

// Line 412
protected function requirePermission(string $permission)
{
    if (!$this->checkPermission($permission)) {
        $this->audit()->log('permission.denied', [...]);
        return redirect()->to('/')->with('error', lang('App.errorForbidden'));
    }
    return null;
}

// Line 711
protected function getCommonViewData($pageTitle = null): array
{
    // Returns: profile, parish, page_title, is_system_admin,
    // is_province_admin, is_diocesan_admin, scope_label,
    // scope_icon, scope_name, profile_pending, profile_nav_url
}
```

---

### 2.4 Database Tables Queried

| Table                     | Purpose |
|---------------------------|---------|
| `auth_groups_users`       | Identifies which users belong to `systemadmin` group (Shield) |
| `auth_identities`         | Retrieves the email address (`secret` column, type `email_password`) (Shield) |
| `profile` / `profiles`    | First/last name and encrypted phone number |
| `system_admin_primary`    | Single row (`id = 1`) designating the primary system admin |
| `auth_groups_permissions` | Per-group permission assignments (for Permissions modal) |
| `auth_permissions` | Permission definitions (for Permissions modal) |

No Model classes are used for the listing query — the controller uses CI4 Query Builder directly.

---

### 2.5 View Files

#### Layout shell

| File                 | `app/Views/layouts/main.php` |
|----------------------|------------------------------|
| Role                 | Outer HTML shell (Bootstrap 5, dark/light theme, sidebar, nav) |
| Sections consumed    | `styles`, `content`, `modals`, `scripts` |

---

#### Main view

**`app/Views/System/system_admins.php`**

Extends `layouts/main`. Outputs the `content` and `scripts` sections.

Sections / structure:

| Block                 | Contents |
|-----------------------|----------|
| `content`             | Card with admins table + all modals |
| Table loop            | `foreach ($admins as $admin)` — one row per system admin |
| Per-row actions       | `$this->setData([...])->include('System/partials/admin_actions')` |
| Create modal          | Inline HTML + `admin_password_create` partial |
| Reset PW modal        | `$this->include('System/partials/reset_password_modal')` |
| Edit modal            | Inline HTML |
| Permissions modal     | Inline HTML (reads `$permissionsHierarchy` / `$systemAdminPermissions`) |
| Transfer Primary modal| Inline HTML |
| `scripts`             | JS listeners for Edit modal and Transfer Primary modal |

**View data received from controller:**

| Variable                    | Type      | Description |
|-----------------------------|-----------|-------------|
| `$admins`                   | `array[]` | Rows: `user_id`, `email`, `first_name`, `last_name`, `phone`, `is_primary` |
| `$primary_system_admin_id`  | `int\|null` | `user_id` of the designated primary admin |
| `$current_user_id`          | `int`     | `auth()->id()` of the logged-in user |
| `$systemAdminPermissions`   | `array`   | Permissions held by `systemadmin` group |
| `$permissionsHierarchy`     | `array`   | All permissions grouped by category |
| + common data               | mixed     | `profile`, `parish`, `page_title`, role flags, scope labels |

---

#### Partials

| File                                                  | Included by                                      | Data passed |
|-------------------------------------------------------|--------------------------------------------------|-------------|
| `app/Views/System/partials/admin_actions.php`         | `system_admins.php` (per-row, via `setData`)     | See §3 below |
| `app/Views/System/partials/admin_password_create.php` | `system_admins.php` (Create modal)               | `prefix='createSA'` |
| `app/Views/System/partials/reset_password_modal.php`  | `system_admins.php` (once, after Create modal)   | *(none — self-contained)* |
| `app/Views/System/partials/admin_password_create.php` | `reset_password_modal.php` (Reset modal)         | `prefix`, `passwordName`, `confirmName`, `passwordLabel`, `confirmLabel` |

---

## 3. Action Buttons Logic (Logged-In User)

This is the core of the recent change. The per-row button rendering is split across two files.

### 3.1 Per-row variable computation — `system_admins.php` (lines 53–67)

```php
<?php
    $isSelf        = (int) (auth()->id() ?? 0) === (int) $admin['user_id'];
    $isPrimaryAdmin = (int) ($current_user_id ?? 0) === (int) ($primary_system_admin_id ?? 0);
?>
<?= $this->setData([
    'admin'                  => $admin,
    'editModalId'            => 'editSystemAdminModal',
    'resetModalId'           => 'resetPasswordModal',
    'transferPrimaryModalId' => 'transferPrimaryModal',
    'isSelf'                 => $isSelf,
    // Show Transfer Primary when: current user is primary admin OR viewing own row
    // AND the target row is NOT itself the primary admin
    'showTransferPrimary'    => (int) ($admin['is_primary'] ?? 0) !== 1
                                    && ($isPrimaryAdmin || $isSelf),
    // Show Delete when same conditions
    'canDelete'              => (int) ($admin['is_primary'] ?? 0) !== 1
                                    && ($isPrimaryAdmin || $isSelf),
], 'raw')->include('System/partials/admin_actions', [], false) ?>
```

**Decision table for `showTransferPrimary` / `canDelete`:**

| Scenario                                    | `isPrimaryAdmin` | `isSelf` | `is_primary` (target) | Show button? |
|---------------------------------------------|------------------|----------|-----------------------|--------------|
| Primary admin viewing another non-primary   | true             | false    | 0                     | ✅ enabled    |
| Primary admin viewing own row               | true             | true     | 0                     | ✅ disabled   |
| Non-primary admin viewing another's row     | false            | false    | 0                     | ❌ hidden     |
| Non-primary admin viewing **own** row       | false            | true     | 0                     | ✅ disabled   |
| Any admin viewing the primary admin row     | any              | any      | 1                     | ❌ hidden     |

---

### 3.2 Button rendering — `app/Views/System/partials/admin_actions.php`

```php
<?php
$isSelf              = $isSelf ?? false;
$showTransferPrimary = $showTransferPrimary ?? false;
$canDelete           = $canDelete ?? true;
?>

<div class="btn-group">

    <!-- Edit — always enabled for any row (primary guard is in the controller) -->
    <button type="button" class="btn btn-outline-primary"
        data-bs-toggle="modal" data-bs-target="#<?= esc($editModalId, 'attr') ?>"
        data-id="..." data-first-name="..." data-last-name="..."
        data-email="..." data-phone="..."
        title="<?= esc(lang('App.edit')) ?>">
        <i class="bi bi-pencil"></i>
    </button>

    <!-- Reset Password — always enabled -->
    <button type="button" class="btn btn-outline-warning"
        data-bs-toggle="modal" data-bs-target="#<?= esc($resetModalId, 'attr') ?>"
        data-id="..." data-name="..."
        title="<?= esc(lang('System.resetPasswordTitle')) ?>">
        <i class="bi bi-key"></i>
    </button>

    <!-- Transfer Primary — shown when $showTransferPrimary; disabled (not hidden) when $isSelf -->
    <?php if ($showTransferPrimary): ?>
        <button type="button"
            class="btn btn-outline-info<?= $isSelf ? ' disabled' : '' ?>"
            <?php if (!$isSelf): ?>
                data-bs-toggle="modal"
                data-bs-target="#<?= esc($transferPrimaryModalId, 'attr') ?>"
                data-id="..." data-name="..."
            <?php else: ?>
                aria-disabled="true" tabindex="-1"
            <?php endif; ?>
            title="<?= esc(lang('System.btnTransferPrimary')) ?>">
            <i class="bi bi-arrow-left-right"></i>
        </button>
    <?php endif; ?>

    <!-- Delete — shown when $canDelete; disabled (not hidden) when $isSelf -->
    <?php if ($canDelete): ?>
        <?php if ($isSelf): ?>
            <!-- Disabled placeholder — no form wrapping, no submit possible -->
            <button type="button" class="btn btn-outline-danger disabled"
                aria-disabled="true" tabindex="-1"
                title="<?= esc(lang('App.delete')) ?>">
                <i class="bi bi-trash3"></i>
            </button>
        <?php else: ?>
            <!-- Fully functional delete with SweetAlert double-confirm -->
            <form action="<?= base_url('system/admins/delete/' . (int) $admin['user_id']) ?>"
                method="post" class="d-inline"
                data-confirm-title="..."
                data-confirm-body="..."
                data-confirm-double
                data-confirm-swal-title="...">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger"
                    title="<?= esc(lang('App.delete')) ?>">
                    <i class="bi bi-trash3"></i>
                </button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

</div>
```

**Button state summary per row:**

| Button                      | Own row         | Other non-primary | Primary row |
|-----------------------------|-----------------|-------------------|-------------|
| Edit (blue pencil)          | ✅ enabled      | ✅ enabled        | ✅ enabled  |
| Reset Password (yellow key) | ✅ enabled      | ✅ enabled        | ✅ enabled  |
| Transfer Primary (teal arrows) | ⛔ disabled* | ✅ enabled*       | ❌ hidden   |
| Delete (red trash)          | ⛔ disabled     | ✅ enabled*       | ❌ hidden   |

\* Only visible when the logged-in user is either the primary admin **or** is looking at their own row.

---

## 4. Modals on the Page

| Modal ID                 | Trigger | Action route | File |
|--------------------------|---------|--------------|------|
| `createSystemAdminModal` | "Add New" header button | `POST system/admins/store` | Inline in `system_admins.php` |
| `editSystemAdminModal`   |     Edit button (per row) | `POST system/admins/update/{id}` | Inline in `system_admins.php` |
| `resetPasswordModal`     | Reset Password button (per row) | `POST system/admins/reset-password/{id}` | `partials/reset_password_modal.php` |
| `permissionsModal`       | "View Permissions" header button | *(read-only, no POST)* | Inline in `system_admins.php` |
| `transferPrimaryModal`   | Transfer Primary button (per row) | `POST system/admins/transfer-primary/{id}` | Inline in `system_admins.php` |

---

## 5. Complete File List

| File | Role |
|------|------|
| `app/Config/Routes.php` | Route definition (lines 53, 70–95) |
| `app/Config/Filters.php` | Global filter registration |
| `app/Config/AuthGroups.php` | Permission definitions and hierarchy |
| `app/Filters/PermissionFilter.php` | Custom group-based permission check |
| `app/Controllers/BaseController.php` | `isSystemAdmin()`, `requirePermission()`, `getCommonViewData()` |
| `app/Controllers/SystemAdmin.php` | `listSystemAdmins()`, `getPrimarySystemAdminId()`, `resolveExistingProfileTable()`, `tableExistsSafe()` |
| `app/Libraries/DataEncryption.php` | `decrypt()` for phone numbers |
| `app/Models/ProfileModel.php` | Used by `getCommonViewData()` (nav/profile data) |
| `app/Models/ParishModel.php` | Used by `getCommonViewData()` (scope label) |
| `app/Views/layouts/main.php` | HTML shell, Bootstrap 5, theme, sidebar |
| `app/Views/System/system_admins.php` | Main content view, table, all modals |
| `app/Views/System/partials/admin_actions.php` | Per-row button group (Edit, Reset PW, Transfer Primary, Delete) |
| `app/Views/System/partials/admin_password_create.php` | Password strength input (used in Create and Reset PW modals) |
| `app/Views/System/partials/reset_password_modal.php` | Reset Password modal HTML + JS listener |
