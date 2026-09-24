<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;
use Config\Services;
use DateTimeImmutable;

class Events extends BaseController
{
    protected $eventModel;
    protected $eventRegistrationModel;
    protected $memberParishAffiliationModel;
    protected $eventCalendarModel;
    protected $eventAttachmentModel;
    protected $eventVisibilityService;
    protected $teamModel;
    protected $familyModel;
    protected $eventInviteeModel;

    public function __construct()
    {
        $this->eventModel = model('EventModel');
        $this->eventRegistrationModel = model('EventRegistrationModel');
        $this->memberParishAffiliationModel = model('MemberParishAffiliationModel');
        $this->eventCalendarModel = model('EventCalendarModel');
        $this->eventAttachmentModel = model('EventAttachmentModel');
        $this->eventVisibilityService = Services::eventVisibility();
        $this->teamModel = model('TeamModel');
        $this->familyModel = model('FamilyModel');
        $this->eventInviteeModel = model('EventInviteeModel');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('event.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        \App\Services\EventPublishService::publishDueDraftEvents();

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = $this->resolveCurrentDioceseId();
        $status = trim((string) ($this->request->getGet('status') ?? ''));
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        $events = $this->eventModel->getParishEventsWithCounts($parishId, $dioceseId, $status, $search);
        $events = $this->eventVisibilityService->filterVisibleEvents($events, $this->getEventVisibilityContext());
        $canEditEvents = auth()->user()->can('event.edit');
        $canDeleteEvents = auth()->user()->can('event.delete');

        foreach ($events as &$event) {
            $canManageEvent = $this->canManageEvent($event);
            $event['can_manage'] = $canEditEvents && $canManageEvent;
            $event['can_delete'] = $canDeleteEvents && $canManageEvent;
        }
        unset($event);

        return view('Clergy\Views\Events/index', array_merge($this->getCommonViewData(lang('Events.pageTitleEvents')), [
            'events' => $events,
            'status' => $status,
            'search' => $search,
        ]));
    }

    public function calendar()
    {
        if ($redirect = $this->requirePermission('event.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        \App\Services\EventPublishService::publishDueDraftEvents();

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = $this->resolveCurrentDioceseId();
        $mode = trim((string) ($this->request->getGet('mode') ?? 'month'));
        $mode = in_array($mode, ['month', 'week', 'agenda'], true) ? $mode : 'month';
        $anchorInput = trim((string) ($this->request->getGet('date') ?? 'today'));

        try {
            $anchorDate = new DateTimeImmutable($anchorInput);
        } catch (\Throwable $e) {
            $anchorDate = new DateTimeImmutable('today');
        }

        $anchorDate = $anchorDate->setTime(0, 0, 0);
        $isWeekMode = $mode === 'week';
        $isMonthMode = $mode === 'month';

        $periodStart = $isWeekMode
            ? $this->startOfWeek($anchorDate)
            : $anchorDate->modify('first day of this month')->setTime(0, 0, 0);
        $periodEnd = $isWeekMode
            ? $this->endOfWeek($anchorDate)
            : $anchorDate->modify('last day of this month')->setTime(23, 59, 59);
        $gridStart = $isWeekMode ? $periodStart : ($isMonthMode ? $this->startOfWeek($periodStart) : $periodStart);
        $gridEnd = $isWeekMode ? $periodEnd : ($isMonthMode ? $this->endOfWeek($periodEnd) : $periodEnd);

        $events = $this->eventModel->getVisibleEventsForRange(
            $parishId,
            $dioceseId,
            $gridStart->format('Y-m-d H:i:s'),
            $gridEnd->format('Y-m-d H:i:s')
        );
        $events = $this->eventVisibilityService->filterVisibleEvents($events, $this->getEventVisibilityContext());
        $occurrences = $this->buildCalendarOccurrences($events, $gridStart, $gridEnd);
        $dayBuckets = $this->buildCalendarDayBuckets($gridStart, $gridEnd, $occurrences, $isMonthMode ? $periodStart->format('Y-m') : null);

        $monthWeeks = [];
        if ($isMonthMode) {
            $week = [];
            foreach ($dayBuckets as $dayBucket) {
                $week[] = $dayBucket;
                if (count($week) === 7) {
                    $monthWeeks[] = $week;
                    $week = [];
                }
            }
        }

        $agendaRows = $mode === 'agenda' ? $this->buildCalendarAgendaRows($dayBuckets) : [];

        $prevDate = $isWeekMode
            ? $anchorDate->modify('-7 days')
            : $anchorDate->modify('first day of previous month');
        $nextDate = $isWeekMode
            ? $anchorDate->modify('+7 days')
            : $anchorDate->modify('first day of next month');

        return view('Clergy\Views\Events/calendar', array_merge($this->getCommonViewData(lang('Events.pageTitleCalendarBoard')), [
            'calendar_mode' => $mode,
            'anchor_date' => $anchorDate->format('Y-m-d'),
            'period_label' => $this->formatCalendarPeriodLabel($mode, $periodStart, $periodEnd),
            'prev_date' => $prevDate->format('Y-m-d'),
            'next_date' => $nextDate->format('Y-m-d'),
            'month_weeks' => $monthWeeks,
            'week_days' => $isWeekMode ? array_values($dayBuckets) : [],
            'agenda_rows' => $agendaRows,
            'calendar_legend' => $this->buildCalendarLegend($occurrences),
            'can_manage_calendars' => $this->canManageCalendars(),
        ]));
    }

    public function view($id)
    {
        if ($redirect = $this->requirePermission('event.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        \App\Services\EventPublishService::publishDueDraftEvents();

        $event = $this->getViewableParishEvent((int) $id);
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        $members = $this->memberParishAffiliationModel->getParishMembers((int) ($this->getOperationalParishId() ?? 0), 'active');
        $registrations = $this->eventRegistrationModel->getEventRegistrations((int) $id);

        return view('Clergy\Views\Events/view', array_merge($this->getCommonViewData(lang('Events.pageTitleEventDetails')), [
            'event' => $event,
            'members' => $members,
            'registrations' => $registrations,
            'attachments' => $this->eventAttachmentModel->getEventAttachments((int) $id),
            'can_manage_event' => auth()->user()->can('event.edit') && $this->canManageEvent($event),
        ]));
    }

    public function create()
    {
        if ($redirect = $this->requirePermission('event.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Clergy\Views\Events/create', array_merge($this->getCommonViewData(lang('Events.pageTitleCreateEvent')), [
            'calendar_options' => $this->getAssignableCalendars(),
            'can_manage_calendars' => $this->canManageCalendars(),
            'can_assign_diocese_scope' => $this->canAssignDioceseScope(),
            'audience_members' => $this->getAudienceMembers(),
            'audience_teams' => $this->getAudienceTeams(),
            'audience_families' => $this->getAudienceFamilies(),
            'selected_invitee_ids' => [],
        ]));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('event.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = $this->getEventValidationRules();

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $payload = $this->buildEventPayload();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $payload['parish_id'] = (int) $this->getOperationalParishId();
        $payload['created_by'] = (int) auth()->id();

        if ($this->eventModel->insert($payload)) {
            $eventId = (int) $this->eventModel->getInsertID();

            try {
                $coverPath = \App\Services\EventCoverUploadService::handleUpload($eventId, null, $this->request->getFile('cover'), (string) ($this->request->getPost('remove_cover') ?? '') === '1');
                if ($coverPath !== '') {
                    $this->eventModel->update($eventId, ['cover_path' => $coverPath]);
                }
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }

            $this->syncEventInvitees($eventId, (string) ($payload['visibility'] ?? 'members'));
            $this->handleEventAttachments($eventId);

            return redirect()->to('/events')->with('success', lang('Members.eventCreated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Members.failedEvent'));
    }

    public function edit($id)
    {
        if ($redirect = $this->requirePermission('event.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $event = $this->getManageableParishEvent((int) $id);
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        return view('Clergy\Views\Events/edit', array_merge($this->getCommonViewData(lang('Events.pageTitleEditEvent')), [
            'event' => $event,
            'calendar_options' => $this->getAssignableCalendars(),
            'attachments' => $this->eventAttachmentModel->getEventAttachments((int) $id),
            'can_manage_calendars' => $this->canManageCalendars(),
            'can_assign_diocese_scope' => $this->canAssignDioceseScope(),
            'audience_members' => $this->getAudienceMembers(),
            'audience_teams' => $this->getAudienceTeams(),
            'audience_families' => $this->getAudienceFamilies(),
            'selected_invitee_ids' => $this->eventInviteeModel->getInviteeUserIdsForEvent((int) $id),
        ]));
    }

    public function update($id)
    {
        if ($redirect = $this->requirePermission('event.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $event = $this->getManageableParishEvent((int) $id);
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        $rules = $this->getEventValidationRules();

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $payload = $this->buildEventPayload();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        if ($this->eventModel->update((int) $id, $payload)) {
            try {
                $coverPath = \App\Services\EventCoverUploadService::handleUpload((int) $id, (string) ($event['cover_path'] ?? ''), $this->request->getFile('cover'), (string) ($this->request->getPost('remove_cover') ?? '') === '1');
                if ($coverPath !== (string) ($event['cover_path'] ?? '')) {
                    $this->eventModel->update((int) $id, ['cover_path' => $coverPath !== '' ? $coverPath : null]);
                }
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }

            $this->syncEventInvitees((int) $id, (string) ($payload['visibility'] ?? 'members'));
            $this->removeSelectedAttachments((int) $id);
            $this->handleEventAttachments((int) $id);

            return redirect()->to('/events/view/' . (int) $id)->with('success', lang('Members.eventUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Members.failedUpdateEvent'));
    }

    public function delete($id)
    {
        if ($redirect = $this->requirePermission('event.delete')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $event = $this->getManageableParishEvent((int) $id);
        if ($event === null) {
            return $this->jsonResponse(['success' => false, 'message' => 'Event not found'], 404);
        }

        $reason = trim((string) ($this->request->getPost('delete_reason') ?? ''));
        if (strlen($reason) < 8) {
            return $this->jsonResponse(['success' => false, 'message' => 'Delete reason is required and must be at least 8 characters.'], 422);
        }

        if ($this->eventModel->delete((int) $id)) {
            \App\Services\EventCoverUploadService::removeCoverFile((string) ($event['cover_path'] ?? ''), (int) $id);
            $this->removeAllEventAttachments((int) $id);

            // Log the deletion
            $auditLogModel = model('AuditLogModel');
            $auditLogModel->insert([
                'user_id' => auth()->id(),
                'parish_id' => $event['parish_id'] ?? null,
                'action' => 'event.delete',
                'entity_type' => 'event',
                'entity_id' => (int) $id,
                'old_values' => json_encode($event),
                'new_values' => json_encode(['deleted' => true, 'reason' => $reason]),
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => (string) $this->request->getUserAgent(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return $this->jsonResponse(['success' => true, 'message' => lang('Events.errorDeleteMessage')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Events.errorDeleteFailed')], 500);
    }

    public function register($id)
    {
        if ($redirect = $this->requirePermission('event.register')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $event = $this->getViewableParishEvent((int) $id);
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        if ((int) ($event['requires_registration'] ?? 1) !== 1) {
            return redirect()->to('/events/view/' . (int) $id)->with('error', lang('Members.registrationNotRequired'));
        }

        $memberUserId = (int) ($this->request->getPost('user_id') ?? 0);
        $guestName = trim((string) ($this->request->getPost('guest_name') ?? ''));

        if ($memberUserId <= 0 && $guestName === '') {
            return redirect()->back()->withInput()->with('error', lang('Members.selectMemberOrVisitor'));
        }

        if ($memberUserId > 0) {
            $isAffiliated = $this->memberParishAffiliationModel
                ->isUserAffiliatedWithParish($memberUserId, (int) ($this->getOperationalParishId() ?? 0), true);

            if (!$isAffiliated) {
                return redirect()->back()->withInput()->with('error', lang('Members.memberOutsideScope'));
            }

            if ($this->eventRegistrationModel->registrationExistsForMember((int) $id, $memberUserId)) {
                return redirect()->back()->withInput()->with('error', lang('Members.alreadyRegistered'));
            }
        }

        if ($this->isEventAtCapacity($event)) {
            return redirect()->back()->withInput()->with('error', lang('Members.capacityReached'));
        }

        $payload = [
            'event_id' => (int) $id,
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'user_id' => $memberUserId > 0 ? $memberUserId : null,
            'guest_name' => $memberUserId > 0 ? null : $guestName,
            'guest_email' => $memberUserId > 0 ? null : trim((string) ($this->request->getPost('guest_email') ?? '')),
            'guest_phone' => $memberUserId > 0 ? null : trim((string) ($this->request->getPost('guest_phone') ?? '')),
            'status' => 'registered',
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if ($this->eventRegistrationModel->insert($payload)) {
            return redirect()->to('/events/view/' . (int) $id)->with('success', lang('Members.registrationSaved'));
        }

        return redirect()->back()->withInput()->with('error', lang('Members.failedSaveRegistration'));
    }

    public function updateRegistrationStatus($registrationId)
    {
        if ($redirect = $this->requirePermission('event.checkin')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $registration = $this->eventRegistrationModel->find((int) $registrationId);
        if (!is_array($registration)) {
            return redirect()->to('/events')->with('error', lang('Members.registrationNotFound'));
        }

        $event = $this->getViewableParishEvent((int) ($registration['event_id'] ?? 0));
        if ($event === null) {
            return redirect()->to('/events')->with('error', lang('Members.eventNotFound'));
        }

        if (!$this->canManageRegistrationForEvent($event, (int) ($registration['parish_id'] ?? 0))) {
            return redirect()->to('/events')->with('error', lang('Members.unauthorizedAction'));
        }

        $status = trim((string) ($this->request->getPost('status') ?? ''));
        if (!in_array($status, ['registered', 'checked_in', 'cancelled'], true)) {
            return redirect()->back()->with('error', lang('Members.invalidStatus'));
        }

        if ($status === 'checked_in' && $this->isEventAtCapacity($event, (int) $registrationId)) {
            return redirect()->back()->with('error', lang('Members.cannotCheckIn'));
        }

        if ($this->eventRegistrationModel->update((int) $registrationId, ['status' => $status])) {
            return redirect()->to('/events/view/' . (int) ($registration['event_id'] ?? 0))->with('success', lang('Members.registrationUpdated'));
        }

        return redirect()->back()->with('error', lang('Members.failedUpdateStatus'));
    }

    private function getViewableParishEvent(int $eventId): ?array
    {
        $event = $this->eventModel->getEventWithCounts($eventId);

        if (!is_array($event)) {
            return null;
        }

        if (!$this->eventVisibilityService->isVisibleEvent($event, $this->getEventVisibilityContext())) {
            return null;
        }

        return $event;
    }

    private function getManageableParishEvent(int $eventId): ?array
    {
        $event = $this->getViewableParishEvent($eventId);

        if ($event === null || !$this->canManageEvent($event)) {
            return null;
        }

        return $event;
    }

    private function canManageEvent(array $event): bool
    {
        return $this->eventVisibilityService->canManageEvent($event, $this->getEventVisibilityContext());
    }

    private function canManageRegistrationForEvent(array $event, int $registrationParishId): bool
    {
        return $this->eventVisibilityService->canManageRegistrationForEvent($event, $registrationParishId, $this->getEventVisibilityContext());
    }

    /**
     * @return array<string,mixed>
     */
    private function getEventVisibilityContext(): array
    {
        return [
            'user_id' => (int) (auth()->id() ?? 0),
            'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
            'diocese_id' => $this->resolveCurrentDioceseId(),
            'can_edit_events' => auth()->user()->can('event.edit'),
            'can_manage_diocese_scope' => $this->canAssignDioceseScope(),
        ];
    }

    private function syncEventInvitees(int $eventId, string $visibility): void
    {
        $inviteeIds = $visibility === 'invited'
            ? (array) ($this->request->getPost('invited_user_ids') ?? [])
            : [];

        $this->eventInviteeModel->syncInvitees($eventId, array_map('intval', $inviteeIds));
    }

    private function getAudienceMembers(): array
    {
        return $this->memberParishAffiliationModel->getParishMembers((int) ($this->getOperationalParishId() ?? 0));
    }

    private function getAudienceTeams(): array
    {
        return $this->teamModel->getParishTeams((int) ($this->getOperationalParishId() ?? 0));
    }

    private function getAudienceFamilies(): array
    {
        return $this->familyModel->getParishFamilies((int) ($this->getOperationalParishId() ?? 0));
    }

    private function buildEventPayload(): array
    {
        $schedule = \App\Services\EventScheduleService::resolveSchedulePayload($this->request->getPost() ?? []);
        $capacity = (int) ($this->request->getPost('capacity') ?? 0);
        $scopeLevel = trim((string) ($this->request->getPost('scope_level') ?? 'parish'));
        if ($scopeLevel !== 'diocese' || !$this->canAssignDioceseScope()) {
            $scopeLevel = 'parish';
        }

        $calendarId = (int) ($this->request->getPost('calendar_id') ?? 0);
        $calendar = $calendarId > 0 ? $this->eventCalendarModel->find($calendarId) : null;
        if (!is_array($calendar)) {
            throw new \RuntimeException(lang('Events.errorCalendarRequired'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = $this->resolveCurrentDioceseId();
        if (!$this->isCalendarAllowedForScope($calendar, $scopeLevel, $parishId, $dioceseId)) {
            throw new \RuntimeException(lang('Events.errorCalendarScopeMismatch'));
        }

        $recurrencePattern = trim((string) ($this->request->getPost('recurrence_pattern') ?? 'none'));
        if (!in_array($recurrencePattern, ['none', 'daily', 'weekly', 'biweekly', 'monthly'], true)) {
            $recurrencePattern = 'none';
        }

        $recurrenceUntil = trim((string) ($this->request->getPost('recurrence_until') ?? ''));
        if ($recurrencePattern !== 'none' && $recurrenceUntil === '') {
            throw new \RuntimeException(lang('Events.errorRepeatEndDateRequired'));
        }

        $recurrenceDays = \App\Services\EventScheduleService::normalizeRecurrenceDays((array) ($this->request->getPost('recurrence_days') ?? []), (string) $schedule['start_at']);
        $visibility = trim((string) ($this->request->getPost('visibility') ?? 'members'));
        if ($visibility === 'people' || !in_array($visibility, ['members', 'public', 'staff', 'volunteers', 'invited', 'registered_only', 'household'], true)) {
            $visibility = 'members';
        }

        $audienceTeamId = (int) ($this->request->getPost('audience_team_id') ?? 0);
        $audienceFamilyId = (int) ($this->request->getPost('audience_family_id') ?? 0);

        if ($visibility === 'volunteers') {
            $team = $audienceTeamId > 0 ? $this->teamModel->find($audienceTeamId) : null;
            if (!is_array($team) || (int) ($team['parish_id'] ?? 0) !== $parishId) {
                throw new \RuntimeException(lang('Events.errorAudienceTeamRequired'));
            }
        } else {
            $audienceTeamId = 0;
        }

        if ($visibility === 'household') {
            $family = $audienceFamilyId > 0 ? $this->familyModel->find($audienceFamilyId) : null;
            if (!is_array($family) || (int) ($family['parish_id'] ?? 0) !== $parishId) {
                throw new \RuntimeException(lang('Events.errorAudienceFamilyRequired'));
            }
        } else {
            $audienceFamilyId = 0;
        }

        if ($visibility === 'invited') {
            $inviteeIds = array_values(array_filter(array_map('intval', (array) ($this->request->getPost('invited_user_ids') ?? [])), static fn(int $id): bool => $id > 0));
            if ($inviteeIds === []) {
                throw new \RuntimeException(lang('Events.errorInviteesRequired'));
            }
        }

        return [
            'title' => trim((string) ($this->request->getPost('title') ?? '')),
            'description' => trim((string) ($this->request->getPost('description') ?? '')),
            'calendar_id' => $calendarId,
            // Organizer abstraction
            'organizer_type' => $scopeLevel,
            'organizer_parish_id' => null,
            'organizer_province_id' => null,
            'organizer_diocese_id' => $scopeLevel === 'diocese' && $dioceseId > 0 ? $dioceseId : null,
            'organizer_parish_id' => $scopeLevel === 'parish' && $parishId > 0 ? $parishId : null,
            'host_parish_id' => $parishId > 0 ? $parishId : null,
            // Legacy dual-write columns (kept in sync by EventModel callbacks)
            'scope_level' => $scopeLevel,
            'scope_diocese_id' => $scopeLevel === 'diocese' && $dioceseId > 0 ? $dioceseId : null,
            'event_type' => trim((string) ($this->request->getPost('event_type') ?? '')) ?: 'general',
            'visibility' => $visibility,
            'audience_team_id' => $audienceTeamId > 0 ? $audienceTeamId : null,
            'audience_family_id' => $audienceFamilyId > 0 ? $audienceFamilyId : null,
            'schedule_mode' => (string) $schedule['schedule_mode'],
            'is_all_day' => (int) $schedule['is_all_day'],
            'location' => trim((string) ($this->request->getPost('location') ?? '')),
            'start_at' => (string) $schedule['start_at'],
            'end_at' => $schedule['end_at'] !== '' ? (string) $schedule['end_at'] : null,
            'capacity' => $capacity > 0 ? $capacity : null,
            'requires_registration' => $this->request->getPost('requires_registration') ? 1 : 0,
            'status' => trim((string) ($this->request->getPost('status') ?? 'published')),
            'publish_at' => ($publishAt = trim((string) ($this->request->getPost('publish_at') ?? ''))) !== '' ? date('Y-m-d H:i:s', (int) strtotime($publishAt)) : null,
            'recurrence_pattern' => $recurrencePattern,
            'recurrence_days_json' => $recurrencePattern !== 'none' ? json_encode($recurrenceDays) : null,
            'recurrence_until' => $recurrencePattern !== 'none' ? $recurrenceUntil : null,
        ];
    }

    private function getEventValidationRules(): array
    {
        return [
            'title' => 'required|min_length[3]|max_length[255]',
            'event_type' => 'permit_empty|max_length[100]',
            'location' => 'permit_empty|max_length[255]',
            'capacity' => 'permit_empty|integer|greater_than[0]',
            'status' => 'required|in_list[draft,published,cancelled,completed]',
            'calendar_id' => 'required|integer',
            'visibility' => 'required|in_list[members,public,staff,volunteers,invited,registered_only,household]',
            'audience_team_id' => 'permit_empty|integer',
            'audience_family_id' => 'permit_empty|integer',
            'scope_level' => 'required|in_list[parish,diocese]',
            'schedule_mode' => 'required|in_list[one_day,all_day,multi_day]',
            'publish_at' => 'permit_empty',
            'recurrence_pattern' => 'permit_empty|in_list[none,daily,weekly,biweekly,monthly]',
            'cover' => 'permit_empty',
        ];
    }

    private function getAssignableCalendars(): array
    {
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = $this->resolveCurrentDioceseId();

        return $this->eventCalendarModel->getAssignableCalendars($parishId, $dioceseId, $this->canAssignDioceseScope());
    }

    private function canManageCalendars(): bool
    {
        return $this->isDioceseAdmin() || auth()->user()->can('event.edit');
    }

    private function canAssignDioceseScope(): bool
    {
        return $this->isDioceseAdmin() || auth()->user()->can('diocese.admin');
    }

    private function resolveCurrentDioceseId(): int
    {
        $dioceseId = (int) $this->resolveDiocesanScopeId();
        if ($dioceseId > 0) {
            return $dioceseId;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return 0;
        }

        $parish = model('ParishModel')->find($parishId);

        return (int) ($parish['diocese_id'] ?? 0);
    }

    private function isCalendarAllowedForScope(array $calendar, string $scopeLevel, int $parishId, int $dioceseId): bool
    {
        if ($scopeLevel === 'parish') {
            return (string) ($calendar['scope_level'] ?? '') === 'parish'
                && (int) ($calendar['parish_id'] ?? 0) === $parishId;
        }

        return (string) ($calendar['scope_level'] ?? '') === 'diocese'
            && (int) ($calendar['diocese_id'] ?? 0) === $dioceseId;
    }

    private function handleEventAttachments(int $eventId): void
    {
        $files = $this->request->getFileMultiple('attachments');
        if (!is_array($files)) {
            return;
        }

        foreach ($files as $file) {
            if ($file === null || !$file->isValid() || $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $mimeType = (string) $file->getMimeType();
            $isImage = str_starts_with($mimeType, 'image/');
            $allowedMimeTypes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
            ];

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                continue;
            }

            $relativeDir = $isImage
                ? \App\Services\UploadPathService::attachmentImageRelativeDir('event', $eventId)
                : \App\Services\UploadPathService::attachmentDocumentRelativeDir('event', $eventId);
            $targetDir = \App\Services\UploadPathService::absoluteUploadDir($relativeDir);
            if ($targetDir === null) {
                continue;
            }

            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            $storedName = $file->getRandomName();
            $file->move($targetDir, $storedName, true);

            $this->eventAttachmentModel->insert([
                'event_id' => $eventId,
                'original_name' => $file->getClientName(),
                'stored_name' => $relativeDir . '/' . $storedName,
                'mime_type' => $mimeType,
                'file_size' => (int) $file->getSize(),
                'attachment_type' => $isImage ? 'image' : 'document',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function removeSelectedAttachments(int $eventId): void
    {
        $attachmentIds = array_map('intval', (array) ($this->request->getPost('remove_attachment_ids') ?? []));
        if ($attachmentIds === []) {
            return;
        }

        // Scope the delete to attachments owned by the event being edited,
        // so a user cannot remove attachments belonging to a different event
        // by submitting foreign attachment IDs.
        $attachments = $this->eventAttachmentModel
            ->whereIn('id', $attachmentIds)
            ->where('event_id', $eventId)
            ->findAll();
        foreach ($attachments as $attachment) {
            $path = $this->resolveStoredUploadAbsolutePath((string) ($attachment['stored_name'] ?? ''), (int) ($attachment['event_id'] ?? 0));
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
            $this->eventAttachmentModel->delete((int) $attachment['id']);
        }
    }

    private function removeAllEventAttachments(int $eventId): void
    {
        $attachments = $this->eventAttachmentModel->where('event_id', $eventId)->findAll();
        foreach ($attachments as $attachment) {
            $path = $this->resolveStoredUploadAbsolutePath((string) ($attachment['stored_name'] ?? ''), $eventId);
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
            $this->eventAttachmentModel->delete((int) $attachment['id']);
        }
    }

    private function resolveStoredUploadAbsolutePath(string $storedPath, int $eventId): ?string
    {
        $normalized = str_replace('\\', '/', trim($storedPath));
        $normalized = ltrim($normalized, '/');
        if ($normalized === '' || str_contains($normalized, '../') || str_starts_with($normalized, '..')) {
            return null;
        }

        $uploadsRoot = realpath(rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads');
        if ($uploadsRoot === false) {
            return null;
        }

        $candidates = [$normalized];
        if (!str_starts_with($normalized, 'attachment/event/')) {
            $candidates[] = 'attachment/event/' . $eventId . '/image/' . $normalized;
            $candidates[] = 'attachment/event/' . $eventId . '/document/' . $normalized;
            $candidates[] = 'events/' . $eventId . '/' . $normalized;
        }

        foreach ($candidates as $candidate) {
            $fullPath = realpath($uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $candidate));
            if ($fullPath === false || !is_file($fullPath)) {
                continue;
            }

            $uploadsRootPrefix = rtrim($uploadsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (str_starts_with($fullPath, $uploadsRootPrefix)) {
                return $fullPath;
            }
        }

        return null;
    }

    private function isEventAtCapacity(array $event, int $excludeRegistrationId = 0): bool
    {
        $capacity = (int) ($event['capacity'] ?? 0);
        if ($capacity <= 0) {
            return false;
        }

        $builder = $this->eventRegistrationModel
            ->where('event_id', (int) ($event['id'] ?? 0))
            ->whereIn('status', ['registered', 'checked_in']);

        if ($excludeRegistrationId > 0) {
            $builder->where('id !=', $excludeRegistrationId);
        }

        $currentCount = $builder->countAllResults();

        return $currentCount >= $capacity;
    }

    private function startOfWeek(DateTimeImmutable $date): DateTimeImmutable
    {
        $daysToSubtract = max(0, (int) $date->format('N') - 1);

        return $date->modify('-' . $daysToSubtract . ' days')->setTime(0, 0, 0);
    }

    private function endOfWeek(DateTimeImmutable $date): DateTimeImmutable
    {
        return $this->startOfWeek($date)->modify('+6 days')->setTime(23, 59, 59);
    }

    private function buildCalendarOccurrences(array $events, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];

        foreach ($events as $event) {
            foreach ($this->expandEventOccurrences($event, $rangeStart, $rangeEnd) as $occurrence) {
                $occurrence['can_manage'] = auth()->user()->can('event.edit') && $this->canManageEvent($event);
                $occurrences[] = $occurrence;
            }
        }

        usort($occurrences, static function (array $left, array $right): int {
            return [$left['start_at'], $left['title'], $left['id']] <=> [$right['start_at'], $right['title'], $right['id']];
        });

        return $occurrences;
    }

    private function expandEventOccurrences(array $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $startValue = trim((string) ($event['start_at'] ?? ''));
        if ($startValue === '') {
            return [];
        }

        $baseStart = new DateTimeImmutable($startValue);
        $baseEndValue = trim((string) ($event['end_at'] ?? ''));
        $baseEnd = $baseEndValue !== '' ? new DateTimeImmutable($baseEndValue) : $baseStart;
        $duration = max(0, $baseEnd->getTimestamp() - $baseStart->getTimestamp());
        $pattern = (string) ($event['recurrence_pattern'] ?? 'none');
        $occurrences = [];

        if ($pattern === 'none') {
            $occurrenceEnd = $baseStart->modify('+' . $duration . ' seconds');
            if ($occurrenceEnd >= $rangeStart && $baseStart <= $rangeEnd) {
                $occurrences[] = $this->makeOccurrencePayload($event, $baseStart, $occurrenceEnd);
            }

            return $occurrences;
        }

        $baseDate = $baseStart->setTime(0, 0, 0);
        $cursor = $baseDate > $rangeStart->setTime(0, 0, 0) ? $baseDate : $rangeStart->setTime(0, 0, 0);
        $limit = $rangeEnd->setTime(23, 59, 59);
        $untilValue = trim((string) ($event['recurrence_until'] ?? ''));
        if ($untilValue !== '') {
            $until = new DateTimeImmutable($untilValue . ' 23:59:59');
            if ($until < $limit) {
                $limit = $until;
            }
        }

        if ($cursor > $limit) {
            return [];
        }

        $recurrenceDays = json_decode((string) ($event['recurrence_days_json'] ?? '[]'), true);
        if (!is_array($recurrenceDays)) {
            $recurrenceDays = [];
        }

        while ($cursor <= $limit) {
            if ($this->doesRecurrenceMatchDate($pattern, $recurrenceDays, $baseStart, $cursor)) {
                $occurrenceStart = $cursor->setTime(
                    (int) $baseStart->format('H'),
                    (int) $baseStart->format('i'),
                    (int) $baseStart->format('s')
                );
                $occurrenceEnd = $occurrenceStart->modify('+' . $duration . ' seconds');

                if ($occurrenceEnd >= $rangeStart && $occurrenceStart <= $rangeEnd) {
                    $occurrences[] = $this->makeOccurrencePayload($event, $occurrenceStart, $occurrenceEnd);
                }
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $occurrences;
    }

    private function doesRecurrenceMatchDate(string $pattern, array $recurrenceDays, DateTimeImmutable $baseStart, DateTimeImmutable $date): bool
    {
        if ($date < $baseStart->setTime(0, 0, 0)) {
            return false;
        }

        $weekday = strtolower(substr($date->format('D'), 0, 3));
        $selectedDays = $recurrenceDays !== [] ? $recurrenceDays : [strtolower(substr($baseStart->format('D'), 0, 3))];

        return match ($pattern) {
            'daily' => true,
            'weekly' => in_array($weekday, $selectedDays, true),
            'biweekly' => in_array($weekday, $selectedDays, true)
                && (((int) floor($this->startOfWeek($baseStart)->diff($this->startOfWeek($date))->days / 7)) % 2 === 0),
            'monthly' => $date->format('j') === $baseStart->format('j'),
            default => false,
        };
    }

    private function makeOccurrencePayload(array $event, DateTimeImmutable $startAt, DateTimeImmutable $endAt): array
    {
        return [
            'id' => (int) ($event['id'] ?? 0),
            'title' => (string) ($event['title'] ?? ''),
            'status' => (string) ($event['status'] ?? 'published'),
            'scope_level' => (string) ($event['scope_level'] ?? 'parish'),
            'calendar_name' => (string) ($event['calendar_name'] ?? ''),
            'calendar_color' => (string) ($event['calendar_color'] ?? '#0d6efd'),
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'start_date' => $startAt->format('Y-m-d'),
            'end_date' => $endAt->format('Y-m-d'),
            'is_all_day' => (int) ($event['is_all_day'] ?? 0),
            'view_url' => base_url('events/view/' . (int) ($event['id'] ?? 0)),
            'edit_url' => base_url('events/edit/' . (int) ($event['id'] ?? 0)),
        ];
    }

    private function buildCalendarDayBuckets(DateTimeImmutable $gridStart, DateTimeImmutable $gridEnd, array $occurrences, ?string $monthContext = null): array
    {
        $days = [];
        $today = date('Y-m-d');

        for ($cursor = $gridStart; $cursor <= $gridEnd; $cursor = $cursor->modify('+1 day')) {
            $dateKey = $cursor->format('Y-m-d');
            $days[$dateKey] = [
                'date' => $dateKey,
                'day_number' => $cursor->format('j'),
                'weekday_short' => $cursor->format('D'),
                'is_today' => $dateKey === $today,
                'is_current_month' => $monthContext === null || str_starts_with($dateKey, $monthContext),
                'items' => [],
            ];
        }

        foreach ($occurrences as $occurrence) {
            $segmentStart = new DateTimeImmutable((string) $occurrence['start_date']);
            $segmentEnd = new DateTimeImmutable((string) $occurrence['end_date']);
            if ($segmentStart < $gridStart) {
                $segmentStart = $gridStart;
            }
            if ($segmentEnd > $gridEnd) {
                $segmentEnd = $gridEnd;
            }

            for ($cursor = $segmentStart; $cursor <= $segmentEnd; $cursor = $cursor->modify('+1 day')) {
                $dateKey = $cursor->format('Y-m-d');
                if (!isset($days[$dateKey])) {
                    continue;
                }

                $days[$dateKey]['items'][] = [
                    'id' => $occurrence['id'],
                    'title' => $occurrence['title'],
                    'status' => $occurrence['status'],
                    'scope_level' => $occurrence['scope_level'],
                    'calendar_name' => $occurrence['calendar_name'],
                    'calendar_color' => $occurrence['calendar_color'],
                    'view_url' => $occurrence['view_url'],
                    'edit_url' => $occurrence['edit_url'],
                    'can_manage' => $occurrence['can_manage'],
                    'starts_today' => $dateKey === $occurrence['start_date'],
                    'ends_today' => $dateKey === $occurrence['end_date'],
                    'time_label' => $this->formatOccurrenceTimeLabel($occurrence, $dateKey),
                ];
            }
        }

        return $days;
    }

    private function formatOccurrenceTimeLabel(array $occurrence, string $dateKey): string
    {
        if ((int) ($occurrence['is_all_day'] ?? 0) === 1) {
            return lang('Events.allDayShort');
        }

        $startAt = new DateTimeImmutable((string) ($occurrence['start_at'] ?? $dateKey . ' 00:00:00'));
        $endAt = new DateTimeImmutable((string) ($occurrence['end_at'] ?? $dateKey . ' 00:00:00'));

        if ($occurrence['start_date'] !== $dateKey && $occurrence['end_date'] !== $dateKey) {
            return lang('Events.continues');
        }

        if ($occurrence['start_date'] !== $dateKey) {
            return lang('Events.untilLabel') . ' ' . $endAt->format('g:i A');
        }

        if ($occurrence['end_date'] !== $dateKey) {
            return $startAt->format('g:i A') . ' ' . lang('Events.continues');
        }

        if ($startAt->format('H:i:s') === $endAt->format('H:i:s')) {
            return $startAt->format('g:i A');
        }

        return $startAt->format('g:i A') . ' - ' . $endAt->format('g:i A');
    }

    private function buildCalendarLegend(array $occurrences): array
    {
        $legend = [];

        foreach ($occurrences as $occurrence) {
            $key = ($occurrence['calendar_name'] ?? '') . '|' . ($occurrence['calendar_color'] ?? '#0d6efd');
            if (isset($legend[$key])) {
                continue;
            }

            $legend[$key] = [
                'name' => (string) ($occurrence['calendar_name'] ?? lang('Events.calendar')),
                'color' => (string) ($occurrence['calendar_color'] ?? '#0d6efd'),
            ];
        }

        return array_values($legend);
    }

    private function buildCalendarAgendaRows(array $dayBuckets): array
    {
        $rows = [];

        foreach ($dayBuckets as $dayBucket) {
            foreach ((array) ($dayBucket['items'] ?? []) as $item) {
                $rows[] = [
                    'date' => (string) ($dayBucket['date'] ?? ''),
                    'day_number' => (string) ($dayBucket['day_number'] ?? ''),
                    'weekday_short' => (string) ($dayBucket['weekday_short'] ?? ''),
                    'time_label' => (string) ($item['time_label'] ?? ''),
                    'title' => (string) ($item['title'] ?? ''),
                    'status' => (string) ($item['status'] ?? 'published'),
                    'scope_level' => (string) ($item['scope_level'] ?? 'parish'),
                    'calendar_name' => (string) ($item['calendar_name'] ?? ''),
                    'calendar_color' => (string) ($item['calendar_color'] ?? '#0d6efd'),
                    'view_url' => (string) ($item['view_url'] ?? '#'),
                ];
            }
        }

        return $rows;
    }

    private function formatCalendarPeriodLabel(string $mode, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): string
    {
        if ($mode === 'week') {
            return $periodStart->format('M j') . ' - ' . $periodEnd->format('M j, Y');
        }

        return $periodStart->format('F Y');
    }
}
