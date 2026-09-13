<?php

namespace App\Models;

use CodeIgniter\Model;

class ExpenseModel extends Model
{
    protected $table            = 'expense';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'fund_id',
        'description',
        'amount',
        'category',
        'chart_account_id',
        'date',
        'approved_by',
        'notes',
        'is_capital',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'parish_id'   => 'required|is_not_unique[parish.id]',
        'fund_id'     => 'permit_empty|is_not_unique[fund.id]',
        'description' => 'required|min_length[3]',
        'amount'      => 'required|decimal|greater_than[0]',
        'date'        => 'required|valid_date',
        'category'    => 'required',
        'chart_account_id' => 'permit_empty|is_not_unique[chart_of_accounts.id]',
        'is_capital'  => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get parish expenses
     */
    public function getParishExpenses($parishId, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('expense e')
            ->select('e.*, p.first_name as approver_first_name, p.last_name as approver_last_name, f.name as fund_name, f.fund_type')
            ->join('profile p', 'p.user_id = e.approved_by', 'left')
            ->join('fund f', 'f.id = e.fund_id', 'left')
            ->where('e.parish_id', $parishId);

        if ($startDate) {
            $builder->where('e.date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('e.date <=', $endDate);
        }

        return $builder->orderBy('e.date', 'DESC')->get()->getResultArray();
    }

    /**
     * Get expenses by category
     */
    public function getExpensesByCategory($parishId, $startDate, $endDate)
    {
        return $this->db->table('expense')
            ->select('category, SUM(amount) as total, COUNT(*) as count')
            ->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->groupBy('category')
            ->get()
            ->getResultArray();
    }

    /**
     * Get total expenses for period
     */
    public function getTotalExpenses($parishId, $startDate, $endDate)
    {
        $result = $this->db->table('expense')
            ->selectSum('amount', 'total')
            ->where('parish_id', $parishId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->get()
            ->getRowArray();

        return $result['total'] ?? 0;
    }

    public function getExpensesByFund($parishId, $startDate, $endDate): array
    {
        return $this->db->table('expense e')
            ->select('f.id as fund_id, f.name as fund_name, f.fund_type, SUM(e.amount) as total, COUNT(e.id) as count')
            ->join('fund f', 'f.id = e.fund_id', 'left')
            ->where('e.parish_id', $parishId)
            ->where('e.date >=', $startDate)
            ->where('e.date <=', $endDate)
            ->groupBy('e.fund_id')
            ->orderBy('f.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get total expense summary for selected period.
     */
    public function getParishSummary($parishId, $startDate = null, $endDate = null)
    {
        $builder = $this->db->table('expense')
            ->select('SUM(amount) as total')
            ->where('parish_id', $parishId);

        if ($startDate) {
            $builder->where('date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('date <=', $endDate);
        }

        $row = $builder->get()->getRowArray();

        return [
            'total' => (float) ($row['total'] ?? 0),
        ];
    }

    /**
    * Get total expenses across all parishes (ProvinceAdmin / DioceseAdmin)
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getAllParishesTotalExpenses($startDate, $endDate, array $parishIds = [], bool $strictScope = true)
    {
        if ($strictScope && empty($parishIds)) {
            return 0.0;
        }
        $builder = $this->db->table('expense')
            ->selectSum('amount', 'total')
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);

        if (!empty($parishIds)) {
            $builder->whereIn('parish_id', $parishIds);
        }

        $result = $builder->get()->getRowArray();

        return (float) ($result['total'] ?? 0);
    }

    /**
    * Get expenses by parish for ProvinceAdmin / DioceseAdmin comparison
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getExpensesByParish($startDate, $endDate, array $parishIds = [], bool $strictScope = true)
    {
        if ($strictScope && empty($parishIds)) {
            return [];
        }

        $builder = $this->db->table('expense e')
            ->select('c.id as parish_id, c.name as parish_name, SUM(e.amount) as total_expenses, COUNT(e.id) as expense_count')
            ->join('parish c', 'c.id = e.parish_id')
            ->where('e.date >=', $startDate)
            ->where('e.date <=', $endDate);

        if (!empty($parishIds)) {
            $builder->whereIn('e.parish_id', $parishIds);
        }

        return $builder
            ->groupBy('c.id')
            ->orderBy('total_expenses', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get monthly expense trend across parishes for the last N months.
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getNetworkMonthlyTrend(int $months = 12, array $parishIds = [], bool $strictScope = true): array
    {
        if ($strictScope && empty($parishIds)) {
            return [];
        }
        $startDate = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $builder = $this->db->table('expense')
            ->select("DATE_FORMAT(date, '%Y-%m') as month_key, DATE_FORMAT(date, '%b %Y') as month_label, SUM(amount) as total_amount")
            ->where('date >=', $startDate);

        if (!empty($parishIds)) {
            $builder->whereIn('parish_id', $parishIds);
        }

        return $builder
            ->groupBy("DATE_FORMAT(date, '%Y-%m'), DATE_FORMAT(date, '%b %Y')")
            ->orderBy('month_key', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get monthly expense trend grouped by parish for the last N months.
     *
     * @param array<int> $parishIds When provided, restrict to these parish IDs only.
     */
    public function getMonthlyTrendByParish(int $months = 12, array $parishIds = [], bool $strictScope = true): array
    {
        if ($strictScope && empty($parishIds)) {
            return [];
        }
        $startDate = date('Y-m-01', strtotime('-' . ($months - 1) . ' months'));

        $builder = $this->db->table('expense e')
            ->select("e.parish_id, c.name as parish_name, DATE_FORMAT(e.date, '%Y-%m') as month_key, DATE_FORMAT(e.date, '%b %Y') as month_label, SUM(e.amount) as total_amount")
            ->join('parish c', 'c.id = e.parish_id')
            ->where('e.date >=', $startDate);

        if (!empty($parishIds)) {
            $builder->whereIn('e.parish_id', $parishIds);
        }

        return $builder
            ->groupBy("e.parish_id, c.name, DATE_FORMAT(e.date, '%Y-%m'), DATE_FORMAT(e.date, '%b %Y')")
            ->orderBy('month_key', 'ASC')
            ->get()
            ->getResultArray();
    }
}
