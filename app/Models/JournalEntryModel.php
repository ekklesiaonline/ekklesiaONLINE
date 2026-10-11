<?php

namespace App\Models;

class JournalEntryModel extends BaseTenantModel
{
    protected $table            = 'journal_entry';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'entry_number',
        'period_id',
        'entry_date',
        'source_type',
        'source_id',
        'memo',
        'status',
        'posted_by',
        'posted_at',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected array $casts = [
        'id'          => '?int',
        'parish_id'   => '?int',
        'period_id'   => '?int',
        'source_id'   => '?int',
        'posted_by'   => '?int',
        'created_by'  => '?int',
    ];

    protected $validationRules = [
        'parish_id'    => 'required|integer',
        'entry_number' => 'required|max_length[50]',
        'entry_date'   => 'required|valid_date',
        'status'       => 'permit_empty|in_list[draft,posted,void]',
    ];
}
