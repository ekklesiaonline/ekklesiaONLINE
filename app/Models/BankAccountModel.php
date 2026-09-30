<?php

namespace App\Models;

class BankAccountModel extends BaseTenantModel
{
    protected $table            = 'bank_account';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'chart_account_id',
        'name',
        'institution_name',
        'masked_account_number',
        'routing_last4',
        'currency_code',
        'opening_balance',
        'opened_on',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'parish_id'             => 'required|is_not_unique[parish.id]',
        'name'                  => 'required|max_length[150]',
        'institution_name'      => 'permit_empty|max_length[150]',
        'masked_account_number' => 'permit_empty|max_length[34]',
        'routing_last4'         => 'permit_empty|max_length[4]',
        'currency_code'         => 'required|exact_length[3]|alpha',
        'opening_balance'       => 'permit_empty|decimal',
        'is_active'             => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Accounts belonging to a parish with a computed book balance
     * (opening_balance + SUM(bank_transaction.amount)).
     *
     * @return array<int, array<string, mixed>>
     */
    public function accountsWithBalances(int $parishId): array
    {
        $txnTable = $this->db->prefixTable('bank_transaction');

        return $this->db->table('bank_account ba')
            ->select('ba.*, COALESCE(tx.txn_sum, 0) AS txn_sum, COALESCE(tx.txn_count, 0) AS txn_count')
            ->join(
                "(SELECT bank_account_id, SUM(amount) AS txn_sum, COUNT(*) AS txn_count FROM {$txnTable} GROUP BY bank_account_id) tx",
                'tx.bank_account_id = ba.id',
                'left'
            )
            ->where('ba.parish_id', $parishId)
            ->orderBy('ba.name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
