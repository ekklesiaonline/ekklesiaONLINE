# EkklesiaONLINE - Multi-Parish Management System

ekklesiaONLINE is a Parish Management System (PMS) for province, diocese, and local parish operations, built on CodeIgniter 4.7 with Shield 1.4+ (v1.4.1) authentication.

## Core Capabilities

- Multi-parish and role-scoped access control.
- Member and family management.
- Services and attendance tracking.
- Visitor follow-up workflow.
- Finance workflows for donations, expenses, budgets, and reports.
- Volunteer teams and scheduling.

## Tech Stack

- PHP 8.5+
- CodeIgniter 4.7+
- CodeIgniter Shield 1.4+ (v1.4.1)
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
mysql -u root -p -e "CREATE DATABASE ekklesiaONLINE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations and Shield setup
php spark migrate --all
php spark shield:setup

# Seed baseline data
php spark db:seed DatabaseSeeder
```

### 4. Run Application

```bash
php spark serve
```

Open: <http://localhost:80>

## Bootstrap Accounts

Create deployment accounts through the approved seeding or administrative bootstrap process. The repository does not publish reusable login credentials.

## Project Layout

- Migrations: `app/Database/Migrations/`
- Seeders: `app/Database/Seeds/`
- Models: `app/Models/`
- Shared app routes/config: `app/Config/`
- Role modules: `Province/`, `Diocese/`, `Clergy/`, `Treasurer/`, `Volunteer/`, `MemberPortal/`

## Notes for Deployment and Runtime

- Supported PHP versions are 8.2–8.5 with MySQL 8.0+.
- The web root is the `public/` directory.
- Required extensions include `intl`, `mbstring`, `mysqli`/`mysqlnd`, `gd`, `curl`, `dom`, `xml`, and `zip`.
- Copy `.env.example` to `.env`, replace every placeholder, and follow the deployment runbook.
- `GET /health` is the public liveness probe; `GET /health/ready` is restricted to system administrators.

## Operations

- Deployment runbook: `docs/DEPLOYMENT_RUNBOOK.md`
- Database backup script: `build/backup_database.sh`
- Database restore script: `build/restore_database.sh`
- Nightly staging security workflow: `.github/workflows/nightly-security-rate-limit.yml`

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
