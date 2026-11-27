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

## Parish Table Requirement Before Go-Live

`DatabaseSeeder` intentionally does **not** populate `parish` rows (`ProvincesAndDiocesesSeeder` inserts provinces and sample dioceses only). Going live on webhosting with an empty `parish` table (or missing `province` / `diocese` parents) is a **release no-go** enforced by `php spark directory:verify-readiness` and `GET /health/ready` (`components.directory`). `ProvincesAndDiocesesSeeder` only inserts the sample dioceses when `diocese` is completely empty, so routine re-seeding never recreates them after real dioceses exist; leftover `Sample Diocese One/Two` rows are reported as a warning and fail `directory:verify-readiness --strict` — remove them before go-live.

Before going live, populate `province`, `diocese`, and `parish` via one of the approved paths in `docs/HOSTGATOR_DEPLOY.md` §4.1 (source-DB hierarchy export/import in FK order `province` → `diocese` → `parish`, `php spark directory:sync-ueid --apply`, or manual System Admin UI creation), then verify:

```bash
php spark directory:verify-readiness --strict
```

## Removed Superseded Seeders

- `app/Database/Seeds/UnifiedAppSeeder.php`
- `app/Database/Seeds/InitialDataSeeder.php`
- `app/Database/Seeds/LiturgicalCalendarSeeder.php`

## Role-to-Menu Policy

- Canonical policy table: `docs/ROLE_MENU_PERMISSION_POLICY.md`
- Enforced by: `app/Database/Seeds/PermissionRoleSyncSeeder.php`
