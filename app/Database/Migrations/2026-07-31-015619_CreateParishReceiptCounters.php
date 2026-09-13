<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateParishReceiptCounters extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('parish_receipt_counter')) {
            return;
        }

        $this->forge->addField([
            'parish_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'last_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
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

        $this->forge->addKey('parish_id', true);
        $this->forge->createTable('parish_receipt_counter');
    }

    public function down()
    {
        if ($this->db->tableExists('parish_receipt_counter')) {
            $this->forge->dropTable('parish_receipt_counter');
        }
    }
}
