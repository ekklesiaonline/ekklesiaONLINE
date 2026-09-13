<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropGuestVisit extends Migration
{
    public function up()
    {
        $this->forge->dropTable('guest_visit', true);
    }

    public function down()
    {
        // Not reversible; table recreation is out of scope for a rollback.
    }
}
