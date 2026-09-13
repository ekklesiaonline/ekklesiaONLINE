<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsureParishAffiliationIsPrimary extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('is_primary', 'parish_affiliation')) {
            $this->forge->addColumn('parish_affiliation', [
                'is_primary' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'updated_at',
                ],
            ]);
            $this->db->query('UPDATE `parish_affiliation` SET `is_primary` = 0');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_primary', 'parish_affiliation')) {
            $this->forge->dropColumn('parish_affiliation', 'is_primary');
        }
    }
}
