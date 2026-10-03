<?php

namespace Province\Controllers;

use App\Services\EventCoverUploadService;
use App\Services\EventScheduleService;

/**
 * ProvinceEventsManage
 *
 * Provincial event mutation endpoints: create/store/edit/update/delete
 * plus the validation and persistence helpers they own. Extends Events to
 * inherit the shared provinceId scope helper. Extracted from Events
 * (phase 5 split); routes and behavior unchanged.
 */
class ProvinceEventsManage extends Events
{

    public function create()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        return view('Province\Views\Events\create', array_merge($this->getCommonViewData(lang('App.navProvinceCalendarCreate')), [
            'event' => null,
            'calendars' => model('EventCalendarModel')->getAssignableCalendars(0, 0, false, $provinceId),
            'can_manage_calendars' => auth()->user()->can('province.calendar.manage'),
        ]));
    }


    public function store()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        if (!$this->validate($this->eventValidationRules($provinceId))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        try {
            $eventId = $this->persistEvent($provinceId);
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

            return redirect()->to(base_url('province/events/calendar'))->with('success', lang('Province.eventCreated'));
        }
        return redirect()->back()->withInput()->with('error', lang('Province.eventSaveFailed'));
    }


    public function edit(int $eventId)
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $event = $this->scopedEvent($eventId, $provinceId);
        if (!$event) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        return view('Province\Views\Events\edit', array_merge($this->getCommonViewData(lang('App.navProvinceCalendarEdit')), [
            'event' => $event,
            'calendars' => model('EventCalendarModel')->getAssignableCalendars(0, 0, false, $provinceId),
            'can_manage_calendars' => auth()->user()->can('province.calendar.manage'),
        ]));
    }


    public function update(int $eventId)
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $event = $this->scopedEvent($eventId, $provinceId);
        if (!$event) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        if (!$this->validate($this->eventValidationRules($provinceId, $eventId))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $updated = $this->persistEvent($provinceId, $eventId);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        if ($updated) {
            try {
                $existingCover = (string) ($event['cover_path'] ?? '');
                $coverPath = EventCoverUploadService::handleUpload($eventId, $existingCover, $this->request->getFile('cover'), (string) ($this->request->getPost('remove_cover') ?? '') === '1');
                if ($coverPath !== $existingCover) {
                    model('EventModel')->update($eventId, ['cover_path' => $coverPath !== '' ? $coverPath : null]);
                }
            } catch (\RuntimeException $e) {
                return redirect()->back()->withInput()->with('error', $e->getMessage());
            }

            return redirect()->to(base_url('province/events/calendar'))->with('success', lang('Province.eventUpdated'));
        }
        return redirect()->back()->withInput()->with('error', lang('Province.eventSaveFailed'));
    }


    public function delete(int $eventId)
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $event = $this->scopedEvent($eventId, $provinceId);
        if (!$event) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        EventCoverUploadService::removeCoverFile((string) ($event['cover_path'] ?? ''), $eventId);
        model('EventModel')->delete($eventId);
        return redirect()->to(base_url('province/events/calendar'))->with('success', lang('Province.eventDeleted'));
    }


    private function eventValidationRules(int $provinceId, ?int $eventId = null): array
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


    private function persistEvent(int $provinceId, ?int $eventId = null): int|bool
    {
        $calendarId = (int) $this->request->getPost('calendar_id');
        if (!$this->calendarInProvince($calendarId, $provinceId)) {
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
            'organizer_type' => 'province',
            'organizer_province_id' => $provinceId,
            'organizer_diocese_id' => null,
            'organizer_parish_id' => null,
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

        if (!$this->validateOrganizerIds($data)) {
            return false;
        }

        if ($eventId === null) {
            $data['created_by'] = (int) auth()->id();
            return (int) model('EventModel')->insert($data, true);
        }

        return model('EventModel')->update($eventId, $data);
    }


    private function validateOrganizerIds(array $data): bool
    {
        return model('EventModel')->validateOrganizerIds($data);
    }


    private function calendarInProvince(int $calendarId, int $provinceId): bool
    {
        if ($calendarId <= 0 || $provinceId <= 0) {
            return false;
        }

        return (int) db_connect()->table('event_calendar')
            ->where('id', $calendarId)
            ->where('scope_level', 'province')
            ->where('province_id', $provinceId)
            ->countAllResults() > 0;
    }


    private function scopedEvent(int $eventId, int $provinceId): ?array
    {
        $row = db_connect()->table('event e')
            ->select('e.*')
            ->where('e.id', $eventId)
            ->where('e.organizer_type', 'province')
            ->where('e.organizer_province_id', $provinceId)
            ->get()->getRowArray();
        return $row ?: null;
    }
}
