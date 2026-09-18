<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Drops the legacy parish_people snapshot table (T4).
 *
 * parish_people was a derived read-model keyed by user_id/profile_id,
 * rebuilt by the retired ParishPeopleRegistryService. All readers now use
 * canonical queries against parish_affiliation + person, so the snapshot is
 * dropped. See docs/LEGACY_PATH_RETIREMENT.md.
 */
class DropParishPeopleTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable('parish_people', true);
    }

    public function down()
    {
        // Recreate the snapshot table structure so a rollback does not lose
        // the schema (data is not restorable — it was derived).
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
                'null'       => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'profile_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'first_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'last_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'nickname' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'profile_picture' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'portal_access' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'none',
            ],
            'membership_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'visitor',
            ],
            'is_member' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'is_good_standing' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'is_leader' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'leader_titles' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_baptized' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'is_confirmed' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'is_communicant' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'is_dependent' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'is_under_16' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'has_pledge' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ],
            'source' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'user_profile',
            ],
            'synced_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('user_id');
        $this->forge->createTable('parish_people', true);
    }
}
