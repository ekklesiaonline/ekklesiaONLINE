<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class ClergyCanonicalStatusModel extends Model
{
    protected $table = 'clergy_canonical_status';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'person_id', 'diocese_id', 'status_code', 'effective_from', 'effective_to', 'evidence_reference', 'verified_by_user_id',
        'lift_evidence_reference', 'lifted_by_user_id', 'lifted_at',
    ];
    protected $useTimestamps = true;
    protected array $casts = [
        'id' => '?int', 'person_id' => '?int', 'diocese_id' => '?int', 'verified_by_user_id' => '?int',
    ];
    protected $validationRules = [
        'person_id' => 'required|is_natural_no_zero|is_not_unique[person.id]',
        'diocese_id' => 'required|is_natural_no_zero|is_not_unique[diocese.id]',
        'status_code' => 'required|in_list[resident,licensed,transferred,retired,suspended,inhibited]',
        'effective_from' => 'required|valid_date',
        'effective_to' => 'permit_empty|valid_date',
        'evidence_reference' => 'permit_empty|max_length[255]',
    ];
}
