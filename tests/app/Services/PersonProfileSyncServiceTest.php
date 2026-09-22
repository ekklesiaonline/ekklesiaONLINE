<?php

use App\Models\PersonModel;
use App\Models\ProfileModel;
use App\Services\PersonProfileSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class PersonProfileSyncServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private PersonProfileSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        $this->service = new PersonProfileSyncService();
    }

    public function testSyncFromProfileCreatesPersonAndLinksIt(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('sync.create');

        $this->db->table('profile')->insert([
            'user_id'     => $userId,
            'parish_id'   => $parishId,
            'first_name'  => 'Jane',
            'last_name'   => 'Doe',
            'phone'       => '+18005551234',
            'address'     => '123 Main St',
            'birth_date'  => '1990-05-15',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $profileId = (int) $this->db->insertID();

        $personId = $this->service->syncFromProfile($profileId);

        $this->assertGreaterThan(0, $personId);

        $profile = $this->db->table('profile')->where('id', $profileId)->get()->getRowArray();
        $this->assertSame($personId, (int) ($profile['person_id'] ?? 0));

        $person = model(PersonModel::class)->find($personId);
        $this->assertSame('Jane', $person['first_name']);
        $this->assertSame('Doe', $person['last_name']);
        $this->assertSame('+18005551234', $person['phone']);
        $this->assertSame('123 Main St', $person['address']);
        $this->assertSame('1990-05-15', $person['birthdate']);

        $link = $this->db->table('user_person')
            ->where('user_id', $userId)
            ->where('person_id', $personId)
            ->countAllResults();
        $this->assertSame(1, $link);
    }

    public function testSyncFromProfileUpdatesExistingPerson(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('sync.update');
        $personId = $this->insertPerson($userId, 'Old', 'Name');

        $this->db->table('profile')->insert([
            'user_id'     => $userId,
            'parish_id'   => $parishId,
            'person_id'   => $personId,
            'first_name'  => 'New',
            'last_name'   => 'Name',
            'phone'       => '+18005559876',
            'birth_date'  => '1985-01-01',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $profileId = (int) $this->db->insertID();

        $this->service->syncFromProfile($profileId);

        $person = model(PersonModel::class)->find($personId);
        $this->assertSame('New', $person['first_name']);
        $this->assertSame('+18005559876', $person['phone']);
        $this->assertSame('1985-01-01', $person['birthdate']);
    }

    public function testSyncFromProfileRepairsDanglingPersonId(): void
    {
        $parishId = $this->insertParish();
        $userId   = $this->insertUser('sync.dangling');

        // person_id points at a person row that does not exist (e.g. a person
        // hard-deleted by an earlier merge before reassignment covered profile).
        $this->db->table('profile')->insert([
            'user_id'     => $userId,
            'parish_id'   => $parishId,
            'person_id'   => 99999,
            'first_name'  => 'Orphan',
            'last_name'   => 'Profile',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $profileId = (int) $this->db->insertID();

        $personId = $this->service->syncFromProfile($profileId);

        $this->assertGreaterThan(0, $personId);
        $this->assertNotSame(99999, $personId);

        $profile = $this->db->table('profile')->where('id', $profileId)->get()->getRowArray();
        $this->assertSame($personId, (int) ($profile['person_id'] ?? 0));

        $person = model(PersonModel::class)->find($personId);
        $this->assertSame('Orphan', $person['first_name']);
        $this->assertSame('Profile', $person['last_name']);
    }

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();
        $forge->dropTable('profile', true);
        $forge->dropTable('user_person', true);
        $forge->dropTable('person', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);
        $forge->dropTable('auth_identities', true);

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
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id'    => ['type' => 'INTEGER', 'null' => true],
            'parish_id'         => ['type' => 'INTEGER', 'null' => true],
            'person_id'         => ['type' => 'INTEGER', 'null' => true],
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
            'id'                  => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'             => ['type' => 'INTEGER'],
            'person_id'           => ['type' => 'INTEGER', 'null' => true],
            'parish_id'           => ['type' => 'INTEGER', 'null' => true],
            'province_id'         => ['type' => 'INTEGER', 'null' => true],
            'diocese_id'          => ['type' => 'INTEGER', 'null' => true],
            'first_name'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'last_name'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'middle_name'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
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
            'portal_access'       => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'none'],
            'profile_picture'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_confirmed'        => ['type' => 'INTEGER', 'default' => 0],
            'is_dependent'        => ['type' => 'INTEGER', 'default' => 0],
            'spiritual_gifts'     => ['type' => 'TEXT', 'null' => true],
            'locale'              => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'en'],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

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
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'    => ['type' => 'INTEGER'],
            'type'       => ['type' => 'VARCHAR', 'constraint' => 32],
            'secret'     => ['type' => 'VARCHAR', 'constraint' => 255],
            'secret2'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities', true);
    }

    private function insertParish(): int
    {
        $this->db->table('parish')->insert([
            'name'       => 'Sync Parish',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $this->db->insertID();
        session()->set('selected_parish_id', $id);
        return $id;
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
