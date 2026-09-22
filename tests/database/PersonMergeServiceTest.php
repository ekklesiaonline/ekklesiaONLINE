<?php

use App\Models\PersonModel;
use App\Services\PersonMergeService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class PersonMergeServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private PersonMergeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        $this->service = new PersonMergeService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testMergeReassignsProfileAndAffiliationAndRemovesSourcePerson(): void
    {
        $parishId = $this->insertParish();
        $userA    = $this->insertUser('merge.source');
        $userB    = $this->insertUser('merge.target');

        $sourcePersonId = $this->insertPerson($userA, 'Dup', 'Person');
        $targetPersonId = $this->insertPerson($userB, 'Target', 'Person');

        $this->db->table('profile')->insert([
            'user_id'    => $userA,
            'parish_id'  => $parishId,
            'person_id'  => $sourcePersonId,
            'first_name' => 'Dup',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $sourceProfileId = (int) $this->db->insertID();

        $this->db->table('profile')->insert([
            'user_id'    => $userB,
            'parish_id'  => $parishId,
            'person_id'  => $targetPersonId,
            'first_name' => 'Target',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userA,
            'parish_id'         => $parishId,
            'person_id'         => $sourcePersonId,
            'affiliation_type'  => 'member',
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userB,
            'parish_id'         => $parishId,
            'person_id'         => $targetPersonId,
            'affiliation_type'  => 'member',
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $result = $this->service->merge($sourcePersonId, $targetPersonId, $parishId, 1, []);

        $this->assertTrue($result['ok'], $result['message'] ?? 'merge failed');

        $source = model(PersonModel::class)->withDeleted()->find($sourcePersonId);
        $this->assertTrue(
            $source === null || (int) ($source['is_active'] ?? 1) === 0,
            'source person should be removed or deactivated after merge'
        );

        $affiliations = $this->db->table('parish_affiliation')
            ->where('person_id', $targetPersonId)
            ->get()
            ->getResultArray();
        $linkedUserIds = array_map(static fn(array $row): int => (int) $row['linked_user_id'], $affiliations);
        $this->assertContains($userA, $linkedUserIds);
        $this->assertContains($userB, $linkedUserIds);

        $profile = $this->db->table('profile')->where('id', $sourceProfileId)->get()->getRowArray();
        $this->assertSame($targetPersonId, (int) ($profile['person_id'] ?? 0));
    }

    public function testMergeDetectsConflictsForCommonUser(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('merge.conflict');

        $sourcePersonId = $this->insertPerson($userId, 'Dup', 'Person');
        $targetPersonId = $this->insertPerson($userId, 'Dup', 'Person');

        $this->db->table('user_person')->insert([
            'user_id'    => $userId,
            'person_id'  => $targetPersonId,
            'is_primary' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $preview = $this->service->getPreview($sourcePersonId, $targetPersonId, $parishId);

        $this->assertFalse($preview['ok']);
        $this->assertNotEmpty($preview['conflicts']);
    }

    public function testFindCandidatesReturnsDuplicatePairsByName(): void
    {
        $parishId = $this->insertParish();
        $userA    = $this->insertUser('cand.a');
        $userB    = $this->insertUser('cand.b');

        $personA = $this->insertPerson($userA, 'John', 'Doe');
        $personB = $this->insertPerson($userB, 'John', 'Doe');

        $this->insertAffiliation($userA, $parishId, $personA);
        $this->insertAffiliation($userB, $parishId, $personB);

        $candidates = $this->service->findCandidates($parishId);

        $this->assertCount(1, $candidates);
    }

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('profile', true);
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('auth_identities', true);
        $forge->dropTable('user_person', true);
        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('person', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish', true);

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
            'user_id'             => ['type' => 'INTEGER', 'null' => true],
            'parish_id'           => ['type' => 'INTEGER', 'null' => true],
            'person_id'           => ['type' => 'INTEGER', 'null' => true],
            'first_name'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_name'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'nickname'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone_country_code'  => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'address'             => ['type' => 'TEXT', 'null' => true],
            'address_line_2'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city'                => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'state'               => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'zip'                 => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'country'             => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'birth_date'          => ['type' => 'DATE', 'null' => true],
            'baptism_date'        => ['type' => 'DATE', 'null' => true],
            'confirmation_date'   => ['type' => 'DATE', 'null' => true],
            'is_confirmed'        => ['type' => 'INTEGER', 'default' => 0],
            'portal_access'       => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'none'],
            'profile_picture'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_dependent'        => ['type' => 'INTEGER', 'default' => 0],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id'    => ['type' => 'INTEGER', 'null' => true],
            'parish_id'         => ['type' => 'INTEGER'],
            'person_id'         => ['type' => 'INTEGER', 'null' => true],
            'affiliation_type'  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'member'],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'system'],
            'start_date'        => ['type' => 'DATE', 'null' => true],
            'end_date'          => ['type' => 'DATE', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'    => ['type' => 'INTEGER'],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 32],
            'secret'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'secret2'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'force_reset' => ['type' => 'INTEGER', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'    => ['type' => 'INTEGER'],
            'group'      => ['type' => 'VARCHAR', 'constraint' => 64],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users', true);

    }

    private function insertParish(): int
    {
        $this->db->table('parish')->insert([
            'name'       => 'Merge Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
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

    private function insertAffiliation(int $userId, int $parishId, int $personId): void
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
            'parish_id'         => $parishId,
            'person_id'         => $personId,
            'affiliation_type'  => 'member',
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }
}
