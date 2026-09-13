<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConvertedUserIdsToGuest extends Migration
{
    public function up()
    {
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
        $this->forge->dropColumn('guest', 'converted_user_id');
        $this->forge->dropColumn('guest', 'converted_by_user_id');
    }
}
