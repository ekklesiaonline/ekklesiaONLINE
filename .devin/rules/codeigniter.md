---
description: CodeIgniter 4 + Shield coding conventions for ekklesiaRD
trigger: always_on
---

# CI4 / Shield Conventions

## Controllers & Auth

- Place Controllers in `app/Controllers/` or module namespaces (`Parish`, `Clergy`, `Diocesan`, `MemberPortal`).
- Use Shield’s `auth()->user()` to access the authenticated user.
- Protect routes with Shield filters (`session`, `permission:*`) and validate permissions inside controllers.
- Never bypass `pending_review` gating except via `BaseController::canBypassPendingReviewGate()`.

## Validation

- Use CI4’s `Validation` class for every form/POST endpoint.
- Return validation errors through the standard CI4 pattern or JSON for AJAX.

## Models & Casts

- Models live in `app/Models/` or module namespaces.
- Cast types must use the nullable prefix form: `?int`, `?string`, `?bool`, `?datetime`.
- Never use suffix casts like `int?` or `string?`.

## Code Generation

- Use `php spark` commands to generate controllers, models, migrations, seeders, and tests.
- Do not create CI4 boilerplate files by hand.

## Tests

- Write tests with `CIUnitTestCase`.
- Prefer `php spark make:test` and follow existing test conventions.
- Run tests before declaring a task complete.

## Queries

- Use Query Builder or prepared statements.
- Parish-scope queries by `parish_id`; elevated admins can cross scopes explicitly.
