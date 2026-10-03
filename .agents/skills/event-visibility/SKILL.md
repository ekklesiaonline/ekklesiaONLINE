---
name: event-visibility
description: Explain event calendar scoping and EventVisibilityService rules
triggers:
  - user
  - model
allowed-tools:
  - read
  - grep
  - find_file_by_name
---

Help with event calendar visibility and scoping.

Key sources:
- `docs/EVENT_VISIBILITY_SERVICE_USAGE_SCAFFOLD.md`
- `EventVisibilityService` (search for the class/service)
- `EventCalendars` controller and related models
- `SystemEventCalendars` controller for system-level calendars

When asked:
1. Explain how events are scoped (public vs diocesan vs parish-only).
2. Reference `EventVisibilityService` and the controllers that use it.
3. Cite specific files and line ranges.
4. Note any permission gates (`events.view`, `events.edit`, etc.).
