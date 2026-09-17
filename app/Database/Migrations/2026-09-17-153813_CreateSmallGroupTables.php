<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the small-group ministry tables and wires pathway tracking.
 *
 * small_group       — parish-scoped small group (name, meeting rhythm, capacity).
 * group_membership  — a person's placement in a group (role-aware, multiple
 *                     groups per person allowed; one active row per pair).
 *
 * Also adds:
 *  - parish_affiliation.pathway_stage   — denormalized 1-3 pathway stage,
 *    maintained by PathwayStageService.
 *  - member_standing_summary.is_integrated — good-standing member who is also
 *    placed in a small group or ministry team ("Active Member" in the
 *    membership framework).
 */
class CreateSmallGroupTables extends Migration
{
    public function up()
    {
        // small_group
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
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'meeting_day' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'meeting_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'location' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'capacity' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'is_active' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
                'null' => false,
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
        $this->forge->addKey('is_active');
        $this->forge->createTable('small_group', true);

        // group_membership
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'small_group_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'person_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => false,
            ],
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['member', 'leader', 'co_leader', 'host'],
                'default' => 'member',
                'null' => false,
            ],
            'status' => [
                'type' => 'ENUM',
                'constraint' => ['active', 'inactive'],
                'default' => 'active',
                'null' => false,
            ],
            'joined_on' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'left_on' => [
                'type' => 'DATE',
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
        $this->forge->addKey('small_group_id');
        $this->forge->addKey('person_id');
        $this->forge->addUniqueKey(['small_group_id', 'person_id']);
        $this->forge->createTable('group_membership', true);

        // parish_affiliation.pathway_stage — denormalized 1-3 stage
        if (! $this->db->fieldExists('pathway_stage', 'parish_affiliation')) {
            $this->forge->addColumn('parish_affiliation', [
                'pathway_stage' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'membership_status',
                ],
            ]);
        }

        // member_standing_summary.is_integrated — good-standing member placed
        // in a small group or ministry team (framework "Active Member").
        if (! $this->db->fieldExists('is_integrated', 'member_standing_summary')) {
            $this->forge->addColumn('member_standing_summary', [
                'is_integrated' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                    'null' => false,
                    'after' => 'is_good_standing',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('is_integrated', 'member_standing_summary')) {
            $this->forge->dropColumn('member_standing_summary', 'is_integrated');
        }
        if ($this->db->fieldExists('pathway_stage', 'parish_affiliation')) {
            $this->forge->dropColumn('parish_affiliation', 'pathway_stage');
        }
        $this->forge->dropTable('group_membership', true);
        $this->forge->dropTable('small_group', true);
    }
}
