# Query Optimization Phase 6 Plan

Date: 2026-04-02
Scope: Continuous optimization review and regression control after Phase 5 baseline completion.

## Objective

Phase 6 formalizes the operational follow-up that comes after the initial optimization implementation. The focus is no longer adding broad optimizations, but preventing regressions and reacting to real workload data.

## Success Criteria

1. Query audit stays green after schema and query changes.
2. Benchmark results remain within the Phase 5 targets.
3. Slow-query review is performed on a repeatable cadence.
4. New indexes or cache changes are driven by measured regressions, not guesswork.
5. Review outcomes are captured in dated snapshots for comparison.

## Phase 6 Workstreams

### 1. Automated Review Snapshot

Run a single routine that combines:

1. `php spark optimize:query-audit --strict`
2. `php spark optimize:benchmark`
3. Optional authenticated HTTP baseline run
4. Consolidated status output saved to a timestamped log

Status: implemented in `build/phase6_review.sh`.

### 2. Scheduled Review Cadence

Run Phase 6 review:

1. Weekly in staging
2. After any DB migration affecting hot tables
3. After report/dashboard query changes
4. Before production release candidates

### 3. Regression Triage Rules

If the review finds regressions:

1. `query-audit` fails: inspect index presence and `EXPLAIN` plan changes first.
2. `optimize:benchmark` warns: compare row counts and query shape before changing thresholds.
3. HTTP baseline degrades: inspect slow query log and request monitoring logs together.
4. Only add indexes after confirming recurring workload patterns.

### 4. Evidence Capture

Each review run should preserve:

1. Timestamped review log
2. Benchmark output
3. Audit output
4. HTTP baseline output when authenticated credentials are available

## First Implementation Slice

The first implemented part of Phase 6 is the automated review runner:

```bash
sh build/phase6_review.sh
```

Optional authenticated HTTP baseline:

```bash
BASE_URL=http://127.0.0.1:8181 \
LOGIN_EMAIL=church@admin.com \
LOGIN_PASSWORD='Change@me' \
sh build/phase6_review.sh
```

The script writes a timestamped snapshot to `writable/logs/phase6-review-*.log`.

## Recommended Next Implementation After This Slice

1. Add the Phase 6 review script to CI or a scheduled staging job.
2. Add threshold comparison against the previous snapshot to flag trend regressions automatically.
3. Extend the benchmark set with higher-volume report/export scenarios once staging data is larger.

## Phase 6 Status

Phase 6 is defined.
Phase 6 implementation slice 1 is complete.
