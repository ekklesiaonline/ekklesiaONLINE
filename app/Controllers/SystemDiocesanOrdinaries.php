<?php

namespace App\Controllers;

use App\Services\ClergyReviewService;

class SystemDiocesanOrdinaries extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }
        if (! $this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $db = db_connect();
        $dioceses = $db->table('diocese d')
            ->select('d.id, d.name AS diocese_name, province.name AS province_name')
            ->join('province', 'province.id = d.province_id', 'left')
            ->orderBy('province.name')->orderBy('d.name')->get()->getResultArray();
        $today = date('Y-m-d');
        $ordinaryRows = $db->table('leadership_position lp')
            ->select('lp.diocese_id, lp.started_at, profile.first_name, profile.last_name')
            ->join('profile', 'profile.user_id = lp.user_id', 'left')
            ->where('lp.scope_type', 'diocese')->where('lp.role_code', 'diocesan_bishop')
            ->where('lp.active', 1)->where('lp.approved_at IS NOT NULL', null, false)
            ->where('lp.revoked_at', null)->where('lp.started_at <=', $today)
            ->groupStart()->where('lp.ended_at', null)->orWhere('lp.ended_at >=', $today)->groupEnd()
            ->orderBy('lp.started_at', 'DESC')->orderBy('lp.id', 'DESC')
            ->get()->getResultArray();
        $ordinaryByDiocese = [];
        foreach ($ordinaryRows as $ordinary) {
            $ordinaryByDiocese[(int) $ordinary['diocese_id']] ??= $ordinary;
        }

        $candidateRows = $db->table('users u')
            ->select('u.id AS user_id, profile.diocese_id, profile.first_name, profile.last_name')
            ->distinct()
            ->join('profile', 'profile.user_id = u.id')
            ->join('auth_groups_users agu', 'agu.user_id = u.id')
            ->join('auth_identities ai', "ai.user_id = u.id AND ai.type = 'email_password'")
            ->where('agu.group', 'dioceseadmin')->where('u.active', 1)
            ->where('profile.portal_access', 'enabled')
            ->orderBy('profile.first_name')->orderBy('profile.last_name')
            ->get()->getResultArray();
        $candidatesByDiocese = [];
        foreach ($candidateRows as $candidate) {
            $candidatesByDiocese[(int) $candidate['diocese_id']][] = $candidate;
        }

        return view('System/diocesan_ordinaries', array_merge(
            $this->getCommonViewData(lang('System.diocesanOrdinariesTitle')),
            [
                'dioceses' => $dioceses,
                'ordinary_by_diocese' => $ordinaryByDiocese,
                'candidates_by_diocese' => $candidatesByDiocese,
            ]
        ));
    }

    public function install(int $dioceseId)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }
        if (! $this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $rules = [
            'user_id' => 'required|is_natural_no_zero',
            'office_started_on' => 'required|valid_date[Y-m-d]',
            'ordained_on' => 'permit_empty|valid_date[Y-m-d]',
            'evidence_reference' => 'required|max_length[255]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $userId = (int) $this->request->getPost('user_id');
        $ordainedOn = trim((string) $this->request->getPost('ordained_on'));
        $positionId = (new ClergyReviewService())->installOrdinary(
            (int) auth()->id(),
            $dioceseId,
            $userId,
            $ordainedOn === '' ? null : $ordainedOn,
            (string) $this->request->getPost('office_started_on'),
            (string) $this->request->getPost('evidence_reference')
        );
        if ($positionId === null) {
            return redirect()->back()->withInput()->with('error', lang('System.diocesanOrdinaryInstallFailed'));
        }

        $this->audit()->log('system.diocesan_ordinary.installed', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'diocese',
            'entity_id' => $dioceseId,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'new_values' => [
                'user_id' => $userId,
                'position_id' => $positionId,
            ],
        ]);

        return redirect()->to(base_url('system/diocesan-ordinaries'))
            ->with('success', lang('System.diocesanOrdinaryInstalled'));
    }
}
