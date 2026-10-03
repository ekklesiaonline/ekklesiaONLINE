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
     * Resolve the canonical person id for a portal user (null when unlinked).
     */
    private function resolvePersonIdForUser(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $row = db_connect()->table('user_person')
                ->select('person_id')
                ->where('user_id', $userId)
                ->orderBy('is_primary', 'DESC')
                ->get()
                ->getRowArray();
        } catch (\Throwable $e) {
            return null;
        }

        $personId = (int) ($row['person_id'] ?? 0);

        return $personId > 0 ? $personId : null;
    }

    /**
     * Best-effort pathway stage recompute after a team roster change.
     * Team placement is the Stage 3 criterion.
     */
    private function recomputePathwayStage(?int $personId): void
    {
        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        if ($personId === null || $personId <= 0 || $parishId <= 0) {
            return;
        }

        try {
            (new \App\Services\PathwayStageService())->recompute($personId, $parishId);
        } catch (\Throwable $e) {
            log_message('error', 'Pathway stage recompute failed after team change: ' . $e->getMessage());
        }
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

        $data = [
            'team' => $team,
            'members' => $members,
            'schedules' => $schedules,
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

        $personId = $this->resolvePersonIdForUser((int) $userId);

        $data = [
            'team_id'   => $teamId,
            'user_id'   => $userId,
            'person_id' => $personId,
            'role'      => $role ?? 'member',
        ];

        if ($this->teamMemberModel->insert($data)) {
            $teamMemberId = (int) $this->teamMemberModel->getInsertID();
            $this->recomputePathwayStage($personId);
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
            $memberPersonId = (int) ($member['person_id'] ?? 0);
            $this->recomputePathwayStage($memberPersonId > 0 ? $memberPersonId : $this->resolvePersonIdForUser((int) ($member['user_id'] ?? 0)));
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
}
