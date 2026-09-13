# Event Form Expansion and Calendar Baseline Report (2026-04-11)

## Scope

This document records the implementation completed for the expanded Events creation and edit workflow before the broader province or diocese or church calendar rollout.

The work delivered:

1. Expanded event creation and edit form fields.
1. Baseline event calendars table and management page.
1. Event attachments support for images and documents.
1. Scheduling metadata for one-day, all-day, and multi-day events.
1. Recurrence metadata persistence.
1. Visibility, scope, and publish scheduling metadata.

## Functional Changes Implemented

## 1. Dynamic scheduling scenarios

The form now supports three scheduling modes:

- One Day Event
- All Day Event
- Multi Day Event

Behavior:

- One Day Event uses one date plus start and end times.
- All Day Event uses one date and hides time inputs.
- Multi Day Event uses separate start date and time and end date and time.

Controller behavior:

- Scheduling mode is normalized server-side.
- Invalid or reversed date ranges are rejected.
- All-day events are stored with day start and day end datetimes.

## 2. Recurring event metadata

The form now includes:

- Repeats: none, daily, weekly, every two weeks, monthly
- Weekday checkboxes for weekly and biweekly scenarios
- Repeat end date

Current implementation status:

- Recurrence configuration is persisted on the event record.
- Recurrence does not yet generate child occurrences or expanded calendar feed rows.
- This was intentionally implemented as metadata-first so the later month or week calendar phase can use it without another schema change.

## 3. Description plus attachments

The event form still uses a textarea for description, but now supports file uploads alongside it.

Supported attachment intent:

- Images
- Documents

Implementation behavior:

- Files are stored in `writable/uploads`
- Attachment metadata is stored in `event_attachments`
- Existing attachments can be removed during edit
- Attachments are shown on the event detail page

Important note:

- This is attachment support beside the description field, not inline rich-text embedding inside the textarea.
- Inline editor embedding can be added later if a WYSIWYG editor is selected.

## 4. Calendar dropdown plus admin link

The event form now includes:

- Calendar dropdown
- Add Calendar button when user can manage calendars

Baseline calendar management page added:

- `/event-calendars`

Current calendar scope support:

- Church calendars
- Diocese calendars

## 5. Visibility metadata

The form now persists event visibility as:

- people
- members
- public

Current implementation status:

- Visibility is stored and displayed on the event detail page.
- Public exposure and people or members enforcement across public or portal surfaces is not yet activated.
- This was implemented as persisted metadata for the next delivery phase.

## 6. Scope metadata

The form now persists scope as:

- church
- diocese

Current behavior:

- Diocese scope can be assigned only when diocesan scope is allowed.
- Church-scoped event behavior remains compatible with the current Pastor module flow.
- Church event listing now includes diocese-scoped events visible to the current diocese.

## 7. Registration toggle

The event form now uses clearer language:

- Allow people to register

This still maps to the existing registration requirement field and current registration flow remains intact.

## 8. Publish scheduling metadata

The form now includes:

- Publish At datetime

Current implementation status:

- Publish datetime is stored.
- It is displayed on the event detail page.
- Frontend or query-level publish gating for public or portal display is not yet enforced everywhere.

## Data Model Added

## New table: event_calendars

Purpose:

- Stores reusable event calendars for church and diocese scope.

Key fields:

- church_id
- diocese_id
- scope_level
- name
- color_hex
- is_active
- sort_order
- created_by

## New table: event_attachments

Purpose:

- Stores uploaded files attached to events.

Key fields:

- event_id
- original_name
- stored_name
- mime_type
- file_size
- attachment_type

## Events table extensions

Added fields:

- calendar_id
- scope_level
- scope_diocese_id
- visibility
- schedule_mode
- is_all_day
- publish_at
- recurrence_pattern
- recurrence_days_json
- recurrence_until

## MVC and Routing Added or Updated

## Controllers

Updated:

- `Pastor/Controllers/Events.php`

Added:

- `Pastor/Controllers/EventCalendars.php`

## Models

Updated:

- `app/Models/EventModel.php`

Added:

- `app/Models/EventCalendarModel.php`
- `app/Models/EventAttachmentModel.php`

## Views

Updated:

- `Pastor/Views/Events/create.php`
- `Pastor/Views/Events/edit.php`
- `Pastor/Views/Events/view.php`

Added:

- `Pastor/Views/Events/_form.php`
- `Pastor/Views/Events/calendars.php`

## Routes

Updated:

- `Pastor/Config/Routes.php`

Added route group:

- `/event-calendars`

## Migrations Added

Pending migrations created:

- `2026_04_11_010001_CreateEventCalendars`
- `2026_04_11_010002_AddEventPlanningFields`
- `2026_04_11_010003_CreateEventAttachments`

Important note:

- Migration filenames were intentionally renumbered to avoid collision with existing `2026_04_11_000001` through `000004` migrations already present in this environment.
- As of this report, these new migrations are pending and were not auto-applied.

## Validation Completed

- PHP lint passed for all changed controllers, models, views, routes, languages, and migrations.
- VS Code file diagnostics returned no errors for the main edited files.
- Migration status confirms the new migrations are uniquely pending with no version collision.

## Known Deliberate Limits

These items were intentionally prepared but not fully activated in this change:

- Recurrence expansion into generated occurrences
- Public publication gating using `publish_at`
- Public or members visibility enforcement outside current internal event surfaces
- Province-scoped event creation and visibility
- Full month or week color calendar rendering
- Advanced calendar CRUD beyond baseline create and list

## Recommended Next Step After Running Migrations

1. Run migrations to create calendars and attachment tables and add event metadata columns.
1. Verify event create and edit flow in church scope.
1. Verify diocesan staff can create diocese-scoped events and select diocese calendars.
1. Then move into the full calendar phase with month or week colored views and province or diocese or church visibility engine expansion.
