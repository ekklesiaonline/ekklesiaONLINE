<?php

namespace App\Models;

use CodeIgniter\Model;

class BankReconciliationModel extends Model
{
    protected $table            = 'bank_reconciliation';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'bank_account_id',
        'statement_date',
        'statement_balance',
        'status',
        'closed_by',
        'closed_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'bank_account_id'   => 'required|is_not_unique[bank_account.id]',
        'statement_date'    => 'required|valid_date',
        'statement_balance' => 'required|decimal',
        'status'            => 'permit_empty|in_list[open,closed]',
    ];

    public function openForAccount(int $bankAccountId): ?array
    {
        $row = $this->where('bank_account_id', $bankAccountId)
            ->where('status', 'open')
            ->orderBy('id', 'DESC')
            ->first();

        return $row ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forAccount(int $bankAccountId): array
    {
        return $this->where('bank_account_id', $bankAccountId)
            ->orderBy('statement_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }
}
