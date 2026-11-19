<?php

namespace MemberPortal\Controllers;

use App\Controllers\BaseController;

class AnnualPledges extends BaseController
{
    public function index()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getGet('year') ?? date('Y'));
        if ($year < 2000 || $year > (int) date('Y') + 1) {
            $year = (int) date('Y');
        }
        $search = trim((string) ($this->request->getGet('search') ?? ''));
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if ($perPage < 10 || $perPage > 100) {
            $perPage = 25;
        }

        $db = db_connect();
        $profileTable = $this->resolveExistingProfileTable($db);
        if ($profileTable === null) {
            return view('MemberPortal\\Views\\Pledges/index', array_merge($this->getCommonViewData(lang('Pledges.pageTitle')), [
                'pledges' => [],
                'selected_year' => $year,
                'search' => $search,
                'page' => 1,
                'per_page' => $perPage,
                'total_rows' => 0,
                'total_pages' => 1,
            ]));
        }

        $builder = $db->table('annual_pledge ap')
            ->select('ap.*, p.first_name, p.last_name, ai.secret as email')
            ->join($profileTable . ' p', 'p.user_id = ap.user_id', 'left')
            ->join('users u', 'u.id = ap.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('ap.parish_id', $parishId)
            ->where('ap.pledge_year', $year)
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC');

        if ($search !== '') {
            $builder->groupStart()
                ->like('p.first_name', $search)
                ->orLike('p.last_name', $search)
                ->orLike('ai.secret', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $totalRows = (int) $countBuilder->countAllResults();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $pledges = $builder
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return view('MemberPortal\Views\Pledges/index', array_merge($this->getCommonViewData(lang('Pledges.pageTitle')), [
            'pledges' => $pledges,
            'selected_year' => $year,
            'search' => $search,
            'page' => $page,
            'per_page' => $perPage,
            'total_rows' => $totalRows,
            'total_pages' => $totalPages,
        ]));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'user_id' => 'required|is_not_unique[users.id]',
            'pledge_year' => 'required|integer|greater_than_equal_to[2000]',
            'pledged_amount' => 'permit_empty|decimal',
            'fulfilled_amount' => 'permit_empty|decimal',
            'fulfillment_status' => 'required|in_list[pledged,partial,fulfilled,cancelled]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = model('AnnualPledgeModel');
        $attributionPolicy = self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT;
        $attributionParishId = (int) ($this->resolveAttributionParishId($attributionPolicy) ?? 0);
        if ($attributionParishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }

        // The posted user_id must hold a live affiliation with the attribution
        // parish — otherwise a finance operator could file pledge rows against
        // arbitrary users in other parishes (cross-tenant IDOR).
        $targetUserId = (int) $this->request->getPost('user_id');
        $isAffiliated = model(\App\Models\MemberParishAffiliationModel::class)
            ->isUserAffiliatedWithParish($targetUserId, $attributionParishId, false);
        if (!$isAffiliated) {
            return redirect()->back()->withInput()->with('error', lang('Pledges.memberNotInParish'));
        }

        $payload = [
            'user_id' => $targetUserId,
            'parish_id' => $attributionParishId,
            'pledge_year' => (int) $this->request->getPost('pledge_year'),
            'pledged_amount' => $this->request->getPost('pledged_amount') !== '' ? (float) $this->request->getPost('pledged_amount') : null,
            'fulfilled_amount' => $this->request->getPost('fulfilled_amount') !== '' ? (float) $this->request->getPost('fulfilled_amount') : 0,
            'pledged_at' => $this->request->getPost('pledged_at') ?: null,
            'fulfillment_status' => (string) $this->request->getPost('fulfillment_status'),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if ($model->insert($payload) === false) {
            $errors = $model->errors();
            $errorMessage = $errors !== [] ? implode(' ', array_values($errors)) : lang('Pledges.saveFailed');
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        $pledgeId = (int) $model->getInsertID();

        // Recalculate fulfillment from any existing donations for this member and year
        service('pledgeFulfillment')->recalculatePledge(
            (int) $payload['user_id'],
            $attributionParishId,
            (int) $payload['pledge_year']
        );

        $this->audit()->log('annual_pledge.create', [
            'parish_id' => $attributionParishId,
            'entity_type' => 'annual_pledge',
            'entity_id' => $pledgeId,
            'new_values' => $this->redactPledgeForAudit($payload),
            'meta' => $this->buildParishContextMeta($attributionParishId, $attributionPolicy),
        ]);

        return redirect()->to('/pledges?year=' . (int) $payload['pledge_year'])->with('success', lang('App.pledgeSavedSuccess'));
    }

    public function update($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $model = model('AnnualPledgeModel');
        $pledge = $model->find((int) $id);
        if (!$pledge || (int) ($pledge['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return redirect()->to('/pledges')->with('error', lang('App.pledgeNotFound'));
        }

        $rules = [
            'pledged_amount' => 'permit_empty|decimal',
            'fulfilled_amount' => 'permit_empty|decimal',
            'fulfillment_status' => 'required|in_list[pledged,partial,fulfilled,cancelled]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = [
            'pledged_amount' => $this->request->getPost('pledged_amount') !== '' ? (float) $this->request->getPost('pledged_amount') : null,
            'fulfilled_amount' => $this->request->getPost('fulfilled_amount') !== '' ? (float) $this->request->getPost('fulfilled_amount') : 0,
            'pledged_at' => $this->request->getPost('pledged_at') ?: null,
            'fulfillment_status' => (string) $this->request->getPost('fulfillment_status'),
            'notes' => trim((string) ($this->request->getPost('notes') ?? '')),
        ];

        if (!$model->update((int) $id, $payload)) {
            $errors = $model->errors();
            $errorMessage = $errors !== [] ? implode(' ', array_values($errors)) : lang('Pledges.updateFailed');
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

        $this->audit()->log('annual_pledge.update', [
            'parish_id' => (int) ($pledge['parish_id'] ?? 0),
            'entity_type' => 'annual_pledge',
            'entity_id' => (int) $id,
            'old_values' => $this->redactPledgeForAudit($pledge),
            'new_values' => $this->redactPledgeForAudit($payload),
            'meta' => $this->buildParishContextMeta((int) ($pledge['parish_id'] ?? 0), self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        return redirect()->to('/pledges?year=' . (int) ($pledge['pledge_year'] ?? date('Y')))->with('success', lang('App.pledgeUpdatedSuccess'));
    }

    public function delete($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $model = model('AnnualPledgeModel');
        $pledge = $model->find((int) $id);
        if (!$pledge || (int) ($pledge['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return redirect()->to('/pledges')->with('error', lang('App.pledgeNotFound'));
        }

        $this->audit()->log('annual_pledge.delete', [
            'parish_id' => (int) ($pledge['parish_id'] ?? 0),
            'entity_type' => 'annual_pledge',
            'entity_id' => (int) $id,
            'old_values' => $this->redactPledgeForAudit($pledge),
            'meta' => $this->buildParishContextMeta((int) ($pledge['parish_id'] ?? 0), self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);

        $model->delete((int) $id);

        return redirect()->to('/pledges?year=' . (int) ($pledge['pledge_year'] ?? date('Y')))->with('success', lang('App.pledgeDeletedSuccess'));
    }

    /**
     * Strip financial values and freeform notes before audit logging —
     * audit entries must never carry pledged/fulfilled amounts or notes.
     *
     * @param array<string,mixed> $pledge
     * @return array<string,mixed>
     */
    private function redactPledgeForAudit(array $pledge): array
    {
        return array_diff_key($pledge, array_flip([
            'pledged_amount',
            'fulfilled_amount',
            'notes',
        ]));
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
