<?php

namespace Treasurer\Controllers;

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

        return view('Treasurer\Views\Budgets/index', array_merge($this->getCommonViewData(lang('Finance.budgetsTitle')), [
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
