<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonStatusHistoryModel extends Model
{
    protected $table            = 'person_status_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    public function getTableName(): string
    {
        return $this->table;
    }

    protected $allowedFields    = [
        'person_id',
        'parish_id',
        'status_type',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
        'created_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'person_id'  => 'int',
        'parish_id'  => '?int',
        'changed_by' => '?int',
    ];
    protected array $castHandlers = [];

    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
}
