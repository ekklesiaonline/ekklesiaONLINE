# Query Optimization Phase 4 Runbook

Date: 2026-04-02
Scope: Operational rollout and monitoring for query optimizations (indexes + cache + explain checks)

## Goals

1. Apply optimization indexes safely.
2. Verify index presence and query plans.
3. Enable and review slow query logging.
4. Establish a repeatable weekly audit process.

## Prerequisites

1. Application deploy includes migration `2026_04_01_000001_AddQueryOptimizationIndexes`.
2. Environment has DB access for `SHOW INDEX` and `EXPLAIN`.
3. Application cache driver is configured (file/redis/memcached).

## Step 1: Apply Index Migration

Run in app root:

```bash
php spark migrate
```

Expected:

1. Migration completes without error.
2. New indexes exist on:

- `member_church_affiliations`
- `attendance`
- `donations`
- `expenses`
- `services`
- `member_standing_summary`

## Step 2: Run Query Audit Command

Run the new audit command:

```bash
php spark optimize:query-audit
```

Strict mode (CI-friendly non-zero exit on warnings):

```bash
php spark optimize:query-audit --strict
```

What it checks:

1. Required index names per table.
2. `EXPLAIN` plans for critical read queries (members, donations, expenses, services, standing summary).

Interpretation:

1. `GREEN`: all required indexes present and no full-scan explain warnings.
2. `ATTENTION`: at least one missing index or explain warning; review before release.

## Step 3: Enable Slow Query Logging (MySQL)

Temporary (runtime):

```sql
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 0.2;
SET GLOBAL log_output = 'FILE';
```

Persistent (`my.cnf`):

```ini
[mysqld]
slow_query_log = 1
long_query_time = 0.2
log_output = FILE
slow_query_log_file = /var/log/mysql/slow-query.log
```

Notes:

1. Use `0.2` in staging, optionally `0.1` in focused profiling windows.
2. Tune threshold higher in production if log volume is excessive.

## Step 4: Review Slow Queries

Basic review commands:

```bash
tail -n 200 /var/log/mysql/slow-query.log
mysqldumpslow -s t /var/log/mysql/slow-query.log | head -n 20
```

Watch for:

1. Repeated full scans on `donations`, `expenses`, `services`, `member_church_affiliations`.
2. Missing `key` usage in explain output for common date-range queries.
3. Query patterns bypassing cached list helpers.

## Step 5: Cache Verification Checklist

Caching implemented in Phase 3:

1. Chart of Accounts list/read methods (with write invalidation).
2. Creatable roles lookup in members controller.
3. Province/diocese church selector lists in base controller.

Quick checks:

1. Load affected pages twice and compare latency.
2. Edit/create/delete chart accounts and verify updated lists are visible immediately.
3. Confirm no stale scope lists after church hierarchy changes (if those changes happen often, add manual cache flush step in admin workflow).

## Step 6: Weekly Audit Routine

Run weekly in staging and after DB schema releases:

```bash
php spark optimize:query-audit --strict
```

Then:

1. Review slow query top 20.
2. Update index plan if new high-frequency scans appear.
3. Track trend in `docs/OPTIMIZATION_SUMMARY.md`.

## Rollback

If index migration causes unexpected side effects:

```bash
php spark migrate:rollback
```

If cache behavior is suspected:

1. Clear app cache (driver-specific).
2. Re-run `php spark optimize:query-audit`.
3. Verify read paths against expected row counts.

## Completion Criteria

1. Migration applied successfully.
2. `php spark optimize:query-audit --strict` exits successfully.
3. No recurring slow-query regressions on critical workflows.
4. Cache-backed views return correct data after write operations.
