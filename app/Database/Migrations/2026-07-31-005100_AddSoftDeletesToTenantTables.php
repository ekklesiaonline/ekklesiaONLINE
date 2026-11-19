<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSoftDeletesToTenantTables extends Migration
{
    private const TABLES = [
        'family',
        'family_member',
        'event',
        'profile',
        'donation',
        'parish_people',
    ];

    public function up()
    {
        $column = [
            'deleted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'updated_at',
            ],
        ];

        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            if ($this->db->fieldExists('deleted_at', $table)) {
                continue;
            }

            $this->forge->addColumn($table, $column);
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            if (! $this->db->fieldExists('deleted_at', $table)) {
                continue;
            }

            $this->forge->dropColumn($table, 'deleted_at');
        }
    }
}
