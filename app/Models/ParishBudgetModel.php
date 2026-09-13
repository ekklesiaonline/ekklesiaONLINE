<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishBudgetModel extends Model
{
    protected $table            = 'parish_budget';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['parish_id', 'chart_account_id', 'fiscal_year', 'budget_amount', 'created_by', 'updated_by'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getBudgetLinesWithActuals(int $parishId, int $year): array
    {
        $startDate = sprintf('%d-01-01', $year);
        $endDate = sprintf('%d-12-31', $year);

        $expenseActualsSql = $this->db->table('expense')
            ->select('chart_account_id, SUM(amount) as total_actual')
            ->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->where('chart_account_id IS NOT NULL', null, false)
            ->groupBy('chart_account_id')
            ->getCompiledSelect();

        $incomeActuals = model(DonationModel::class)->getChartAccountTotalsForParish($parishId, $startDate, $endDate);

        $rows = $this->db->table('chart_of_accounts coa')
            ->select([
                'coa.id as chart_account_id',
                'coa.code',
                'coa.name',
                'coa.account_type',
                'coa.category',
                'coa.sort_order',
                'COALESCE(cb.id, 0) as budget_line_id',
                'COALESCE(cb.budget_amount, 0) as budget_amount',
                'COALESCE(expense_actuals.total_actual, 0) as expense_actual',
            ])
            ->join('parish_budget cb', 'cb.chart_account_id = coa.id AND cb.parish_id = ' . $this->db->escape($parishId) . ' AND cb.fiscal_year = ' . $this->db->escape($year), 'left')
            ->join('(' . $expenseActualsSql . ') expense_actuals', 'expense_actuals.chart_account_id = coa.id', 'left')
            ->where('coa.is_active', 1)
            ->where('coa.allow_budget', 1)
            ->whereIn('coa.account_type', ['income', 'expense'])
            ->orderBy('coa.account_type', 'ASC')
            ->orderBy('coa.sort_order', 'ASC')
            ->orderBy('coa.code', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $budgetAmount = (float) ($row['budget_amount'] ?? 0);
            $incomeActual = (float) ($incomeActuals[(int) ($row['chart_account_id'] ?? 0)] ?? 0);
            $actualAmount = $row['account_type'] === 'income'
                ? $incomeActual
                : (float) ($row['expense_actual'] ?? 0);

            $row['income_actual'] = $incomeActual;
            $row['actual_amount'] = $actualAmount;
            $row['variance_amount'] = $row['account_type'] === 'income' ? $actualAmount - $budgetAmount : $budgetAmount - $actualAmount;
            $row['utilization_percentage'] = $budgetAmount > 0 ? round(($actualAmount / $budgetAmount) * 100, 1) : null;
        }
        unset($row);

        return $rows;
    }

    public function saveBudgetLines(int $parishId, int $year, array $amounts, int $userId): bool
    {
        $validAccounts = $this->db->table('chart_of_accounts')
            ->select('id')
            ->where('is_active', 1)
            ->where('allow_budget', 1)
            ->whereIn('account_type', ['income', 'expense'])
            ->get()
            ->getResultArray();

        $validAccountIds = array_map(static fn(array $row): int => (int) $row['id'], $validAccounts);
        $existingRows = $this->where('parish_id', $parishId)->where('fiscal_year', $year)->findAll();

        $existingByAccount = [];
        foreach ($existingRows as $row) {
            $existingByAccount[(int) $row['chart_account_id']] = $row;
        }

        $this->db->transStart();

        foreach ($validAccountIds as $accountId) {
            $amount = isset($amounts[$accountId]) ? (float) $amounts[$accountId] : 0.0;
            $existing = $existingByAccount[$accountId] ?? null;

            if ($amount <= 0) {
                if ($existing !== null) {
                    $this->delete((int) $existing['id']);
                }
                continue;
            }

            $payload = [
                'parish_id' => $parishId,
                'chart_account_id' => $accountId,
                'fiscal_year' => $year,
                'budget_amount' => $amount,
                'updated_by' => $userId,
            ];

            if ($existing !== null) {
                $this->update((int) $existing['id'], $payload);
            } else {
                $payload['created_by'] = $userId;
                $this->insert($payload);
            }
        }

        $this->db->transComplete();

        return $this->db->transStatus() !== false;
    }
}
