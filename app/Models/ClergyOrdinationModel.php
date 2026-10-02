<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ClergyOrdinationModel extends Model
{
    protected $table = 'clergy_ordination';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'person_id', 'order_code', 'deacon_track', 'ordained_on', 'evidence_reference', 'verification_status',
        'verified_by_user_id', 'verified_at',
    ];
    protected $useTimestamps = true;
    protected array $casts = ['id' => '?int', 'person_id' => '?int', 'verified_by_user_id' => '?int'];
    protected $validationRules = [
        'person_id' => 'required|is_natural_no_zero|is_not_unique[person.id]',
        'order_code' => 'required|in_list[deacon,priest,bishop]',
        'deacon_track' => 'permit_empty|in_list[vocational,transitional]',
        'ordained_on' => 'permit_empty|valid_date',
        'evidence_reference' => 'permit_empty|max_length[255]',
        'verification_status' => 'required|in_list[pending,verified,rejected]',
    ];
}
