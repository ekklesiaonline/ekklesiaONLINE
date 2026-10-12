<?php

namespace Diocese\Controllers;

use App\Controllers\BaseController;

class ChartOfAccounts extends BaseController
{
    protected $chartOfAccountModel;

    public function __construct()
    {
        $this->chartOfAccountModel = model('ChartOfAccountModel');
    }

    public function index()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse('Access denied. Diocesan admin privileges required.');
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();

        $type = $this->request->getGet('type');
        $status = $this->request->getGet('status');
        $section = $this->request->getGet('section');

        $builder = $this->chartOfAccountModel
            ->groupStart()
                ->where('scope_type', 'system')
                ->orGroupStart()
                    ->where('scope_type', 'diocese')
                    ->where('scope_id', $dioceseId)
                ->groupEnd()
            ->groupEnd()
            ->orderBy("FIELD(account_type, 'asset', 'liability', 'equity', 'income', 'expense')", '', false)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC');
        if (!empty($type)) {
            $builder->where('account_type', $type);
        }
        if (!empty($section)) {
            $this->applySectionFilter($builder, $section);
        }
        if ($status === 'active') {
            $builder->where('is_active', 1);
        } elseif ($status === 'inactive') {
            $builder->where('is_active', 0);
        }

        return view('Diocese\Views\ChartAccounts/index', array_merge($this->getCommonViewData('Chart of Accounts'), [
            'accounts' => $builder->findAll(),
            'selected_type' => $type,
            'selected_status' => $status,
            'selected_section' => $section,
            'section_options' => $this->getSectionOptions(),
            'diocese_id' => $dioceseId,
        ]));
    }

    public function create()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse('Access denied. Diocesan admin privileges required.');
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();
        $parentAccounts = $this->chartOfAccountModel
            ->where('scope_type', 'system')
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();

        return view('Diocese\Views\ChartAccounts/create', array_merge($this->getCommonViewData('Create Chart Account'), [
            'parent_accounts' => $parentAccounts,
            'diocese_id' => $dioceseId,
        ]));
    }

    public function store()
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse('Access denied. Diocesan admin privileges required.');
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();
        if ($dioceseId <= 0) {
            return redirect()->back()->withInput()->with('error', 'A valid diocese scope is required.');
        }

        $rules = [
            'code' => 'required|max_length[50]',
            'name' => 'required|max_length[255]',
            'account_type' => 'required|in_list[income,expense,asset,liability,equity]',
            'sort_order' => 'permit_empty|integer',
            'parent_account_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));

        // Ensure code does not collide with a system account or existing diocesan account in this diocese
        $duplicate = $this->chartOfAccountModel
            ->where('code', $code)
            ->groupStart()
                ->where('scope_type', 'system')
                ->orGroupStart()
                    ->where('scope_type', 'diocese')
                    ->where('scope_id', $dioceseId)
                ->groupEnd()
            ->groupEnd()
            ->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.chartAccountCodeExists'));
        }

        $payload = array_merge($this->extractPayload(), [
            'scope_type' => 'diocese',
            'scope_id'   => $dioceseId,
            'parent_account_id' => $this->request->getPost('parent_account_id') ? (int) $this->request->getPost('parent_account_id') : null,
        ]);

        if (!$this->chartOfAccountModel->insert($payload)) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.chartAccountCreateFailed'));
        }

        $accountId = $this->chartOfAccountModel->getInsertID();

        try {
            $this->audit()->log('diocese.chart_account.created', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'chart_account',
                'entity_id' => $accountId,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'new_values' => [
                    'code' => $this->request->getPost('code'),
                    'name' => $this->request->getPost('name'),
                    'account_type' => $this->request->getPost('account_type'),
                    'scope_type' => 'diocese',
                    'scope_id' => $dioceseId,
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for chart account creation: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/chart-accounts')->with('success', lang('Diocesan.chartAccountCreated'));
    }

    public function edit($id)
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse('Access denied. Diocesan admin privileges required.');
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();
        $account = $this->chartOfAccountModel->find($id);
        if (!$account) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.chartAccountNotFound'));
        }

        if ($this->chartOfAccountModel->isSystemAccount($account)) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.systemAccountReadOnly'));
        }

        if (!$this->chartOfAccountModel->canMutateAccount($account, 'diocese', $dioceseId)) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.chartAccountUnauthorized'));
        }

        $parentAccounts = $this->chartOfAccountModel
            ->where('scope_type', 'system')
            ->where('is_active', 1)
            ->where('id !=', $id)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();

        return view('Diocese\Views\ChartAccounts/edit', array_merge($this->getCommonViewData('Edit Chart Account'), [
            'account' => $account,
            'parent_accounts' => $parentAccounts,
            'diocese_id' => $dioceseId,
        ]));
    }

    public function update($id)
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse('Access denied. Diocesan admin privileges required.');
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();
        $account = $this->chartOfAccountModel->find($id);
        if (!$account) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.chartAccountNotFound'));
        }

        if ($this->chartOfAccountModel->isSystemAccount($account)) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.systemAccountReadOnly'));
        }

        if (!$this->chartOfAccountModel->canMutateAccount($account, 'diocese', $dioceseId)) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.chartAccountUnauthorized'));
        }

        // Capture old values for audit logging
        $oldValues = [
            'code' => $account['code'] ?? null,
            'name' => $account['name'] ?? null,
            'account_type' => $account['account_type'] ?? null,
            'is_active' => $account['is_active'] ?? null,
        ];

        $rules = [
            'code' => 'required|max_length[50]',
            'name' => 'required|max_length[255]',
            'account_type' => 'required|in_list[income,expense,asset,liability,equity]',
            'sort_order' => 'permit_empty|integer',
            'parent_account_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));
        $duplicate = $this->chartOfAccountModel
            ->where('code', $code)
            ->where('id !=', $id)
            ->groupStart()
                ->where('scope_type', 'system')
                ->orGroupStart()
                    ->where('scope_type', 'diocese')
                    ->where('scope_id', $dioceseId)
                ->groupEnd()
            ->groupEnd()
            ->first();

        if ($duplicate) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.chartAccountCodeExists'));
        }

        $payload = array_merge($this->extractPayload(), [
            'parent_account_id' => $this->request->getPost('parent_account_id') ? (int) $this->request->getPost('parent_account_id') : null,
        ]);

        if (!$this->chartOfAccountModel->update($id, $payload)) {
            return redirect()->back()->withInput()->with('error', lang('Diocesan.chartAccountUpdateFailed'));
        }

        try {
            $this->audit()->log('diocese.chart_account.updated', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'chart_account',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => $oldValues,
                'new_values' => [
                    'code' => $this->request->getPost('code'),
                    'name' => $this->request->getPost('name'),
                    'account_type' => $this->request->getPost('account_type'),
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for chart account update: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/chart-accounts')->with('success', lang('Diocesan.chartAccountUpdated'));
    }

    public function toggle($id)
    {
        if (!$this->isDioceseAdmin()) {
            return $this->forbiddenResponse('Access denied. Diocesan admin privileges required.');
        }

        $dioceseId = (int) $this->resolveDiocesanScopeId();
        $account = $this->chartOfAccountModel->find($id);
        if (!$account) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.chartAccountNotFound'));
        }

        if ($this->chartOfAccountModel->isSystemAccount($account)) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.systemAccountReadOnly'));
        }

        if (!$this->chartOfAccountModel->canMutateAccount($account, 'diocese', $dioceseId)) {
            return redirect()->to('/chart-accounts')->with('error', lang('Diocesan.chartAccountUnauthorized'));
        }

        $oldStatus = (int) $account['is_active'];
        $newStatus = (int) !$oldStatus;

        $this->chartOfAccountModel->update($id, ['is_active' => $newStatus]);

        try {
            $this->audit()->log('diocese.chart_account.toggled', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'chart_account',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => [
                    'is_active' => $oldStatus,
                ],
                'new_values' => [
                    'is_active' => $newStatus,
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for chart account toggle: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/chart-accounts')->with('success', lang('Diocesan.chartAccountStatusUpdated'));
    }

    protected function extractPayload(): array
    {
        return [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'account_type' => (string) $this->request->getPost('account_type'),
            'category' => trim((string) $this->request->getPost('category')) ?: null,
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'allow_budget' => $this->request->getPost('allow_budget') ? 1 : 0,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
        ];
    }

    protected function getSectionOptions(): array
    {
        return [
            'balance_sheet' => lang('ChartAccounts.sectionBalanceSheet'),
            'income_unrestricted' => lang('ChartAccounts.sectionIncomeUnrestricted'),
            'income_restricted' => lang('ChartAccounts.sectionIncomeRestricted'),
            'income_transfers' => lang('ChartAccounts.sectionIncomeTransfers'),
            'income_grants' => lang('ChartAccounts.sectionIncomeGrants'),
            'expense_outreach' => lang('ChartAccounts.sectionExpenseOutreach'),
            'expense_ministry' => lang('ChartAccounts.sectionExpenseMinistry'),
            'expense_payroll' => lang('ChartAccounts.sectionExpensePayroll'),
            'expense_governance' => lang('ChartAccounts.sectionExpenseGovernance'),
            'expense_operations' => lang('ChartAccounts.sectionExpenseOperations'),
            'multi_campus' => lang('ChartAccounts.sectionMultiCampus'),
        ];
    }

    protected function applySectionFilter($builder, string $section): void
    {
        switch ($section) {
            case 'balance_sheet':
                $builder->groupStart()
                    ->where('sort_order >=', 1000)
                    ->where('sort_order <=', 3999)
                    ->groupEnd();
                break;
            case 'income_unrestricted':
                $builder->where('sort_order >=', 4000)->where('sort_order <=', 4999);
                break;
            case 'income_restricted':
                $builder->where('sort_order >=', 5000)->where('sort_order <=', 5399);
                break;
            case 'income_transfers':
                $builder->where('sort_order >=', 5400)->where('sort_order <=', 5499);
                break;
            case 'income_grants':
                $builder->where('sort_order >=', 5500)->where('sort_order <=', 5599);
                break;
            case 'expense_outreach':
                $builder->where('sort_order >=', 6000)->where('sort_order <=', 6999);
                break;
            case 'expense_ministry':
                $builder->where('sort_order >=', 7000)->where('sort_order <=', 7499);
                break;
            case 'expense_payroll':
                $builder->where('sort_order >=', 7500)->where('sort_order <=', 7599);
                break;
            case 'expense_governance':
                $builder->where('sort_order >=', 7600)->where('sort_order <=', 7699);
                break;
            case 'expense_operations':
                $builder->where('sort_order >=', 8000)->where('sort_order <=', 8999);
                break;
            case 'multi_campus':
                $builder->like('code', '-');
                break;
        }
    }
}
