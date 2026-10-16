# ekklesiaONLINE - Migrations & Seeders Setup

**Created: May 12, 2026**

## Overview

The old multi-phase migration chain has been replaced with a clean baseline and new seeders. All existing database tables (148) are now captured in a canonical baseline, and all new features will build on top of this.

## Migrations

### 2026_05_12_000000 - CreateShieldTables

**Status:** ✅ Active

Creates the Shield authentication framework tables required for roles and permissions:

- `auth_groups` - Role/group definitions
- `auth_permissions` - Permission definitions
- `auth_groups_permissions` - Junction table for role-permission assignments

**Run:** `php spark migrate`

### 2026_05_12_000001 - CreateCanonicalSchema

**Status:** ✅ Active

Creates all 138 remaining application tables in correct dependency order. This migration:

- Reads from `app/Database/canonical_schema_tables.sql`
- Disables foreign key checks during creation to handle table dependencies
- Is idempotent (safe to re-run)

**Run:** `php spark migrate`

## Seeders

### DatabaseSeeder (Master)

**Command:** `php spark db:seed DatabaseSeeder`

Master seeder that orchestrates all other seeders in the correct order:

1. PermissionsAndRolesSeeder
2. ProvincesAndDiocesesSeeder

### PermissionsAndRolesSeeder

**Command:** `php spark db:seed PermissionsAndRolesSeeder`

Seeds:

- **49 permissions** - All application permissions (members._, attendance._, finance.\*, etc.)
- **9 roles/groups** - systemadmin, provinceadmin, diocesanadmin, parishadmin, pastor, treasurer, volunteer_coordinator, followup_volunteer, member
- **223 role-permission mappings** - Assigns appropriate permissions to each role

### ProvincesAndDiocesesSeeder

**Command:** `php spark db:seed ProvincesAndDiocesesSeeder`

Seeds:

- **10 provinces** - US Episcopal Church provinces I-X
- **2 sample dioceses** - Sample data in the first province


## Follow-up workflow migrations

The default-sequence work added four migrations:

| Migration | Purpose |
| --- | --- |
| `AddFollowupSequenceDefaultFlags` | Adds `is_default` and `is_global_default` to `followup_sequence`. |
| `FixFollowupActionChannelEnum` | Adds `manual` to the MySQL `followup_action.channel` enum. |
| `SeedGlobalDefaultFollowupSequence` | Creates the system/global "6-Week First-Time Visitor Journey" sequence, its templates, and actions. |
| `BackfillFollowupSequenceDefaults` | Marks the oldest active sequence in each parish as the default. |

Run them with `php spark migrate`.

After the migrations, use `php spark followup:apply-default` to clone the
system default into parishes that still have no default sequence.

## Usage

### Fresh Setup

```bash
# Run all migrations (creates tables)
php spark migrate

# Seed initial data
php spark db:seed DatabaseSeeder
```

### Adding New Features

1. Create a new migration for schema changes:

    ```bash
    php spark make:migration AddNewFeatureTable
    ```

2. Create new seeders for initial data:

    ```bash
    php spark make:seeder NewFeatureSeeder
    ```

3. Add seeder to DatabaseSeeder::run() if needed

### Rollback (use with caution in production)

```bash
# Rollback all migrations
php spark migrate:rollback

# Rollback specific batch
php spark migrate:rollback --batch=1
```

## Current Database State

| Component    | Count |
| ------------ | ----- |
| Total Tables | 148   |
| Migrations   | 2     |
| Permissions  | 49    |
| Roles/Groups | 9     |
| Provinces    | 10    |
| Dioceses     | 2     |

## Migration Files

- `app/Database/Migrations/2026_05_12_000000_CreateShieldTables.php`
- `app/Database/Migrations/2026_05_12_000001_CreateCanonicalSchema.php`
- `app/Database/canonical_schema_tables.sql` (schema source file for the baseline migration)

**Schema truth policy:** migrations are the only source of schema truth.
`canonical_schema_tables.sql` is not a snapshot — it is the input file the
baseline migration executes on fresh installs, kept in step with the
migration chain. Do not commit `mysqldump` exports or other `*.sql`
snapshots under `app/Database/`; they drift from the migration truth.
(The former `canonical_schema.sql`, `canonical_schema_tables_ordered.sql`,
and `localhost.sql` dumps were retired for exactly that reason.)

## Seeder Files

- `app/Database/Seeds/DatabaseSeeder.php` (master orchestrator)
- `app/Database/Seeds/PermissionsAndRolesSeeder.php`
- `app/Database/Seeds/ProvincesAndDiocesesSeeder.php`

## Notes

- All migrations use idempotent patterns (`IF NOT EXISTS`, error suppression)
- Foreign key checks are disabled during baseline schema creation to handle dependency order
- Seeders check for existing data before inserting (safe for re-running)
- The canonical schema file contains all 138 application tables with proper indexing and constraints
- Future migrations should add incrementally to the baseline, not replace it

## Deployment Checklist

- [ ] Run migrations: `php spark migrate`
- [ ] Seed data: `php spark db:seed DatabaseSeeder`
- [ ] Verify table count: `php spark db:table --metadata | wc -l`
- [ ] Test authentication: Create a test user and verify role assignment
- [ ] Verify permissions: Check that roles have expected permissions
- [ ] Check environment: `.env` SMTP, encryption key, app URL configured
- [ ] Test backup/restore: Run `build/backup_database.sh` and verify restore works
