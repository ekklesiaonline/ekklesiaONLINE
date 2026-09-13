<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonOnboardingModel extends Model
{
    protected $table            = 'person_onboarding';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'person_id',
        'parish_id',
        'onboarding_status',
        'started_at',
        'completed_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'person_id' => 'int',
        'parish_id' => '?int',
    ];
    protected array $castHandlers = [];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
