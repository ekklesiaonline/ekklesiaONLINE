# Agent Skills and Instructions Index

This repository contains project-specific instructions for AI agents and contributors.

## Precedence

1. `AGENTS.md` — repository-wide architecture, terminology, security, testing, and operational rules.
2. `.agents/rules/*.md` (or `.devin/rules/*.md`) — always-on CodeIgniter/Shield, domain (membership, sacraments, hierarchy/authz, finance), and Bootstrap/view conventions.
3. `docs/DOCUMENTATION_CANONICAL.md` — identifies authoritative documentation and separates it from historical reports.
4. `.agents/skills/*/SKILL.md` (or `.devin/skills/*/SKILL.md`) — task-specific workflows and diagnostic playbooks; load only when relevant.
5. Current code, migrations, and tests — final authority for implemented behavior.

When sources conflict, stop and resolve the discrepancy rather than silently choosing a convenient rule.

## Always-on instructions

- Use CI4 validation for every form/POST endpoint and Shield filters plus in-controller permission checks.
- Scope parish queries by `parish_id`; use elevated cross-scope access only when the code explicitly grants it.
- Use nullable-prefix model casts (`?int`, `?string`, `?bool`, `?datetime`).
- Prefer services/models and Query Builder/prepared statements; use `db_connect()` only for ad-hoc queries.
- Use `EmailScopeService` for sender/transport resolution and `credentialMailer()` for credential-bearing messages.
- Follow the canonical API/AJAX envelope and never expose raw exceptions or secrets.
- Generate CI4 boilerplate with `php spark`; write tests with `CIUnitTestCase`.
- Run `vendor/bin/phpunit --no-coverage`; there is no `php spark test` command.
- Do not deploy, restore, delete data, or change credentials without explicit authorization.

## Skill catalog

| Skill | Use when |
| --- | --- |
| `project-architecture` | Tracing routes/modules, deciding ownership, explaining scope or project structure |
| `api-ajax-contract` | Creating/reviewing endpoints, fetch code, JSON contracts, CSRF, or AJAX smoke tests |
| `deployment-release-gate` | Release readiness, deployment, rollback, backup/restore, health, or staging validation |
| `data-integrity` | Person/profile/affiliation repairs, lifecycle invariants, snapshot or sacrament reconciliation |
| `dashboard-scope` | Dashboard landing routes and hierarchy scope |
| `parish-permissions` / `parish-staff` / `permission-denied` | Role, staff, permission, and 403 diagnosis |
| `visitor-lifecycle` / `visitor-followup` | Visitor intake, conversion, scheduling, and follow-up workflows |
| `email-deliverability` | SMTP, SPF/DKIM/DMARC, scoped sender, or provider-delivery issues |
| `localization` / `audit-logging` | Locale resolution, audit, redaction, and observability |
| `codeigniter-test` | CI4 test creation and execution |
| `event-visibility` / `finance-parish` / `liturgical-calendar` / `vestry-governance` | Domain-specific module work |
| `episcopal-clergy-authz-planning` | Episcopal clergy ordination, canonical standing, deaneries, and scoped authorization |
| `member-portal-security` | Member personal dashboard, session security, device verification, and communicant standing |
| `parochial-report-governance` | Parochial report preparation, multi-tier approvals, and cryptographic snapshots |
| `person-member-merge` | Deduplicating persons/members, candidate preview, transactional merge, and reparenting |
| `volunteer-scheduling` | Volunteer opportunities, shifts, rosters, and reminder notifications |
| `worship-planning` | Service creation, worship planning, song repertoire, and attendance reporting |
| `add-member` / `sacrament-sync` / `sunday-attendance-charts` / `debug-no-parish` / `debug-person-not-found` / `explain` | Member onboarding, sacramental sync, attendance charting, diagnostics, and walk-throughs |

## Canonical document map

- Setup/runtime: `README.md`
- Deployment/rollback: `docs/DEPLOYMENT_RUNBOOK.md`
- API/AJAX: `docs/API_AJAX_ERROR_CONTRACT.md`
- Permissions: `docs/ROLE_MENU_PERMISSION_POLICY.md`
- Domain lifecycle: `docs/VISITOR_LIFECYCLE.md`
- Follow-up engine: `docs/FOLLOWUP_WORKFLOW_SYSTEM.md`
- Email: `docs/EMAIL_DELIVERABILITY.md`
- Observability: `docs/OBSERVABILITY_POLICY.md`
- Modularization: `docs/MODULARIZATION_CUTOVER_EXECUTION_PLAN.md`
- Seeders/migrations: `docs/SEEDERS.md`, `docs/SERVICE_MIGRATIONS.md`

Keep this index concise. Update an existing canonical document instead of adding a dated status snapshot when the information is operational policy.
