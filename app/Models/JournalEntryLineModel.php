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
}
