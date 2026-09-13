<?php

namespace Clergy\Controllers;

use App\Controllers\BaseController;

class EventCalendars extends BaseController
{
    protected $eventCalendarModel;

    public function __construct()
    {
        $this->eventCalendarModel = model('EventCalendarModel');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('event.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = (int) $this->resolveDiocesanScopeId();
        $canAssignDioceseScope = $this->isDioceseAdmin() || auth()->user()->can('diocese.admin');

        return view('Clergy\Views\Events/calendars', array_merge($this->getCommonViewData(lang('Events.pageTitleCalendars')), [
            'calendars' => $this->eventCalendarModel->getAssignableCalendars($parishId, $dioceseId, $canAssignDioceseScope),
            'can_assign_diocese_scope' => $canAssignDioceseScope,
        ]));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('event.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $scopeLevel = trim((string) ($this->request->getPost('scope_level') ?? 'parish'));
        $canAssignDioceseScope = $this->isDioceseAdmin() || auth()->user()->can('diocese.admin');
        if ($scopeLevel !== 'diocese' || !$canAssignDioceseScope) {
            $scopeLevel = 'parish';
        }

        $rules = [
            'name' => 'required|max_length[120]',
            'color_hex' => 'required|regex_match[/^#[0-9A-Fa-f]{6}$/]',
            'scope_level' => 'required|in_list[parish,diocese]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'parish_id' => $scopeLevel === 'parish' ? (int) ($this->getOperationalParishId() ?? 0) : null,
            'diocese_id' => $scopeLevel === 'diocese' ? (int) $this->resolveDiocesanScopeId() : (int) $this->resolveDiocesanScopeId(),
            'scope_level' => $scopeLevel,
            'name' => trim((string) ($this->request->getPost('name') ?? '')),
            'color_hex' => trim((string) ($this->request->getPost('color_hex') ?? '#0d6efd')),
            'is_active' => 1,
            'sort_order' => 0,
            'created_by' => (int) auth()->id(),
        ];

        $this->eventCalendarModel->insert($payload);

        return redirect()->to('/event-calendars')->with('success', lang('Events.calendarCreated'));
    }
}
