<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropGuestIdFromCallTask extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('guest_id', 'call_task')) {
            return;
        }

        $prefix = $this->db->getPrefix();
        $dbName = $this->db->getDatabase();

        $fk = $this->db->query("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = '{$prefix}call_task'
              AND CONSTRAINT_NAME = 'fk_call_guest'
        ")->getRowArray();

        if ($fk !== null) {
            $this->db->query('ALTER TABLE ' . $prefix . 'call_task DROP FOREIGN KEY fk_call_guest');
        }

        $this->forge->dropColumn('call_task', 'guest_id');
    }

    public function down()
    {
        // Not reversible.
    }
}
