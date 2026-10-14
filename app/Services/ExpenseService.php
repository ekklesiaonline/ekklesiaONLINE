<?php

namespace App\Services;

use App\Models\ChartOfAccountModel;
use App\Models\ExpenseModel;
use App\Models\FundModel;

class ExpenseService
{
    private ExpenseModel $expenses;
    private FundModel $funds;
    private ChartOfAccountModel $chartAccounts;

    public function __construct(
        ?ExpenseModel $expenses = null,
        ?FundModel $funds = null,
        ?ChartOfAccountModel $chartAccounts = null
    ) {
        $this->expenses = $expenses ?? model(ExpenseModel::class);
        $this->funds = $funds ?? model(FundModel::class);
        $this->chartAccounts = $chartAccounts ?? model(ChartOfAccountModel::class);
    }

    public function validationRules(): array
    {
        return [
            'date'             => 'required|valid_date',
            'amount'           => 'required|decimal|greater_than[0]',
            'fund_id'          => 'required|is_not_unique[fund.id]',
            'category'         => 'permit_empty|max_length[100]',
            'description'      => 'required|min_length[3]|max_length[1000]',
            'chart_account_id' => 'required|is_not_unique[chart_of_accounts.id]',
            'notes'            => 'permit_empty|max_length[1000]',
        ];
    }

    public function findUsableFund(int $fundId, int $parishId): ?array
    {
        $fund = $this->funds->find($fundId);

        if (! is_array($fund)
            || (int) ($fund['parish_id'] ?? 0) !== $parishId
            || (int) ($fund['is_active'] ?? 0) !== 1
            || (int) ($fund['is_locked'] ?? 0) === 1) {
            return null;
        }

        return $fund;
    }

    public function findExpenseAccount(int $chartAccountId): ?array
    {
        $account = $this->chartAccounts
            ->where('id', $chartAccountId)
            ->where('is_active', 1)
            ->where('account_type', 'expense')
            ->first();

        return is_array($account) ? $account : null;
    }

    public function findInParish(int $expenseId, int $parishId): ?array
    {
        $expense = $this->expenses->find($expenseId);

        if (! is_array($expense) || (int) ($expense['parish_id'] ?? 0) !== $parishId) {
            return null;
        }

        return $expense;
    }
}
