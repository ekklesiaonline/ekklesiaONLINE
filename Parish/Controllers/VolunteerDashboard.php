<?php

namespace Parish\Controllers;

use App\Controllers\BaseController;

/**
 * Parish volunteer dashboard — volunteer teams and schedules hub
 * for the volunteer coordinator role.
 */
class VolunteerDashboard extends BaseController
{
    public function index()
    {
        if (!auth()->loggedIn()) {
            return redirect()->to('/login');
        }

        $parishId = $this->getOperationalParishId();

        if (!$parishId) {
            return redirect()->to('/profile')->with('error', lang('App.errorNotFound'));
        }

        $parishId = (int) $parishId;

        // Use existing models with correct table names.
        // TeamModel uses the 'team' table (not 'volunteer_team').
        $teams = model('TeamModel')->getParishTeams($parishId);

        // VolunteerScheduleModel uses the 'volunteer_schedule' table.
        // It has no soft deletes, so we filter by upcoming service_date.
        $upcomingSchedules = model('VolunteerScheduleModel')
            ->select('volunteer_schedule.*, t.name AS team_name')
            ->join('team t', 't.id = volunteer_schedule.team_id', 'left')
            ->where('t.parish_id', $parishId)
            ->where('volunteer_schedule.service_date >=', date('Y-m-d'))
            ->orderBy('volunteer_schedule.service_date', 'ASC')
            ->limit(10)
            ->findAll();

        $canManage = auth()->user()->can('volunteer.manage');
        $canSchedule = auth()->user()->can('volunteer.schedule');

        $data = array_merge($this->getCommonViewData(lang('Dashboard.parishVolunteerDashboard')), [
            'parish_id' => $parishId,
            'teams' => $teams,
            'upcoming_schedules' => $upcomingSchedules,
            'can_manage' => $canManage,
            'can_schedule' => $canSchedule,
        ]);

        return view('Parish\Views\Volunteer\index', $data);
    }
}
