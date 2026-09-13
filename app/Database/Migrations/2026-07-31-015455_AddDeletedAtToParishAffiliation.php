<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeletedAtToParishAffiliation extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        if ($this->db->fieldExists('deleted_at', 'parish_affiliation')) {
            return;
        }

        $this->forge->addColumn('parish_affiliation', [
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
        if (! $this->db->tableExists('parish_affiliation')) {
            return;
        }

        if (! $this->db->fieldExists('deleted_at', 'parish_affiliation')) {
            return;
        }

        $this->forge->dropColumn('parish_affiliation', 'deleted_at');
    }
}
