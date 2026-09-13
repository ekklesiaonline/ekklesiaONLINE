<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDioceseAdminPrimaryTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('diocese_admin_primary')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'diocese_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('user_id');
        $this->forge->addUniqueKey('diocese_id');
        $this->forge->createTable('diocese_admin_primary');
    }

    public function down()
    {
        if ($this->db->tableExists('diocese_admin_primary')) {
            $this->forge->dropTable('diocese_admin_primary');
        }
    }
}
