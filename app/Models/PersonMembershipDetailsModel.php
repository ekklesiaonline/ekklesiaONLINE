<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonMembershipDetailsModel extends Model
{
    protected $table            = 'person_membership_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'person_id',
        'parish_id',
        'is_baptized',
        'is_confirmed',
        'is_communicant',
        'is_dependent',
        'has_pledge',
        'pledge_status',
        'good_standing_override',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [
        'is_baptized'            => 'int',
        'is_confirmed'           => 'int',
        'is_communicant'         => 'int',
        'is_dependent'           => 'int',
        'has_pledge'             => 'int',
        'good_standing_override' => 'int',
    ];
    protected array $castHandlers = [];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
