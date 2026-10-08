<?php

namespace MemberPortal\Controllers;

use App\Controllers\AdminHierarchyGuard;
use App\Controllers\BaseController;
use App\Services\ClergyAuthorizationService;
use App\Services\HierarchyService;
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
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
            return $redirect;
        }

        if (! $this->isDioceseAdmin() && ($redirect = $this->requireParish())) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $dioceseId = $this->isDioceseAdmin() ? $this->assignedDioceseId() : 0;
        $scope = trim((string) ($this->request->getGet('scope') ?? ($parishId > 0 ? 'parish' : 'diocese')));
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }
        if (!in_array($scope, ['parish', 'deanery', 'diocese'], true)) {
            $scope = 'parish';
        }
        if (in_array($scope, ['deanery', 'diocese'], true) && ! $this->isDioceseAdmin()) {
            return $this->forbiddenResponse();
        }

        $db = db_connect();
        if ($this->isDioceseAdmin() && ($dioceseId <= 0 || ($scope === 'parish'
            && ! (new HierarchyService($db))->parishInDiocese($parishId, $dioceseId)))) {
            return $this->forbiddenResponse();
        }
        $deaneries = $dioceseId > 0 && $db->tableExists('deanery')
            ? (new HierarchyService($db))->deaneriesInDiocese($dioceseId) : [];
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return view('MemberPortal\\Views\\Leadership/index', array_merge($this->getCommonViewData('Leadership Positions'), [
                'positions' => [],
                'selected_scope' => $scope,
                'deaneries' => $deaneries,
                'is_diocese_admin' => $this->isDioceseAdmin(),
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
        if ($db->tableExists('deanery')) {
            $builder->select('dn.name as deanery_name')
                ->join('deanery dn', 'dn.id = lp.deanery_id', 'left');
        }

        if ($scope === 'parish') {
            $builder->where('lp.scope_type', 'parish')->where('lp.parish_id', $parishId);
        } elseif ($scope === 'deanery') {
            if ($dioceseId > 0 && $db->tableExists('deanery')) {
                $builder->where('lp.scope_type', 'deanery')->where('dn.diocese_id', $dioceseId);
            } else {
                $builder->where('1 = 0', null, false);
            }
        } elseif ($scope === 'diocese') {
            if ($dioceseId > 0) {
                $builder->where('lp.scope_type', 'diocese')->where('lp.diocese_id', $dioceseId);
            } else {
                $builder->where('1 = 0', null, false);
            }
        }

        // Resetting a cloned builder clears CI4's connection-wide alias map.
        // With a DB prefix the subsequent SELECT then treats "lp" as a table.
        $totalRows = (int) $builder->countAllResults(false);
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
            'deaneries' => $deaneries,
            'is_diocese_admin' => $this->isDioceseAdmin(),
            'profile_data' => $this->getProfile(),
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ]));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
            return $redirect;
        }

        if (! $this->isDioceseAdmin() && ($redirect = $this->requireParish())) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        $dioceseId = $this->isDioceseAdmin() ? $this->assignedDioceseId() : null;
        if ($this->isDioceseAdmin() && ! $dioceseId) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        $rules = ['user_id' => 'required|is_not_unique[users.id]']
            + $this->leadershipPositionService->validationRules(true);

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $targetUserId = (int) $this->request->getPost('user_id');
        if ($code = $this->guardTargetUserMutation($targetUserId)) {
            return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
        }

        $scopeType = (string) $this->request->getPost('scope_type');
        if ($scopeType === 'province' || ($scopeType !== 'parish' && ! $this->isDioceseAdmin())) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }
        $scopeIds = $this->leadershipPositionService->resolveScopeIds(
            $scopeType, (int) ($this->getOperationalParishId() ?? 0),
            $dioceseId,
            null, (int) ($this->request->getPost('deanery_id') ?? 0)
        );
        if ($scopeIds === null || ! $this->leadershipPositionService->canManage(
            array_merge(['scope_type' => $scopeType], $scopeIds),
            (int) ($this->getOperationalParishId() ?? 0),
            $dioceseId,
            null
        )) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        if ($this->lacksEcclesialAuthority($scopeType, (int) ($scopeIds['parish_id'] ?? 0), (int) ($dioceseId ?? 0))) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        $payload = array_merge($scopeIds, [
            'user_id' => $targetUserId,
            'scope_type' => $scopeType,
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'pastoral_in_charge' => $this->request->getPost('pastoral_in_charge') ? 1 : 0,
            'employment_type' => (string) ($this->request->getPost('employment_type') ?? ''),
            'active' => $this->request->getPost('active') ? 1 : 0,
            'started_at' => $this->request->getPost('started_at') ?: null,
            'ended_at' => $this->request->getPost('ended_at') ?: null,
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
            'appointed_by_user_id' => (int) auth()->id(),
        ]);

        // Populate person_id — positions created here used to write NULL,
        // which breaks person-scoped leadership lookups.
        $personId = (int) ($this->leadershipPositionService->resolvePersonIdForUser($targetUserId) ?? 0);
        $payload['person_id'] = $personId > 0 ? $personId : null;

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

        $positionId = $this->leadershipPositionService->create($payload);
        if ($positionId === null) {
            $errors = $this->leadershipPositionService->errors();
            $errorMessage = $errors !== [] ? implode(' ', array_values($errors)) : lang('Leadership.saveFailed');
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }
        $this->audit()->log('leadership_position.create', [
            'entity_type' => 'leadership_position', 'entity_id' => $positionId,
            'parish_id' => $payload['parish_id'],
        ]);

        return redirect()->to('/leadership')->with('success', lang('Leadership.saveSuccess'));
    }

    public function update($id)
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
            return $redirect;
        }

        if (! $this->isDioceseAdmin() && ($redirect = $this->requireParish())) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->back()->withInput()->with('error', lang('App.errorUnauthorized'));
        }

        $dioceseId = $this->isDioceseAdmin() ? $this->assignedDioceseId() : null;
        if ($this->isDioceseAdmin() && ! $dioceseId) {
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
            $dioceseId,
            $this->resolveAdminProvinceId()
        )) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        if ($this->lacksEcclesialAuthority(
            (string) ($position['scope_type'] ?? ''),
            (int) ($position['parish_id'] ?? 0),
            (int) ($dioceseId ?? 0)
        )) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return redirect()->back()->withInput()->with('error', $this->hierarchyGuardMessage($code));
        }

        if (!$this->validate($this->leadershipPositionService->validationRules(false))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'title' => trim((string) $this->request->getPost('title')),
            'role_code' => trim((string) ($this->request->getPost('role_code') ?? '')),
            'role_category' => (string) ($this->request->getPost('role_category') ?? ''),
            'clergy_order' => (string) ($this->request->getPost('clergy_order') ?? ''),
            'pastoral_in_charge' => $this->request->getPost('pastoral_in_charge') ? 1 : 0,
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

        if (!$this->leadershipPositionService->update((int) $id, $payload)) {
            $errors = $this->leadershipPositionService->errors();
            $errorMessage = $errors !== [] ? implode(' ', array_values($errors)) : lang('Leadership.updateFailed');
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }
        $this->audit()->log('leadership_position.update', [
            'entity_type' => 'leadership_position', 'entity_id' => (int) $id,
            'parish_id' => $position['parish_id'] ?? null,
        ]);

        return redirect()->to('/leadership')->with('success', lang('Leadership.updateSuccess'));
    }

    public function delete($id)
    {
        if ($redirect = $this->requirePermission('leadership_position.manage')) {
            return $redirect;
        }

        if (! $this->isDioceseAdmin() && ($redirect = $this->requireParish())) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        $dioceseId = $this->isDioceseAdmin() ? $this->assignedDioceseId() : null;
        if ($this->isDioceseAdmin() && ! $dioceseId) {
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
            $dioceseId,
            $this->resolveAdminProvinceId()
        )) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        if ($this->lacksEcclesialAuthority(
            (string) ($position['scope_type'] ?? ''),
            (int) ($position['parish_id'] ?? 0),
            (int) ($dioceseId ?? 0)
        )) {
            return redirect()->to('/leadership')->with('error', lang('App.errorUnauthorized'));
        }

        if ($code = $this->guardTargetUserMutation((int) ($position['user_id'] ?? 0))) {
            return redirect()->to('/leadership')->with('error', $this->hierarchyGuardMessage($code));
        }

        if (! $this->leadershipPositionService->delete((int) $id)) {
            return redirect()->to('/leadership')->with('error', lang('Leadership.deleteFailed'));
        }
        $this->audit()->log('leadership_position.retire', [
            'entity_type' => 'leadership_position', 'entity_id' => (int) $id,
            'parish_id' => $position['parish_id'] ?? null,
        ]);

        return redirect()->to('/leadership')->with('success', lang('Leadership.deleteSuccess'));
    }

    /**
     * Scoped ecclesial check against the mutation's actual target:
     * parish positions need pastoral.parish.manage over that parish;
     * deanery/diocese positions need clergy.appointment.manage over the
     * assigned diocese (canManage() already confined the target to it).
     * Fails closed and audits through the shared denial contract.
     */
    private function lacksEcclesialAuthority(string $scopeType, int $parishId, int $dioceseId): bool
    {
        if ($scopeType === 'parish') {
            $permission = 'pastoral.parish.manage';
            $targetType = 'parish';
            $targetId = $parishId;
        } elseif (in_array($scopeType, ['deanery', 'diocese'], true)) {
            $permission = 'clergy.appointment.manage';
            $targetType = 'diocese';
            $targetId = $dioceseId;
        } else {
            $permission = '';
            $targetType = $scopeType;
            $targetId = 0;
        }

        $userId = (int) (auth()->id() ?? 0);
        if ($permission !== '' && $targetId > 0
            && (new ClergyAuthorizationService())->allows($userId, $permission, $targetType, $targetId)) {
            return false;
        }

        $context = [
            'controller' => self::class,
            'permission' => $permission,
            'scope'      => $targetType,
            'target_id'  => $targetId,
        ];
        if ($targetType === 'parish' && $targetId > 0) {
            $context['parish_id'] = $targetId;
        }
        service('audit')->logAuthorizationDenial('clergy.scope.denied', 'CLERGY_SCOPE_DENIED', $context);

        return true;
    }

    /** A selected or home parish alone is not proof of diocesan admin jurisdiction. */
    private function assignedDioceseId(): int
    {
        if (! $this->isDioceseAdmin() || (int) (auth()->id() ?? 0) <= 0) {
            return 0;
        }
        try {
            $primary = model('DioceseAdminPrimaryModel')->getByUser((int) auth()->id());
            if ((int) ($primary['diocese_id'] ?? 0) > 0) {
                return (int) $primary['diocese_id'];
            }
        } catch (\Throwable $e) {
            // A non-primary admin may instead have a verified profile scope.
        }
        return max(0, (int) ($this->getProfile()['diocese_id'] ?? 0));
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
