<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;
use App\Models\FundModel;

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

        return view('Treasurer\Views\Funds/index', array_merge($this->getCommonViewData(lang('Finance.fundsTitle')), [
            'funds'     => $funds,
            'totals'    => $totals,
            'status'    => $status,
            'canManage' => auth()->user()->can('finance.manage'),
        ]));
    }

    /**
     * Export fund balances to CSV.
     */
    public function export()
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

        if ($status !== 'all') {
            $wantActive = $status === 'active' ? 1 : 0;
            $funds      = array_values(array_filter(
                $funds,
                static fn (array $fund): bool => (int) ($fund['is_active'] ?? 0) === $wantActive
            ));
        }

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundSaveFailed'));
        }

        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, [
            lang('Finance.fundName'),
            lang('Finance.fundType'),
            lang('Finance.fundRestrictionPurpose'),
            lang('Finance.chartOfAccountField'),
            lang('Finance.fundIncome'),
            lang('Finance.fundExpensesTotal'),
            lang('Finance.fundBalance'),
            lang('Finance.donationsTitle'),
            lang('Finance.expensesTitle'),
            lang('App.colStatus'),
            lang('Finance.fundLockedBadge'),
        ]);

        foreach ($funds as $fund) {
            fputcsv($handle, [
                (string) ($fund['name'] ?? ''),
                (string) ($fund['fund_type'] ?? ''),
                (string) ($fund['restriction_purpose'] ?? ''),
                trim(($fund['chart_account_code'] ?? '') . ' ' . ($fund['chart_account_name'] ?? '')),
                number_format((float) ($fund['total_income'] ?? 0), 2, '.', ''),
                number_format((float) ($fund['total_expenses'] ?? 0), 2, '.', ''),
                number_format((float) ($fund['balance'] ?? 0), 2, '.', ''),
                (int) ($fund['donation_count'] ?? 0),
                (int) ($fund['expense_count'] ?? 0),
                (int) ($fund['is_active'] ?? 0) === 1 ? lang('Finance.fundStatusActive') : lang('Finance.fundStatusInactive'),
                (int) ($fund['is_locked'] ?? 0) === 1 ? lang('App.yes') : lang('App.no'),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $this->audit()->log('fund.export_csv', [
            'parish_id'   => $parishId,
            'entity_type' => 'fund',
            'entity_id'   => null,
            'meta'        => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count'  => count($funds),
                    'status' => $status,
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="funds-' . date('Y-m-d') . '.csv"')
            ->setBody($csv ?: '');
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

        return view('Treasurer\Views\Funds/view', array_merge($this->getCommonViewData(lang('Finance.fundLedger')), [
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

        return view('Treasurer\Views\Funds/form', array_merge($this->getCommonViewData(lang('Finance.fundCreate')), [
            'fund'             => null,
            'typeChangeLocked' => false,
            'chartAccounts'    => model(\App\Models\ChartOfAccountModel::class)->getActiveByType(),
        ]));
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

        return view('Treasurer\Views\Funds/form', array_merge($this->getCommonViewData(lang('Finance.fundEdit')), [
            'fund'             => $fund,
            'typeChangeLocked' => $this->hasTransactions((int) $fund['id']),
            'chartAccounts'    => model(\App\Models\ChartOfAccountModel::class)->getActiveByType(),
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

    public function lock($id)
    {
        return $this->setFlag((int) $id, 'is_locked', 1, 'fund.lock', 'Finance.fundLocked');
    }

    public function unlock($id)
    {
        return $this->setFlag((int) $id, 'is_locked', 0, 'fund.unlock', 'Finance.fundUnlocked');
    }

    public function activate($id)
    {
        return $this->setFlag((int) $id, 'is_active', 1, 'fund.activate', 'Finance.fundActivated');
    }

    public function deactivate($id)
    {
        return $this->setFlag((int) $id, 'is_active', 0, 'fund.deactivate', 'Finance.fundDeactivated');
    }

    /**
     * Toggle a single status flag on a parish fund.
     */
    private function setFlag(int $id, string $field, int $value, string $auditAction, string $successKey)
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $fund     = $this->findInParish($id, $parishId);

        if ($fund === null) {
            return redirect()->to('/funds')->with('error', lang('Finance.fundNotFound'));
        }

        if ((int) $fund[$field] !== $value) {
            if (! $this->fundModel->update($id, [$field => $value])) {
                return redirect()->to('/funds')->with('error', lang('Finance.fundSaveFailed'));
            }

            $this->auditFund($auditAction, $parishId, $id, [$field => (int) $fund[$field]], [$field => $value]);
        }

        return redirect()->to('/funds')->with('success', lang($successKey));
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
    private function findInParish(int $fundId, int $parishId): ?array
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
    private function auditFund(string $action, int $parishId, int $fundId, ?array $old, array $new): void
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
