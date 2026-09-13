<?php

namespace App\Models;

use CodeIgniter\Model;

class ServicePeopleModel extends Model
{
    protected $table            = 'service_people';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'service_id',
        'person_name',
        'role_name',
        'sort_order',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
