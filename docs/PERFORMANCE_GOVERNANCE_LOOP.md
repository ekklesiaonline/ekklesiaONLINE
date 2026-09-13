# Performance Governance Loop

Date: 2026-04-02
Status: Phase C kickoff (started)

## Objective

Operationalize recurring performance review so query/index improvements stay enforced over time instead of one-off tuning.

## Cadence

1. Weekly: slow-query review + top endpoint latency review.
2. Bi-weekly: query plan verification for top 10 expensive queries.
3. Monthly: SLA review and index effectiveness audit.
4. Per release: smoke benchmark of high-traffic workflows.

## Baseline SLAs (initial)

1. P95 page response for key dashboards: <= 800 ms (app-only, warm cache).
2. P95 mutation endpoint response: <= 500 ms.
3. Slow query threshold: > 300 ms flagged for review.
4. Error budget for regression checks: 0 unresolved P1 performance regressions before release.

## Governance Checklist

1. Capture top slow queries from DB logs for review window.
2. Attach EXPLAIN output for each top slow query.
3. Confirm index coverage and cardinality assumptions.
4. Identify query-level or schema-level remediations.
5. Record action owner, target date, and validation plan.
6. Re-measure impacted endpoints after fixes.
7. Publish monthly summary in docs with trend deltas.

## Artifacts

1. docs/OPTIMIZATION_SUMMARY.md (historical implementation baseline)
2. build/performance_governance_review.sh (repeatable collection helper)
3. docs/PERFORMANCE_GOVERNANCE_LOOP.md (this policy)

## Initial Owners

1. Performance and reliability owner: performance review lead.
2. Platform owner: release gate enforcement in CI/release checklist.
3. Module owners: remediation for module-owned regressions.
