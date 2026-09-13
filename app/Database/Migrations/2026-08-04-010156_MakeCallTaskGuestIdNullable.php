<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeCallTaskGuestIdNullable extends Migration
{
    public function up()
    {
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
        $this->forge->modifyColumn('call_task', [
            'guest_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
    }
}
