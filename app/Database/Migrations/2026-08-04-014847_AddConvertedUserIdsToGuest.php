<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConvertedUserIdsToGuest extends Migration
{
    public function up()
    {
        // Fresh-install guard: the guest table was retired; only run on legacy schemas.
        if (! $this->db->tableExists('guest')) {
            return;
        }

        if (! $this->db->fieldExists('converted_user_id', 'guest')) {
            $this->forge->addColumn('guest', [
                'converted_user_id' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'converted_to_member_at',
                ],
            ]);
        }

        if (! $this->db->fieldExists('converted_by_user_id', 'guest')) {
            $this->forge->addColumn('guest', [
                'converted_by_user_id' => [
                    'type'       => 'INT',
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'converted_user_id',
                ],
            ]);
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('guest')) {
            return;
        }

        if ($this->db->fieldExists('converted_user_id', 'guest')) {
            $this->forge->dropColumn('guest', 'converted_user_id');
        }
        if ($this->db->fieldExists('converted_by_user_id', 'guest')) {
            $this->forge->dropColumn('guest', 'converted_by_user_id');
        }
    }
}
