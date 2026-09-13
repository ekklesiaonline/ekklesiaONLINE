<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtToParish extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish')) {
            return;
        }

        if ($this->db->fieldExists('deleted_at', 'parish')) {
            return;
        }

        $this->forge->addColumn('parish', [
            'deleted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'updated_at',
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->tableExists('parish')) {
            return;
        }

        if (! $this->db->fieldExists('deleted_at', 'parish')) {
            return;
        }

        $this->forge->dropColumn('parish', 'deleted_at');
    }
}
