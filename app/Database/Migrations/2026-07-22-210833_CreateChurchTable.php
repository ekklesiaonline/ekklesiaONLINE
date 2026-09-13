<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChurchTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
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
        $this->forge->createTable('church');

        $this->db->table('church')->insert([
            'id'   => 1,
            'name' => 'Episcopal Church',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('church', true);
    }
}
