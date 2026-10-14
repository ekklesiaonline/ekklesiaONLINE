<?php

namespace App\Controllers;

use App\Services\ChartAccountCategoryService;

class SystemChartOfAccounts extends BaseController
{
    protected $chartOfAccountModel;

    public function __construct()
    {
        $this->chartOfAccountModel = model('ChartOfAccountModel');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $type = $this->request->getGet('type');
        $status = $this->request->getGet('status');
        $section = $this->request->getGet('section');

        $builder = $this->chartOfAccountModel
            ->where('scope_type', 'system')
            ->orderBy("CASE account_type WHEN 'asset' THEN 1 WHEN 'liability' THEN 2 WHEN 'equity' THEN 3 WHEN 'income' THEN 4 WHEN 'expense' THEN 5 ELSE 6 END", '', false)
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

        return view('App\Views\System\ChartOfAccounts/index', array_merge($this->getCommonViewData('Chart of Accounts'), [
            'accounts' => $builder->findAll(),
            'selected_type' => $type,
            'selected_status' => $status,
            'selected_section' => $section,
            'section_options' => $this->getSectionOptions(),
        ]));
    }

    public function create()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $parentAccounts = $this->chartOfAccountModel->getSystemAccounts(null, true);

        return view('App\Views\System\ChartOfAccounts/create', array_merge($this->getCommonViewData(lang('ChartAccounts.pageHeadingSystemCreate')), $this->categoryViewData($parentAccounts)));
    }

    public function store()
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        if (!$this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $category = $this->resolveCategory();
        if (!$category) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.invalidCategory'));
        }

        $parentId = (int) ($this->request->getPost('parent_account_id') ?: 0);
        if ($parentId > 0 && !$this->validParentAccount($parentId, (string) $this->request->getPost('account_type'))) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.invalidParentAccount'));
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));
        if ($this->chartOfAccountModel->systemCodeExists($code)) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.systemAccountCodeExists'));
        }

        $payload = array_merge($this->extractPayload(), [
            'scope_type' => 'system',
            'scope_id' => 0,
            'parent_account_id' => $parentId > 0 ? $parentId : null,
            'category_id' => (int) $category['id'],
            'category' => (string) $category['category_key'],
        ]);

        if (!$this->chartOfAccountModel->insert($payload)) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.systemAccountCreateFailed'));
        }

        $accountId = $this->chartOfAccountModel->getInsertID();

        try {
            $this->audit()->log('system.chart_account.created', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'chart_account',
                'entity_id' => $accountId,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'new_values' => [
                    'code' => $code,
                    'name' => $this->request->getPost('name'),
                    'account_type' => $this->request->getPost('account_type'),
                    'scope_type' => 'system',
                    'scope_id' => 0,
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for system chart account creation: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/system/chart-of-accounts')->with('success', lang('ChartAccounts.systemAccountCreated'));
    }

    public function edit($id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $account = $this->chartOfAccountModel->find($id);
        if (!$account || !$this->chartOfAccountModel->isSystemAccount($account)) {
            return redirect()->to('/system/chart-of-accounts')->with('error', lang('ChartAccounts.systemAccountNotFound'));
        }

        $parentAccounts = $this->chartOfAccountModel
            ->where('scope_type', 'system')
            ->where('is_active', 1)
            ->where('id !=', $id)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('code', 'ASC')
            ->findAll();

        return view('App\Views\System\ChartOfAccounts/edit', array_merge($this->getCommonViewData(lang('ChartAccounts.pageHeadingSystemEdit')), $this->categoryViewData($parentAccounts), [
            'account' => $account,
        ]));
    }

    public function update($id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $account = $this->chartOfAccountModel->find($id);
        if (!$account || !$this->chartOfAccountModel->isSystemAccount($account)) {
            return redirect()->to('/system/chart-of-accounts')->with('error', lang('ChartAccounts.systemAccountNotFound'));
        }

        if (!$this->chartOfAccountModel->canMutateSystemAccount($account)) {
            return redirect()->to('/system/chart-of-accounts')->with('error', lang('ChartAccounts.systemAccountNotFound'));
        }

        if (!$this->validate($this->validationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $category = $this->resolveCategory();
        if (!$category) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.invalidCategory'));
        }

        $parentId = (int) ($this->request->getPost('parent_account_id') ?: 0);
        if ($parentId > 0 && !$this->validParentAccount($parentId, (string) $this->request->getPost('account_type'), $id)) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.invalidParentAccount'));
        }

        $code = strtoupper(trim((string) $this->request->getPost('code')));
        if ($this->chartOfAccountModel->systemCodeExists($code, (int) $id)) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.systemAccountCodeExists'));
        }

        $oldValues = [
            'code' => $account['code'] ?? null,
            'name' => $account['name'] ?? null,
            'account_type' => $account['account_type'] ?? null,
            'is_active' => $account['is_active'] ?? null,
        ];

        $payload = array_merge($this->extractPayload(), [
            'parent_account_id' => $parentId > 0 ? $parentId : null,
            'category_id' => (int) $category['id'],
            'category' => (string) $category['category_key'],
        ]);

        if (!$this->chartOfAccountModel->update($id, $payload)) {
            return redirect()->back()->withInput()->with('error', lang('ChartAccounts.systemAccountUpdateFailed'));
        }

        try {
            $this->audit()->log('system.chart_account.updated', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'chart_account',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => $oldValues,
                'new_values' => [
                    'code' => $code,
                    'name' => $this->request->getPost('name'),
                    'account_type' => $this->request->getPost('account_type'),
                ],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for system chart account update: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/system/chart-of-accounts')->with('success', lang('ChartAccounts.systemAccountUpdated'));
    }

    public function toggle($id)
    {
        if ($redirect = $this->requirePermission('system.admin')) {
            return $redirect;
        }

        if (!$this->isSystemAdmin()) {
            return $this->forbiddenResponse(lang('App.accessDenied'));
        }

        $account = $this->chartOfAccountModel->find($id);
        if (!$account || !$this->chartOfAccountModel->isSystemAccount($account)) {
            return redirect()->to('/system/chart-of-accounts')->with('error', lang('ChartAccounts.systemAccountNotFound'));
        }

        $oldStatus = (int) $account['is_active'];
        $newStatus = (int) !$oldStatus;

        $this->chartOfAccountModel->update($id, ['is_active' => $newStatus]);

        try {
            $this->audit()->log('system.chart_account.toggled', [
                'user_id' => (int) auth()->id(),
                'entity_type' => 'chart_account',
                'entity_id' => (int) $id,
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => esc($this->request->getUserAgent()->getAgentString()),
                'timestamp' => date('Y-m-d H:i:s'),
                'old_values' => ['is_active' => $oldStatus],
                'new_values' => ['is_active' => $newStatus],
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to log audit event for system chart account toggle: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to('/system/chart-of-accounts')->with('success', lang('ChartAccounts.systemAccountStatusUpdated'));
    }

    protected function validationRules(): array
    {
        return [
            'code' => 'required|max_length[50]',
            'name' => 'required|max_length[255]',
            'account_type' => 'required|in_list[income,expense,asset,liability,equity]',
            'sort_order' => 'permit_empty|integer',
            'parent_account_id' => 'permit_empty|is_natural_no_zero',
            'category_id' => 'required|is_natural_no_zero',
        ];
    }

    protected function extractPayload(): array
    {
        return [
            'code' => strtoupper(trim((string) $this->request->getPost('code'))),
            'name' => trim((string) $this->request->getPost('name')),
            'account_type' => (string) $this->request->getPost('account_type'),
            'description' => trim((string) $this->request->getPost('description')) ?: null,
            'allow_budget' => $this->request->getPost('allow_budget') ? 1 : 0,
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'sort_order' => (int) ($this->request->getPost('sort_order') ?: 0),
        ];
    }

    protected function resolveCategory(): ?array
    {
        return (new ChartAccountCategoryService())->findSelectable(
            (int) $this->request->getPost('category_id'),
            (string) $this->request->getPost('account_type')
        );
    }

    protected function validParentAccount(int $parentId, string $accountType, ?int $excludeId = null): bool
    {
        $parent = $this->chartOfAccountModel
            ->where('id', $parentId)
            ->where('scope_type', 'system')
            ->where('account_type', $accountType)
            ->where('is_active', 1)
            ->first();

        if (!$parent) {
            return false;
        }

        if ($excludeId !== null && $parentId === $excludeId) {
            return false;
        }

        if ($excludeId !== null && $this->isDescendantOf((int) $excludeId, $parentId)) {
            return false;
        }

        return true;
    }

    protected function isDescendantOf(int $ancestorId, int $candidateId): bool
    {
        $accounts = $this->chartOfAccountModel->where('scope_type', 'system')->findAll();
        $byId = [];
        foreach ($accounts as $account) {
            $byId[(int) $account['id']] = $account;
        }

        $currentId = $candidateId;
        $visited = [];
        while ($currentId > 0) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            if ($currentId === $ancestorId) {
                return true;
            }

            $currentId = (int) ($byId[$currentId]['parent_account_id'] ?? 0);
        }

        return false;
    }

    protected function categoryViewData(array $parentAccounts): array
    {
        $service = new ChartAccountCategoryService();
        $categories = $service->all();
        foreach ($parentAccounts as &$parent) {
            $parent['category_path'] = !empty($parent['category_id']) ? $service->path((int) $parent['category_id'], $categories) : '';
        }
        unset($parent);

        foreach ($categories as &$category) {
            $category['label'] = $service->label((string) $category['category_key']);
            $category['path'] = $service->path((int) $category['id'], $categories);
        }
        unset($category);

        return ['parent_accounts' => $parentAccounts, 'account_categories' => $categories];
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
