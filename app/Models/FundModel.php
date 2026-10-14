<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class FundModel extends Model
{
    protected $table            = 'fund';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'name',
        'description',
        'fund_type',
        'restriction_purpose',
        'chart_account_id',
        'is_active',
        'is_locked',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'parish_id' => 'required|is_not_unique[parish.id]',
        'name' => 'required|min_length[3]|max_length[255]',
        'fund_type' => 'required|in_list[unrestricted,restricted,permanent]',
        'chart_account_id' => 'permit_empty|is_not_unique[chart_of_accounts.id]',
    ];

    public function getParishFunds(int $parishId, bool $activeOnly = true): array
    {
        $builder = $this->db->table('fund f')
            ->select('f.*, coa.code as chart_account_code, coa.name as chart_account_name')
            ->join('chart_of_accounts coa', 'coa.id = f.chart_account_id', 'left')
            ->where('f.parish_id', $parishId);

        if ($activeOnly) {
            $builder->where('f.is_active', 1);
        }

        return $builder
            ->orderBy('f.fund_type', 'ASC')
            ->orderBy('f.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getUsableParishFunds(int $parishId): array
    {
        return $this->db->table('fund f')
            ->select('f.*, coa.code as chart_account_code, coa.name as chart_account_name')
            ->join('chart_of_accounts coa', 'coa.id = f.chart_account_id', 'left')
            ->where('f.parish_id', $parishId)
            ->where('f.is_active', 1)
            ->where('f.is_locked', 0)
            ->orderBy('f.fund_type', 'ASC')
            ->orderBy('f.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getFundWithBalance(int $fundId): ?array
    {
        $fund = $this->db->table('fund f')
            ->select('f.*, coa.code as chart_account_code, coa.name as chart_account_name')
            ->join('chart_of_accounts coa', 'coa.id = f.chart_account_id', 'left')
            ->where('f.id', $fundId)
            ->get()
            ->getRowArray();

        if (!$fund) {
            return null;
        }

        $donationRows = $this->db->table('donation')
            ->select('amount')
            ->where('fund_id', $fundId)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();

        $expenses = $this->db->table('expense')
            ->selectSum('amount', 'total')
            ->where('fund_id', $fundId)
            ->get()
            ->getRowArray();

        $fund['total_income'] = $this->sumEncryptedAmounts($donationRows);
        $fund['total_expenses'] = (float) ($expenses['total'] ?? 0);
        $fund['balance'] = $fund['total_income'] - $fund['total_expenses'];

        return $fund;
    }

    /**
     * Parish funds with income/expense totals, balance and transaction counts.
     * Donation amounts are encrypted, so income is summed in PHP; all donation
     * and expense rows for the parish are fetched once rather than per fund.
     */
    public function getParishFundsWithBalances(int $parishId, bool $activeOnly = true): array
    {
        $funds = $this->getParishFunds($parishId, $activeOnly);

        $incomeByFund = [];
        $donationRows = $this->db->table('donation')
            ->select('fund_id, amount')
            ->where('parish_id', $parishId)
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();
        foreach ($donationRows as $row) {
            $incomeByFund[(int) $row['fund_id']][] = $row;
        }

        $expenseByFund = [];
        $expenseRows = $this->db->table('expense')
            ->select('fund_id, SUM(amount) AS total, COUNT(*) AS cnt')
            ->where('parish_id', $parishId)
            ->groupBy('fund_id')
            ->get()
            ->getResultArray();
        foreach ($expenseRows as $row) {
            $expenseByFund[(int) $row['fund_id']] = $row;
        }

        return array_map(function (array $fund) use ($incomeByFund, $expenseByFund): array {
            $fundId = (int) $fund['id'];
            $rows   = $incomeByFund[$fundId] ?? [];

            $fund['total_income']    = $this->sumEncryptedAmounts($rows);
            $fund['total_expenses']  = (float) ($expenseByFund[$fundId]['total'] ?? 0);
            $fund['balance']         = $fund['total_income'] - $fund['total_expenses'];
            $fund['donation_count']  = count($rows);
            $fund['expense_count']   = (int) ($expenseByFund[$fundId]['cnt'] ?? 0);

            return $fund;
        }, $funds);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function sumEncryptedAmounts(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $plain = DataEncryption::decrypt(isset($row['amount']) ? (string) $row['amount'] : null);
            if ($plain === null || $plain === '') {
                continue;
            }
            $total += (float) $plain;
        }

        return $total;
    }
}
