<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrganizerColumnsToEvents extends Migration
{
    public function up()
    {
        $definitions = [
            'organizer_type' => [
                'type'       => 'ENUM',
                'constraint' => ['national','province','diocese','parish'],
                'default'    => 'parish',
                'null'       => false,
            ],
            'organizer_parish_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'organizer_province_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'organizer_diocese_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'host_parish_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
        ];

        $toAdd       = [];
        $afterColumn = 'scope_province_id';
        foreach ($definitions as $name => $def) {
            if (! $this->db->fieldExists($name, 'event')) {
                $def['after']  = $afterColumn;
                $toAdd[$name] = $def;
            }
            $afterColumn = $name;
        }

        if ($toAdd !== []) {
            $this->forge->addColumn('event', $toAdd);
        }

        $indexes = [
            'idx_event_organizer_parish'   => '(`organizer_type`,`organizer_parish_id`)',
            'idx_event_organizer_province' => '(`organizer_type`,`organizer_province_id`)',
            'idx_event_organizer_diocese'  => '(`organizer_type`,`organizer_diocese_id`)',
            'idx_event_host_parish'        => '(`host_parish_id`)',
        ];

        foreach ($indexes as $name => $columns) {
            $exists = $this->db->query('SHOW INDEX FROM `event` WHERE Key_name = ?', [$name])->getNumRows() > 0;
            if (! $exists) {
                $this->db->query("ALTER TABLE `event` ADD KEY `{$name}` {$columns}");
            }
        }
    }

    public function down()
    {
        $toDrop = [];
        foreach (['host_parish_id', 'organizer_diocese_id', 'organizer_province_id', 'organizer_parish_id', 'organizer_type'] as $column) {
            if ($this->db->fieldExists($column, 'event')) {
                $toDrop[] = $column;
            }
        }

        if ($toDrop !== []) {
            $this->forge->dropColumn('event', $toDrop);
        }
    }
}
