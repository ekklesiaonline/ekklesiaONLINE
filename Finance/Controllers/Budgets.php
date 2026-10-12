<?php

namespace Finance\Controllers;

use App\Controllers\BaseController;

class Budgets extends BaseController
{
    protected $budgetModel;

    public function __construct()
    {
        $this->budgetModel = model('ParishBudgetModel');
    }

    public function index()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')->with('error', lang('Treasurer.provinceBudgetRestriction'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.noParishForBudgeting'));
        }

        $year = (int) ($this->request->getGet('year') ?: date('Y'));
        $budgetLines = $this->budgetModel->getBudgetLinesWithActuals($parishId, $year);

        return view('Finance\Views\Budgets/index', array_merge($this->getCommonViewData(lang('Finance.budgetsTitle')), [
            'budget_lines' => $budgetLines,
            'summary' => $this->summarizeBudgetLines($budgetLines),
            'selected_year' => $year,
            'year_options' => range(date('Y') - 1, date('Y') + 2),
            'canManageBudget' => auth()->user()->can('finance.manage'),
        ]));
    }

    public function save()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')->with('error', lang('Treasurer.provinceBudgetRestriction'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        $year = (int) ($this->request->getPost('fiscal_year') ?: date('Y'));
        $budgetAmounts = $this->request->getPost('budget_amounts') ?? [];

        if ($parishId <= 0 || $year < 2000 || $year > 2100) {
            return redirect()->to('/budgets')->with('error', lang('Treasurer.invalidBudgetRequest'));
        }

        $sanitized = [];
        foreach ((array) $budgetAmounts as $accountId => $amount) {
            $sanitized[(int) $accountId] = max(0, (float) $amount);
        }

        if (!$this->budgetModel->saveBudgetLines($parishId, $year, $sanitized, (int) auth()->id())) {
            return redirect()->to('/budgets?year=' . $year)->with('error', lang('Treasurer.budgetSaveFailed'));
        }

        return redirect()->to('/budgets?year=' . $year)->with('success', lang('Treasurer.budgetSaved'));
    }

    /**
     * Export budget lines and actuals as CSV.
     */
    public function exportCsv()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')->with('error', lang('Treasurer.provinceBudgetRestriction'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.noParishForBudgeting'));
        }

        $year = (int) ($this->request->getGet('year') ?: date('Y'));
        $budgetLines = $this->budgetModel->getBudgetLinesWithActuals($parishId, $year);
        $summary = $this->summarizeBudgetLines($budgetLines);

        $filename = 'budget_' . $parishId . '_' . $year . '.csv';
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return redirect()->back()->with('error', lang('App.errorGeneric'));
        }

        // Title and Executive Summary
        fputcsv($output, [lang('Treasurer.fiscalYear'), (string) $year, '', '']);
        fputcsv($output, [lang('Treasurer.incomeBudget'), number_format((float) $summary['income_budget'], 2, '.', ''), lang('Treasurer.incomeActual'), number_format((float) $summary['income_actual'], 2, '.', '')]);
        fputcsv($output, [lang('Treasurer.expenseBudget'), number_format((float) $summary['expense_budget'], 2, '.', ''), lang('Treasurer.expenseActual'), number_format((float) $summary['expense_actual'], 2, '.', '')]);
        fputcsv($output, [lang('Treasurer.plannedNet'), number_format((float) $summary['net_budget'], 2, '.', ''), lang('Treasurer.actualNet'), number_format((float) $summary['net_actual'], 2, '.', '')]);
        fputcsv($output, []);

        // Table Columns
        fputcsv($output, [
            lang('Treasurer.colCode'),
            lang('Treasurer.colAccount'),
            lang('Treasurer.colType'),
            lang('Treasurer.categoryLabel'),
            lang('Finance.budgetAmount'),
            lang('Finance.actualAmount'),
            lang('Finance.variance'),
            lang('Treasurer.colUtilization'),
        ]);

        foreach ($budgetLines as $line) {
            $util = $line['utilization_percentage'] !== null ? number_format((float) $line['utilization_percentage'], 1, '.', '') . '%' : '—';
            fputcsv($output, [
                $line['code'],
                $line['name'],
                ucfirst((string) ($line['account_type'] ?? '')),
                $line['category'] ?? '',
                number_format((float) ($line['budget_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($line['actual_amount'] ?? 0), 2, '.', ''),
                number_format((float) ($line['variance_amount'] ?? 0), 2, '.', ''),
                $util,
            ]);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($csvContent ?: '');
    }

    /**
     * Export formal annual budget document for Vestry presentation & approval as PDF.
     */
    public function exportPdf()
    {
        if ($redirect = $this->requirePermission('finance.view')) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')->with('error', lang('Treasurer.provinceBudgetRestriction'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.noParishForBudgeting'));
        }

        $year = (int) ($this->request->getGet('year') ?: date('Y'));
        $budgetLines = $this->budgetModel->getBudgetLinesWithActuals($parishId, $year);
        $summary = $this->summarizeBudgetLines($budgetLines);

        $parish = db_connect()->table('parish')->select('name')->where('id', $parishId)->get()->getRowArray();
        $parishName = $parish['name'] ?? 'Parish';

        $incomeLines = array_filter($budgetLines, static fn($l) => ($l['account_type'] ?? '') === 'income');
        $expenseLines = array_filter($budgetLines, static fn($l) => ($l['account_type'] ?? '') !== 'income');

        $renderBudgetRows = static function (array $lines): string {
            if (empty($lines)) {
                return '<tr><td colspan="6" style="font-style: italic; color: #64748b; text-align: center;">' . htmlspecialchars(lang('Finance.noneLabel')) . '</td></tr>';
            }
            $html = '';
            foreach ($lines as $line) {
                $var = (float) ($line['variance_amount'] ?? 0);
                $varColor = $var >= 0 ? '#15803d' : '#b91c1c';
                $util = $line['utilization_percentage'] !== null ? number_format((float) $line['utilization_percentage'], 1) . '%' : '—';
                $html .= '<tr>'
                    . '<td style="width: 12%; font-family: monospace;">' . htmlspecialchars((string) $line['code']) . '</td>'
                    . '<td style="width: 38%;">' . htmlspecialchars((string) $line['name']) . '</td>'
                    . '<td style="width: 15%; text-align: right; font-family: monospace;">$' . number_format((float) ($line['budget_amount'] ?? 0), 2) . '</td>'
                    . '<td style="width: 15%; text-align: right; font-family: monospace;">$' . number_format((float) ($line['actual_amount'] ?? 0), 2) . '</td>'
                    . '<td style="width: 12%; text-align: right; font-family: monospace; color: ' . $varColor . ';">$' . number_format($var, 2) . '</td>'
                    . '<td style="width: 8%; text-align: right;">' . htmlspecialchars($util) . '</td>'
                    . '</tr>';
            }
            return $html;
        };

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars(lang('Finance.budgetVestryTitle')) . ' - ' . $year . '</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 10px; color: #0f172a; margin: 15px; line-height: 1.35; }
                h1 { font-size: 16px; margin: 0 0 3px; color: #0f172a; }
                .subtitle { font-size: 11px; color: #475569; margin-bottom: 12px; }
                .kpi-container { display: table; width: 100%; margin-bottom: 14px; border-collapse: separate; border-spacing: 6px 0; }
                .kpi-box { display: table-cell; width: 33.3%; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px; text-align: center; }
                .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; margin-bottom: 2px; }
                .kpi-value { font-size: 14px; font-weight: bold; }
                .section-header { font-size: 11px; font-weight: bold; background: #f1f5f9; padding: 5px 8px; margin: 12px 0 4px; border-left: 3px solid #0284c7; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
                th { background: #f8fafc; font-weight: bold; border-bottom: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
                td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
                tr.subtotal td { border-top: 1px solid #94a3b8; font-weight: bold; background: #f8fafc; }
                .signature-section { margin-top: 25px; border-top: 1px solid #cbd5e1; padding-top: 12px; }
                .sig-grid { display: table; width: 100%; margin-top: 15px; }
                .sig-box { display: table-cell; width: 33.3%; padding: 0 8px; }
                .sig-line { border-bottom: 1px solid #334155; height: 25px; margin-bottom: 4px; }
                .sig-label { font-size: 9px; color: #475569; text-align: center; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($parishName) . '</h1>
            <div class="subtitle">' . htmlspecialchars(lang('Finance.annualBudgetSummary')) . ' — ' . htmlspecialchars(lang('Treasurer.fiscalYear')) . ': ' . $year . '</div>

            <div class="kpi-container">
                <div class="kpi-box">
                    <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.incomeBudget')) . '</div>
                    <div class="kpi-value" style="color: #15803d;">$' . number_format((float) $summary['income_budget'], 2) . '</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.expenseBudget')) . '</div>
                    <div class="kpi-value" style="color: #b91c1c;">$' . number_format((float) $summary['expense_budget'], 2) . '</div>
                </div>
                <div class="kpi-box">
                    <div class="kpi-label">' . htmlspecialchars(lang('Treasurer.plannedNet')) . '</div>
                    <div class="kpi-value" style="color: ' . ($summary['net_budget'] >= 0 ? '#15803d' : '#b91c1c') . ';">$' . number_format((float) $summary['net_budget'], 2) . '</div>
                </div>
            </div>

            <div class="section-header">' . htmlspecialchars(lang('Treasurer.accountTypeIncome')) . ' ' . htmlspecialchars(lang('Finance.budgetsTitle')) . '</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">' . htmlspecialchars(lang('Treasurer.colCode')) . '</th>
                        <th style="width: 38%;">' . htmlspecialchars(lang('Treasurer.colAccount')) . '</th>
                        <th style="width: 15%; text-align: right;">' . htmlspecialchars(lang('Finance.budgetAmount')) . '</th>
                        <th style="width: 15%; text-align: right;">' . htmlspecialchars(lang('Finance.actualAmount')) . '</th>
                        <th style="width: 12%; text-align: right;">' . htmlspecialchars(lang('Finance.variance')) . '</th>
                        <th style="width: 8%; text-align: right;">' . htmlspecialchars(lang('Treasurer.colUtilization')) . '</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $renderBudgetRows($incomeLines) . '
                    <tr class="subtotal">
                        <td colspan="2">' . htmlspecialchars(lang('Treasurer.incomeBudget')) . ' / ' . htmlspecialchars(lang('Treasurer.incomeActual')) . '</td>
                        <td style="text-align: right; font-family: monospace;">$' . number_format((float) $summary['income_budget'], 2) . '</td>
                        <td style="text-align: right; font-family: monospace;">$' . number_format((float) $summary['income_actual'], 2) . '</td>
                        <td style="text-align: right; font-family: monospace;">$' . number_format((float) ($summary['income_actual'] - $summary['income_budget']), 2) . '</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="section-header">' . htmlspecialchars(lang('Treasurer.accountTypeExpense')) . ' ' . htmlspecialchars(lang('Finance.budgetsTitle')) . '</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">' . htmlspecialchars(lang('Treasurer.colCode')) . '</th>
                        <th style="width: 38%;">' . htmlspecialchars(lang('Treasurer.colAccount')) . '</th>
                        <th style="width: 15%; text-align: right;">' . htmlspecialchars(lang('Finance.budgetAmount')) . '</th>
                        <th style="width: 15%; text-align: right;">' . htmlspecialchars(lang('Finance.actualAmount')) . '</th>
                        <th style="width: 12%; text-align: right;">' . htmlspecialchars(lang('Finance.variance')) . '</th>
                        <th style="width: 8%; text-align: right;">' . htmlspecialchars(lang('Treasurer.colUtilization')) . '</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $renderBudgetRows($expenseLines) . '
                    <tr class="subtotal">
                        <td colspan="2">' . htmlspecialchars(lang('Treasurer.expenseBudget')) . ' / ' . htmlspecialchars(lang('Treasurer.expenseActual')) . '</td>
                        <td style="text-align: right; font-family: monospace;">$' . number_format((float) $summary['expense_budget'], 2) . '</td>
                        <td style="text-align: right; font-family: monospace;">$' . number_format((float) $summary['expense_actual'], 2) . '</td>
                        <td style="text-align: right; font-family: monospace;">$' . number_format((float) ($summary['expense_budget'] - $summary['expense_actual']), 2) . '</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="signature-section">
                <div style="font-weight: bold; margin-bottom: 4px;">' . htmlspecialchars(lang('Finance.vestryApprovalSection')) . '</div>
                <div style="font-size: 9px; color: #475569;">' . htmlspecialchars(lang('Finance.vestryApprovalText', [$year])) . '</div>
                <div class="sig-grid">
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">' . htmlspecialchars(lang('Finance.seniorWardenSignature')) . '</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">' . htmlspecialchars(lang('Finance.treasurerSignature')) . '</div>
                    </div>
                    <div class="sig-box">
                        <div class="sig-line"></div>
                        <div class="sig-label">' . htmlspecialchars(lang('Finance.rectorSignature')) . '</div>
                    </div>
                </div>
            </div>
        </body>
        </html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        $filename = 'budget_' . $parishId . '_' . $year . '.pdf';

        return $this->response
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($dompdf->output());
    }

    /**
     * Roll-forward / clone prior year budget lines into a target fiscal year.
     */
    public function clone()
    {
        if ($redirect = $this->requirePermission('finance.manage')) {
            return $redirect;
        }

        if ($this->isProvinceAdmin()) {
            return redirect()->to('/province/dashboard')->with('error', lang('Treasurer.provinceBudgetRestriction'));
        }

        $parishId = (int) ($this->getOperationalParishId() ?? 0);
        if ($parishId <= 0) {
            return redirect()->to('/dashboard')->with('error', lang('Treasurer.noParishForBudgeting'));
        }

        $sourceYear = (int) ($this->request->getPost('source_year') ?: (date('Y') - 1));
        $targetYear = (int) ($this->request->getPost('target_year') ?: date('Y'));
        $sourceBasis = (string) ($this->request->getPost('source_basis') ?: 'budget');
        $adjustmentPercent = (float) ($this->request->getPost('adjustment_percent') ?: 0.0);
        $overwrite = (bool) ($this->request->getPost('overwrite') === '1');

        if ($sourceYear < 2000 || $sourceYear > 2100 || $targetYear < 2000 || $targetYear > 2100) {
            return redirect()->to('/budgets')->with('error', lang('Treasurer.invalidBudgetRequest'));
        }

        $result = $this->budgetModel->cloneBudget(
            $parishId,
            $sourceYear,
            $targetYear,
            $sourceBasis,
            $adjustmentPercent,
            $overwrite,
            (int) auth()->id()
        );

        if (!$result['success']) {
            return redirect()->to('/budgets?year=' . $targetYear)->with('error', $result['message']);
        }

        $this->audit()->log('budget.cloned', [
            'parish_id' => $parishId,
            'entity_type' => 'parish_budget',
            'meta' => [
                'source_year' => $sourceYear,
                'target_year' => $targetYear,
                'source_basis' => $sourceBasis,
                'adjustment_percent' => $adjustmentPercent,
                'overwrite' => $overwrite,
                'copied_count' => $result['copied_count'],
            ],
        ]);

        return redirect()->to('/budgets?year=' . $targetYear)->with('success', $result['message']);
    }

    protected function summarizeBudgetLines(array $lines): array
    {
        $summary = [
            'income_budget' => 0.0,
            'income_actual' => 0.0,
            'expense_budget' => 0.0,
            'expense_actual' => 0.0,
        ];

        foreach ($lines as $line) {
            $budget = (float) ($line['budget_amount'] ?? 0);
            $actual = (float) ($line['actual_amount'] ?? 0);

            if (($line['account_type'] ?? '') === 'income') {
                $summary['income_budget'] += $budget;
                $summary['income_actual'] += $actual;
            } else {
                $summary['expense_budget'] += $budget;
                $summary['expense_actual'] += $actual;
            }
        }

        $summary['net_budget'] = $summary['income_budget'] - $summary['expense_budget'];
        $summary['net_actual'] = $summary['income_actual'] - $summary['expense_actual'];
        $summary['net_variance'] = $summary['net_actual'] - $summary['net_budget'];

        return $summary;
    }
}
