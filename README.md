# EkklesiaONLINE — Multi-Parish Management System

ekklesiaONLINE is a Parish Management System (PMS) for province, diocese, and local parish operations, built on CodeIgniter 4.7 with Shield 1.4+ (v1.4.1) authentication.

## Core Capabilities

- Multi-parish and role-scoped access control.
- Member and family management.
- Services and attendance tracking.
- Visitor follow-up workflow.
- Finance workflows for donations, expenses, budgets, and reports.
- Volunteer teams and scheduling.

## Tech Stack

- PHP 8.5
- CodeIgniter 4.7+
- CodeIgniter Shield 1.4+ (v1.4.1)
- MySQL 8.0+
- Bootstrap 5

## Requirements

### System

- PHP 8.5 (CLI and web SAPI should match)
- Composer 2.x
- MySQL 8.0+ or MariaDB 10.4+
- A web server that serves the `public/` directory (Apache, Nginx, or `php spark serve` for development)

### Required PHP Extensions

- `intl`
- `mbstring`
- `mysqli` or `mysqlnd`
- `gd`
- `curl`
- `dom`
- `xml`
- `zip`

### Optional / Environment-Specific

- Mail transport (SMTP) configured via `.env` for credential-bearing mail (activation, password reset, device verification)
- Cron access for scheduled jobs (e.g., volunteer reminders, visitor follow-up dispatch)

## Installation

### 1. Install Dependencies

```bash
composer install
```

If you prefer the framework defaults as a starting point, you can also copy the stock `env` file to `.env`.

### 2. Configure Environment

```bash
cp .env.example .env
# Update .env (database credentials, app.baseURL, and mail settings if needed)
```

Replace every placeholder in `.env` — in particular `app.baseURL` must match the URL you will browse to (no trailing path beyond `/`).

### 3. Setup Database

Create the database, run all migrations, initialize Shield, and seed baseline data:

```bash
# Create database (example)
mysql -u root -p -e "CREATE DATABASE ekklesiaONLINE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations and Shield setup
php spark migrate --all
php spark shield:setup

# Seed baseline data
php spark db:seed DatabaseSeeder
```

### 4. Run Application

Development server:

```bash
php spark serve
```

Open: <http://localhost:8080> (port shown in the `spark serve` output)

Production: point your web server's document root at the `public/` directory and serve over HTTPS.

## Bootstrap Accounts

Create deployment accounts through the approved seeding or administrative bootstrap process. The repository does not publish reusable login credentials.

## Project Layout

- Migrations: `app/Database/Migrations/`
- Seeders: `app/Database/Seeds/`
- Models: `app/Models/`
- Shared app routes/config: `app/Config/`
- Role modules: `Province/`, `Diocese/`, `Clergy/`, `Parish/`, `Worship/`, `Finance/`, `Volunteer/`, `MemberPortal/`

## Notes for Deployment and Runtime

- The web root is the `public/` directory.
- `GET /health` is the public liveness probe; `GET /health/ready` is restricted to system administrators.
- Copy `.env.example` to `.env`, replace every placeholder, and follow the deployment runbook.

## Operations

- Deployment runbook: `docs/DEPLOYMENT_RUNBOOK.md`
- Database backup script: `build/backup_database.sh`
- Database restore script: `build/restore_database.sh`
- Nightly staging rate-limit check: `.github/workflows/nightly-security-rate-limit.yml`

## Parish Staff Role Actions

On parish staff management pages (`/parishes/{id}/staff`):

- `Remove Role` removes only the selected leadership role from a user.
- `Demote` removes all parish leadership roles (`parishadmin`, `pastor`, `treasurer`, `volunteer_coordinator`) and ensures the user remains in `member`.

## Canonical Documentation

- Documentation index: `docs/DOCUMENTATION_CANONICAL.md`
- Accounting reference: `docs/CHART_OF_ACCOUNTS_GUIDELINES.md`
- Seed strategy: `docs/SEEDERS.md`
- Service migration lineage: `docs/SERVICE_MIGRATIONS.md`
- Deployment and rollback SOP: `docs/DEPLOYMENT_RUNBOOK.md`

## Framework Reference

- CodeIgniter site: <https://codeigniter.com>
- CodeIgniter user guide: <https://codeigniter.com/user_guide/>
