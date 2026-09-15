<?php

namespace App\Controllers;

use App\Services\EventCoverUploadService;
use App\Services\EventPublishService;
use App\Services\EventScheduleService;
use DateTimeImmutable;
use DateInterval;
use DatePeriod;

class SystemEvents extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        EventPublishService::publishDueDraftEvents();

        $status = trim((string) ($this->request->getGet('status') ?? ''));
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        $events = model('EventModel')->getNationalEventsWithCounts($parishId, $status === '' ? null : $status, $search === '' ? null : $search);

        $canEdit = auth()->user()->can('national.event.manage');
        foreach ($events as &$event) {
            $event['can_manage'] = $canEdit;
            $event['can_delete'] = $canEdit;
        }
        unset($event);

        return view('App\Views\System\Events\index', array_merge($this->getCommonViewData(lang('App.navSystemEvents')), [
            'events' => $events,
            'status' => $status,
            'search' => $search,
        ]));
    }

    public function calendar()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        EventPublishService::publishDueDraftEvents();

        $mode = in_array((string) ($this->request->getGet('mode') ?? 'month'), ['month', 'week', 'agenda'], true)
            ? (string) ($this->request->getGet('mode') ?? 'month')
            : 'month';
        $anchor = trim((string) ($this->request->getGet('date') ?? date('Y-m-d')));
        try {
            $anchorDate = new DateTimeImmutable($anchor);
        } catch (\Throwable $e) {
            $anchorDate = new DateTimeImmutable();
        }
        $anchorDate = $anchorDate->setTime(0, 0, 0);

        if ($mode === 'month') {
            $periodStart = $anchorDate->modify('first day of this month')->setTime(0, 0);
            $periodEnd = $anchorDate->modify('last day of this month')->setTime(23, 59, 59);
            $gridStart = $this->startOfWeek($periodStart);
            $gridEnd = $this->endOfWeek($periodEnd);
        } elseif ($mode === 'week') {
            $gridStart = $this->startOfWeek($anchorDate);
            $gridEnd = $this->endOfWeek($anchorDate);
            $periodStart = $gridStart;
            $periodEnd = $gridEnd;
        } else {
            $periodStart = $anchorDate->setTime(0, 0);
            $periodEnd = $anchorDate->modify('+30 days')->setTime(23, 59, 59);
            $gridStart = $periodStart;
            $gridEnd = $periodEnd;
        }

        $events = model('EventModel')->getNationalEventsForRange($parishId, $gridStart->format('Y-m-d H:i:s'), $gridEnd->format('Y-m-d H:i:s'));
        $events = array_map(static function (array $event): array {
            $event['scope_level'] = 'national';
            $event['source_label'] = lang('Province.calendarNationalLabel');
            $event['color'] = (string) ($event['calendar_color'] ?? '#0d6efd');
            $event['list_url'] = base_url('system/events');
            return $event;
        }, $events);

        $occurrences = $this->buildCalendarOccurrences($events, $gridStart, $gridEnd);
        $dayBuckets = $this->buildCalendarDayBuckets($gridStart, $gridEnd, $occurrences);

        return view('App\Views\System\Events\calendar', array_merge($this->getCommonViewData(lang('App.navSystemCalendar')), [
            'mode' => $mode,
            'anchor' => $anchorDate->format('Y-m-d'),
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            'day_buckets' => $dayBuckets,
            'weeks' => ($mode === 'month' || $mode === 'week') ? $this->splitBucketsIntoWeeks($dayBuckets) : null,
            'can_manage_calendars' => auth()->user()->can('system.admin'),
        ]));
    }

    public function view(int $id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $event = model('EventModel')->getEventWithCounts($id);
        if (!is_array($event) || (string) ($event['organizer_type'] ?? '') !== 'national') {
            return redirect()->to(base_url('system/events'))->with('error', lang('Members.eventNotFound'));
        }

        return view('App\Views\System\Events\view', array_merge($this->getCommonViewData(lang('Events.pageTitleEventDetails')), [
            'event' => $event,
            'registrations' => model('EventRegistrationModel')->getEventRegistrations($id),
            'can_manage_event' => auth()->user()->can('system.admin'),
        ]));
    }

    public function create()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        return view('App\Views\System\Events\create', array_merge($this->getCommonViewData(lang('Events.pageTitleCreateEvent')), [
            'event' => null,
            'calendars' => $this->getNationalCalendars($parishId),
            'can_manage_calendars' => auth()->user()->can('national.calendar.manage'),
        ]));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        if (!$this->validate($this->eventValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $eventId = $this->persistEvent($parishId);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        if (is_int($eventId)) {
            try {
                $coverPath = EventCoverUploadService::handleUpload($eventId, null, $this->request->getFile('cover'), (string) ($this->request->getPost('remove_cover') ?? '') === '1');
                if ($coverPath !== '') {
                    model('EventModel')->update($eventId, ['cover_path' => $coverPath]);
                }
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }

            return redirect()->to(base_url('system/events/calendar'))->with('success', lang('Province.eventCreated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Province.eventSaveFailed'));
    }

    public function edit(int $id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        $event = $this->scopedEvent($id);
        if (!$event) {
            return redirect()->to(base_url('system/events'))->with('error', lang('Members.eventNotFound'));
        }

        return view('App\Views\System\Events\edit', array_merge($this->getCommonViewData(lang('Events.pageTitleEditEvent')), [
            'event' => $event,
            'calendars' => $this->getNationalCalendars($parishId),
            'can_manage_calendars' => auth()->user()->can('national.calendar.manage'),
        ]));
    }

    public function update(int $id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        $event = $this->scopedEvent($id);
        if (!$event) {
            return redirect()->to(base_url('system/events'))->with('error', lang('Members.eventNotFound'));
        }

        if (!$this->validate($this->eventValidationRules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $updated = $this->persistEvent($parishId, $id);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        if ($updated) {
            try {
                $existingCover = (string) ($event['cover_path'] ?? '');
                $coverPath = EventCoverUploadService::handleUpload($id, $existingCover, $this->request->getFile('cover'), (string) ($this->request->getPost('remove_cover') ?? '') === '1');
                if ($coverPath !== $existingCover) {
                    model('EventModel')->update($id, ['cover_path' => $coverPath !== '' ? $coverPath : null]);
                }
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }

            return redirect()->to(base_url('system/events/calendar'))->with('success', lang('Province.eventUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Province.eventSaveFailed'));
    }

    public function delete(int $id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        $event = $this->scopedEvent($id);
        if (!$event) {
            return redirect()->to(base_url('system/events'))->with('error', lang('Members.eventNotFound'));
        }

        EventCoverUploadService::removeCoverFile((string) ($event['cover_path'] ?? ''), $id);
        model('EventModel')->delete($id);

        return redirect()->to(base_url('system/events/calendar'))->with('success', lang('Province.eventDeleted'));
    }

    private function nationalParishId(): ?int
    {
        $row = db_connect()->table('parish')->select('id')->limit(1)->get()->getRowArray();
        $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        return $id > 0 ? $id : null;
    }

    private function getNationalCalendars(int $parishId): array
    {
        return model('EventCalendarModel')
            ->where('scope_level', 'national')
            ->where('parish_id', $parishId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    private function scopedEvent(int $id): ?array
    {
        $row = db_connect()->table('event e')
            ->select('e.*')
            ->where('e.id', $id)
            ->where('e.organizer_type', 'national')
            ->get()->getRowArray();
        return is_array($row) ? $row : null;
    }

    private function eventValidationRules(?int $eventId = null): array
    {
        return [
            'title' => 'required|min_length[3]|max_length[255]',
            'calendar_id' => 'required|integer|is_not_unique[event_calendar.id]',
            'event_type' => 'permit_empty|max_length[100]',
            'location' => 'permit_empty|max_length[255]',
            'description' => 'permit_empty',
            'capacity' => 'permit_empty|integer|greater_than[0]',
            'status' => 'required|in_list[draft,published,cancelled,completed]',
            'visibility' => 'required|in_list[public,members,staff]',
            'schedule_mode' => 'required|in_list[one_day,all_day,multi_day]',
            'publish_at' => 'permit_empty',
            'recurrence_pattern' => 'permit_empty|in_list[none,daily,weekly,biweekly,monthly]',
            'recurrence_days' => 'permit_empty',
            'recurrence_until' => 'permit_empty',
            'cover' => 'permit_empty',
        ];
    }

    private function persistEvent(int $parishId, ?int $eventId = null): int|bool
    {
        $calendarId = (int) $this->request->getPost('calendar_id');
        $calendar = model('EventCalendarModel')->where('id', $calendarId)->where('scope_level', 'national')->where('parish_id', $parishId)->first();
        if (!is_array($calendar)) {
            return false;
        }

        $schedule = EventScheduleService::resolveSchedulePayload($this->request->getPost() ?? []);

        $recurrencePattern = trim((string) ($this->request->getPost('recurrence_pattern') ?? 'none'));
        if (!in_array($recurrencePattern, ['none', 'daily', 'weekly', 'biweekly', 'monthly'], true)) {
            $recurrencePattern = 'none';
        }

        $recurrenceUntil = trim((string) ($this->request->getPost('recurrence_until') ?? ''));
        if ($recurrencePattern !== 'none' && $recurrenceUntil === '') {
            throw new \RuntimeException(lang('Events.errorRepeatEndDateRequired'));
        }

        $recurrenceDays = EventScheduleService::normalizeRecurrenceDays(
            (array) ($this->request->getPost('recurrence_days') ?? []),
            (string) $schedule['start_at']
        );

        $capacity = (int) ($this->request->getPost('capacity') ?? 0);
        $publishAt = trim((string) ($this->request->getPost('publish_at') ?? ''));

        $data = [
            'calendar_id' => $calendarId,
            'organizer_type' => 'national',
            'organizer_parish_id' => $parishId,
            'organizer_province_id' => null,
            'organizer_diocese_id' => null,
            'organizer_parish_id' => null,
            'host_parish_id' => null,
            'title' => trim((string) $this->request->getPost('title')),
            'description' => trim((string) $this->request->getPost('description')),
            'event_type' => trim((string) $this->request->getPost('event_type')) ?: 'general',
            'location' => trim((string) $this->request->getPost('location')),
            'schedule_mode' => (string) $schedule['schedule_mode'],
            'is_all_day' => (int) $schedule['is_all_day'],
            'start_at' => (string) $schedule['start_at'],
            'end_at' => $schedule['end_at'] !== '' ? (string) $schedule['end_at'] : null,
            'capacity' => $capacity > 0 ? $capacity : null,
            'requires_registration' => $this->request->getPost('requires_registration') ? 1 : 0,
            'status' => trim((string) $this->request->getPost('status')),
            'visibility' => trim((string) $this->request->getPost('visibility')),
            'publish_at' => $publishAt !== '' ? date('Y-m-d H:i:s', (int) strtotime($publishAt)) : null,
            'recurrence_pattern' => $recurrencePattern,
            'recurrence_days_json' => $recurrencePattern !== 'none' ? json_encode($recurrenceDays) : null,
            'recurrence_until' => $recurrencePattern !== 'none' ? $recurrenceUntil : null,
        ];

        if (!model('EventModel')->validateOrganizerIds($data)) {
            return false;
        }

        if ($eventId === null) {
            $data['created_by'] = (int) auth()->id();
            return (int) model('EventModel')->insert($data, true);
        }

        return model('EventModel')->update($eventId, $data);
    }

    private function startOfWeek(DateTimeImmutable $date): DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('w');
        return $date->modify('-' . $dayOfWeek . ' days')->setTime(0, 0);
    }

    private function endOfWeek(DateTimeImmutable $date): DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('w');
        return $date->modify('+' . (6 - $dayOfWeek) . ' days')->setTime(23, 59, 59);
    }

    private function buildCalendarOccurrences(array $events, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];
        foreach ($events as $event) {
            $pattern = (string) ($event['recurrence_pattern'] ?? 'none');
            if ($pattern === '' || $pattern === 'none') {
                $occurrences = array_merge($occurrences, $this->buildSingleEventOccurrences($event, $rangeStart, $rangeEnd));
            } else {
                $occurrences = array_merge($occurrences, $this->expandRecurringEvent($event, $rangeStart, $rangeEnd));
            }
        }
        return $occurrences;
    }

    private function buildSingleEventOccurrences(array $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];
        $eventStart = new DateTimeImmutable((string) $event['start_at']);
        $eventEnd = !empty($event['end_at']) ? new DateTimeImmutable((string) $event['end_at']) : $eventStart;
        $current = clone $eventStart;
        while ($current <= $eventEnd && $current <= $rangeEnd) {
            if ($current >= $rangeStart) {
                $occurrences[] = array_merge($event, ['occurrence_date' => $current->format('Y-m-d')]);
            }
            $current = $current->modify('+1 day');
        }
        return $occurrences;
    }

    private function expandRecurringEvent(array $event, DateTimeImmutable $rangeStart, DateTimeImmutable $rangeEnd): array
    {
        $occurrences = [];
        $pattern = (string) ($event['recurrence_pattern'] ?? 'none');
        if ($pattern === '' || $pattern === 'none') {
            return $occurrences;
        }

        $eventStart = new DateTimeImmutable((string) $event['start_at']);
        $untilRaw = (string) ($event['recurrence_until'] ?? '');
        $until = $untilRaw !== '' ? new DateTimeImmutable($untilRaw . ' 23:59:59') : $rangeEnd;
        if ($until > $rangeEnd) {
            $until = $rangeEnd;
        }

        $cursor = $eventStart > $rangeStart ? $eventStart : $rangeStart;
        if ($cursor > $until) {
            return $occurrences;
        }

        if ($pattern === 'daily') {
            $period = new DatePeriod($cursor, new DateInterval('P1D'), $until);
            foreach ($period as $date) {
                $occurrences[] = array_merge($event, ['occurrence_date' => $date->format('Y-m-d')]);
            }
            return $occurrences;
        }

        if (in_array($pattern, ['weekly', 'biweekly'], true)) {
            $allowedDays = json_decode((string) ($event['recurrence_days_json'] ?? '[]'), true);
            $allowedDays = is_array($allowedDays) ? array_map('strtolower', $allowedDays) : [];
            if ($allowedDays === []) {
                $allowedDays = [strtolower($eventStart->format('D'))];
            }
            $interval = $pattern === 'biweekly' ? 14 : 7;
            $period = new DatePeriod($cursor, new DateInterval('P1D'), $until);
            foreach ($period as $date) {
                $dayName = strtolower($date->format('D'));
                if (!in_array($dayName, $allowedDays, true)) {
                    continue;
                }
                $diffDays = (int) $eventStart->diff($date)->format('%r%a');
                if ($diffDays % $interval !== 0) {
                    continue;
                }
                $occurrences[] = array_merge($event, ['occurrence_date' => $date->format('Y-m-d')]);
            }
            return $occurrences;
        }

        if ($pattern === 'monthly') {
            $period = new DatePeriod($cursor, new DateInterval('P1M'), $until);
            $startDay = (int) $eventStart->format('j');
            foreach ($period as $date) {
                $target = $date->setDate((int) $date->format('Y'), (int) $date->format('n'), min($startDay, (int) $date->format('t')));
                if ($target < $eventStart || $target < $rangeStart || $target > $rangeEnd || $target > $until) {
                    continue;
                }
                $occurrences[] = array_merge($event, ['occurrence_date' => $target->format('Y-m-d')]);
            }
            return $occurrences;
        }

        return $occurrences;
    }

    private function buildCalendarDayBuckets(DateTimeImmutable $start, DateTimeImmutable $end, array $occurrences): array
    {
        $buckets = [];
        $current = clone $start;
        while ($current <= $end) {
            $date = $current->format('Y-m-d');
            $buckets[$date] = ['date' => $date, 'label' => $current->format('D M j'), 'occurrences' => []];
            $current = $current->modify('+1 day');
        }
        foreach ($occurrences as $occurrence) {
            $date = (string) $occurrence['occurrence_date'];
            if (isset($buckets[$date])) {
                $buckets[$date]['occurrences'][] = $occurrence;
            }
        }
        return $buckets;
    }

    private function splitBucketsIntoWeeks(array $buckets): array
    {
        $weeks = [];
        $currentWeek = [];
        foreach ($buckets as $bucket) {
            $currentWeek[] = $bucket;
            if (count($currentWeek) === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }
        if ($currentWeek !== []) {
            while (count($currentWeek) < 7) {
                $currentWeek[] = ['date' => '', 'label' => '', 'occurrences' => []];
            }
            $weeks[] = $currentWeek;
        }
        return $weeks;
    }
}
