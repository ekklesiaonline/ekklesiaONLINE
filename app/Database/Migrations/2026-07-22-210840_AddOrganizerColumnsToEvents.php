<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrganizerColumnsToEvents extends Migration
{
    public function up()
    {
        $this->forge->addColumn('event', [
            'organizer_type' => [
                'type'       => 'ENUM',
                'constraint' => ['national','province','diocese','parish'],
                'default'    => 'parish',
                'null'       => false,
                'after'      => 'scope_province_id',
            ],
            'organizer_church_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'organizer_type',
            ],
            'organizer_province_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'organizer_church_id',
            ],
            'organizer_diocese_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'organizer_province_id',
            ],
            'organizer_parish_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'organizer_diocese_id',
            ],
            'host_parish_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'organizer_parish_id',
            ],
        ]);

        $this->db->query("ALTER TABLE `event` ADD KEY `idx_event_organizer_church` (`organizer_type`,`organizer_church_id`)");
        $this->db->query("ALTER TABLE `event` ADD KEY `idx_event_organizer_province` (`organizer_type`,`organizer_province_id`)");
        $this->db->query("ALTER TABLE `event` ADD KEY `idx_event_organizer_diocese` (`organizer_type`,`organizer_diocese_id`)");
        $this->db->query("ALTER TABLE `event` ADD KEY `idx_event_organizer_parish` (`organizer_type`,`organizer_parish_id`)");
        $this->db->query("ALTER TABLE `event` ADD KEY `idx_event_host_parish` (`host_parish_id`)");
    }

    public function down()
    {
        $this->forge->dropColumn('event', [
            'host_parish_id',
            'organizer_parish_id',
            'organizer_diocese_id',
            'organizer_province_id',
            'organizer_church_id',
            'organizer_type',
        ]);
    }
}
