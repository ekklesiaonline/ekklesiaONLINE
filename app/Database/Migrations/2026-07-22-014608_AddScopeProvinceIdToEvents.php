<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddScopeProvinceIdToEvents extends Migration
{
    public function up()
    {
        $this->db->query("ALTER TABLE `event` MODIFY `scope_level` ENUM('parish','diocese','province') NOT NULL DEFAULT 'parish'");

        $this->forge->addColumn('event', [
            'scope_province_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true,
                'after' => 'scope_diocese_id',
            ],
        ]);

        $this->db->query("ALTER TABLE `event` ADD CONSTRAINT `fk_events_scope_province_id` FOREIGN KEY (`scope_province_id`) REFERENCES `province` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        $this->db->query("ALTER TABLE `event` ADD KEY `idx_events_scope_province` (`scope_province_id`)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE `event` DROP FOREIGN KEY `fk_events_scope_province_id`");
        $this->forge->dropColumn('event', 'scope_province_id');
        $this->db->query("ALTER TABLE `event` MODIFY `scope_level` ENUM('parish','diocese') NOT NULL DEFAULT 'parish'");
    }
}
