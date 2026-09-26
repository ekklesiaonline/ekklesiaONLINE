<?php

namespace MemberPortal\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Services\LeadershipPositionService;

class LeadershipPositions extends BaseController
{
    use AdminHierarchyGuard;

    private LeadershipPositionService $leadershipPositionService;

    public function __construct()
    {
        $this->leadershipPositionService = new LeadershipPositionService();
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $scope = trim((string) ($this->request->getGet('scope') ?? 'parish'));
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }
        if (!in_array($scope, ['parish', 'diocese', 'province'], true)) {
            $scope = 'parish';
        }

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return view('MemberPortal\\Views\\Leadership/index', array_merge($this->getCommonViewData('Leadership Positions'), [
                'positions' => [],
                'selected_scope' => $scope,
                'profile_data' => $this->getProfile(),
                'page' => 1,
                'per_page' => $perPage,
                'total_rows' => 0,
                'total_pages' => 1,
            ]));
        }

        $builder = $db->table('leadership_position lp')
            ->select('lp.*, p.first_name, p.last_name, ai.secret as email, c.name as parish_name, d.name as diocese_name, pz.name as province_name')
            ->join($profileTable . ' p', 'p.user_id = lp.user_id', 'left')
            ->join('users u', 'u.id = lp.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('parish c', 'c.id = lp.parish_id', 'left')
            ->join('diocese d', 'd.id = lp.diocese_id', 'left')
            ->join('province pz', 'pz.id = lp.province_id', 'left')
            ->orderBy('lp.active', 'DESC')
            ->orderBy('lp.scope_type', 'ASC')
            ->orderBy('lp.title', 'ASC');

        if ($scope === 'parish') {
            $builder->where('lp.scope_type', 'parish')->where('lp.parish_id', $parishId);
        } elseif ($scope === 'diocese') {
            $dioceseId = (int) $this->resolveDiocesanScopeId();
            if ($dioceseId > 0) {
                $builder->where('lp.scope_type', 'diocese')->where('lp.diocese_id', $dioceseId);
            } else {
                $builder->where('1 = 0', null, false);
            }
        } else {
            $provinceId = (int) (($this->getProfile()['province_id'] ?? 0) ?: $this->resolveAdminProvinceId());
            if ($provinceId > 0) {
                $builder->where('lp.scope_type', 'province')->where('lp.province_id', $provinceId);
            } else {
                $builder->where('1 = 0', null, false);
            }
        }

        $countBuilder = clone $builder;
        $totalRows = (int) $countBuilder->countAllResults();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $positions = $builder
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return view('MemberPortal\Views\Leadership/index', array_merge($this->getCommonViewData('Leadership Positions'), [
            'positions' => $positions,
            'selected_scope' => $scope,
            'profile_data' => $this->getProfile(),
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ]));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        $rules = [
            'user_id' => 'required|is_not_unique[users.id]',
            'scope_type' => 'required|in_list[parish,diocese,province]',
            'title' => 'required|min_length[2]|max_length[150]',
            'role_category' => 'permit_empty|in_list[clergy,lay_staff]',
            'clergy_order' => 'permit_empty|in_list[bishop,priest,deacon]',
            'employment_type' => 'permit_empty|in_list[full_time,part_time,non_stipendiary,unpaid]',
            'started_at' => 'permit_empty|valid_date',
            'ended_at' => 'permit_empty|valid_date',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $targetUserId = (int) $this->request->getPost('user_id');
        if ($code = $this->guardTargetUserMutation($targetUserId)) {
            return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
        }

        $scopeType = (string) $this->request->getPost('scope_type');
        $profile = $this->getProfile() ?? [];

        $payload = [
            'user_id' => $targetUserId,
            'scope_type' => $scopeType,
            'parish_id' => null,
            'diocese_id' => null,
            'province_id' => null,
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => $this->request->getPost('active') ? 1 : 0,
            'started_at' => $this->request->getPost('started_at') ?: null,
            'ended_at' => $this->request->getPost('ended_at') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            'appointed_by_user_id' => (int) auth()->id(),
        ];

        // Populate person_id — positions created here used to write NULL,
        // which breaks person-scoped leadership lookups.
        $personId = (int) ($this->leadershipPositionService->resolvePersonIdForUser($targetUserId) ?? 0);
        $payload['person_id'] = $personId > 0 ? $personId : null;

        if ($scopeType === 'parish') {
            $payload['parish_id'] = (int) ($this->getOperationalParishId() ?? 0);
        } elseif ($scopeType === 'diocese') {
            $payload['diocese_id'] = (int) $this->resolveDiocesanScopeId();
            if ($payload['diocese_id'] <= 0) {
                return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
            }
        } else {
            $payload['province_id'] = (int) (($profile['province_id'] ?? 0) ?: $this->resolveAdminProvinceId());
            if ($payload['province_id'] <= 0) {
                return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
            }
        }

        $eligibility = $this->leadershipPositionService->eligibilityFor(
            $targetUserId,
            $personId,
            $this->leadershipPositionService->resolveEligibilityParishId($personId, $payload['parish_id'], $targetUserId),
            $payload
        );
        if (! ($eligibility['eligible'] ?? false)) {
            return redirect()->back()->withInput()->with(
                'error',
                $this->leadershipPositionService->eligibilityMessage($eligibility)
            );
        }

        $model = model('LeadershipPositionModel');
        if ($model->insert($payload) === false) {
            $errors = $model->errors();
            $errorMessage = $errors !== [] ? implode(' ', array_values($errors)) : lang('Leadership.saveFailed');
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        return redirect()->to('/leadership')->with('success', lang('Leadership.saveSuccess'));
    }

    public function update($id)
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        $model = model('LeadershipPositionModel');
        $position = $model->find((int) $id);
        if (!$position) {
            return redirect()->to('/leadership')->with('error', lang('App.errorNotFound'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if (! $this->leadershipPositionService->canManage(
            $position,
            $parishId,
            $this->resolveDiocesanScopeId(),
            $this->resolveAdminProvinceId()
        )) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
        }

        $rules = [
            'title' => 'required|min_length[2]|max_length[150]',
            'role_category' => 'permit_empty|in_list[clergy,lay_staff]',
            'clergy_order' => 'permit_empty|in_list[bishop,priest,deacon]',
            'employment_type' => 'permit_empty|in_list[full_time,part_time,non_stipendiary,unpaid]',
            'started_at' => 'permit_empty|valid_date',
            'ended_at' => 'permit_empty|valid_date',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => $this->request->getPost('active') ? 1 : 0,
            'started_at' => $this->request->getPost('started_at') ?: null,
            'ended_at' => $this->request->getPost('ended_at') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        // Reactivating a position (active 0→1) must clear the same
        // eligibility gate as a new appointment.
        if ((int) ($position['active'] ?? 0) !== 1 && $payload['active'] === 1) {
            $eligUserId = (int) ($position['user_id'] ?? 0);
            $eligPersonId = (int) ($position['person_id'] ?? 0);
            if ($eligPersonId <= 0) {
                $eligPersonId = (int) ($this->leadershipPositionService->resolvePersonIdForUser($eligUserId) ?? 0);
            }

            $eligibility = $this->leadershipPositionService->eligibilityFor(
                $eligUserId,
                $eligPersonId,
                $this->leadershipPositionService->resolveEligibilityParishId(
                    $eligPersonId,
                    (int) ($position['parish_id'] ?? 0) > 0 ? (int) $position['parish_id'] : null,
                    $eligUserId
                ),
                array_merge($position, $payload)
            );
            if (! ($eligibility['eligible'] ?? false)) {
                return redirect()->back()->withInput()->with(
                    'error',
                    $this->leadershipPositionService->eligibilityMessage($eligibility)
                );
            }
        }

        if (!$model->update((int) $id, $payload)) {
            $errors = $model->errors();
            $errorMessage = $errors !== [] ? implode(' ', array_values($errors)) : lang('Leadership.updateFailed');
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        return redirect()->to('/leadership')->with('success', lang('Leadership.updateSuccess'));
    }

    public function delete($id)
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        $model = model('LeadershipPositionModel');
        $position = $model->find((int) $id);
        if (!$position) {
            return redirect()->to('/leadership')->with('error', lang('App.errorNotFound'));
        }

        if (! $this->leadershipPositionService->canManage(
            $position,
            (int) ($this->getOperationalParishId() ?? 0),
            $this->resolveDiocesanScopeId(),
            $this->resolveAdminProvinceId()
        )) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return redirect()->to('/leadership')->with('error', $this->hierarchyGuardMessage($code));
        }

        if (! $model->delete((int) $id)) {
            return redirect()->to('/leadership')->with('error', lang('Leadership.deleteFailed'));
        }

        return redirect()->to('/leadership')->with('success', lang('Leadership.deleteSuccess'));
    }

    private function resolveExistingProfileTable($db): ?string
    {
        $prefix = (string) $db->getPrefix();
        $candidates = [];
        if ($prefix !== '') {
            $candidates[] = $prefix . 'profile';
        }
        $candidates[] = 'profile';

        foreach ($candidates as $table) {
            try {
                $db->getFieldNames($table);
                return $table;
            } catch (\Throwable $e) {
                // Try next candidate.
            }
        }

        return null;
    }
}
