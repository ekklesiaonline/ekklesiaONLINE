<?php

namespace App\Models;

use CodeIgniter\Model;

class JournalEntryLineModel extends Model
{
    protected $table            = 'journal_entry_line';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'journal_entry_id',
        'chart_account_id',
        'debit_amount',
        'credit_amount',
        'memo',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected array $casts = [
        'id'               => '?int',
        'journal_entry_id' => '?int',
        'chart_account_id' => '?int',
        'debit_amount'     => '?float',
        'credit_amount'    => '?float',
    ];

    protected $validationRules = [
        'journal_entry_id' => 'required|integer',
        'chart_account_id' => 'required|integer',
        'debit_amount'     => 'permit_empty|decimal',
        'credit_amount'    => 'permit_empty|decimal',
        'memo'             => 'permit_empty|max_length[255]',
    ];

    /**
     * Retrieve line items for a journal entry joined with chart of accounts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLinesForEntry(int $journalEntryId): array
    {
        return $this->db->table($this->table . ' jel')
            ->select('jel.*, coa.code as account_code, coa.name as account_name, coa.account_type')
            ->join('chart_of_accounts coa', 'coa.id = jel.chart_account_id', 'left')
            ->where('jel.journal_entry_id', $journalEntryId)
            ->orderBy('jel.id', 'ASC')
            ->get()
            ->getResultArray();
    }
}

