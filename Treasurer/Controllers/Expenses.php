<?php

namespace Treasurer\Controllers;

use App\Controllers\BaseController;

class Expenses extends BaseController
{
    protected $expenseModel;
    protected $fundModel;

    public function __construct()
    {
        $this->expenseModel = model('ExpenseModel');
    }

    protected function getFundModel()
    {
        if ($this->fundModel === null) {
            $this->fundModel = model('FundModel');
        }

        return $this->fundModel;
    }

    /**
     * Display list of expenses
     */
    public function index()
    {
        if ($redirect = $this->requirePermission('expense.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = $this->getOperationalParishId();
        $perPage = 20;

        $filters = $this->getExpenseFilters();
        $category = $filters['category'];
        $fundId = $filters['fund_id'];
        $startDate = $filters['start_date'];
        $endDate = $filters['end_date'];

        $expenses = $this->buildFilteredExpensesQuery((int) $parishId, $filters)
            ->orderBy('expense.date', 'DESC')
            ->paginate($perPage);

        // Calculate summary
        $summary = $this->expenseModel->getParishSummary($parishId, $startDate, $endDate);
        $funds = $this->getFundModel()->getParishFunds((int) $parishId);

        $data = [
            'expenses' => $expenses,
            'pager' => $this->expenseModel->pager,
            'summary' => $summary,
            'category' => $category,
            'fund_id' => $fundId,
            'funds' => $funds,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        return view('Treasurer\Views\Expenses/index', array_merge($this->getCommonViewData(lang('Finance.expensesTitle')), $data));
    }

    /**
     * Show create expense form
     */
    public function create()
    {
        if ($redirect = $this->requirePermission('expense.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $chartOfAccountModel = model('ChartOfAccountModel');
        $funds = $this->getFundModel()->getParishFunds((int) ($this->getOperationalParishId() ?? 0));

        return view('Treasurer\Views\Expenses/create', array_merge($this->getCommonViewData(lang('Finance.recordExpense')), [
            'funds' => $funds,
            'chartAccounts' => $chartOfAccountModel->getActiveByType('expense'),
            'groupedChartAccounts' => $chartOfAccountModel->getGroupedActiveAccountsForType('expense'),
        ]));
    }

    /**
     * Store new expense
     */
    public function store()
    {
        if ($redirect = $this->requirePermission('expense.create')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $rules = [
            'date' => 'required|valid_date',
            'amount' => 'required|decimal',
            'fund_id' => 'required|is_not_unique[fund.id]',
            'category' => 'permit_empty|max_length[100]',
            'description' => 'required|min_length[3]',
            'chart_account_id' => 'required|is_not_unique[chart_of_accounts.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $attributionPolicy = self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT;
        $attributionParishId = (int) ($this->resolveAttributionParishId($attributionPolicy) ?? 0);
        if ($attributionParishId <= 0) {
            return redirect()->back()->withInput()->with('error', lang('App.errorNoParishScope'));
        }

        $chartAccount = model('ChartOfAccountModel')
            ->where('id', (int) $this->request->getPost('chart_account_id'))
            ->where('is_active', 1)
            ->where('account_type', 'expense')
            ->first();

        if (empty($chartAccount)) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validExpenseAccountRequired'));
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->getFundModel()->find($fundId);
        if (!$fund || (int) ($fund['parish_id'] ?? 0) !== $attributionParishId) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validFundRequired'));
        }

        if ((int) ($fund['is_locked'] ?? 0) === 1) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.fundLocked'));
        }

        $receiptData = null;
        try {
            $receiptData = \App\Services\ExpenseReceiptUploadService::handleUpload(
                $attributionParishId,
                null,
                $this->request->getFile('receipt'),
                false
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $data = [
            'parish_id' => $attributionParishId,
            'fund_id' => $fundId,
            'date' => $this->request->getPost('date'),
            'amount' => $this->request->getPost('amount'),
            // Keep transaction category aligned with the selected chart account.
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'description' => $this->request->getPost('description'),
            'notes' => $this->request->getPost('notes'),
            'approved_by' => auth()->id(),
        ];

        if ($receiptData !== null) {
            $data['receipt_path']          = $receiptData['receipt_path'];
            $data['receipt_original_name'] = $receiptData['receipt_original_name'];
            $data['receipt_mime_type']     = $receiptData['receipt_mime_type'];
            $data['receipt_file_size']     = $receiptData['receipt_file_size'];
        }

        $expenseId = (int) $this->expenseModel->insert($data, true);

        if ($expenseId > 0) {
            $this->audit()->log('expense.create', [
                'parish_id' => $attributionParishId,
                'entity_type' => 'expense',
                'entity_id' => $expenseId,
                'new_values' => [
                    'fund_id' => (int) ($data['fund_id'] ?? 0),
                    'date' => (string) ($data['date'] ?? ''),
                    'amount' => (string) ($data['amount'] ?? ''),
                    'category' => (string) ($data['category'] ?? ''),
                    'chart_account_id' => (int) ($data['chart_account_id'] ?? 0),
                    'approved_by' => isset($data['approved_by']) ? (int) $data['approved_by'] : null,
                    'has_receipt' => !empty($data['receipt_path']),
                ],
                'meta' => $this->buildParishContextMeta($attributionParishId, $attributionPolicy),
            ]);

            return redirect()->to('/expenses')->with('success', lang('Treasurer.expenseRecorded'));
        }

        return redirect()->back()->withInput()->with('error', lang('Treasurer.expenseRecordFailed'));
    }

    /**
     * Show edit expense form
     */
    public function edit($id)
    {
        if ($redirect = $this->requirePermission('expense.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $expense = $this->expenseModel->find($id);

        if (!$expense || $expense['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/expenses')->with('error', lang('Treasurer.expenseNotFound'));
        }

        $chartOfAccountModel = model('ChartOfAccountModel');
        $funds = $this->getFundModel()->getParishFunds((int) ($this->getOperationalParishId() ?? 0));

        return view('Treasurer\Views\Expenses/edit', array_merge($this->getCommonViewData(lang('Treasurer.editExpenseTitle')), [
            'expense' => $expense,
            'funds' => $funds,
            'chartAccounts' => $chartOfAccountModel->getActiveByType('expense'),
            'groupedChartAccounts' => $chartOfAccountModel->getGroupedActiveAccountsForType('expense'),
        ]));
    }

    /**
     * Update expense
     */
    public function update($id)
    {
        if ($redirect = $this->requirePermission('expense.edit')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $expense = $this->expenseModel->find($id);

        if (!$expense || $expense['parish_id'] != $this->getOperationalParishId()) {
            return redirect()->to('/expenses')->with('error', lang('Treasurer.expenseNotFound'));
        }

        $rules = [
            'date' => 'required|valid_date',
            'amount' => 'required|decimal',
            'fund_id' => 'required|is_not_unique[fund.id]',
            'category' => 'permit_empty|max_length[100]',
            'description' => 'required|min_length[3]',
            'chart_account_id' => 'required|is_not_unique[chart_of_accounts.id]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $chartAccount = model('ChartOfAccountModel')
            ->where('id', (int) $this->request->getPost('chart_account_id'))
            ->where('is_active', 1)
            ->where('account_type', 'expense')
            ->first();

        if (empty($chartAccount)) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validExpenseAccountRequired'));
        }

        $fundId = (int) $this->request->getPost('fund_id');
        $fund = $this->getFundModel()->find($fundId);
        if (!$fund || (int) ($fund['parish_id'] ?? 0) !== (int) ($this->getOperationalParishId() ?? 0)) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validFundRequired'));
        }

        if ((int) ($fund['is_locked'] ?? 0) === 1 && (int) ($expense['fund_id'] ?? 0) !== $fundId) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.fundLocked'));
        }

        $attributionParishId = (int) ($expense['parish_id'] ?? 0);
        $removeReceipt = (string) ($this->request->getPost('remove_receipt') ?? '') === '1';

        $receiptData = null;
        try {
            $receiptData = \App\Services\ExpenseReceiptUploadService::handleUpload(
                $attributionParishId,
                (string) ($expense['receipt_path'] ?? ''),
                $this->request->getFile('receipt'),
                $removeReceipt
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $data = [
            'fund_id' => $fundId,
            'date' => $this->request->getPost('date'),
            'amount' => $this->request->getPost('amount'),
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'description' => $this->request->getPost('description'),
            'notes' => $this->request->getPost('notes'),
        ];

        if ($receiptData !== null) {
            $data['receipt_path']          = $receiptData['receipt_path'];
            $data['receipt_original_name'] = $receiptData['receipt_original_name'];
            $data['receipt_mime_type']     = $receiptData['receipt_mime_type'];
            $data['receipt_file_size']     = $receiptData['receipt_file_size'];
        }

        if ($this->expenseModel->update($id, $data)) {
            $this->audit()->log('expense.update', [
                'parish_id' => $attributionParishId,
                'entity_type' => 'expense',
                'entity_id' => (int) $id,
                'old_values' => [
                    'fund_id' => (int) ($expense['fund_id'] ?? 0),
                    'date' => (string) ($expense['date'] ?? ''),
                    'amount' => (string) ($expense['amount'] ?? ''),
                    'category' => (string) ($expense['category'] ?? ''),
                    'chart_account_id' => isset($expense['chart_account_id']) ? (int) $expense['chart_account_id'] : null,
                    'description' => (string) ($expense['description'] ?? ''),
                    'has_receipt' => !empty($expense['receipt_path']),
                ],
                'new_values' => [
                    'fund_id' => (int) ($data['fund_id'] ?? 0),
                    'date' => (string) ($data['date'] ?? ''),
                    'amount' => (string) ($data['amount'] ?? ''),
                    'category' => (string) ($data['category'] ?? ''),
                    'chart_account_id' => (int) ($data['chart_account_id'] ?? 0),
                    'description' => (string) ($data['description'] ?? ''),
                    'has_receipt' => array_key_exists('receipt_path', $data) ? !empty($data['receipt_path']) : !empty($expense['receipt_path']),
                ],
                'meta' => $this->buildParishContextMeta($attributionParishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
            ]);

            return redirect()->to('/expenses')->with('success', lang('Treasurer.expenseUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Treasurer.expenseUpdateFailed'));
    }

    /**
     * View or download an expense receipt/invoice document.
     *
     * @param int|string $id Expense ID
     */
    public function receipt($id)
    {
        if ($redirect = $this->requirePermission('expense.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $expense = $this->expenseModel->find($id);
        if (!$expense || (int) $expense['parish_id'] !== (int) $this->getOperationalParishId()) {
            return redirect()->to('/expenses')->with('error', lang('Treasurer.expenseNotFound'));
        }

        $storedPath = (string) ($expense['receipt_path'] ?? '');
        if ($storedPath === '') {
            return redirect()->to('/expenses')->with('error', lang('Treasurer.receiptNotFound'));
        }

        $absolutePath = \App\Services\ExpenseReceiptUploadService::resolveAbsolutePath($storedPath, (int) $expense['parish_id']);
        if ($absolutePath === null || !is_file($absolutePath)) {
            return redirect()->to('/expenses')->with('error', lang('Treasurer.receiptFileNotFound'));
        }

        $mimeType = (string) ($expense['receipt_mime_type'] ?? (mime_content_type($absolutePath) ?: 'application/octet-stream'));
        $filename = (string) ($expense['receipt_original_name'] ?? basename($absolutePath));

        $this->audit()->log('expense.receipt_view', [
            'parish_id'   => (int) $expense['parish_id'],
            'entity_type' => 'expense',
            'entity_id'   => (int) $id,
            'meta'        => [
                'filename' => $filename,
            ],
        ]);

        $disposition = $this->request->getGet('download') === '1' ? 'attachment' : 'inline';

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', $disposition . '; filename="' . addslashes($filename) . '"')
            ->setBody((string) file_get_contents($absolutePath));
    }

    /**
     * Delete expense
     */
    public function delete($id)
    {
        if ($redirect = $this->requirePermission('expense.delete')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $expense = $this->expenseModel->find($id);

        if (!$expense || $expense['parish_id'] != $this->getOperationalParishId()) {
            return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.expenseNotFound')], 404);
        }

        if ($this->expenseModel->delete($id)) {
            if (!empty($expense['receipt_path'])) {
                \App\Services\ExpenseReceiptUploadService::removeReceiptFile(
                    (string) $expense['receipt_path'],
                    (int) ($expense['parish_id'] ?? 0)
                );
            }

            $attributionParishId = (int) ($expense['parish_id'] ?? 0);
            $this->audit()->log('expense.delete', [
                'parish_id' => $attributionParishId,
                'entity_type' => 'expense',
                'entity_id' => (int) $id,
                'old_values' => [
                    'fund_id' => (int) ($expense['fund_id'] ?? 0),
                    'date' => (string) ($expense['date'] ?? ''),
                    'amount' => (string) ($expense['amount'] ?? ''),
                    'category' => (string) ($expense['category'] ?? ''),
                    'chart_account_id' => isset($expense['chart_account_id']) ? (int) $expense['chart_account_id'] : null,
                    'description' => (string) ($expense['description'] ?? ''),
                ],
                'meta' => $this->buildParishContextMeta($attributionParishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Treasurer.expenseDeleted')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.expenseDeleteFailed')], 500);
    }

    protected function getExpenseFilters(): array
    {
        return [
            'category' => trim((string) ($this->request->getGet('category') ?? '')),
            'fund_id' => (int) ($this->request->getGet('fund_id') ?? 0),
            'start_date' => $this->normalizeDate($this->request->getGet('start_date')),
            'end_date' => $this->normalizeDate($this->request->getGet('end_date')),
        ];
    }

    protected function buildFilteredExpensesQuery(int $parishId, array $filters)
    {
        $profileTable = $this->resolveExistingProfileTable();
        $builder = $this->expenseModel
            ->select('expense.*, f.name as fund_name, f.fund_type')
            ->join('fund f', 'f.id = expense.fund_id', 'left');

        if ($profileTable !== null) {
            $builder->select($profileTable . '.first_name as approver_first_name, ' . $profileTable . '.last_name as approver_last_name')
                ->join($profileTable, $profileTable . '.user_id = expense.approved_by', 'left');
        } else {
            $builder->select('NULL as approver_first_name, NULL as approver_last_name');
        }

        $builder->where('expense.parish_id', $parishId);

        if (!empty($filters['category'])) {
            $builder->where('expense.category', (string) $filters['category']);
        }

        if (!empty($filters['fund_id'])) {
            $builder->where('expense.fund_id', (int) $filters['fund_id']);
        }

        if (!empty($filters['start_date'])) {
            $builder->where('expense.date >=', (string) $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $builder->where('expense.date <=', (string) $filters['end_date']);
        }

        return $builder;
    }



    protected function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }



    protected function resolveExistingProfileTable(): ?string
    {
        $db = db_connect();
        $candidates = [$db->getPrefix() . 'profile', 'profile'];
        foreach (array_unique($candidates) as $table) {
            try {
                $columns = $db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Table doesn't exist.
            }
        }
        return null;
    }
}
