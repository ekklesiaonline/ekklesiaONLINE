<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProfileLocaleColumn extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('locale', 'profile')) {
            $this->forge->addColumn('profile', [
                'locale' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => true,
                    'default'    => 'en',
                    'after'      => 'spiritual_gifts',
                ],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('profile', 'locale');
    }
}
