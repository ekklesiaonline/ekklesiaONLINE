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

        $fk = $this->db->query("
            SELECT CONSTRAINT_NAME
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = '{$dbName}'
              AND TABLE_NAME = '{$prefix}followup_log'
              AND CONSTRAINT_NAME = 'fk_followup_guest'
        ")->getRowArray();

        if ($fk !== null) {
            $this->db->query('ALTER TABLE ' . $prefix . 'followup_log DROP FOREIGN KEY fk_followup_guest');
        }

        $this->forge->dropColumn('followup_log', 'guest_id');
    }

    public function down()
    {
        // Not reversible.
    }
}
