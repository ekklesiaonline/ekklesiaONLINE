<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFollowupSequenceDefaultFlags extends Migration
{
    public function up()
    {
        // Fresh-install guard: the create migration may already ship these
        // columns; add each only when missing so the migration stays rerunnable.
        if (! $this->db->fieldExists('is_default', 'followup_sequence')) {
            $this->forge->addColumn('followup_sequence', [
                'is_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_active',
                ],
            ]);
        }

        if (! $this->db->fieldExists('is_global_default', 'followup_sequence')) {
            $this->forge->addColumn('followup_sequence', [
                'is_global_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_default',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('followup_sequence', 'is_default');
        $this->forge->dropColumn('followup_sequence', 'is_global_default');
    }
}
