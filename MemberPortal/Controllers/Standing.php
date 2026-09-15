<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;

class Standing extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $year = (int) ($this->request->getGet('year') ?? date('Y'));

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0 && !$this->isProvinceAdmin() && !$this->isDioceseAdmin()) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        $scope = 'parish';
        if ($this->isProvinceAdmin()) {
            $scope = 'province';
        } elseif ($this->isDioceseAdmin()) {
            $scope = 'diocese';
        }

        $summary = $this->buildSummary($scope, $parishId, $year);

        return view('MemberPortal\Views\Standing/index', array_merge($this->getCommonViewData(lang('Standing.pageTitle')), [
            'selected_year' => $year,
            'scope' => $scope,
            'summary' => $summary,
        ]));
    }

    public function refresh()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $redirect;
        }

        $year = (int) ($this->request->getPost('year') ?? date('Y'));
        $count = 0;

        /** @var \App\Models\MemberStandingSummaryModel $standingModel */
        $standingModel = model(\App\Models\MemberStandingSummaryModel::class);

        $scope = 'parish';
        if ($this->isProvinceAdmin()) {
            $scope = 'province';
        } elseif ($this->isDioceseAdmin()) {
            $scope = 'diocese';
        } else {
            $parishId = (int) ($this->getOperationalParishId() ?? 0);
            if ($parishId <= 0) {
                return redirect()->to('/standing')->with('error', lang('App.errorNoParishScope'));
            }
        }

        $count = $standingModel->refreshForYear($year);

        return redirect()->to('/standing?year=' . $year)->with('success', lang('Standing.refreshSuccess', [$count, $scope]));
    }

    public function summaryApi()
    {
        if ($redirect = $this->requirePermission('member.edit')) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorForbidden'), 403, 'FORBIDDEN'), 403);
        }

        $year = (int) ($this->request->getGet('year') ?? date('Y'));
        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0 && !$this->isProvinceAdmin() && !$this->isDioceseAdmin()) {
            return $this->jsonResponse($this->apiErrorPayload(lang('App.errorNoParishScope'), 403, 'PARISH_SCOPE_REQUIRED'), 403);
        }

        $scope = 'parish';
        if ($this->isProvinceAdmin()) {
            $scope = 'province';
        } elseif ($this->isDioceseAdmin()) {
            $scope = 'diocese';
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'year' => $year,
                'scope' => $scope,
                'summary' => $this->buildSummary($scope, $parishId, $year),
            ],
        ]);
    }

    private function buildSummary(string $scope, int $parishId, int $year): array
    {
        $db = db_connect();
        // Check if is_member column exists
        $hasIsMember = false;
        try {
            $columns = $db->getFieldNames($db->getPrefix() . 'parish_affiliation');
            $hasIsMember = in_array('is_member', array_map('strtolower', $columns), true);
        } catch (\Throwable $e) {
        }

        $memberJoin = 'mca.linked_user_id = mss.user_id AND mca.is_member = 1 AND mca.membership_status IN ("member", "inactive")';
        if ($hasIsMember) {
            $memberJoin .= ' AND mca.is_member = 1';
        }

        if ($scope === 'province') {
            $provinceId = (int) $this->resolveAdminProvinceId();

            $eligible = $db->table('member_standing_summary mss')
                ->select('COUNT(DISTINCT mss.user_id) as count')
                ->join('parish_affiliation mca', $memberJoin, 'inner')
                ->join('parish c', 'c.id = mca.parish_id')
                ->join('diocese d', 'd.id = c.diocese_id')
                ->join('users u', 'u.id = mss.user_id', 'inner')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
                ->where('d.province_id', $provinceId)
                ->where('mss.year', $year)
                ->where('mss.is_good_standing', 1)
                ->where('agu.id IS NULL')
                ->get()->getRowArray();

            $total = $db->table('member_standing_summary mss')
                ->select('COUNT(DISTINCT mss.user_id) as count')
                ->join('parish_affiliation mca', $memberJoin, 'inner')
                ->join('parish c', 'c.id = mca.parish_id')
                ->join('diocese d', 'd.id = c.diocese_id')
                ->join('users u', 'u.id = mss.user_id', 'inner')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
                ->where('d.province_id', $provinceId)
                ->where('mss.year', $year)
                ->where('agu.id IS NULL')
                ->get()->getRowArray();

            return [
                'total' => (int) ($total['count'] ?? 0),
                'good_standing' => (int) ($eligible['count'] ?? 0),
            ];
        }

        if ($scope === 'diocese') {
            $dioceseId = (int) $this->resolveDiocesanScopeId();

            $eligible = $db->table('member_standing_summary mss')
                ->select('COUNT(DISTINCT mss.user_id) as count')
                ->join('parish_affiliation mca', $memberJoin, 'inner')
                ->join('parish c', 'c.id = mca.parish_id')
                ->join('users u', 'u.id = mss.user_id', 'inner')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
                ->where('c.diocese_id', $dioceseId)
                ->where('mss.year', $year)
                ->where('mss.is_good_standing', 1)
                ->where('agu.id IS NULL')
                ->get()->getRowArray();

            $total = $db->table('member_standing_summary mss')
                ->select('COUNT(DISTINCT mss.user_id) as count')
                ->join('parish_affiliation mca', $memberJoin, 'inner')
                ->join('parish c', 'c.id = mca.parish_id')
                ->join('users u', 'u.id = mss.user_id', 'inner')
                ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
                ->where('c.diocese_id', $dioceseId)
                ->where('mss.year', $year)
                ->where('agu.id IS NULL')
                ->get()->getRowArray();

            return [
                'total' => (int) ($total['count'] ?? 0),
                'good_standing' => (int) ($eligible['count'] ?? 0),
            ];
        }

        $eligible = $db->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) as count')
            ->join('parish_affiliation mca', $memberJoin, 'inner')
            ->join('users u', 'u.id = mss.user_id', 'inner')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mss.year', $year)
            ->where('mss.is_good_standing', 1)
            ->where('agu.id IS NULL')
            ->get()->getRowArray();

        $total = $db->table('member_standing_summary mss')
            ->select('COUNT(DISTINCT mss.user_id) as count')
            ->join('parish_affiliation mca', $memberJoin, 'inner')
            ->join('users u', 'u.id = mss.user_id', 'inner')
            ->join('auth_groups_users agu', 'agu.user_id = u.id AND agu.group IN ("provinceadmin","diocesanadmin")', 'left')
            ->where('mca.parish_id', $parishId)
            ->where('mss.year', $year)
            ->where('agu.id IS NULL')
            ->get()->getRowArray();

        return [
            'total' => (int) ($total['count'] ?? 0),
            'good_standing' => (int) ($eligible['count'] ?? 0),
        ];
    }
}
