---
name: explain
description: Explain the ekklesiaRD project, a module, or a specific file
argument-hint: "[topic]"
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Explain the requested aspect of ekklesiaRD clearly and concisely.

Context sources:
- `README.md` for setup, tech stack, and project layout.
- `.github/ekklesiaRD_overview.md` for high-level project overview and ministry framework.
- `.github/copilot-instructions.md` for features, stack, schema, and conventions.
- `docs/DOCUMENTATION_CANONICAL.md` for the canonical documentation set.
- `AGENTS.md` for current architecture and domain rules.
- `docs/MIGRATIONS_AND_SEEDERS.md` for migration status.

Guidelines:
1. State what the topic is and which module(s) it belongs to.
2. Cite specific files and line ranges using `<ref_file ... />` and `<ref_snippet ... />`.
3. If the topic involves data flow, trace the controller → service → model path.
4. Mention related rules/skills when relevant (`views.md`, `parish-domain.md`, `codeigniter.md`, etc.).
5. Note any known gaps or pending implementation.

Do not make up file paths. Verify with `read`, `grep`, or `find_file_by_name` before citing.
