<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProvinceAdminPrimaryTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('province_admin_primary')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'province_id' => [
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
        $this->forge->addUniqueKey('province_id');
        $this->forge->createTable('province_admin_primary');
    }

    public function down()
    {
        if ($this->db->tableExists('province_admin_primary')) {
            $this->forge->dropTable('province_admin_primary');
        }
    }
}
