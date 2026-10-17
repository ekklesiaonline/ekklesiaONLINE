<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeCallTaskGuestIdNullable extends Migration
{
    public function up()
    {
        // Fresh-install guard: guest_id was later dropped; only modify when present.
        if (! $this->db->fieldExists('guest_id', 'call_task')) {
            return;
        }

        $this->forge->modifyColumn('call_task', [
            'guest_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->fieldExists('guest_id', 'call_task')) {
            return;
        }

        $this->forge->modifyColumn('call_task', [
            'guest_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
    }
}
