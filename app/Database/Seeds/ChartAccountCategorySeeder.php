<?php

namespace App\Database\Seeds;

use App\Services\ChartAccountCategoryService;
use CodeIgniter\Database\Seeder;

class ChartAccountCategorySeeder extends Seeder
{
    public function run()
    {
        (new ChartAccountCategoryService($this->db))->seedDefaults();
    }
}
