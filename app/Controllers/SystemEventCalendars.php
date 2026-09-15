<?php

namespace App\Controllers;

class SystemEventCalendars extends BaseController
{
    public function index()
    {
        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        $calendars = model('EventCalendarModel')
            ->where('scope_level', 'national')
            ->where('parish_id', $parishId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        return view('App\Views\System\EventCalendars\index', array_merge($this->getCommonViewData(lang('Events.pageTitleCalendars')), compact('calendars')));
    }

    public function create()
    {
        return view('App\Views\System\EventCalendars\form', array_merge($this->getCommonViewData(lang('Events.pageTitleCalendars')), [
            'calendar' => null,
        ]));
    }

    public function store()
    {
        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return $this->forbiddenResponse(lang('App.errorGeneric'));
        }

        if (!$this->validate($this->calendarValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->calendarPayload($parishId);
        $data['created_by'] = (int) auth()->id();

        if (model('EventCalendarModel')->insert($data, false)) {
            return redirect()->to(base_url('system/event-calendars'))->with('success', lang('Events.calendarCreated'));
        }

        return redirect()->back()->withInput()->with('error', lang('App.saveFailed'));
    }

    public function edit(int $id)
    {
        $calendar = $this->scopedCalendar($id);
        if (!$calendar) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        return view('App\Views\System\EventCalendars\form', array_merge($this->getCommonViewData(lang('Events.pageTitleCalendars')), compact('calendar')));
    }

    public function update(int $id)
    {
        $calendar = $this->scopedCalendar($id);
        if (!$calendar) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        if (!$this->validate($this->calendarValidationRules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->calendarPayload((int) $calendar['parish_id']);

        if (model('EventCalendarModel')->update($id, $data)) {
            return redirect()->to(base_url('system/event-calendars'))->with('success', lang('Events.calendarUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('App.saveFailed'));
    }

    public function delete(int $id)
    {
        $calendar = $this->scopedCalendar($id);
        if (!$calendar) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        model('EventCalendarModel')->delete($id);
        return redirect()->to(base_url('system/event-calendars'))->with('success', lang('Events.calendarDeleted'));
    }

    private function calendarValidationRules(?int $id = null): array
    {
        return [
            'name' => 'required|max_length[120]',
            'color_hex' => 'required|regex_match[/^#[0-9A-Fa-f]{6}$/]',
            'is_active' => 'required|in_list[0,1]',
            'sort_order' => 'required|integer',
        ];
    }

    private function calendarPayload(int $parishId): array
    {
        return [
            'scope_level' => 'national',
            'parish_id'   => $parishId,
            'province_id' => null,
            'diocese_id'  => null,
            'parish_id'   => null,
            'name'        => trim((string) $this->request->getPost('name')),
            'color_hex'   => (string) $this->request->getPost('color_hex'),
            'is_active'   => (int) $this->request->getPost('is_active'),
            'sort_order'  => (int) $this->request->getPost('sort_order'),
        ];
    }

    private function scopedCalendar(int $id): ?array
    {
        $parishId = $this->nationalParishId();
        if ($parishId === null) {
            return null;
        }

        return model('EventCalendarModel')
            ->where('id', $id)
            ->where('scope_level', 'national')
            ->where('parish_id', $parishId)
            ->first();
    }

    private function nationalParishId(): ?int
    {
        $row = db_connect()->table('parish')->select('id')->limit(1)->get()->getRowArray();
        $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;

        return $id > 0 ? $id : null;
    }
}
