<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixPersonContactColumns extends Migration
{
    public function up()
    {
        $fields = [
            'email_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'email',
            ],
            'phone_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'phone',
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'preferred_contact_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => 'email',
            ],
            'preferred_timezone' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'opted_out_channels' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => '',
            ],
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => 'system',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
        ];

        foreach ($fields as $column => $definition) {
            if (! $this->db->fieldExists($column, 'person')) {
                $this->forge->addColumn('person', [$column => $definition]);
            }
        }
    }

    public function down()
    {
        foreach ([
            'email_hash',
            'phone_hash',
            'address',
            'preferred_contact_method',
            'preferred_timezone',
            'opted_out_channels',
            'source',
            'is_active',
        ] as $column) {
            if ($this->db->fieldExists($column, 'person')) {
                $this->forge->dropColumn('person', $column);
            }
        }
    }
}
