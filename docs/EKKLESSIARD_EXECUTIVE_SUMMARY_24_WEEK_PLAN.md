# ekklessiaRD Executive Summary (24-Week Plan)

## Purpose

Launch ekklessiaRD as a new Episcopal-aligned platform with canonical terminology and governance:

- Province, Diocese, Parish.
- Bishop and Rector role model.
- AdminLTE4 user experience foundation.
- Controlled migration and low-risk production cutover.

## Why This Matters

The current platform can continue serving operations, but long-term scalability and clarity improve significantly by adopting:

- Canonical Episcopal domain language across data, permissions, and UX.
- Stronger role-based governance by organizational level.
- Cleaner foundation for reporting, onboarding, training, and expansion.

## Scope at a Glance

In scope:

- New canonical data model and migration tooling.
- AdminLTE4 shell and standardized module UX.
- Module migration waves: Foundation, Services/Attendance, Finance, Volunteer/Worship.
- UAT, cutover runbook, rollback drill, and hypercare.

Out of scope for this 24-week window:

- Large non-essential feature expansion unrelated to canonical migration.
- Parallel redesign of non-critical historical reports unless required for parity.

## Timeline Summary (24 Weeks)

1. Weeks 1-2: Mobilization and governance baseline.
1. Weeks 3-5: Canonical schema and data mapping contracts.
1. Weeks 6-8: Platform foundation and AdminLTE4 shell.
1. Weeks 9-17: Module migration waves A through D.
1. Weeks 18-20: Migration rehearsals and data reconciliation.
1. Weeks 21-22: UAT, readiness, and CAB approval.
1. Weeks 23-24: Production cutover and hypercare.

## Leadership Milestones and Gates

- G1 (Week 8): Foundation complete.
- G2 (Week 17): Module migration complete.
- G3 (Week 20): Data migration proven.
- G4 (Week 22): Business readiness approved.
- G5 (Week 24): Production stabilization complete.

Decision rule: do not advance to the next gate with unresolved critical defects or unresolved data discrepancies.

## Delivery Model and Team

Core roles:

- Product Owner and Episcopal Domain Lead for prioritization and canonical decisions.
- Architect, Backend, Frontend, Data Engineer for implementation.
- QA Lead, QA Engineer, Security, and SRE for release confidence.
- Change/Training support for user adoption and operational continuity.

## Risk Posture and Controls

Top risks:

- Domain ambiguity between old and canonical terms.
- Data migration mismatch during cutover rehearsal.
- Role/scope regressions in hierarchical permissions.
- UI inconsistency during module transitions.

Controls:

- Canonical glossary and alias governance.
- Staging ETL with repeatable dry-runs and discrepancy reports.
- Automated role/scope regression suite.
- Shared AdminLTE4 layout and component standards.

## Expected Outcomes

By Week 24, leadership should expect:

- A production-ready Episcopal-domain platform (ekklessiaRD).
- Canonical, auditable hierarchy and permission model.
- Validated data migration with rollback safety.
- Improved user consistency through a standardized AdminLTE4 experience.

## Immediate Executive Actions (This Week)

1. Confirm scope and approve gate criteria.
1. Assign named owners per workstream.
1. Approve change-control policy for in-flight scope requests.
1. Authorize kickoff of Phase 0 and Phase 1 activities.

## Reference

Detailed matrix: docs/EKKLESSIARD_24_WEEK_GANTT_EXECUTION_MATRIX.md
