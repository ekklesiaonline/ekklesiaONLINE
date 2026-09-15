# Event Calendar Board and Scope Enforcement Report (2026-04-11)

## Scope Delivered

This phase completed three follow-on steps on top of the event form expansion baseline:

1. Applied the pending event schema migrations.
2. Tightened event management scope rules so view visibility is no longer the same as edit or delete authority.
3. Added a color-based month and week calendar board on top of the calendar and recurrence metadata already stored in the events module.

## Migrations Applied

The following migrations were executed successfully:

- `2026_04_11_010001_CreateEventCalendars`
- `2026_04_11_010002_AddEventPlanningFields`
- `2026_04_11_010003_CreateEventAttachments`

Outcome:

- `event_calendars` is now present.
- `event_attachments` is now present.
- `events` now includes the planning, scope, visibility, publish, and recurrence fields introduced in the previous phase.

## Scope Enforcement Changes

Previous risk:

- Diocese-visible events could be fetched through the same visibility path used for parish-scoped viewing.
- That made view access and management access too closely coupled.

Implemented split:

- View access now uses a dedicated event visibility path.
- Edit, update, and delete now use a stricter management path.

Current management rule:

- Parish-scoped events remain manageable by the current operational parish.
- Diocese-scoped events are only manageable when the user is allowed to assign diocesan scope and the event belongs to the current diocese.
- Parish users who can only view diocese-visible events no longer inherit edit or delete access from visibility alone.

Additional UI alignment:

- Event list action buttons now hide edit and delete options when the current user can view but cannot manage a given event.
- Event detail quick actions now only show the edit action when management access is allowed.

## Calendar Board Delivered

New route added:

- `/events/calendar`

Supported modes:

- Month view
- Week view

Delivered behavior:

- Events render with calendar color styling.
- Month view renders a full calendar grid.
- Week view renders day columns for the active week.
- Event cards display schedule time, calendar name, and scope.
- Multi-day events render across each day they overlap.
- All-day events render with an all-day label.

## Recurrence Handling in Calendar Board

This phase moved recurrence beyond metadata-only persistence for display purposes.

Implemented for board rendering:

- Non-recurring events render directly from stored datetimes.
- Daily recurrence expands across the visible range.
- Weekly recurrence expands using selected weekdays.
- Biweekly recurrence expands using weekday selection plus a two-week interval from the seed event week.
- Monthly recurrence expands on the original day-of-month.

Important constraint:

- Recurrence is expanded for calendar rendering in memory for the requested range.
- Recurring child rows are still not materialized as separate database records.

## Attachment Persistence Correction

During this phase, attachment persistence was corrected so manual `created_at` values are allowed through the attachment model field protection.

Outcome:

- Event attachment inserts can persist their created timestamp correctly while timestamp automation remains disabled for that table model.

## Files Updated In This Phase

Backend:

- `Pastor/Controllers/Events.php`
- `app/Models/EventModel.php`
- `app/Models/EventAttachmentModel.php`
- `Pastor/Config/Routes.php`

Views:

- `Pastor/Views/Events/index.php`
- `Pastor/Views/Events/view.php`
- `Pastor/Views/Events/calendars.php`
- `Pastor/Views/Events/calendar.php`

Language files:

- `app/Language/en/Events.php`
- `app/Language/es/Events.php`
- `app/Language/fr/Events.php`

## Validation Completed

Completed successfully:

- Database migrations applied cleanly.
- PHP lint passed for all touched files.
- Editor diagnostics reported no file errors.

## Smoke Test Status

Attempted:

- Authenticated HTTP smoke test for event create, edit, view, and month or week calendar rendering.

Current result:

- The local isolated server exposed an environment-level base URL mismatch.
- Login requests on the isolated smoke server still redirected to `http://ekklesiaRD.local`, which prevented a stable authenticated session on the temporary test host.
- Because of that environment mismatch, the smoke test could not be completed as a trustworthy end-to-end browser or curl validation in the current local harness.

Interpretation:

- This is not evidence of a controller or syntax failure.
- It is a local host or base URL configuration problem in the smoke environment.

## Recommended Follow-up

1. Run the authenticated smoke again in the primary local vhost once the local base URL resolves to the app root.
2. If diocesan event management needs a dedicated operational role, align `events.*` and `admin.diocese` permissions for at least one diocesan operations group.
3. Consider adding a dedicated event calendar smoke script under `build/` now that the route surface includes create, edit, view, and month or week board behavior.
