<?php

namespace App\Controllers;

use App\Services\ClergyReviewService;
use App\Services\ClergyAuthorizationService;
use App\Services\LeadershipPositionService;

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
            ->select('lp.id AS position_id, lp.user_id, lp.diocese_id, lp.started_at, profile.first_name, profile.last_name')
            ->join('profile', 'profile.user_id = lp.user_id', 'left')
            ->where('lp.scope_type', 'diocese')->where('lp.role_code', 'diocesan_bishop')
            ->where('lp.active', 1)->where('lp.approved_at IS NOT NULL', null, false)
            ->where('lp.revoked_at', null)->where('lp.started_at <=', $today)
            ->groupStart()->where('lp.ended_at', null)->orWhere('lp.ended_at >=', $today)->groupEnd()
            ->orderBy('lp.started_at', 'DESC')->orderBy('lp.id', 'DESC')
            ->get()->getResultArray();
        $ordinaryByDiocese = [];
        $authorization = new ClergyAuthorizationService();
        $positions = new LeadershipPositionService();
        foreach ($ordinaryRows as $ordinary) {
            $ordinary['effective'] = $authorization->isCurrentOrdinary((int) $ordinary['user_id'],
                (int) $ordinary['diocese_id']);
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
            $personId = $positions->resolvePersonIdForUser((int) $candidate['user_id']);
            if (($personId ?? 0) > 0 && $authorization->hasSystemWideSuspension($personId)) {
                continue;
            }
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
            'user_id' => [
                'label' => lang('System.diocesanOrdinaryUser'),
                'rules' => 'required|is_natural_no_zero',
            ],
            'office_started_on' => [
                'label' => lang('System.diocesanOrdinaryOfficeStartedOn'),
                'rules' => 'required|valid_date[Y-m-d]',
            ],
            'ordained_on' => [
                'label' => lang('System.diocesanOrdinaryOrdainedOn'),
                'rules' => 'permit_empty|valid_date[Y-m-d]',
            ],
            'evidence_reference' => [
                'label' => lang('System.diocesanOrdinaryEvidenceReference'),
                'rules' => 'required|max_length[255]',
            ],
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())
                ->with('open_modal', 'installOrdinaryModal' . $dioceseId);
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
            return redirect()->back()->withInput()->with('error', lang('System.diocesanOrdinaryInstallFailed'))
                ->with('open_modal', 'installOrdinaryModal' . $dioceseId);
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

    public function endOffice(int $positionId)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }
        if (! $this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.errorForbidden'));
        }

        $position = db_connect()->table('leadership_position')->select('id, diocese_id, user_id')
            ->where('id', $positionId)->get()->getRowArray();
        $dioceseId = (int) ($position['diocese_id'] ?? 0);
        if ($position === null || $dioceseId <= 0) {
            return redirect()->to(base_url('system/diocesan-ordinaries'))
                ->with('error', lang('System.diocesanOrdinaryEndFailed'));
        }

        $rules = [
            'ended_on' => [
                'label' => lang('System.diocesanOrdinaryEndOn'),
                'rules' => 'required|valid_date[Y-m-d]',
            ],
            'reason' => [
                'label' => lang('System.diocesanOrdinaryEndReason'),
                'rules' => 'required|max_length[255]',
            ],
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())
                ->with('open_modal', 'endOfficeModal' . $dioceseId);
        }

        $endedOn = (string) $this->request->getPost('ended_on');
        $service = new ClergyReviewService();
        $success = $service->endOrdinaryOffice(
            (int) auth()->id(),
            $positionId,
            $endedOn,
            (string) $this->request->getPost('reason')
        );
        if (! $success) {
            return redirect()->back()->withInput()->with('error', lang('System.diocesanOrdinaryEndFailed'))
                ->with('open_modal', 'endOfficeModal' . $dioceseId);
        }

        $this->audit()->log('system.diocesan_ordinary.ended', [
            'user_id' => (int) auth()->id(),
            'entity_type' => 'diocese',
            'entity_id' => $dioceseId,
            'ip_address' => $this->request->getIPAddress(),
            'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
            'timestamp' => date('Y-m-d H:i:s'),
            'new_values' => [
                'user_id' => (int) $position['user_id'],
                'position_id' => $positionId,
                'ended_on' => $endedOn,
                'delegations_revoked' => count($service->lastRevokedDelegationIds()),
            ],
        ]);

        return redirect()->to(base_url('system/diocesan-ordinaries'))
            ->with('success', lang('System.diocesanOrdinaryEnded'));
    }
}
