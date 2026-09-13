<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateParishAdminPrimaryTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('parish_admin_primary')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'parish_id' => [
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
        $this->forge->addUniqueKey('parish_id');
        $this->forge->createTable('parish_admin_primary');
    }

    public function down()
    {
        if ($this->db->tableExists('parish_admin_primary')) {
            $this->forge->dropTable('parish_admin_primary');
        }
    }
}
