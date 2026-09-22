<?php

use App\Models\PersonModel;
use App\Services\PersonCleanupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class PersonCleanupServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private PersonCleanupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        $this->service = new PersonCleanupService();
    }

    public function testCleanupForUserDeletesOrphanPerson(): void
    {
        $userId   = $this->insertUser('cleanup.orphan');
        $personId = $this->insertPerson($userId, 'Orphan', 'Person');

        // Add a profile referencing the person so it is not orphaned pre-delete.
        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'first_name' => 'Orphan',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->service->cleanupForUser($userId);

        // Person still referenced by profile, so it must survive.
        $person = model(PersonModel::class)->withDeleted()->find($personId);
        $this->assertNotNull($person);

        // Soft-delete the profile; the person should become orphaned and removed.
        $this->db->table('profile')->where('user_id', $userId)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->service->cleanupForUser($userId);

        $person = model(PersonModel::class)->withDeleted()->find($personId);
        $this->assertNull($person);

        $link = $this->db->table('user_person')->where('user_id', $userId)->countAllResults();
        $this->assertSame(0, $link);
    }

    public function testCleanupForUserKeepsPersonReferencedByAnotherUser(): void
    {
        $userA    = $this->insertUser('cleanup.userA');
        $userB    = $this->insertUser('cleanup.userB');
        $personId = $this->insertPerson($userA, 'Shared', 'Person');

        // userB also links to the same person.
        $this->db->table('user_person')->insert([
            'user_id'    => $userB,
            'person_id'  => $personId,
            'is_primary' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->service->cleanupForUser($userA);

        $person = model(PersonModel::class)->find($personId);
        $this->assertNotNull($person);
    }

    public function testDeactivateIfNoActiveProfilesMarksPersonInactive(): void
    {
        $userId   = $this->insertUser('deactivate.person');
        $personId = $this->insertPerson($userId, 'No', 'Active');

        $this->db->table('profile')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'first_name' => 'No',
            'last_name'  => 'Active',
            'deleted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->service->deactivateIfNoActiveProfiles($personId);

        $person = model(PersonModel::class)->find($personId);
        $this->assertSame(0, (int) ($person['is_active'] ?? 1));
    }

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();
        $forge->dropTable('profile', true);
        $forge->dropTable('user_person', true);
        $forge->dropTable('person', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish_affiliation', true);
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
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id'      => ['type' => 'INTEGER', 'null' => true],
            'parish_id'           => ['type' => 'INTEGER'],
            'person_id'           => ['type' => 'INTEGER', 'null' => true],
            'affiliation_type'    => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'member'],
            'membership_status'   => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username'   => $username,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    private function insertPerson(int $userId, string $firstName, string $lastName): int
    {
        $this->db->table('person')->insert([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();
        $this->db->table('user_person')->insert([
            'user_id'    => $userId,
            'person_id'  => $personId,
            'is_primary' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $personId;
    }
}
