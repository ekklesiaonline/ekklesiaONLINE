<?php

namespace App\Models;

use CodeIgniter\Model;

class BankTransactionModel extends Model
{
    protected $table            = 'bank_transaction';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'bank_account_id',
        'transaction_date',
        'amount',
        'description',
        'reference',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'bank_account_id'  => 'required|is_not_unique[bank_account.id]',
        'transaction_date' => 'required|valid_date',
        'amount'           => 'required|decimal',
        'description'      => 'permit_empty|max_length[255]',
        'reference'        => 'permit_empty|max_length[100]',
        'status'           => 'permit_empty|in_list[unmatched,cleared,reconciled]',
    ];

    protected array $casts = [
        'bank_account_id' => 'int',
        'amount'          => 'float',
    ];

    /**
     * Transactions for an account, optionally limited to a date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAccount(int $bankAccountId, ?string $throughDate = null): array
    {
        $builder = $this->where('bank_account_id', $bankAccountId)
            ->orderBy('transaction_date', 'DESC')
            ->orderBy('id', 'DESC');

        if ($throughDate !== null) {
            $builder->where('transaction_date <=', $throughDate);
        }

        return $builder->findAll();
    }
}
