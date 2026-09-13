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

    public function export()
    {
        if ($redirect = $this->requirePermission('expense.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $expenses = $this->buildFilteredExpensesQuery($parishId, $this->getExpenseFilters())
            ->orderBy('expense.date', 'DESC')
            ->get()
            ->getResultArray();

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            lang('Finance.colDate'),
            lang('Finance.colDescription'),
            lang('Finance.colFund'),
            lang('Finance.colFundType'),
            lang('Finance.colCategory'),
            lang('Finance.colAmount'),
            lang('Finance.colApprovedBy'),
            lang('Finance.colNotes'),
        ]);

        foreach ($expenses as $expense) {
            fputcsv($handle, [
                (string) ($expense['date'] ?? ''),
                (string) ($expense['description'] ?? ''),
                (string) ($expense['fund_name'] ?? lang('Finance.unassigned')),
                $this->mapFundTypeLabel((string) ($expense['fund_type'] ?? 'unrestricted')),
                $this->mapExpenseCategoryLabel((string) ($expense['category'] ?? 'other')),
                number_format((float) ($expense['amount'] ?? 0), 2, '.', ''),
                $this->formatApproverName($expense),
                (string) ($expense['notes'] ?? ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filters = $this->getExpenseFilters();
        $this->audit()->log('expense.export_csv', [
            'parish_id' => $parishId,
            'entity_type' => 'expense_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($expenses),
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildExpenseExportFilename('csv') . '"')
            ->setBody($csv ?: '');
    }

    public function exportPdf()
    {
        if ($redirect = $this->requirePermission('expense.view')) {
            return $redirect;
        }

        if ($redirect = $this->requireParish()) {
            return $redirect;
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $filters = $this->getExpenseFilters();
        $expenses = $this->buildFilteredExpensesQuery($parishId, $filters)
            ->orderBy('expense.date', 'DESC')
            ->get()
            ->getResultArray();
        $summary = $this->expenseModel->getParishSummary($parishId, $filters['start_date'], $filters['end_date']);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($this->buildExpensesPdfHtml($parishId, $filters, $expenses, $summary));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $this->audit()->log('expense.export_pdf', [
            'parish_id' => $parishId,
            'entity_type' => 'expense_report',
            'meta' => array_merge(
                $this->buildParishContextMeta($parishId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
                [
                    'count' => count($expenses),
                    'category' => (string) ($filters['category'] ?? ''),
                    'fund_id' => (int) ($filters['fund_id'] ?? 0),
                    'start_date' => (string) ($filters['start_date'] ?? ''),
                    'end_date' => (string) ($filters['end_date'] ?? ''),
                ]
            ),
        ]);

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $this->buildExpenseExportFilename('pdf') . '"')
            ->setBody($dompdf->output());
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
        $attributionChurchId = (int) ($this->resolveAttributionParishId($attributionPolicy) ?? 0);
        if ($attributionChurchId <= 0) {
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
        if (!$fund || (int) ($fund['parish_id'] ?? 0) !== $attributionChurchId) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.validFundRequired'));
        }

        if ((int) ($fund['is_locked'] ?? 0) === 1) {
            return redirect()->back()->withInput()->with('error', lang('Treasurer.fundLocked'));
        }

        $data = [
            'parish_id' => $attributionChurchId,
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

        $expenseId = (int) $this->expenseModel->insert($data, true);

        if ($expenseId > 0) {
            $this->audit()->log('expense.create', [
                'parish_id' => $attributionChurchId,
                'entity_type' => 'expense',
                'entity_id' => $expenseId,
                'new_values' => [
                    'fund_id' => (int) ($data['fund_id'] ?? 0),
                    'date' => (string) ($data['date'] ?? ''),
                    'amount' => (string) ($data['amount'] ?? ''),
                    'category' => (string) ($data['category'] ?? ''),
                    'chart_account_id' => (int) ($data['chart_account_id'] ?? 0),
                    'approved_by' => isset($data['approved_by']) ? (int) $data['approved_by'] : null,
                ],
                'meta' => $this->buildParishContextMeta($attributionChurchId, $attributionPolicy),
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

        $data = [
            'fund_id' => $fundId,
            'date' => $this->request->getPost('date'),
            'amount' => $this->request->getPost('amount'),
            'category' => (string) ($chartAccount['category'] ?? ($this->request->getPost('category') ?: 'other')),
            'chart_account_id' => (int) $chartAccount['id'],
            'description' => $this->request->getPost('description'),
            'notes' => $this->request->getPost('notes'),
        ];

        if ($this->expenseModel->update($id, $data)) {
            $attributionChurchId = (int) ($expense['parish_id'] ?? 0);
            $this->audit()->log('expense.update', [
                'parish_id' => $attributionChurchId,
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
                'new_values' => [
                    'fund_id' => (int) ($data['fund_id'] ?? 0),
                    'date' => (string) ($data['date'] ?? ''),
                    'amount' => (string) ($data['amount'] ?? ''),
                    'category' => (string) ($data['category'] ?? ''),
                    'chart_account_id' => (int) ($data['chart_account_id'] ?? 0),
                    'description' => (string) ($data['description'] ?? ''),
                ],
                'meta' => $this->buildParishContextMeta($attributionChurchId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
            ]);

            return redirect()->to('/expenses')->with('success', lang('Treasurer.expenseUpdated'));
        }

        return redirect()->back()->withInput()->with('error', lang('Treasurer.expenseUpdateFailed'));
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
            $attributionChurchId = (int) ($expense['parish_id'] ?? 0);
            $this->audit()->log('expense.delete', [
                'parish_id' => $attributionChurchId,
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
                'meta' => $this->buildParishContextMeta($attributionChurchId, self::ATTRIBUTION_POLICY_OPERATIONAL_DEFAULT),
            ]);

            return $this->jsonResponse(['success' => true, 'message' => lang('Treasurer.expenseDeleted')]);
        }

        return $this->jsonResponse(['success' => false, 'message' => lang('Treasurer.expenseDeleteFailed')], 500);
    }

    private function getExpenseFilters(): array
    {
        return [
            'category' => trim((string) ($this->request->getGet('category') ?? '')),
            'fund_id' => (int) ($this->request->getGet('fund_id') ?? 0),
            'start_date' => $this->normalizeDate($this->request->getGet('start_date')),
            'end_date' => $this->normalizeDate($this->request->getGet('end_date')),
        ];
    }

    private function buildFilteredExpensesQuery(int $parishId, array $filters)
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

    private function resolveExistingProfileTable(): ?string
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

    private function normalizeDate($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : date('Y-m-d', $timestamp);
    }

    private function formatApproverName(array $expense): string
    {
        $name = trim((string) (($expense['approver_first_name'] ?? '') . ' ' . ($expense['approver_last_name'] ?? '')));

        return $name !== '' ? $name : (!empty($expense['approved_by']) ? 'User #' . (int) $expense['approved_by'] : '-');
    }

    private function buildExpensesPdfHtml(int $parishId, array $filters, array $expenses, array $summary): string
    {
        $church = model('ParishModel')->find($parishId);
        $rows = '';

        foreach ($expenses as $expense) {
            $rows .= '<tr>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($expense['date'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($expense['description'] ?? '')) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string) ($expense['fund_name'] ?? lang('Finance.unassigned'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($this->mapExpenseCategoryLabel((string) ($expense['category'] ?? 'other'))) . '</td>'
                . '<td style="padding: 8px; border: 1px solid #ddd; text-align: right;">$' . number_format((float) ($expense['amount'] ?? 0), 2) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="padding: 12px; border: 1px solid #ddd; text-align: center; color: #666;">' . htmlspecialchars(lang('Treasurer.noExpensesForFilters')) . '</td></tr>';
        }

        $filterParts = [];
        if (!empty($filters['category'])) {
            $filterParts[] = lang('Treasurer.categoryLabel') . ': ' . ucfirst((string) $filters['category']);
        }
        if (!empty($filters['fund_id'])) {
            $filterParts[] = lang('Treasurer.fundIdLabel') . ': ' . (int) $filters['fund_id'];
        }
        if (!empty($filters['start_date'])) {
            $filterParts[] = lang('Treasurer.fromLabel') . ': ' . $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $filterParts[] = lang('Treasurer.toLabel') . ': ' . $filters['end_date'];
        }

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Treasurer.expensesExportTitle')) . '</title>
            <style>
                body { font-family: Arial, sans-serif; color: #333; font-size: 12px; }
                h1 { color: #8a1c1c; margin-bottom: 6px; }
                .meta { color: #666; margin-bottom: 18px; }
                .total-box { margin: 18px 0; padding: 14px; border: 1px solid #ddd; background: #fafafa; }
                .total-box strong { color: #8a1c1c; font-size: 18px; }
                table { width: 100%; border-collapse: collapse; }
                th { background: #f4f6f8; text-align: left; padding: 8px; border: 1px solid #ddd; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars(lang('Treasurer.expensesExportTitle')) . '</h1>
            <div class="meta">
                <strong>' . htmlspecialchars(lang('Treasurer.churchLabel')) . ':</strong> ' . htmlspecialchars((string) ($church['name'] ?? lang('Treasurer.unknownChurch'))) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.generatedLabel')) . ':</strong> ' . htmlspecialchars($this->formatLocalizedDateTime(date('Y-m-d H:i:s'))) . '<br>
                <strong>' . htmlspecialchars(lang('Treasurer.filtersLabel')) . ':</strong> ' . htmlspecialchars($filterParts !== [] ? implode(' | ', $filterParts) : lang('Treasurer.noneLabel')) . '
            </div>
            <div class="total-box">' . htmlspecialchars(lang('Finance.totalExpenses')) . ': <strong>$' . number_format((float) ($summary['total'] ?? 0), 2) . '</strong></div>
            <table>
                <thead>
                    <tr>
                        <th>' . htmlspecialchars(lang('Finance.colDate')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colDescription')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colFund')) . '</th>
                        <th>' . htmlspecialchars(lang('Finance.colCategory')) . '</th>
                        <th style="text-align: right;">' . htmlspecialchars(lang('Finance.colAmount')) . '</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
        </body>
        </html>';
    }

    private function mapExpenseCategoryLabel(string $category): string
    {
        $map = [
            'utilities' => lang('Finance.catUtilities'),
            'maintenance' => lang('Finance.catMaintenance'),
            'salaries' => lang('Finance.catSalaries'),
            'ministry' => lang('Finance.catMinistry'),
            'supplies' => lang('Finance.catSupplies'),
            'other' => lang('Finance.categoryOther'),
        ];

        return $map[$category] ?? ucfirst($category);
    }

    private function mapFundTypeLabel(string $fundType): string
    {
        $map = [
            'restricted' => lang('Treasurer.fundTypeRestricted'),
            'permanent' => lang('Treasurer.fundTypePermanent'),
            'unrestricted' => lang('Treasurer.fundTypeUnrestricted'),
        ];

        return $map[$fundType] ?? ucfirst($fundType);
    }

    private function buildExpenseExportFilename(string $extension): string
    {
        return 'expenses_export_' . date('Ymd_His') . '.' . $extension;
    }

    private function formatLocalizedDateTime(string $value): string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        $locale = $this->request->getLocale() ?: config('App')->defaultLocale;
        if (class_exists(\IntlDateFormatter::class)) {
            $formatter = new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::SHORT,
                date_default_timezone_get()
            );
            $formatter->setPattern('MMM d, y h:mm a');
            $formatted = $formatter->format($timestamp);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return date('Y-m-d H:i', $timestamp);
    }
}
