<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the vestry governance tables: meetings, motions, and votes.
 *
 * vestry_meeting  — parish-scoped meeting record with minutes.
 * vestry_motion   — a motion raised in a meeting, with status tracking.
 * vestry_vote     — an individual vote cast by a user on a motion.
 */
class CreateVestryTables extends Migration
{
    public function up()
    {
        // vestry_meeting
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'parish_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'meeting_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'meeting_type' => [
                'type' => 'ENUM',
                'constraint' => ['regular', 'special', 'annual'],
                'default' => 'regular',
                'null' => false,
            ],
            'minutes_text' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('parish_id');
        $this->forge->addKey('meeting_date');
        $this->forge->createTable('vestry_meeting', true);

        // vestry_motion
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'meeting_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ],
            'body_text' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'moved_by_person_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'seconded_by_person_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['open', 'passed', 'failed', 'tabled'],
                'default' => 'open',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('meeting_id');
        $this->forge->addKey('status');
        $this->forge->createTable('vestry_motion', true);

        // vestry_vote
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'motion_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'vote' => [
                'type' => 'ENUM',
                'constraint' => ['yea', 'nay', 'abstain'],
                'null' => false,
            ],
            'voted_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('motion_id');
        $this->forge->addKey('user_id');
        $this->forge->addUniqueKey(['motion_id', 'user_id']);
        $this->forge->createTable('vestry_vote', true);
    }

    public function down()
    {
        $this->forge->dropTable('vestry_vote', true);
        $this->forge->dropTable('vestry_motion', true);
        $this->forge->dropTable('vestry_meeting', true);
    }
}
