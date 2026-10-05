<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonIdToGuestVisit extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('guest_visit')) {
            return;
        }
        $this->forge->addColumn('guest_visit', [
            'person_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'guest_id',
            ],
        ]);
        $this->forge->addKey('person_id');
    }

    public function down()
    {
        $this->forge->dropColumn('guest_visit', 'person_id');
    }
}
