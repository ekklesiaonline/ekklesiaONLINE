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
        'bank_account_id' => '?int',
        'amount'          => '?float',
    ];

    protected $beforeUpdate = ['checkClosedPeriodLock'];
    protected $beforeDelete = ['checkClosedPeriodLock'];

    /**
     * Locks mutations on reconciled transactions or transactions within closed periods.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function checkClosedPeriodLock(array $data): array
    {
        $id = $data['id'][0] ?? null;
        if (!$id) {
            return $data;
        }

        $existing = $this->find($id);
        if (!$existing) {
            return $data;
        }

        // If transaction is already reconciled, lock edits to amount/date/delete
        $isStatusChangeOnly = isset($data['data']) && count($data['data']) === 1 && isset($data['data']['status']);
        if (($existing['status'] ?? '') === 'reconciled' && !$isStatusChangeOnly) {
            throw new \RuntimeException('Reconciled bank transactions cannot be modified or deleted.');
        }

        // Check if transaction date is in a closed accounting period
        $bankAccount = $this->db->table('bank_account')
            ->where('id', (int) $existing['bank_account_id'])
            ->get()
            ->getRowArray();

        if ($bankAccount && !empty($bankAccount['parish_id'])) {
            $periodModel = model(AccountingPeriodModel::class);
            if ($periodModel->isDateInClosedPeriod((int) $bankAccount['parish_id'], (string) $existing['transaction_date'])) {
                throw new \RuntimeException('Bank transactions in a closed accounting period cannot be modified or deleted.');
            }
        }

        return $data;
    }

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
