<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class DeaneryModel extends Model
{
    protected $table = 'deanery';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['diocese_id', 'code', 'name', 'active'];
    protected $useTimestamps = true;
    protected array $casts = ['id' => '?int', 'diocese_id' => '?int', 'active' => '?bool'];
    protected $validationRules = [
        'diocese_id' => 'required|is_natural_no_zero|is_not_unique[diocese.id]',
        'code' => 'required|alpha_dash|max_length[64]',
        'name' => 'required|min_length[2]|max_length[150]',
        'active' => 'permit_empty|in_list[0,1]',
    ];
}
