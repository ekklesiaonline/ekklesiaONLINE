<?php

use App\Models\MemberParishAffiliationModel;
use App\Models\ProfileModel;
use App\Services\ProfileLookupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class ParishAffiliationSecurityIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildSchema();
    }

    public function testSetHomeParishUpdatesPrimaryFlag(): void
    {
        $dioceseId = $this->insertDiocese('Home Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Primary');
        $otherId   = $this->insertParish($dioceseId, 'St. Other');
        $userId    = $this->insertUser('home.user');

        $this->insertProfile($userId, $parishId, 'Homer', 'User');
        $this->insertAffiliation($userId, $parishId, $dioceseId, 'member', true);
        $this->insertAffiliation($userId, $otherId, $dioceseId, 'member', false);

        $model = model(MemberParishAffiliationModel::class);
        $ok = $model->setHomeParish($userId, $otherId);

        if (!$ok) {
            var_dump('setHomeParish returned false');
            var_dump($this->db->table('parish_affiliation')->where('linked_user_id', $userId)->get()->getResultArray());
        }

        $this->assertTrue($ok);

        $primary = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('is_primary', 1)
            ->get()
            ->getRowArray();

        $this->assertNotNull($primary);
        $this->assertSame($otherId, (int) ($primary['parish_id'] ?? 0));

        $rows = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('is_primary', 1)
            ->get()
            ->getResultArray();

        $this->assertCount(1, $rows);
    }

    public function testAttachToParishDoesNotDuplicateRows(): void
    {
        $dioceseId = $this->insertDiocese('Single Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Solo');
        $userId    = $this->insertUser('solo.user');

        $this->insertProfile($userId, $parishId, 'Solo', 'User');

        $model = model(MemberParishAffiliationModel::class);
        $first = $model->attachToParish($userId, $parishId, false, 'member');
        $second = $model->attachToParish($userId, $parishId, false, 'member');

        $this->assertTrue($first);
        $this->assertTrue($second);

        $count = (int) $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->countAllResults();

        $this->assertSame(1, $count);
    }

    public function testProfileLookupOnlyReturnsProfilesInParishScope(): void
    {
        $dioceseId = $this->insertDiocese('Scope Diocese');
        $allowedParishId = $this->insertParish($dioceseId, 'Allowed Parish');
        $otherParishId   = $this->insertParish($dioceseId, 'Other Parish');

        $allowedUserId = $this->insertUser('allowed.user');
        $otherUserId   = $this->insertUser('other.user');

        $allowedProfileId = $this->insertProfile($allowedUserId, $allowedParishId, 'Allowed', 'User');
        $otherProfileId   = $this->insertProfile($otherUserId, $otherParishId, 'Other', 'User');

        $this->insertAffiliation($allowedUserId, $allowedParishId, $dioceseId, 'member', true);
        $this->insertAffiliation($otherUserId, $otherParishId, $dioceseId, 'member', true);

        $service = new ProfileLookupService(new ProfileModel());

        $this->assertNotNull($service->findProfileInParishScope($allowedProfileId, $allowedParishId));
        $this->assertNull($service->findProfileInParishScope($otherProfileId, $allowedParishId));
    }

    private function rebuildSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('auth_identities', true);
        $forge->dropTable('profile', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);
        $forge->dropTable('diocese', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'province_id' => ['type' => 'INTEGER', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('diocese');

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'diocese_id' => ['type' => 'INTEGER'],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish');

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 255],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users');

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER'],
            'parish_id' => ['type' => 'INTEGER', 'null' => true],
            'diocese_id' => ['type' => 'INTEGER', 'null' => true],
            'province_id' => ['type' => 'INTEGER', 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile');

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER', 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'email_password'],
            'secret' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities');

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'group' => ['type' => 'VARCHAR', 'constraint' => 50],
            'user_id' => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users');

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id' => ['type' => 'INTEGER'],
            'person_id' => ['type' => 'INTEGER', 'default' => 0],
            'linked_user_id' => ['type' => 'INTEGER', 'null' => true],
            'affiliation_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'canonical_status' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_member' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_good_standing' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'source' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'system'],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'end_date' => ['type' => 'DATE', 'null' => true],
            'is_primary' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation');
    }

    private function insertDiocese(string $name): int
    {
        $this->db->table('diocese')->insert([
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertParish(int $dioceseId, string $name): int
    {
        $this->db->table('parish')->insert([
            'diocese_id' => $dioceseId,
            'name' => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username' => $username,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertProfile(int $userId, int $parishId, string $firstName, string $lastName): int
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'membership_status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertAffiliation(int $userId, int $parishId, int $dioceseId, string $status, bool $isPrimary = false): void
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'person_id' => 0,
            'affiliation_type' => 'member',
            'membership_status' => $status,
            'is_member' => in_array($status, ['member', 'inactive'], true) ? 1 : 0,
            'is_primary' => $isPrimary ? 1 : 0,
            'start_date' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
