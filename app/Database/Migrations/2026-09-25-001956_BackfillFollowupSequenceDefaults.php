<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillFollowupSequenceDefaults extends Migration
{
    public function up()
    {
        $db = $this->db;

        // Set the oldest active sequence in each parish as the default.
        // This preserves the current behavior for parishes that already have
        // active sequences after we switch first-visit scheduling to default-only.
        if ($db->DBDriver === 'MySQLi') {
            $db->query("
                UPDATE followup_sequence fs
                JOIN (
                    SELECT parish_id, MIN(id) AS id
                    FROM followup_sequence
                    WHERE parish_id IS NOT NULL
                      AND is_active = 1
                      AND is_default = 0
                    GROUP BY parish_id
                ) oldest ON oldest.id = fs.id
                SET fs.is_default = 1
            ");
        } else {
            // SQLite fallback: use a correlated update via PHP loop.
            $rows = $db->query("
                SELECT parish_id, MIN(id) AS id
                FROM followup_sequence
                WHERE parish_id IS NOT NULL
                  AND is_active = 1
                GROUP BY parish_id
            ")->getResultArray();

            foreach ($rows as $row) {
                $db->table('followup_sequence')
                    ->where('id', (int) $row['id'])
                    ->update(['is_default' => 1]);
            }
        }
    }

    public function down()
    {
        $this->db->table('followup_sequence')->update(['is_default' => 0]);
    }
}
