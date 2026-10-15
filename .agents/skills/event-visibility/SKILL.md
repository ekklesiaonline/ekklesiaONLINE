---
name: event-visibility
description: Explain, diagnose, test, or change event calendar scoping and EventVisibilityService rules
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
- `app/Services/EventVisibilityService.php`
- `app/Models/EventModel.php`
- `Clergy/Controllers/Events.php` and `Clergy/Controllers/EventCalendars.php`
- `app/Controllers/SystemEvents.php` and `app/Controllers/SystemEventCalendars.php`
- `tests/database/EventsControllerFeatureTest.php`
- `tests/database/EventsAudienceIntegrationTest.php`

Canonical parish-facing flow:
1. Resolve the operational `parish_id` and `diocese_id`.
2. Fetch candidates with identical scope semantics for list, calendar, and detail paths:
   - parish organizer: `organizer_parish_id` must equal the operational parish;
   - diocese organizer: `organizer_diocese_id` must equal the operational diocese.
3. For detail pages, use `EventModel::getEventWithCountsForParishAudience()`; do not use the legacy parish-only `getEventWithCounts()` and do not fetch globally by ID.
4. Apply `EventVisibilityService::filterVisibleEvents()` to collections or `isVisibleEvent()` to a direct lookup. The service remains responsible for publication state, publish time, audience membership, invitation, registration, household, team, and staff rules.
5. Keep system/national retrieval separate; `getEventWithCounts()` is still used by system-level flows.

Regression invariant:
- A published diocesan event hosted by Parish A must be listed and openable from Parish B in the same diocese when its audience permits access.
- A parish-only event from another parish and an event from another diocese must not be retrievable by direct URL.
- Run `vendor/bin/phpunit --no-coverage tests/database/EventsControllerFeatureTest.php tests/database/EventsAudienceIntegrationTest.php` after event visibility changes.

When asked:
1. Trace both candidate retrieval and `EventVisibilityService`; a listing/detail mismatch usually means the model query rejected the row before the policy service ran.
2. Explain public vs diocesan vs parish-only scope and any narrower audience restrictions.
3. Cite specific files and line ranges.
4. Note permission gates (`event.view`, `event.edit`, etc.; permission names are singular `event.*`).
