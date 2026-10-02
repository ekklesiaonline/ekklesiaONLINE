<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ClergyAccessGrantModel extends Model
{
    protected $table = 'clergy_access_grant';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'user_id', 'permission', 'scope_type', 'scope_id', 'source_position_id',
        'grant_type', 'granted_by_user_id', 'evidence_reference', 'starts_at', 'ends_at', 'revoked_at',
    ];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => '?int', 'user_id' => '?int', 'scope_id' => '?int',
        'source_position_id' => '?int', 'granted_by_user_id' => '?int',
    ];
    protected $validationRules = [
        'user_id' => 'required|is_natural_no_zero|is_not_unique[users.id]',
        'permission' => 'required|max_length[100]',
        'scope_type' => 'required|in_list[parish,deanery,diocese,province]',
        'scope_id' => 'required|is_natural_no_zero',
        'grant_type' => 'required|in_list[office,delegation]',
        'granted_by_user_id' => 'required|is_natural_no_zero|is_not_unique[users.id]',
        'evidence_reference' => 'permit_empty|max_length[255]',
        'starts_at' => 'required|valid_date',
        'ends_at' => 'permit_empty|valid_date',
    ];
}
