<?php

namespace App\Models;

use CodeIgniter\Model;

class ChartAccountCategoryModel extends Model
{
    protected $table = 'chart_account_category';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $protectFields = true;
    protected $allowedFields = ['category_key', 'parent_id', 'account_type', 'is_selectable', 'is_active', 'sort_order'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';

    protected array $casts = [
        'id' => '?int',
        'parent_id' => '?int',
        'is_selectable' => '?bool',
        'is_active' => '?bool',
        'sort_order' => '?int',
    ];
}
