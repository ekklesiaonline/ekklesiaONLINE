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
        $builder = $this->where('parish_id', $parishId);

        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        return $builder
            ->orderBy('fund_type', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getFundWithBalance(int $fundId): ?array
    {
        $fund = $this->find($fundId);
        if (!$fund) {
            return null;
        }

        $donationRows = $this->db->table('donation')
            ->select('amount')
            ->where('fund_id', $fundId)
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

    public function getParishFundsWithBalances(int $parishId): array
    {
        $funds = $this->getParishFunds($parishId, true);

        return array_map(function (array $fund): array {
            $incomeRows = $this->db->table('donation')
                ->select('amount')
                ->where('fund_id', (int) $fund['id'])
                ->get()
                ->getResultArray();

            $expenses = $this->db->table('expense')
                ->selectSum('amount', 'total')
                ->where('fund_id', (int) $fund['id'])
                ->get()
                ->getRowArray();

            $fund['total_income'] = $this->sumEncryptedAmounts($incomeRows);
            $fund['total_expenses'] = (float) ($expenses['total'] ?? 0);
            $fund['balance'] = $fund['total_income'] - $fund['total_expenses'];

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
