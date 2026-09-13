<?php

namespace Volunteer\Controllers;

use App\Controllers\BaseController;

class Volunteers extends BaseController
{
    protected $volunteerTeamModel;
    protected $teamMemberModel;
    protected $volunteerScheduleModel;
    protected $memberParishAffiliationModel;

    public function __construct()
    {
        $this->volunteerTeamModel = model('VolunteerTeamModel');
        $this->teamMemberModel = model('TeamMemberModel');
        $this->volunteerScheduleModel = model('VolunteerScheduleModel');
    }

    protected function getMemberParishAffiliationModel()
    {
        if ($this->memberParishAffiliationModel === null) {
            $this->memberParishAffiliationModel = model('MemberParishAffiliationModel');
        }

        return $this->memberParishAffiliationModel;
    }

    /**
     * Display volunteer teams
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('volunteer.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $teamPage = $this->volunteerTeamModel->getParishTeamsPaginated($parishId, $perPage, $page);
        $pagerLinks = service('pager')->makeLinks($page, $perPage, (int) ($teamPage['total'] ?? 0));
        $totalItems = (int) ($teamPage['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        return view('Volunteer\Views\Volunteers/index', array_merge($this->getCommonViewData(lang('Volunteer.pageTitleTeams')), [
            'teams' => $teamPage['rows'] ?? [],
            'pager_links' => $pagerLinks,
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
        ]));
    }

    /**
     * Create team
     */
    public function createTeam()
    {
        if ($redirect = $this->requirePermission('volunteer.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Volunteer\Views\Volunteers/create_team', $this->getCommonViewData(lang('Volunteer.pageTitleCreateTeam')));
    }

    /**
     * Store team
     */
    public function storeTeam()
    {
        if ($redirect = $this->requirePermission('volunteer.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'name' => 'required|min_length[3]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $data = [
            'parish_id' => $this->getOperationalParishId(),
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'leader_id' => $this->request->getPost('leader_id') ?: null,
        ];

        if ($this->volunteerTeamModel->insert($data)) {
            return redirect()->to('/volunteers')->with('success', lang('Volunteer.teamCreated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Volunteer.teamCreateFailed'));
    }

    /**
     * View team details
     */
    public function viewTeam($id)
    {
        if ($redirect = $this->requirePermission('volunteer.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $team = $this->volunteerTeamModel->find($id);

        if (!$team || $team['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/volunteers')->with('error', lang('Volunteer.teamNotFound'));
        }

        $members = $this->teamMemberModel->getTeamMembers($id);
        $schedules = $this->volunteerScheduleModel->getTeamSchedules($id, 10);
        $availableMembers = $this->getMemberParishAffiliationModel()->getParishMembers((int) $this->getOperationalParishId());

        $data = [
            'team' => $team,
            'members' => $members,
            'schedules' => $schedules,
            'available_members' => $availableMembers,
        ];

        return view('Volunteer\Views\Volunteers/view_team', array_merge($this->getCommonViewData(lang('Volunteer.pageTitleTeamDetails')), $data));
    }

    /**
     * Add member to team
     */
    public function addMember()
    {
        if ($redirect = $this->requirePermission('volunteer.assign')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.noParishScope')], 403);
        }

        $teamId = $this->request->getPost('team_id');
        $userId = $this->request->getPost('user_id');
        $role = $this->request->getPost('role');

        // Verify team belongs to parish
        $team = $this->volunteerTeamModel->find($teamId);
        if (!$team || $team['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.invalidTeam')]);
        }

        $isAffiliated = $this->getMemberParishAffiliationModel()
            ->isUserAffiliatedWithParish((int) $userId, (int) ($this->getOperationalParishId() ?? 0), true);
        if (!$isAffiliated) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.invalidMemberSelection')]);
        }

        $data = [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role ?? 'member',
        ];

        if ($this->teamMemberModel->insert($data)) {
            $teamMemberId = (int) $this->teamMemberModel->getInsertID();
            $this->audit()->log('team_member.add', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'team_member',
                'entity_id' => $teamMemberId,
                'new_values' => [
                    'team_id' => (int) $teamId,
                    'user_id' => (int) $userId,
                    'role' => (string) ($data['role'] ?? 'member'),
                ],
            ]);

            if (!$this->request->isAJAX()) {
                return redirect()->to('/volunteers/viewTeam/' . (int) $teamId)->with('success', lang('Volunteer.memberAdded'));
            }
            return $this->jsonResponse(['success' => true, 'message' => lang('Volunteer.memberAdded')]);
        }

        if (!$this->request->isAJAX()) {
            return redirect()->back()->with('error', lang('Volunteer.memberAddFailed'));
        }
        return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.memberAddFailed')]);
    }

    /**
     * Remove member from team
     */
    public function removeMember($id)
    {
        if ($redirect = $this->requirePermission('volunteer.assign')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.noParishScope')], 403);
        }

        $member = $this->teamMemberModel->find($id);
        if (!$member) {
            if (!$this->request->isAJAX()) {
                return redirect()->back()->with('error', lang('Volunteer.memberNotFound'));
            }
            return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.memberNotFound')]);
        }

        // Verify team belongs to parish
        $team = $this->volunteerTeamModel->find($member['team_id']);
        if (!$team || $team['parish_id'] != $this->getOperationalParishId()) {
            if (!$this->request->isAJAX()) {
                return redirect()->back()->with('error', lang('App.errorUnauthorized'));
            }
            return $this->jsonResponse(['success' => false, 'message' => lang('App.errorUnauthorized')]);
        }

        if ($this->teamMemberModel->delete($id)) {
            $this->audit()->log('team_member.remove', [
                'parish_id' => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'team_member',
                'entity_id' => (int) $id,
                'old_values' => [
                    'team_id' => (int) ($member['team_id'] ?? 0),
                    'user_id' => (int) ($member['user_id'] ?? 0),
                    'role' => (string) ($member['role'] ?? 'member'),
                ],
            ]);

            if (!$this->request->isAJAX()) {
                return redirect()->to('/volunteers/viewTeam/' . (int) $team['id'])->with('success', lang('Volunteer.memberRemoved'));
            }
            return $this->jsonResponse(['success' => true, 'message' => lang('Volunteer.memberRemoved')]);
        }

        if (!$this->request->isAJAX()) {
            return redirect()->back()->with('error', lang('Volunteer.memberRemoveFailed'));
        }
        return $this->jsonResponse(['success' => false, 'message' => lang('Volunteer.memberRemoveFailed')]);
    }

    /**
     * Schedule management
     */
    public function schedules()
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        $teamId   = ($this->request->getGet('team_id') !== null && (int) $this->request->getGet('team_id') > 0)
            ? (int) $this->request->getGet('team_id') : null;
        $status   = in_array($this->request->getGet('status'), ['scheduled', 'confirmed', 'cancelled'], true)
            ? $this->request->getGet('status') : null;
        $dateFrom = $this->request->getGet('date_from') ?: null;
        $dateTo   = $this->request->getGet('date_to') ?: null;

        // View mode: 'week' (default), 'month', or 'list'. Support both 'mode' and legacy 'view' params.
        $modeParam = in_array($this->request->getGet('mode'), ['week', 'month', 'list'], true)
            ? $this->request->getGet('mode') : null;
        $calView = $modeParam
            ?? (in_array($this->request->getGet('view'), ['week', 'month', 'list'], true)
                ? $this->request->getGet('view') : 'week');

        $dateParam = $this->request->getGet('date') ?: null;

        // ── Week navigation ──────────────────────────────────────────────
        $weekStartParam = $calView === 'week' ? ($dateParam ?? $this->request->getGet('week_start')) : $this->request->getGet('week_start');
        if ($weekStartParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
            $weekStartDate = new \DateTime($weekStartParam);
        } else {
            $weekStartDate = new \DateTime();
        }
        // Normalise to Sunday (w=0..6, Sun=0)
        $weekStartDate->modify('-' . (int) $weekStartDate->format('w') . ' days');
        $weekEndDate    = (clone $weekStartDate)->modify('+6 days');
        $weekStart      = $weekStartDate->format('Y-m-d');
        $weekEnd        = $weekEndDate->format('Y-m-d');
        $prevWeekStart  = (clone $weekStartDate)->modify('-7 days')->format('Y-m-d');
        $nextWeekStart  = (clone $weekStartDate)->modify('+7 days')->format('Y-m-d');
        $todayWeekStart = (function () {
            $d = new \DateTime();
            $d->modify('-' . (int) $d->format('w') . ' days');
            return $d->format('Y-m-d');
        })();

        // ── Month navigation ─────────────────────────────────────────────
        $monthStartParam = $calView === 'month' ? ($dateParam ?? $this->request->getGet('month_start')) : $this->request->getGet('month_start');
        if ($monthStartParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $monthStartParam)) {
            $monthStartDate = new \DateTime($monthStartParam);
        } else {
            $monthStartDate = new \DateTime();
        }
        $monthStartDate->modify('first day of this month');
        $monthEndDate = (clone $monthStartDate)->modify('last day of this month');

        $monthGridStartDate = (clone $monthStartDate);
        $monthGridStartDate->modify('-' . (int) $monthGridStartDate->format('w') . ' days');

        $monthGridEndDate = (clone $monthEndDate);
        $monthGridEndDate->modify('+' . (6 - (int) $monthGridEndDate->format('w')) . ' days');

        $monthStart = $monthStartDate->format('Y-m-d');
        $monthEnd = $monthEndDate->format('Y-m-d');
        $monthGridStart = $monthGridStartDate->format('Y-m-d');
        $monthGridEnd = $monthGridEndDate->format('Y-m-d');
        $prevMonthStart = (clone $monthStartDate)->modify('-1 month')->format('Y-m-d');
        $nextMonthStart = (clone $monthStartDate)->modify('+1 month')->format('Y-m-d');
        $todayMonthStart = (new \DateTime('first day of this month'))->format('Y-m-d');

        // ── Calendar data (week/month view — all rows, no pagination) ─────
        $calSchedules = [];
        if ($calView === 'week') {
            $calSchedules = $this->volunteerScheduleModel->getParishSchedulesForWeek($parishId, $weekStart, $weekEnd, $teamId, $status);
        } elseif ($calView === 'month') {
            $calSchedules = $this->volunteerScheduleModel->getParishSchedulesForWeek($parishId, $monthGridStart, $monthGridEnd, $teamId, $status);
        }

        // ── List data (paginated) ─────────────────────────────────────────
        $page    = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $schedulePage = ['rows' => [], 'total' => 0];
        if ($calView === 'list') {
            $schedulePage = $this->volunteerScheduleModel->getParishSchedulesPaginated($parishId, $perPage, $page, $teamId, $status, $dateFrom, $dateTo);
        }

        $pagerLinks = service('pager')->makeLinks($page, $perPage, (int) ($schedulePage['total'] ?? 0));
        $totalItems = (int) ($schedulePage['total'] ?? 0);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        $filterTeams = $this->volunteerTeamModel->where('parish_id', $parishId)->orderBy('name', 'ASC')->findAll();

        return view('Volunteer\Views\Volunteers/schedules', array_merge($this->getCommonViewData(lang('Volunteer.pageTitleSchedules')), [
            // list data
            'schedules'        => $schedulePage['rows'] ?? [],
            'pager_links'      => $pagerLinks,
            'current_page'     => $page,
            'per_page'         => $perPage,
            'total_items'      => $totalItems,
            'total_pages'      => $totalPages,
            // filters
            'filter_teams'     => $filterTeams,
            'filter_team_id'   => $teamId ?? 0,
            'filter_status'    => $status ?? '',
            'filter_date_from' => $dateFrom ?? '',
            'filter_date_to'   => $dateTo ?? '',
            // calendar
            'cal_view'         => $calView,
            'cal_schedules'    => $calSchedules,
            'week_start'       => $weekStart,
            'week_end'         => $weekEnd,
            'prev_week_start'  => $prevWeekStart,
            'next_week_start'  => $nextWeekStart,
            'today_week_start' => $todayWeekStart,
            'month_start'      => $monthStart,
            'month_end'        => $monthEnd,
            'month_grid_start' => $monthGridStart,
            'month_grid_end'   => $monthGridEnd,
            'prev_month_start' => $prevMonthStart,
            'next_month_start' => $nextMonthStart,
            'today_month_start' => $todayMonthStart,
        ]));
    }

    /**
     * Create schedule
     */
    public function createSchedule()
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $teams    = $this->volunteerTeamModel->where('parish_id', $parishId)->findAll();
        $services = model('ServiceModel')
            ->where('parish_id', $parishId)
            ->where('service_date >=', date('Y-m-d', strtotime('-90 days')))
            ->orderBy('service_date', 'DESC')
            ->orderBy('service_time', 'DESC')
            ->findAll();

        // If returning after a validation error, restore the member list for the previously-selected team.
        $oldTeamId = (int) old('team_id', 0);
        $members   = $oldTeamId > 0 ? $this->teamMemberModel->getTeamMembers($oldTeamId) : [];

        return view('Volunteer\Views\Volunteers/create_schedule', array_merge($this->getCommonViewData(lang('Volunteer.pageTitleCreateSchedule')), [
            'teams'    => $teams,
            'members'  => $members,
            'services' => $services,
        ]));
    }

    /**
     * Store schedule
     */
    public function storeSchedule()
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'team_id'    => 'required|is_not_unique[team.id]',
            'user_id'    => 'required|is_not_unique[users.id]',
            'service_date' => 'permit_empty|valid_date',
            'status'     => 'permit_empty|in_list[scheduled,confirmed,cancelled]',
            'service_id' => 'permit_empty|is_not_unique[service.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $teamId = (int) $this->request->getPost('team_id');

        // Verify team belongs to parish
        $team = $this->volunteerTeamModel->find($teamId);
        if (!$team || (int) ($team['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.invalidTeam'));
        }

        $isAffiliated = $this->getMemberParishAffiliationModel()
            ->isUserAffiliatedWithParish((int) $this->request->getPost('user_id'), (int) ($this->getOperationalParishId() ?? 0), true);
        if (!$isAffiliated) {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.memberOutsideScope'));
        }

        try {
            $data = $this->buildSchedulePayload();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        if (($data['service_date'] ?? '') === '') {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.scheduleDateRequired'));
        }
        $data['team_id'] = $teamId;

        if ($this->volunteerScheduleModel->hasUserSlotConflict(
            (int) $data['user_id'],
            (string) $data['service_date'],
            $data['service_time_slot']
        )) {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.scheduleConflict'));
        }

        $insertId = $this->volunteerScheduleModel->insert($data, true);
        if ($insertId) {
            $this->audit()->log('schedule.create', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'volunteer_schedule',
                'entity_id'   => (int) $insertId,
                'new_values'  => $data,
            ]);
            return redirect()->to('/volunteers/schedules')->with('success', lang('Volunteer.scheduleCreated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Volunteer.scheduleCreateFailed'));
    }

    public function editSchedule($id)
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $schedule = $this->getParishScopedSchedule((int) $id);
        if (!$schedule) {
            return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleNotFound'));
        }

        $churchId = (int) ($this->getOperationalParishId() ?? 0);
        $teams    = $this->volunteerTeamModel->where('parish_id', $churchId)->findAll();
        $services = model('ServiceModel')
            ->where('parish_id', $churchId)
            ->where('service_date >=', date('Y-m-d', strtotime('-90 days')))
            ->orderBy('service_date', 'DESC')
            ->orderBy('service_time', 'DESC')
            ->findAll();

        // Load members scoped to the schedule's team (or old team_id if returning after a validation error).
        $teamId  = (int) old('team_id', (int) ($schedule['team_id'] ?? 0));
        $members = $teamId > 0 ? $this->teamMemberModel->getTeamMembers($teamId) : [];

        return view('Volunteer\Views\Volunteers/edit_schedule', array_merge($this->getCommonViewData(lang('Volunteer.pageTitleEditSchedule')), [
            'schedule' => $schedule,
            'teams'    => $teams,
            'members'  => $members,
            'services' => $services,
        ]));
    }

    public function updateSchedule($id)
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $schedule = $this->getParishScopedSchedule((int) $id);
        if (!$schedule) {
            return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleNotFound'));
        }

        $rules = [
            'team_id'    => 'required|is_not_unique[team.id]',
            'user_id'    => 'required|is_not_unique[users.id]',
            'service_date' => 'permit_empty|valid_date',
            'status'     => 'required|in_list[scheduled,confirmed,cancelled]',
            'service_id' => 'permit_empty|is_not_unique[service.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $teamId = (int) $this->request->getPost('team_id');
        $team = $this->volunteerTeamModel->find($teamId);
        if (!$team || (int) ($team['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.invalidTeam'));
        }

        $userId = (int) $this->request->getPost('user_id');
        $isAffiliated = $this->getMemberParishAffiliationModel()
            ->isUserAffiliatedWithParish($userId, (int) ($this->getOperationalParishId() ?? 0), true);
        if (!$isAffiliated) {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.memberOutsideScope'));
        }

        try {
            $data = $this->buildSchedulePayload();
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        if (($data['service_date'] ?? '') === '') {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.scheduleDateRequired'));
        }
        $data['team_id'] = $teamId;

        if ($this->volunteerScheduleModel->hasUserSlotConflict(
            (int) $data['user_id'],
            (string) $data['service_date'],
            $data['service_time_slot'],
            (int) $id
        )) {
            return redirect()->back()->withInput()->with('error', lang('Volunteer.scheduleConflict'));
        }

        if ($this->volunteerScheduleModel->update((int) $id, $data)) {
            $this->audit()->log('schedule.update', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'volunteer_schedule',
                'entity_id'   => (int) $id,
                'new_values'  => $data,
            ]);
            return redirect()->to('/volunteers/schedules')->with('success', lang('Volunteer.scheduleUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Volunteer.scheduleUpdateFailed'));
    }

    public function cancelSchedule($id)
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $schedule = $this->getParishScopedSchedule((int) $id);
        if (!$schedule) {
            return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleNotFound'));
        }

        if ($this->volunteerScheduleModel->update((int) $id, ['status' => 'cancelled'])) {
            $this->audit()->log('schedule.cancel', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'volunteer_schedule',
                'entity_id'   => (int) $id,
            ]);
            return redirect()->to('/volunteers/schedules')->with('success', lang('Volunteer.scheduleCancelled'));
        }

        return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleCancelFailed'));
    }

    public function confirmSchedule($id)
    {
        if ($redirect = $this->requirePermission('volunteer.schedule')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $schedule = $this->getParishScopedSchedule((int) $id);
        if (!$schedule) {
            return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleNotFound'));
        }

        if (($schedule['status'] ?? '') !== 'scheduled') {
            return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleAlreadyConfirmed'));
        }

        if ($this->volunteerScheduleModel->update((int) $id, ['status' => 'confirmed'])) {
            $this->audit()->log('schedule.confirm', [
                'parish_id'   => (int) ($this->getOperationalParishId() ?? 0),
                'entity_type' => 'volunteer_schedule',
                'entity_id'   => (int) $id,
            ]);
            return redirect()->to('/volunteers/schedules')->with('success', lang('Volunteer.scheduleConfirmed'));
        }

        return redirect()->to('/volunteers/schedules')->with('error', lang('Volunteer.scheduleConfirmFailed'));
    }

    /**
     * AJAX endpoint — returns team members for a given team, scoped to the current church.
     * GET volunteers/schedules/team-members/:teamId  → JSON {members:[{user_id,name},...]}
     */
    public function teamMembersApi(int $teamId)
    {
        if (!$this->checkPermission('volunteer.schedule')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => lang('App.errorForbidden')]);
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return $this->response->setStatusCode(403)->setJSON(['error' => lang('App.errorForbidden')]);
        }

        // Ensure the team belongs to this parish.
        $team = $this->volunteerTeamModel->where('id', $teamId)->where('parish_id', $parishId)->first();
        if (!$team) {
            return $this->response->setJSON(['members' => []]);
        }

        $rows    = $this->teamMemberModel->getTeamMembers($teamId);
        $members = array_map(static fn($m) => [
            'user_id' => (int) $m['user_id'],
            'name'    => trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')),
        ], $rows);

        return $this->response->setJSON(['members' => $members]);
    }

    protected function getParishScopedSchedule(int $scheduleId): ?array
    {
        $schedule = $this->volunteerScheduleModel->find($scheduleId);
        if (!$schedule) {
            return null;
        }

        $team = $this->volunteerTeamModel->find((int) ($schedule['team_id'] ?? 0));
        if (!$team || (int) ($team['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return null;
        }

        return $schedule;
    }

    protected function buildSchedulePayload(): array
    {
        $slot = trim((string) ($this->request->getPost('service_time_slot') ?? ''));
        $service = $this->resolveLinkedService();

        $serviceId = isset($service['id']) ? (int) $service['id'] : 0;
        $date = $serviceId > 0
            ? (string) ($service['service_date'] ?? '')
            : trim((string) ($this->request->getPost('service_date') ?? ''));

        return [
            'service_id' => $serviceId > 0 ? $serviceId : null,
            'user_id' => (int) $this->request->getPost('user_id'),
            'service_date' => $date,
            'service_time_slot' => $slot !== '' ? $slot : null,
            'status' => (string) ($this->request->getPost('status') ?: 'scheduled'),
        ];
    }

    /**
     * Resolves and validates the linked service within the current church scope.
     * When a service is linked, its date is the single source of truth for schedule date.
     */
    protected function resolveLinkedService(): ?array
    {
        $serviceId = (int) ($this->request->getPost('service_id') ?? 0);
        if ($serviceId <= 0) {
            return null;
        }

        $service = model('ServiceModel')->select('id, parish_id, service_date')->find($serviceId);
        if (!$service || (int) ($service['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            throw new \RuntimeException(lang('Volunteer.invalidLinkedService'));
        }

        return $service;
    }
}
