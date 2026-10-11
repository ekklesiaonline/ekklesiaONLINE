<?php

namespace App\Models;

use CodeIgniter\Model;

class BankTransactionJournalLinkModel extends Model
{
    protected $table            = 'bank_transaction_journal_link';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'bank_transaction_id',
        'journal_entry_id',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected array $casts = [
        'id'                  => '?int',
        'bank_transaction_id' => '?int',
        'journal_entry_id'    => '?int',
    ];

    protected $validationRules = [
        'bank_transaction_id' => 'required|integer',
        'journal_entry_id'    => 'required|integer',
    ];
}
