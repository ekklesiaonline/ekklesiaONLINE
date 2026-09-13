# Event Calendar Scope Plan (Province -> Diocese -> Church)

## Objective

Implement hierarchical event calendars so event visibility follows organizational scope:

1. Province events: visible to dioceses in that province.
1. Diocese events: visible to diocesan staff and all churches in that diocese.
1. Church events: visible to that church scope only (existing behavior).

This plan is designed for the current module boundaries:

- Province dashboard is summary oriented.
- Diocesan scope is operational for diocese plus church metrics.
- Church scope is operational events CRUD.

## Delivery Todo (Phased)

## Phase 1 - Data Model Foundation

- [ ] Create table event_calendars.
- [ ] Add scope fields to event_calendars:
- [ ] scope_level enum: province, diocese, church
- [ ] province_id nullable
- [ ] diocese_id nullable
- [ ] church_id nullable
- [ ] Add display fields to event_calendars:
- [ ] name
- [ ] color_hex
- [ ] is_active
- [ ] sort_order
- [ ] Add calendar_id to events table (nullable first, then required after backfill).
- [ ] Add indexes:
- [ ] events(calendar_id, start_at, status)
- [ ] event_calendars(scope_level, province_id, diocese_id, church_id, is_active)
- [ ] Add foreign keys with restrictive delete policy.

## Phase 2 - Backfill and Safe Migration

- [ ] Seed default calendars per scope:
- [ ] Province default calendar for each province.
- [ ] Diocese default calendar for each diocese.
- [ ] Church default calendar for each church.
- [ ] Backfill existing events with church default calendar by church_id.
- [ ] Switch events.calendar_id to not null.
- [ ] Add migration guard checks and rollback-safe down scripts.

## Phase 3 - Authorization and Policy

- [ ] Add permissions:
- [ ] events.calendars.view
- [ ] events.calendars.manage
- [ ] events.create.province
- [ ] events.create.diocese
- [ ] events.create.church (maps to existing events.create)
- [ ] Add policy service EventScopePolicy to validate:
- [ ] creator can create only allowed scope levels
- [ ] viewer can only access events visible to current operational scope
- [ ] editors can update only events within allowed scope
- [ ] Wire policy checks in controllers before queries and writes.

## Phase 4 - Query Layer and Visibility Engine

- [ ] Add EventVisibilityService with single source-of-truth query builder.
- [ ] For church scope, visible events are:
- [ ] church events where church_id equals operational church
- [ ] diocese events where diocese_id equals church diocese
- [ ] province events where province_id equals church province
- [ ] For diocesan scope, visible events are:
- [ ] diocese events for operational diocese
- [ ] province events for parent province
- [ ] optional church events aggregation by filter toggle
- [ ] For province scope, visible events are:
- [ ] province events for operational province
- [ ] optional diocesan events in province by filter toggle
- [ ] Return source scope metadata with each event for UI badges and filters.

## Phase 5 - Calendar Management UI

- [ ] Create calendar management page:
- [ ] list calendars with color swatches
- [ ] create, edit, activate or deactivate
- [ ] scope badge (Province, Diocese, Church)
- [ ] Add validation:
- [ ] unique calendar name within same scope
- [ ] valid color hex format
- [ ] at least one active default calendar per scope

## Phase 6 - Events CRUD and Month or Week Views

- [ ] Extend Events create and edit forms with calendar selector.
- [ ] Prevent selecting calendars outside current allowed scope.
- [ ] Add Month view and Week view tabs in events index.
- [ ] Add legend by calendar color.
- [ ] Add scope chips and calendar chips for filtering.
- [ ] Render event cards with calendar color and scope badge.
- [ ] Keep list view for operational workflows.

## Phase 7 - API Endpoints

- [ ] Add endpoint for calendar feed with range filters.
- [ ] Inputs:
- [ ] start
- [ ] end
- [ ] view mode month or week
- [ ] calendar_ids optional
- [ ] scope_levels optional
- [ ] Output includes:
- [ ] id, title, start_at, end_at
- [ ] calendar name and color
- [ ] source scope level and source entity id
- [ ] registration count and status if needed

## Phase 8 - Testing and Governance

- [ ] Unit tests:
- [ ] scope policy matrix
- [ ] visibility resolution matrix
- [ ] color and calendar validation
- [ ] Integration tests:
- [ ] church user sees own church plus diocese plus province events
- [ ] diocese user sees diocese plus province events
- [ ] province user visibility behavior
- [ ] Regression tests for existing events CRUD and registration flow.
- [ ] Performance checks on month range queries with indexes.

## Recommended Schema

## event_calendars

- id
- scope_level enum province, diocese, church
- province_id nullable
- diocese_id nullable
- church_id nullable
- name
- color_hex
- is_active
- sort_order
- created_by
- created_at
- updated_at

Constraints:

- Exactly one scope id must be populated matching scope_level.
- Unique name per exact scope key.

## events additions

- calendar_id not null after backfill
- visibility_source_level derived from calendar scope, not duplicated in events

## Visibility Rules (Canonical)

1. Province event visibility:

- visible to users operating within dioceses that belong to the same province.

1. Diocese event visibility:

- visible to diocesan staff of that diocese.
- visible to churches belonging to that diocese.

1. Church event visibility:

- visible only within that church scope unless explicitly shared in future phase.

## Routing and Module Ownership

- Keep events operations in Pastor module for church scope initially.
- Add diocesan event operations in Diocesan module for diocese scope events.
- Add province event operations in Province module for province scope events.
- Share EventVisibilityService and EventScopePolicy from app Services to avoid duplicated logic.

## Rollout Strategy

## Release A

- Data model, backfill, service layer, no UI switch yet.
- Existing list view continues to work with calendar_id defaulted.

## Release B

- Calendar management screens.
- Events create and edit calendar selector.
- Scope-aware visibility in list.

## Release C

- Month and week calendar views with color legends and filters.
- Hardened tests and performance guardrails.

## Risks and Mitigations

- Risk: Cross-scope leakage.
- Mitigation: central EventVisibilityService and policy checks in all endpoints.

- Risk: Migration lock on large events table.
- Mitigation: nullable add plus async backfill plus later not-null migration.

- Risk: Color accessibility issues.
- Mitigation: enforce contrast checks and add text or icon fallback markers.

## Immediate Next 10 Engineering Todos

1. Create migrations for event_calendars and events.calendar_id.
1. Add EventCalendarModel.
1. Seed default calendars for province, diocese, church.
1. Build backfill command for existing events.
1. Add EventVisibilityService.
1. Add EventScopePolicy.
1. Update EventModel queries to join calendar metadata.
1. Extend events create and edit forms with calendar select.
1. Add calendar filter in events index list.
1. Add integration tests for province and diocese visibility matrix.
