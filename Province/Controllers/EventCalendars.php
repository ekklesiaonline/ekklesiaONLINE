<?php

namespace Province\Controllers;

use App\Controllers\BaseController;

class EventCalendars extends BaseController
{
    public function index()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $calendars = model('EventCalendarModel')
            ->where('scope_level', 'province')
            ->where('province_id', $provinceId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        return view('Province\Views\EventCalendars\index', array_merge($this->getCommonViewData(lang('Province.manageCalendars')), compact('calendars', 'provinceId')));
    }

    public function create()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        return view('Province\Views\EventCalendars\form', array_merge($this->getCommonViewData(lang('Province.manageCalendars')), [
            'calendar' => null,
        ]));
    }

    public function store()
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        if (!$this->validate($this->calendarValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->calendarPayload($provinceId);
        $data['created_by'] = (int) auth()->id();

        if (model('EventCalendarModel')->insert($data, false)) {
            return redirect()->to(base_url('province/event-calendars'))->with('success', lang('Events.calendarCreated'));
        }

        return redirect()->back()->withInput()->with('error', lang('App.saveFailed'));
    }

    public function edit(int $id)
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $calendar = $this->scopedCalendar($id, $provinceId);
        if (!$calendar) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        return view('Province\Views\EventCalendars\form', array_merge($this->getCommonViewData(lang('Province.manageCalendars')), compact('calendar')));
    }

    public function update(int $id)
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $calendar = $this->scopedCalendar($id, $provinceId);
        if (!$calendar) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        if (!$this->validate($this->calendarValidationRules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = $this->calendarPayload($provinceId);

        if (model('EventCalendarModel')->update($id, $data)) {
            return redirect()->to(base_url('province/event-calendars'))->with('success', lang('Events.calendarUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('App.saveFailed'));
    }

    public function delete(int $id)
    {
        $provinceId = $this->provinceId();
        if ($provinceId === null) {
            return $this->forbiddenResponse(lang('Province.scopeNotConfigured'));
        }

        $calendar = $this->scopedCalendar($id, $provinceId);
        if (!$calendar) {
            return $this->forbiddenResponse(lang('Province.accessDenied'));
        }

        model('EventCalendarModel')->delete($id);
        return redirect()->to(base_url('province/event-calendars'))->with('success', lang('Events.calendarDeleted'));
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

    private function calendarPayload(int $provinceId): array
    {
        return [
            'scope_level' => 'province',
            'province_id' => $provinceId,
            'church_id'   => null,
            'parish_id'   => null,
            'diocese_id'  => null,
            'name'        => trim((string) $this->request->getPost('name')),
            'color_hex'   => (string) $this->request->getPost('color_hex'),
            'is_active'   => (int) $this->request->getPost('is_active'),
            'sort_order'  => (int) $this->request->getPost('sort_order'),
        ];
    }

    private function scopedCalendar(int $id, int $provinceId): ?array
    {
        return model('EventCalendarModel')
            ->where('id', $id)
            ->where('scope_level', 'province')
            ->where('province_id', $provinceId)
            ->first();
    }

    private function provinceId(): ?int
    {
        $user = auth()->user();
        if (!$user || !$user->inGroup('provinceadmin') || !$user->can('province.admin')) {
            return null;
        }

        $profile = model('ProfileModel')->where('user_id', (int) auth()->id())->first();
        $provinceId = (int) ($profile['province_id'] ?? 0);

        return $provinceId > 0 && db_connect()->table('province')->where('id', $provinceId)->countAllResults() > 0
            ? $provinceId
            : null;
    }
}
