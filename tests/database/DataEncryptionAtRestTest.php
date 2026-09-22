<?php

use App\Libraries\DataEncryption;
use App\Models\PersonModel;
use App\Models\MemberParishAffiliationModel;
use App\Models\ProfileModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class DataEncryptionAtRestTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureEncryptionKeys();
        $this->rebuildTestSchema();
    }

    public function testProtectedFieldsAreStoredAsCiphertextAndReadAsPlaintext(): void
    {
        $parishId = $this->insertParish('Encryption Parish');
        [$userId] = $this->insertUserWithIdentityAndGroup('encrypted.profile', 'encrypted.profile@example.org', 'member');

        $profileModel = model(ProfileModel::class);
        $profileId = (int) $profileModel->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'first_name' => 'Ana',
            'last_name' => 'Encrypted',
            'phone' => '(809) 555-7777',
            'address' => '123 Prayer Street',
        ], true);

        $this->assertGreaterThan(0, $profileId);

        $storedProfile = $this->db->table('profile')->where('id', $profileId)->get()->getRowArray();
        $this->assertNotNull($storedProfile);
        $this->assertStringStartsWith('enc:v1:', (string) ($storedProfile['phone'] ?? ''));
        $this->assertStringStartsWith('enc:v1:', (string) ($storedProfile['address'] ?? ''));
        $this->assertNotSame('(809) 555-7777', $storedProfile['phone']);
        $this->assertSame(DataEncryption::phoneIndex('(809) 555-7777'), $storedProfile['phone_hash']);

        $readProfile = $profileModel->find($profileId);
        $this->assertIsArray($readProfile);
        $this->assertSame('(809) 555-7777', $readProfile['phone']);
        $this->assertSame('123 Prayer Street', $readProfile['address']);

        $personModel = model(PersonModel::class);
        $personId = (int) $personModel->insert([
            'first_name' => 'Guest',
            'last_name' => 'Cipher',
            'email' => 'guest.cipher@example.org',
            'phone' => '+1 (809) 555-1212',
        ], true);

        $this->assertGreaterThan(0, $personId);

        $storedPerson = $this->db->table('person')->where('id', $personId)->get()->getRowArray();
        $this->assertNotNull($storedPerson);
        $this->assertStringStartsWith('enc:v1:', (string) ($storedPerson['email'] ?? ''));
        $this->assertStringStartsWith('enc:v1:', (string) ($storedPerson['phone'] ?? ''));
        $this->assertSame(DataEncryption::emailIndex('guest.cipher@example.org'), $storedPerson['email_hash']);
        $this->assertSame(DataEncryption::phoneIndex('+1 (809) 555-1212'), $storedPerson['phone_hash']);

        $readPerson = $personModel->find($personId);
        $this->assertIsArray($readPerson);
        $this->assertSame('guest.cipher@example.org', $readPerson['email']);
        $this->assertSame('+1 (809) 555-1212', $readPerson['phone']);
    }

    public function testExactPhoneAndEmailSearchStillResolveThroughBlindIndexes(): void
    {
        $parishId = $this->insertParish('Search Parish');

        [$memberUserId] = $this->insertUserWithIdentityAndGroup('search.member', 'search.member@example.org', 'member');
        $this->insertAffiliation($memberUserId, $parishId, 'member');

        $profileModel = model(ProfileModel::class);
        $profileModel->insert([
            'user_id' => $memberUserId,
            'parish_id' => $parishId,
            'first_name' => 'Maria',
            'last_name' => 'Lookup',
            'phone' => '(809) 555-9001',
        ]);

        $searchModel = model(MemberParishAffiliationModel::class);
        $memberResults = $searchModel->searchParishMembers($parishId, '809-555-9001');

        $this->assertNotEmpty($memberResults);
        $this->assertContains($memberUserId, array_column($memberResults, 'user_id'));

        $personModel = model(PersonModel::class);
        $personModel->insert([
            'first_name' => 'Email',
            'last_name' => 'Lookup',
            'email' => 'Exact.Lookup@Example.org',
            'phone' => '809-555-3333',
        ]);

        $search = 'exact.lookup@example.org';
        $emailHash = DataEncryption::emailIndex($search);

        $personRows = $this->db->table('person p')
            ->groupStart()
            ->like('p.first_name', $search)
            ->orLike('p.last_name', $search)
            ->orWhere('p.email_hash', $emailHash)
            ->groupEnd()
            ->get()
            ->getResultArray();

        $this->assertNotEmpty($personRows);

        $found = $personModel->findByEmailHash($emailHash);
        $this->assertIsArray($found);
        $this->assertSame('Exact.Lookup@Example.org', $found['email']);
    }

    private function configureEncryptionKeys(): void
    {
        $key = 'hex2bin:0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';
        $blind = 'test-blind-index-key-for-integration-tests-123';

        putenv('encryption.key=' . $key);
        putenv('encryption.blindIndexKey=' . $blind);
        $_ENV['encryption.key'] = $key;
        $_ENV['encryption.blindIndexKey'] = $blind;
        $_SERVER['encryption.key'] = $key;
        $_SERVER['encryption.blindIndexKey'] = $blind;

        Services::reset(true);
    }

    private function insertParish(string $name): int
    {
        $this->db->table('parish')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $id = (int) $this->db->insertID();

        // Satisfies BaseTenantModel::currentParishId() scoping in this test DB.
        session()->set('selected_parish_id', $id);

        return $id;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function insertUserWithIdentityAndGroup(string $username, string $email, string $group): array
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $this->db->insertID();

        $this->db->table('auth_identities')->insert([
            'user_id' => $userId,
            'type' => 'email_password',
            'secret' => $email,
            'secret2' => null,
            'expires' => null,
            'extra' => null,
            'force_reset' => 0,
            'last_used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('auth_groups_users')->insert([
            'user_id' => $userId,
            'group' => $group,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [$userId, $email];
    }

    private function insertAffiliation(int $userId, int $parishId, string $status): void
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'person_id' => null,
            'membership_status' => $status,
            'source' => 'system',
            'start_date' => date('Y-m-d'),
            'end_date' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function rebuildTestSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('profile', true);
        $forge->dropTable('person', true);
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('auth_identities', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER', 'null' => false],
            'type' => ['type' => 'VARCHAR', 'constraint' => 255],
            'secret' => ['type' => 'TEXT', 'null' => true],
            'secret2' => ['type' => 'TEXT', 'null' => true],
            'expires' => ['type' => 'DATETIME', 'null' => true],
            'extra' => ['type' => 'TEXT', 'null' => true],
            'force_reset' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'group' => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id' => ['type' => 'INTEGER', 'null' => false],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER', 'null' => false],
            // Matches production: profile.parish_id is nullable (elevated admins have
            // no single parish). A NOT NULL definition here previously leaked into
            // every other test file sharing this in-memory SQLite connection within
            // the same PHPUnit run, since this table is created with "IF NOT EXISTS"
            // and never dropped between test classes.
            'parish_id' => ['type' => 'INTEGER', 'null' => true],
            'parish_id' => ['type' => 'INTEGER', 'null' => true],
            'province_id' => ['type' => 'INTEGER', 'null' => true],
            'diocese_id' => ['type' => 'INTEGER', 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nickname' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'middle_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'phone' => ['type' => 'TEXT', 'null' => true],
            'phone_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'address' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'email' => ['type' => 'TEXT', 'null' => true],
            'email_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'phone' => ['type' => 'TEXT', 'null' => true],
            'phone_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'is_active' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id' => ['type' => 'INTEGER', 'null' => true],
            'parish_id' => ['type' => 'INTEGER', 'null' => false],
            'person_id' => ['type' => 'INTEGER', 'null' => true],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'source' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'system'],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'end_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);
    }
}
