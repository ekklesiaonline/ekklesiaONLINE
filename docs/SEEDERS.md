# Seeders (Canonical Set)

This document defines the active seeder strategy for ekklesiaRD.

## Policy

- Use one entry-point seeder for application bootstrap.
- Keep idempotent behavior for liturgical seed data.
- Keep role/user/parish sample bootstrap in one place.

## Canonical Seeder

- `app/Database/Seeds/UnifiedAppSeeder.php`

Responsibilities:

1. Seed sample parish and baseline users (Province Admin + Parish Admin).
2. Seed profile records for created users.
3. Sync role-to-menu permission policy via `PermissionRoleSyncSeeder`.
4. Seed/update liturgical calendar entries.

Run command:

```bash
php spark db:seed UnifiedAppSeeder
```

## Removed Superseded Seeders

- `app/Database/Seeds/InitialDataSeeder.php`
- `app/Database/Seeds/LiturgicalCalendarSeeder.php`

Their logic is now consolidated into `UnifiedAppSeeder`.

## Role-to-Menu Policy

- Canonical policy table: `docs/ROLE_MENU_PERMISSION_POLICY.md`
- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
