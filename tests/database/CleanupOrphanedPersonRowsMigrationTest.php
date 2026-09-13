<?php

require_once APPPATH . 'Database/Migrations/2026-08-21-000000_CleanupOrphanedPersonRows.php';

use App\Database\Migrations\CleanupOrphanedPersonRows;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class CleanupOrphanedPersonRowsMigrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    public function testMigrationRemovesOrphanedPersonRowsButKeepsReferencedOnes(): void
    {
        // Person with a profile reference.
        $this->db->table('person')->insert([
            'first_name' => 'Referenced',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $keptId = (int) $this->db->insertID();

        $this->db->table('users')->insert([
            'username'   => 'referenced.user',
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $this->db->insertID();

        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'person_id'  => $keptId,
            'first_name' => 'Referenced',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // True orphan.
        $this->db->table('person')->insert([
            'first_name' => 'Orphan',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $orphanId = (int) $this->db->insertID();

        // Dangling user_person row.
        $this->db->table('user_person')->insert([
            'user_id'    => 9999,
            'person_id'  => $orphanId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $migration = new CleanupOrphanedPersonRows();
        $migration->up();

        $remainingIds = $this->db->table('person')->select('id')->get()->getResultArray();
        $ids = array_map(static fn(array $row): int => (int) $row['id'], $remainingIds);

        $this->assertContains($keptId, $ids);
        $this->assertNotContains($orphanId, $ids);

        $dangling = $this->db->table('user_person')->where('person_id', $orphanId)->countAllResults();
        $this->assertSame(0, $dangling);
    }

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();
        $forge->dropTable('profile', true);
        $forge->dropTable('user_person', true);
        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('person', true);
        $forge->dropTable('users', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active'     => ['type' => 'INTEGER', 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users', true);

        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'first_name'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_name'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email_hash'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone_hash'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'birthdate'           => ['type' => 'DATE', 'null' => true],
            'gender'              => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'address'             => ['type' => 'TEXT', 'null' => true],
            'preferred_contact_method' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'email'],
            'preferred_timezone'  => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'opted_out_channels'  => ['type' => 'TEXT', 'null' => true],
            'source'              => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'system'],
            'is_active'           => ['type' => 'INTEGER', 'default' => 1],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'    => ['type' => 'INTEGER'],
            'person_id'  => ['type' => 'INTEGER'],
            'is_primary' => ['type' => 'INTEGER', 'default' => 0],
            'linked_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('user_person', true);

        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'             => ['type' => 'INTEGER'],
            'person_id'           => ['type' => 'INTEGER', 'null' => true],
            'first_name'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_name'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'    => ['type' => 'INTEGER'],
            'person_id'  => ['type' => 'INTEGER'],
            'is_primary' => ['type' => 'INTEGER', 'default' => 0],
            'linked_at'  => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('user_person', true);

        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id'      => ['type' => 'INTEGER', 'null' => true],
            'parish_id'           => ['type' => 'INTEGER'],
            'person_id'           => ['type' => 'INTEGER', 'null' => true],
            'affiliation_type'    => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'member'],
            'membership_status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'canonical_status'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'is_member'           => ['type' => 'INTEGER', 'default' => 0],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);
    }
}
