# EkklesiaRD - Multi-Parish Management System

EkklesiaRD is a Parish Management System (PMS) for province, diocese, and local parish operations, built on CodeIgniter 4.7 with Shield 1.2 authentication.

## Core Capabilities

- Multi-parish and role-scoped access control.
- Member and family management.
- Services and attendance tracking.
- Guest follow-up workflow.
- Finance workflows for donations, expenses, budgets, and reports.
- Volunteer teams and scheduling.

## Tech Stack

- PHP 8.2+
- CodeIgniter 4.7
- CodeIgniter Shield 1.2
- MySQL 8.0+
- Bootstrap 5

## Quick Start

### 1. Install Dependencies

```bash
composer install
```

### 2. Configure Environment

```bash
cp .env.example .env
# Update .env (database, app.baseURL, and mail settings if needed)
```

If you prefer the framework defaults as a starting point, you can still copy `env` to `.env`.

### 3. Setup Database

```bash
# Create database (example)
mysql -u root -p -e "CREATE DATABASE ekklesiaRD CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations and Shield setup
php spark migrate --all
php spark shield:setup

# Seed baseline data
php spark db:seed UnifiedAppSeeder
```

### 4. Run Application

```bash
php spark serve
```

Open: <http://localhost:8080>

## Default Login Credentials

- Super Admin: <admin@ekklesiaRD.org> / Admin@123456
- Church Admin: <churchadmin@firstchurch.org> / Church@123456

## Project Layout

- Migrations: `app/Database/Migrations/`
- Seeders: `app/Database/Seeds/`
- Models: `app/Models/`
- Shared app routes/config: `app/Config/`
- Role modules: `Province/`, `Diocesan/`, `Pastor/`, `Treasurer/`, `Volunteer/`, `MemberPortal/`

## Notes for Deployment and Runtime

- The web root is the `public/` directory.
- Ensure PHP extensions include at least `intl`, `mbstring`, `json`, and `mysqlnd` for MySQL usage.
- For production, set environment and security settings appropriately in `.env`.

## Operations

- Deployment runbook: `docs/DEPLOYMENT_RUNBOOK.md`
- Database backup script: `build/backup_database.sh`
- Database restore script: `build/restore_database.sh`
- Optional nightly security CI workflow: `.github/workflows/nightly-security-rate-limit.yml`

## Church Staff Role Actions

On church staff management pages (`/churches/{id}/staff`):

- `Remove Role` removes only the selected leadership role from a user.
- `Demote` removes all church leadership roles (`churchadmin`, `pastor`, `treasurer`, `volunteer_coordinator`) and ensures the user remains in `member`.

## Canonical Documentation

- Documentation index: `docs/DOCUMENTATION_CANONICAL.md`
- Accounting reference: `docs/CHART_OF_ACCOUNTS_GUIDELINES.md`
- Seed strategy: `docs/SEEDERS.md`
- Service migration lineage: `docs/SERVICE_MIGRATIONS.md`
- Deployment and rollback SOP: `docs/DEPLOYMENT_RUNBOOK.md`

## Framework Reference

- CodeIgniter site: <https://codeigniter.com>
- CodeIgniter user guide: <https://codeigniter.com/user_guide/>
