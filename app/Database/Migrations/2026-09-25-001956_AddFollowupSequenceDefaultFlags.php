<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFollowupSequenceDefaultFlags extends Migration
{
    public function up()
    {
        $this->forge->addColumn('followup_sequence', [
            'is_default' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
                'after'      => 'is_active',
            ],
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

    public function down()
    {
        $this->forge->dropColumn('followup_sequence', 'is_default');
        $this->forge->dropColumn('followup_sequence', 'is_global_default');
    }
}
