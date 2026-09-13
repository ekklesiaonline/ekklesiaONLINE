<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLiturgicalAndServiceColumnsToService extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('service')) {
            return;
        }

        if (! $this->db->fieldExists('liturgical_day', 'service')) {
            $this->forge->addColumn('service', [
                'liturgical_day' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'after'      => 'service_time',
                ],
            ]);
        }

        if (! $this->db->fieldExists('service', 'service')) {
            $this->db->query('ALTER TABLE `service` ADD COLUMN `service` JSON NULL AFTER `liturgical_day`');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('service')) {
            return;
        }

        if ($this->db->fieldExists('service', 'service')) {
            $this->db->query('ALTER TABLE `service` DROP COLUMN `service`');
        }

        if ($this->db->fieldExists('liturgical_day', 'service')) {
            $this->forge->dropColumn('service', 'liturgical_day');
        }
    }
}
