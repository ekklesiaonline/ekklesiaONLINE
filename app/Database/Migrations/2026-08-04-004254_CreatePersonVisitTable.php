<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePersonVisitTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'person_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => false,
            ],
            'parish_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
            ],
            'visit_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'visit_type' => [
                'type'       => 'ENUM',
                'constraint' => ['first', 'second', 'return'],
                'default'    => 'first',
                'null'       => false,
            ],
            'service_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK to services if visit maps to a tracked service',
            ],
            'attended_event' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Free-form label when service_id is unavailable',
            ],
            'gift_given' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'comment'    => '1 = welcome gift was given to the person on this visit',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
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
        $this->forge->addKey('person_id');
        $this->forge->addKey(['parish_id', 'visit_date']);
        $this->forge->addKey('service_id');
        $this->forge->addForeignKey('person_id', 'person', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('parish_id', 'parish', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('service_id', 'service', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('person_visit');
    }

    public function down()
    {
        $this->forge->dropTable('person_visit');
    }
}
