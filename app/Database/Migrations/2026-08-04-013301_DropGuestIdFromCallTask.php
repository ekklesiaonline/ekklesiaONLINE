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

        $fks = $this->db->query("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = '{$prefix}call_task'
              AND COLUMN_NAME = 'guest_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->getResultArray();

        foreach ($fks as $fk) {
            $this->db->query('ALTER TABLE `' . $prefix . 'call_task` DROP FOREIGN KEY `' . $fk['CONSTRAINT_NAME'] . '`');
        }

        $this->forge->dropColumn('call_task', 'guest_id');
    }

    public function down()
    {
        // Not reversible.
    }
}
