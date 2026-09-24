<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * person_note — appendable staff notes on a canonical person row.
 *
 * Powers the visitor record's Notes tab, which aggregates these entries
 * with intake-form notes (person_visit.notes) and follow-up outcome notes
 * (followup_log.notes / call_task.notes) into a single chronological feed.
 */
class CreatePersonNoteTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('person_note')) {
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
                'null'       => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => 'author (users.id)',
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['person_id', 'parish_id']);
        $this->forge->addKey('parish_id');
        $this->forge->createTable('person_note');
    }

    public function down()
    {
        $this->forge->dropTable('person_note', true);
    }
}
