<?php

namespace App\Models;

use CodeIgniter\Model;

class BankReconciliationItemModel extends Model
{
    protected $table            = 'bank_reconciliation_item';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'bank_reconciliation_id',
        'bank_transaction_id',
        'is_cleared',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'bank_reconciliation_id' => 'required|is_not_unique[bank_reconciliation.id]',
        'bank_transaction_id'    => 'required|is_not_unique[bank_transaction.id]',
        'is_cleared'             => 'permit_empty|in_list[0,1]',
    ];

    protected array $casts = [
        'bank_reconciliation_id' => 'int',
        'bank_transaction_id'    => '?int',
        'is_cleared'             => 'int',
    ];

    /**
     * Transaction ids already marked cleared in a reconciliation.
     *
     * @return array<int, int>
     */
    public function clearedTransactionIds(int $reconciliationId): array
    {
        $rows = $this->where('bank_reconciliation_id', $reconciliationId)
            ->where('is_cleared', 1)
            ->findAll();

        return array_map(static fn (array $row): int => (int) $row['bank_transaction_id'], $rows);
    }
}
