# System Settings – Findings & Implementation Plan (2026-04-11)

## Context

`codeigniter4/settings 2.2.0` is already installed as a Composer dependency.
The `settings.manage` permission already exists in `app/Config/AuthGroups.php`
and is mapped to `churchadmin` and `systemadmin` groups.
No `Settings` controller or settings view exists yet.

The active cron entry for church people sync is:

```cron
*/5 * * * * cd /Users/juanrosariodelacruz/Sites/ekklesiaRD && sh build/cron_sync_church_people.sh >> writable/logs/church_people_sync_cron.log 2>&1
```

---

## Recommended Settings Registry

Settings are organized into three scopes that map directly to how
`codeigniter4/settings` stores values.

### User Preferences *(per-user — `setting()->setUser()`)*

| Key | Label | Options | Default |
| --- | ----- | ------- | ------- |
| `App.theme` | Interface theme | `light` / `dark` / `system` | `light` |
| `App.sidebarCollapsed` | Sidebar default state | `0` expanded / `1` collapsed | `0` |
| `App.itemsPerPage` | Default list page size | `10` / `25` / `50` / `100` | `25` |
| `App.locale` | Display language | `en` / `es` | `en` |
| `App.dateFormat` | Date display format | `Y-m-d` / `d/m/Y` / `M j, Y` | `M j, Y` |

### Church Settings *(scoped per `church_id`)*

| Key | Label | Options | Default |
| --- | ----- | ------- | ------- |
| `Church.defaultServiceType` | Default service type | `Sunday Worship` / `Weekday Service` / `Special` | `Sunday Worship` |
| `Church.liturgicalCalendar` | Liturgical calendar | `RCL` / `Roman` / `None` | `RCL` |
| `Church.fiscalYearStart` | Fiscal year start month | `1`–`12` | `1` |
| `Church.currency` | Currency code | `USD` / `DOP` / `EUR` | `USD` |
| `Church.enforceSingleFamily` | Prevent dual-family membership | `0` / `1` | `0` |
| `Church.guestFollowUpDays` | Guest follow-up reminder (days) | `3` / `5` / `7` / `14` | `7` |
| `Church.allowSelfCheckin` | Allow member self check-in | `0` / `1` | `0` |

### System Settings *(system admin only)*

| Key | Label | Options | Default |
| --- | ----- | ------- | ------- |
| `System.churchPeopleSyncInterval` | People registry sync interval (minutes) | `5` / `10` / `15` / `30` | `5` |
| `System.serviceMetricsRefreshHours` | Service metrics refresh cycle (hours) | `6` / `12` / `24` | `24` |
| `System.maintenanceMode` | Maintenance mode | `0` / `1` | `0` |
| `System.registrationOpen` | New user registration | `open` / `closed` | `open` |
| `System.emailNotifications` | Outbound email notifications | `0` / `1` | `1` |
| `System.backupRetentionDays` | Backup file retention (days) | `7` / `14` / `30` / `60` | `14` |

> `System.churchPeopleSyncInterval` is advisory — it documents the intended
> cron cadence. The settings view should print the matching crontab line so
> the admin can copy it (see todo step 10).

---

## Implementation Todo

### Step 1 — Verify Settings table migration

- Run `php spark migrate --all` after confirming `codeigniter4/settings` is
  listed in `composer show`.
- Confirm the `settings` table exists in the database.
- `codeigniter4/settings 2.2.0` ships its own migration; no custom migration
  needed.

### Step 2 — Create `App\Controllers\Settings`

- File: `app/Controllers/Settings.php`
- Methods:
  - `index()` — renders tabbed view (System / Church / User tabs).
  - `update()` — POST handler; validates and saves via `setting()`.
- Guard `System` and `Church` tabs with `permission:settings.manage`.
- Any authenticated user may save their own User Preferences tab.

### Step 3 — Create views

- File: `app/Views/settings/index.php`
- Bootstrap 5 tabbed card: **My Preferences** | **Church** | **System**
- `Church` and `System` tabs rendered conditionally based on permission.
- CSRF token included; AJAX save with toast feedback (consistent with UI
  patterns used across the app).

### Step 4 — Add routes

In `app/Config/Routes.php`:

```php
$routes->group('settings', ['filter' => 'session'], function ($routes) {
    $routes->get('/', 'Settings::index');
    $routes->post('update', 'Settings::update',
        ['filter' => 'permission:settings.manage']);
    $routes->post('update-preferences', 'Settings::updatePreferences');
});
```

### Step 5 — Add Settings link in sidebar

- File: `app/Views/partials/sidebar.php`
- Position: bottom of sidebar list, before logout.
- Visible to: all authenticated users (everyone can edit own preferences).
- Icon: `bi-gear`

```php
<li class="nav-item">
    <a class="nav-link <?= (str_starts_with(uri_string(), 'settings')) ? 'active' : '' ?>"
       href="<?= base_url('settings') ?>">
        <i class="bi bi-gear"></i> <?= lang('App.navSettings') ?>
    </a>
</li>
```

### Step 6 — Implement theme (dark/light) in layout

- Read `setting('App.theme')` in the main layout file.
- Set `data-bs-theme="dark|light"` on the `<html>` tag.
- Fall back to `light` if value is not set.

### Step 7 — Implement per-page default in paginated controllers

Replace hard-coded `$perPage = 25` with:

```php
$perPage = (int) (setting('App.itemsPerPage') ?? 25);
```

Applies to: Members, People, Guests, Donations, Expenses controllers.

### Step 8 — Implement locale setting

- After login, read `setting('App.locale')`.
- Call `\Config\Services::language()->setLocale($locale)` or store in
  session (check `app/Config/Events.php` — a preference reload hook may
  already be present).

### Step 9 — Add i18n keys for settings labels

Add to `app/Language/en/App.php` and `app/Language/es/App.php`:

```php
'navSettings'   => 'Settings',
'settingsTitle' => 'System Settings',
// one key per setting label/hint as needed
```

### Step 10 — Consume `System.churchPeopleSyncInterval` in cron hint UI

- The settings view reads the stored interval value and prints the
  corresponding ready-to-copy crontab line:

```cron
*/5 * * * * cd /path/to/ekklesiaRD && sh build/cron_sync_church_people.sh >> writable/logs/church_people_sync_cron.log 2>&1
```

- Changing the interval in the UI updates the hint; it does NOT
  automatically rewrite the crontab (that requires server-level access).

### Step 11 — Wire `System.maintenanceMode` in a before-filter

- Create `app/Filters/MaintenanceMode.php`.
- Returns a 503 view for non-system-admin users when `maintenanceMode` is `1`.
- Register in `app/Config/Filters.php` as a global `before` filter.

### Step 12 — Protect Church tab with church-scope guard

- Apply the same church scoping pattern used in People/Members controllers:

```php
$churchId = $this->getOperationalChurchId();
```

- Store `Church.*` settings using `$churchId` as the context key so
  settings are isolated per church and do not bleed across tenants.

---

## Files to Create / Modify (Summary)

| Action | File |
| ------ | ---- |
| Create | `app/Controllers/Settings.php` |
| Create | `app/Views/settings/index.php` |
| Create | `app/Filters/MaintenanceMode.php` |
| Modify | `app/Config/Routes.php` |
| Modify | `app/Config/Filters.php` |
| Modify | `app/Views/partials/sidebar.php` |
| Modify | `app/Language/en/App.php` |
| Modify | `app/Language/es/App.php` |
| Modify | Paginated controllers (Members, People, Guests, Donations, Expenses) |
| Modify | Main layout file (theme `data-bs-theme` injection) |
