<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropUserScopeAssignmentTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable('user_scope_assignment', true);
    }

    public function down()
    {
        // Intentionally empty: this migration removes an unused legacy table.
    }
}
