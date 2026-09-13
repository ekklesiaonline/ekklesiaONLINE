<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSystemAdminPrimaryTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('system_admin_primary')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 1,
                'unsigned' => true,
                'auto_increment' => false,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('system_admin_primary');
    }

    public function down()
    {
        if ($this->db->tableExists('system_admin_primary')) {
            $this->forge->dropTable('system_admin_primary');
        }
    }
}
