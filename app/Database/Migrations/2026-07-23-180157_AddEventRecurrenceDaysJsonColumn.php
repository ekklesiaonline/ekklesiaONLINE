<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEventRecurrenceDaysJsonColumn extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('event')) {
            return;
        }

        if ($this->db->fieldExists('recurrence_days_json', 'event')) {
            return;
        }

        $this->forge->addColumn('event', [
            'recurrence_days_json' => [
                'type'       => 'TEXT',
                'null'       => true,
                'after'      => 'recurrence_pattern',
            ],
        ]);
    }

    public function down()
    {
        if (!$this->db->tableExists('event')) {
            return;
        }

        if (!$this->db->fieldExists('recurrence_days_json', 'event')) {
            return;
        }

        $this->forge->dropColumn('event', 'recurrence_days_json');
    }
}
