# Seeders (Canonical Set)

This document defines the active seeder strategy for ekklesiaONLINE.

## Policy

- Use one entry-point seeder for application bootstrap.
- Keep idempotent behavior for liturgical seed data.
- Keep role/user/parish sample bootstrap in one place.

## Canonical Seeder

- `app/Database/Seeds/DatabaseSeeder.php`

Responsibilities (idempotent; safe to re-run):

1. `PermissionsAndRolesSeeder` — Shield permissions and role grants.
2. `ProvincesAndDiocesesSeeder` — baseline provinces and dioceses.
3. `ChartAccountCategorySeeder` / `ChartOfAccountsSeeder` — baseline chart of accounts.

Run command:

```bash
php spark db:seed DatabaseSeeder
```

## Removed Superseded Seeders

- `app/Database/Seeds/UnifiedAppSeeder.php`
- `app/Database/Seeds/InitialDataSeeder.php`
- `app/Database/Seeds/LiturgicalCalendarSeeder.php`

## Role-to-Menu Policy

- Canonical policy table: `docs/ROLE_MENU_PERMISSION_POLICY.md`
- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
