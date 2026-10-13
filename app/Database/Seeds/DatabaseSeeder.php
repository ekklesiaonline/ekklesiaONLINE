<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * DatabaseSeeder
 *
 * Master seeder that runs all other seeders in the correct order.
 */
class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // Run seeders in order of dependencies
        $this->call('PermissionsAndRolesSeeder');
        $this->call('ProvincesAndDiocesesSeeder');
        $this->call('ChartAccountCategorySeeder');
        $this->call('ChartOfAccountsSeeder');
        // Add more seeders here as needed
    }
}
