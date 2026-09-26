<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remove province scope from the follow-up tables.
 *
 * Provinces remain part of the organizational hierarchy but are not a
 * follow-up sequence/template scope: the cascade is system default →
 * diocesan default → parish. These columns only exist in environments
 * where the pre-release scope migration ran with a province tier.
 */
class DropFollowupProvinceScope extends Migration
{
    public function up()
    {
        foreach (['province_id', 'is_province_default'] as $col) {
            if ($this->db->fieldExists($col, 'followup_sequence')) {
                $this->forge->dropColumn('followup_sequence', $col);
            }
        }

        if ($this->db->fieldExists('province_id', 'followup_template')) {
            $this->forge->dropColumn('followup_template', 'province_id');
        }
    }

    public function down()
    {
        if (! $this->db->fieldExists('province_id', 'followup_sequence')) {
            $this->forge->addColumn('followup_sequence', [
                'province_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'diocese_id',
                ],
                'is_province_default' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'unsigned'   => true,
                    'null'       => false,
                    'default'    => 0,
                    'after'      => 'is_diocese_default',
                ],
            ]);
        }

        if (! $this->db->fieldExists('province_id', 'followup_template')) {
            $this->forge->addColumn('followup_template', [
                'province_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'diocese_id',
                ],
            ]);
        }
    }
}
