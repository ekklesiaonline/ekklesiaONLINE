<?php

namespace Finance\Controllers;

use App\Controllers\BaseController;
use App\Models\FundModel;
use App\Services\FinanceReadinessService;

/**
 * Parish fund management: list with balances, create, edit, activate/deactivate
 * and lock/unlock. Every operation is scoped to the operational parish; funds
 * are never deleted (they carry donation/expense history).
 */
class Funds extends BaseController
{
    private const FUND_TYPES = ['unrestricted', 'restricted', 'permanent'];

    protected FundModel $fundModel;

    public function __construct()
    {
        $this->fundModel = model(FundModel::class);
    }

    /**
     * List the parish's funds with computed balances.
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $status   = (string) ($this->request->getGet('status') ?? 'active');
        if (! in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $funds = $this->fundModel->getParishFundsWithBalances($parishId, false);

        $totals = ['unrestricted' => 0.0, 'restricted' => 0.0, 'permanent' => 0.0];
        foreach ($funds as $fund) {
            if ((int) ($fund['is_active'] ?? 0) === 1 && isset($totals[$fund['fund_type']])) {
                $totals[$fund['fund_type']] += (float) $fund['balance'];
            }
        }

        if ($status !== 'all') {
            $wantActive = $status === 'active' ? 1 : 0;
            $funds      = array_values(array_filter(
                $funds,
                static fn (array $fund): bool => (int) ($fund['is_active'] ?? 0) === $wantActive
            ));
        }

        return view('Finance\Views\Funds/index', array_merge($this->getCommonViewData(lang('Finance.fundsTitle')), [
            'funds'     => $funds,
            'totals'    => $totals,
            'status'    => $status,
            'readiness' => (new FinanceReadinessService())->readinessForParish($parishId),
            'canManage' => auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * Fund ledger / drill-down view.
     */
    public function view($id)
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fundId   = (int) $id;

        $fund = $this->fundModel->getFundWithBalance($fundId);
        if ($fund === null || (int) ($fund['parish_id'] ?? 0) !== $parishId) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundNotFound'));
        }

        $db = db_connect();

        $donations = $db->table('donation d')
            ->select('d.*, p.first_name, p.last_name')
            ->join('profile p', 'p.user_id = d.user_id', 'left')
            ->where('d.fund_id', $fundId)
            ->where('d.deleted_at', null)
            ->orderBy('d.date', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        foreach ($donations as &$donation) {
            $decrypted = \App\Libraries\DataEncryption::decrypt(isset($donation['amount']) ? (string) $donation['amount'] : null);
            $donation['amount'] = (float) ($decrypted ?: 0);
        }
        unset($donation);

        $expenses = $db->table('expense e')
            ->select('e.*')
            ->where('e.fund_id', $fundId)
            ->orderBy('e.date', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();

        return view('Finance\Views\Funds/view', array_merge($this->getCommonViewData(lang('Finance.fundLedger')), [
            'fund'      => $fund,
            'donations' => $donations,
            'expenses'  => $expenses,
            'canManage' => auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * New fund form.
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        return view('Finance\Views\Funds/form', array_merge($this->getCommonViewData(lang('Finance.fundCreate')), [
            'fund'             => null,
            'typeChangeLocked' => false,
            'chartAccounts'    => model(\App\Models\ChartOfAccountModel::class)->getActiveByType('income'),
        ]));
    }

    public function setup()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('App.errorNoParishScope'));
        }

        try {
            $result = (new FinanceReadinessService())->provisionDefaultFund($parishId, (int) auth()->id());
        } catch (\Throwable $e) {
            return redirect()->to('/funds')->with('error', lang('Finance.financeSetupFailed'));
        }

        if (! $result['created']) {
            return redirect()->to('/funds')->with('error', lang('Finance.financeSetupExistingFunds'));
        }

        return redirect()->to('/funds/edit/' . (int) ($result['fund']['id'] ?? 0))->with('success', lang('Finance.financeSetupCreated'));
    }

    /**
     * Persist a new fund.
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);

        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->extractPayload();

        if ($error = $this->payloadError($payload, $parishId, null)) {
            return redirect()->back()->withInput()->with('errors', $error);
        }

        $payload['parish_id']  = $parishId;
        $payload['is_active']  = 1;
        $payload['is_locked']  = 0;
        $payload['created_by'] = (int) auth()->id();

        $fundId = $this->fundModel->insert($payload, true);
        if ($fundId === false) {
            return redirect()->back()->withInput()->with('error', lang('Finance.fundSaveFailed'));
        }

        $this->auditFund('fund.create', $parishId, (int) $fundId, null, $payload);

        return redirect()->to('/funds')->with('success', lang('Finance.fundCreated'));
    }

    /**
     * Edit fund form.
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fund     = $this->findInParish((int) $id, $parishId);

        if ($fund === null) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundNotFound'));
        }

        if ((int) $fund['is_locked'] === 1) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundLockedNoEdit'));
        }

        return view('Finance\Views\Funds/form', array_merge($this->getCommonViewData(lang('Finance.fundEdit')), [
            'fund'             => $fund,
            'typeChangeLocked' => $this->hasTransactions((int) $fund['id']),
            'chartAccounts'    => model(\App\Models\ChartOfAccountModel::class)->getActiveByType('income'),
        ]));
    }

    /**
     * Update an existing fund.
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fund     = $this->findInParish((int) $id, $parishId);

        if ($fund === null) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundNotFound'));
        }

        if ((int) $fund['is_locked'] === 1) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundLockedNoEdit'));
        }

        if (! $this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->extractPayload();

        // Reclassifying a fund that already has history would silently change
        // past restricted/unrestricted reporting.
        if ($payload['fund_type'] !== $fund['fund_type'] && $this->hasTransactions((int) $fund['id'])) {
            return redirect()->back()->withInput()->with('errors', ['fund_type' => lang('Finance.fundTypeChangeBlocked')]);
        }

        if ($error = $this->payloadError($payload, $parishId, (int) $fund['id'])) {
            return redirect()->back()->withInput()->with('errors', $error);
        }

        if (! $this->fundModel->update((int) $fund['id'], $payload)) {
            return redirect()->back()->withInput()->with('error', lang('Finance.fundSaveFailed'));
        }

        $this->auditFund('fund.update', $parishId, (int) $fund['id'], $this->auditSnapshot($fund), $payload);

        return redirect()->to('/funds')->with('success', lang('Finance.fundUpdated'));
    }

    /**
     * @return array<string, string>
     */
    private function validationRules(): array
    {
        return [
            'name'                => 'required|min_length[3]|max_length[255]',
            'description'         => 'permit_empty|max_length[1000]',
            'fund_type'           => 'required|in_list[' . implode(',', self::FUND_TYPES) . ']',
            'restriction_purpose' => 'permit_empty|max_length[255]',
            'chart_account_id'    => 'permit_empty|is_not_unique[chart_of_accounts.id]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(): array
    {
        $fundType       = (string) $this->request->getPost('fund_type');
        $purpose        = trim((string) ($this->request->getPost('restriction_purpose') ?? ''));
        $chartAccountId = (int) $this->request->getPost('chart_account_id');

        return [
            'name'                => trim((string) $this->request->getPost('name')),
            'description'         => trim((string) ($this->request->getPost('description') ?? '')) ?: null,
            'fund_type'           => $fundType,
            // Purpose only applies to donor-restricted funds.
            'restriction_purpose' => $fundType === 'unrestricted' ? null : ($purpose !== '' ? $purpose : null),
            'chart_account_id'    => $chartAccountId > 0 ? $chartAccountId : null,
        ];
    }

    /**
     * Cross-field checks: restriction purpose and per-parish unique name.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, string>|null
     */
    private function payloadError(array $payload, int $parishId, ?int $ignoreId): ?array
    {
        if ($payload['fund_type'] !== 'unrestricted' && $payload['restriction_purpose'] === null) {
            return ['restriction_purpose' => lang('Finance.fundPurposeRequired')];
        }

        if ($payload['chart_account_id'] !== null) {
            $chartAccount = model(\App\Models\ChartOfAccountModel::class)
                ->where('id', (int) $payload['chart_account_id'])
                ->where('is_active', 1)
                ->where('account_type', 'income')
                ->first();
            if (! is_array($chartAccount)) {
                return ['chart_account_id' => lang('Finance.fundIncomeAccountRequired')];
            }
        }

        $builder = $this->fundModel->builder()
            ->where('parish_id', $parishId)
            ->where('name', $payload['name']);

        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }

        if ($builder->countAllResults() > 0) {
            return ['name' => lang('Finance.fundNameTaken')];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findInParish(int $fundId, int $parishId): ?array
    {
        $fund = $this->fundModel->find($fundId);

        if (! is_array($fund) || (int) ($fund['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        return $fund;
    }

    private function hasTransactions(int $fundId): bool
    {
        $db = db_connect();

        $donations = $db->table('donation')->where('fund_id', $fundId)->where('deleted_at', null)->countAllResults();
        if ($donations > 0) {
            return true;
        }

        return $db->table('expense')->where('fund_id', $fundId)->countAllResults() > 0;
    }

    /**
     * Free-text fields (description) are deliberately excluded from audit payloads.
     *
     * @param array<string, mixed> $fund
     *
     * @return array<string, mixed>
     */
    private function auditSnapshot(array $fund): array
    {
        return array_intersect_key($fund, array_flip(['name', 'fund_type', 'restriction_purpose', 'chart_account_id']));
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>      $new
     */
    protected function auditFund(string $action, int $parishId, int $fundId, ?array $old, array $new): void
    {
        $this->audit()->log($action, [
            'parish_id'   => $parishId,
            'entity_type' => 'fund',
            'entity_id'   => $fundId,
            'old_values'  => $old,
            'new_values'  => array_intersect_key($new, array_flip(['name', 'fund_type', 'restriction_purpose', 'chart_account_id', 'is_active', 'is_locked'])),
            'meta'        => $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
        ]);
    }
}
