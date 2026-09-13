<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonStatusHistory extends Migration
{
    public function up()
    {
        $this->createPersonStatusHistoryTable();
    }

    public function down()
    {
        $this->dropPersonStatusHistoryTable();
    }

    private function createPersonStatusHistoryTable(): void
    {
        if ($this->db->tableExists('person_status_history')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'person_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'parish_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'status_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => false,
            ],
            'from_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'to_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'changed_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('person_id');
        $this->forge->addKey('parish_id');
        $this->forge->addKey('status_type');
        $this->forge->addKey('created_at');
        $this->forge->createTable('person_status_history');
    }

    private function dropPersonStatusHistoryTable(): void
    {
        if ($this->db->tableExists('person_status_history')) {
            $this->forge->dropTable('person_status_history');
        }
    }
}
