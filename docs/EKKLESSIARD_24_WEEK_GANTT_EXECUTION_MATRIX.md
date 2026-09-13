# ekklessiaRD 24-Week Gantt-Style Execution Matrix

## 1. Objective

Build and cut over to a new Episcopal-aligned platform (ekklessiaRD) with:

- Canonical Episcopal domain: Province, Diocese, Parish, Bishop, Rector.
- New database schema and migration pipelines.
- AdminLTE4-based UI shell and module migration.
- Staged go-live with rollback safety.

## 2. Planning Assumptions

- Duration: 24 weeks.
- Team capacity: 2 backend, 1-2 frontend, 1 QA, 1 DevOps/SRE, 1 data engineer, product/domain support.
- Existing system remains source of truth until cutover freeze.
- Release cadence: bi-weekly internal release, monthly steering review.

## 3. Role Abbreviations

- PO: Product Owner.
- DL: Episcopal Domain Lead.
- SA: Solution Architect.
- BE1/BE2: Backend Engineers.
- FE1/FE2: Frontend Engineers.
- DE: Data Engineer.
- QA: QA Engineer.
- QAL: QA Lead.
- SRE: DevOps/SRE.
- SEC: Security Reviewer.
- CL: Change/Training Lead.

## 4. Legend

- X = active execution week.
- M = milestone/gate week.
- D = major deliverable publication week.

## 5. 24-Week Gantt Matrix (Weekly)

```text
Weeks: 01 02 03 04 05 06 07 08 09 10 11 12 13 14 15 16 17 18 19 20 21 22 23 24

WS-01 Program charter, scope, glossary                Owner: PO, DL, SA
      X  X  D  M                                                             Output: Approved charter and Episcopal terminology baseline

WS-02 Domain model and canonical schema               Owner: SA, BE1, DL
         X  X  X  X  D  M                                                    Output: ERD and naming standards

WS-03 Data mapping spec (old -> new)                  Owner: DE, BE2, SA
         X  X  X  X  X  D  M                                                 Output: Field-by-field mapping and conflict rules

WS-04 AdminLTE4 shell and design system               Owner: FE1, FE2, SA
               X  X  X  X  D  M                                              Output: Base layout, nav, theme, component tokens

WS-05 Auth, role, permission model                    Owner: BE1, SA, DL
            X  X  X  X  X  D  M                                              Output: Canonical RBAC and seed policy

WS-06 CI/CD, environments, observability              Owner: SRE, SA
      X  X  X  X  X  X  D  M                                                 Output: Pipelines, controls, telemetry

WS-07 Wave A migration (hierarchy + profile)          Owner: BE1, FE1, QA
                           X  X  X  D  M                                     Output: Province/Diocese/Parish hierarchy + profile core

WS-08 Wave B migration (services + attendance)        Owner: BE2, FE2, QA
                                 X  X  X  D  M                               Output: Service, attendance, guest follow-up parity

WS-09 Wave C migration (finance)                      Owner: BE1, FE1, QA
                                          X  X  X  D  M                      Output: Donations, expenses, budgets, finance dashboards

WS-10 Wave D migration (volunteers + worship)         Owner: BE2, FE2, QA
                                                     X  X  X  D  M           Output: Volunteer and worship modules cutover-ready

WS-11 UEID ingestion + parish assignment              Owner: DE, BE2, DL
                  X  X  X  X  X  X  D  M                                     Output: Validated ParishUEID -> Diocese assignment logic

WS-12 Migration engine + dry-run reporting            Owner: DE, BE1, QA
                     X  X  X  X  X  X  X  X  D  M                            Output: Repeatable ETL with reconciliation outputs

WS-13 Security hardening and threat review            Owner: SEC, SA, BE1
                        X  X        X  X        X  X  D  M                    Output: Security gate reports and remediations

WS-14 Performance and scale testing                   Owner: QAL, SRE, BE2
                                                 X  X  X  D  M                Output: Performance baselines and tuning closure

WS-15 UAT cycles                                      Owner: PO, DL, QAL
                                                       X  X  X  D  M          Output: UAT sign-off evidence

WS-16 Cutover runbook + rollback drills              Owner: SRE, DE, SA
                                                    X  X  X  D  M             Output: Rehearsed and approved cutover runbook

WS-17 Training, docs, support readiness              Owner: CL, PO, QAL
                                                          X  X  X  D  M       Output: User/admin training and support plan

WS-18 Production cutover + hypercare                 Owner: SRE, SA, QAL
                                                                 X  X  M      Output: Go-live completed and stabilized
```

## 6. Phase Breakdown and Deliverables

### Phase 0 (Weeks 1-2): Discovery and Mobilization

Primary outputs:

- Charter, risks, operating model, communication plan.
- Canonical Episcopal glossary.

Exit criteria:

- Sponsor and domain sign-off.

### Phase 1 (Weeks 3-5): Canonical Domain and Data Contracts

Primary outputs:

- ERD, naming conventions, role and permission taxonomy.
- Data mapping matrix with constraints.

Exit criteria:

- All critical entities mapped with no unresolved blockers.

### Phase 2 (Weeks 6-8): Platform and AdminLTE4 Foundation

Primary outputs:

- AdminLTE4 shell, base CI4 layout, shared components and tokens.
- Auth and route guard baseline.
- CI/CD and observability operational.

Exit criteria:

- Foundation smoke suite green.

### Phase 3 (Weeks 9-17): Module Migration Waves

Primary outputs:

- Wave A (Weeks 9-10): hierarchy and profile foundation.
- Wave B (Weeks 11-12): services, attendance, guests/follow-up.
- Wave C (Weeks 13-14): finance.
- Wave D (Weeks 15-17): volunteers/events/worship.

Exit criteria:

- Module parity checklist complete for each wave.
- Permission scope tests pass.

### Phase 4 (Weeks 18-20): Migration Rehearsals and Validation

Primary outputs:

- ETL dry-runs, discrepancy reports, correction cycles.
- UEID and diocesan assignment validation summary.

Exit criteria:

- Zero critical data mismatches.

### Phase 5 (Weeks 21-22): UAT and Readiness

Primary outputs:

- UAT sign-off pack, training assets, support handoff.

Exit criteria:

- Go-live CAB approval.

### Phase 6 (Weeks 23-24): Cutover and Hypercare

Primary outputs:

- Production cutover, stabilization, post-implementation review.

Exit criteria:

- No unresolved Sev-1 incidents and BAU handoff signed.

## 7. Module-Specific Migration Acceptance Matrix

```text
Province
- Functional parity: Province dashboard, diocese/parish oversight.
- Data integrity: Province and diocese links complete.
- Access control: Province-admin scope verified.
- UI/UX acceptance: AdminLTE4 shell compliant.
- Report parity: Summary exports verified.
- Owner: BE1 + FE1 + QA.

Diocesan
- Functional parity: Diocese dashboard and moderation.
- Data integrity: Diocese-to-parish assignments validated.
- Access control: Diocese scope enforced.
- UI/UX acceptance: Navigation/cards standardized.
- Report parity: Diocesan reports match baseline.
- Owner: BE2 + FE2 + QA.

MemberPortal
- Functional parity: Profile, locale, member dashboard.
- Data integrity: Profile fields and locale migration validated.
- Access control: Member path and scope validated.
- UI/UX acceptance: Profile pages tokenized and consistent.
- Owner: BE1 + FE1 + QA.

Pastor/Rector
- Functional parity: Member oversight and service operations.
- Data integrity: Membership and attendance lineage preserved.
- Access control: Rector permissions verified.
- UI/UX acceptance: Forms/tables standardized.
- Report parity: Attendance reports parity.
- Owner: BE2 + FE2 + QA.

Treasurer
- Functional parity: Donations, expenses, budgets.
- Data integrity: Financial ledger checks passed.
- Access control: Treasurer-only actions enforced.
- UI/UX acceptance: Finance UI responsive and standardized.
- Report parity: Financial exports parity.
- Owner: BE1 + FE1 + QA.

Volunteer
- Functional parity: Teams and schedules.
- Data integrity: Schedule integrity preserved.
- Access control: Coordinator scopes enforced.
- UI/UX acceptance: Calendar/list views consistent.
- Report parity: Schedule exports parity.
- Owner: BE2 + FE2 + QA.

Worship
- Functional parity: Planning and service support.
- Data integrity: Planning artifacts linked correctly.
- Access control: Worship permissions validated.
- UI/UX acceptance: Worship planning UI parity.
- Report parity: Planning output parity.
- Owner: BE2 + FE2 + QA.
```

## 8. Go-Live Gates (Must Pass)

### Gate G1: Foundation Complete (End of Week 8)

- AdminLTE4 shell stable in staging.
- RBAC base policy active.
- CI/CD and observability operational.

### Gate G2: Module Migration Complete (End of Week 17)

- All migration waves signed off by QA and PO.
- No critical permission or scope defects open.

### Gate G3: Data Migration Proven (End of Week 20)

- Two successful rehearsal runs.
- Critical discrepancy count = 0.
- UEID parish assignment unresolved count = 0.

### Gate G4: Business Readiness (End of Week 22)

- UAT approved by domain owners.
- Training and support complete.
- Cutover runbook approved by CAB.

### Gate G5: Production Stabilization (End of Week 24)

- Hypercare complete.
- No unresolved Sev-1 incidents.
- Formal BAU handoff completed.

## 9. Weekly Governance Cadence

- Weekly engineering sync: execution status, blockers, risks.
- Weekly QA triage: defect burn-down and gate confidence.
- Bi-weekly product review: scope and acceptance changes.
- Monthly steering committee: timeline, budget, risk decisions.

## 10. Risk Register Starter

```text
R1 Domain naming ambiguity between old/new terms
- Probability: Medium
- Impact: High
- Mitigation: Canonical glossary and alias map with early sign-off
- Owner: DL

R2 Hidden schema coupling during migration
- Probability: High
- Impact: High
- Mitigation: Staging ETL and dual reconciliation runs
- Owner: DE

R3 Permission regression across scopes
- Probability: Medium
- Impact: High
- Mitigation: Automated role/scope regression suite per wave
- Owner: QAL

R4 Frontend inconsistency during AdminLTE4 migration
- Probability: Medium
- Impact: Medium
- Mitigation: Shared base layout/components and UI checklist
- Owner: FE Lead

R5 Cutover downtime overrun
- Probability: Low
- Impact: High
- Mitigation: Rehearsed runbook, rollback drill, and freeze window
- Owner: SRE
```

## 11. Immediate Next Actions (Week 1)

1. Approve this matrix and lock milestone dates.
1. Confirm team assignment per workstream ID.
1. Create project board lanes matching WS-01 to WS-18.
1. Start WS-01 kickoff workshop and glossary approval.
