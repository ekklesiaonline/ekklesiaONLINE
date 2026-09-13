<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParishIdToFamilyTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('family')) {
            return;
        }

        if ($this->db->fieldExists('parish_id', 'family')) {
            return;
        }

        $this->forge->addColumn('family', [
            'parish_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'id',
            ],
        ]);

        $this->forge->addKey('parish_id', false, false, 'church_id');
        $this->forge->addForeignKey('parish_id', 'parish', 'id', 'CASCADE', 'CASCADE', 'families_church_id_foreign');
    }

    public function down()
    {
        if (! $this->db->tableExists('family')) {
            return;
        }

        if (! $this->db->fieldExists('parish_id', 'family')) {
            return;
        }

        $this->forge->dropForeignKey('family', 'families_church_id_foreign');
        $this->forge->dropColumn('family', 'parish_id');
    }
}
