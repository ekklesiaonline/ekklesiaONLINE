<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DropGuestIdFromFollowupLog extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('guest_id', 'followup_log')) {
            return;
        }

        $prefix = $this->db->getPrefix();
        $dbName = $this->db->getDatabase();

        $fks = $this->db->query("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = '{$prefix}followup_log'
              AND COLUMN_NAME = 'guest_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->getResultArray();

        foreach ($fks as $fk) {
            $this->db->query('ALTER TABLE `' . $prefix . 'followup_log` DROP FOREIGN KEY `' . $fk['CONSTRAINT_NAME'] . '`');
        }

        $this->forge->dropColumn('followup_log', 'guest_id');
    }

    public function down()
    {
        // Not reversible.
    }
}
