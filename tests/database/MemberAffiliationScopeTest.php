<?php

use App\Models\MemberParishAffiliationModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * @internal
 */
final class MemberAffiliationScopeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildSchema();
    }

    public function testGetParishMembersExcludesProvinceAdmins(): void
    {
        $dioceseId = $this->insertDiocese('Central Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Mark');

        $memberUserId = $this->insertUser('member.user');
        $adminUserId  = $this->insertUser('province.admin');

        $this->insertProfile($memberUserId, $parishId, 'Maria', 'Member');
        $this->insertProfile($adminUserId, $parishId, 'Peter', 'Admin');

        $this->db->table('auth_groups_users')->insert([
            'group' => 'provinceadmin',
            'user_id' => $adminUserId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->insertAffiliation($memberUserId, $parishId, $dioceseId, 'member');
        $this->insertAffiliation($adminUserId, $parishId, $dioceseId, 'member');

        $rows = model(MemberParishAffiliationModel::class)->getParishMembers($parishId);

        $this->assertCount(1, $rows);
        $this->assertSame($memberUserId, (int) $rows[0]['user_id']);
    }

    public function testCountDistinctMembersDefaultsToActiveAndInactiveOnly(): void
    {
        $dioceseId = $this->insertDiocese('North Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. Luke');

        $activeUserId   = $this->insertUser('active.user');
        $inactiveUserId = $this->insertUser('inactive.user');
        $visitorUserId  = $this->insertUser('visitor.user');

        $this->insertProfile($activeUserId, $parishId, 'A', 'Active');
        $this->insertProfile($inactiveUserId, $parishId, 'I', 'Inactive');
        $this->insertProfile($visitorUserId, $parishId, 'V', 'Visitor');

        $this->insertAffiliation($activeUserId, $parishId, $dioceseId, 'member');
        $this->insertAffiliation($inactiveUserId, $parishId, $dioceseId, 'inactive');
        $this->insertAffiliation($visitorUserId, $parishId, $dioceseId, 'visitor');

        $count = model(MemberParishAffiliationModel::class)->countDistinctMembers($parishId);

        $this->assertSame(2, $count);
    }

    public function testAttachToParishReactivatesExistingAffiliationAndSetsDiocese(): void
    {
        $dioceseId = $this->insertDiocese('South Diocese');
        $parishId  = $this->insertParish($dioceseId, 'St. John');
        $userId    = $this->insertUser('reactivate.user');

        $this->insertProfile($userId, $parishId, 'Re', 'Activate');

        $this->db->table('parish_affiliation')->insert([
            'linked_user_id' => $userId,
            'parish_id' => $parishId,
            'person_id' => null,
            'membership_status' => 'inactive',
            'source' => 'system',
            'start_date' => '2026-01-01',
            'end_date' => '2026-02-01',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $ok = model(MemberParishAffiliationModel::class)->attachToParish($userId, $parishId, true, 'member');
        $this->assertTrue($ok);

        $row = $this->db->table('parish_affiliation')
            ->where('linked_user_id', $userId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $this->assertSame(1, (int) ($row['is_member'] ?? 0));
        $this->assertSame('member', (string) ($row['membership_status'] ?? ''));
        $this->assertNull($row['end_date']);
    }

    private function rebuildSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('user_person', true);
        $forge->dropTable('person', true);
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('auth_identities', true);
        $forge->dropTable('profile', true);
        $forge->dropTable('users', true);
        $forge->dropTable('parish', true);
        $forge->dropTable('dioceses', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('dioceses', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id' => ['type' => 'INTEGER'],
            'person_id' => ['type' => 'INTEGER'],
            'is_primary' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'linked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('user_person', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'diocese_id' => ['type' => 'INTEGER', 'null' => true],
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
            'user_id' => ['type' => 'INTEGER'],
            'parish_id' => ['type' => 'INTEGER', 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nickname' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'phone' => ['type' => 'TEXT', 'null' => true],
            'phone_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'address' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 63],
            'secret' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'force_reset' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'user_id' => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'group' => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id' => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users', true);

        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id' => ['type' => 'INTEGER', 'null' => true],
            'parish_id' => ['type' => 'INTEGER'],
            'person_id' => ['type' => 'INTEGER', 'null' => true],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'visitor'],
            'source' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'system'],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'is_primary' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'end_date' => ['type' => 'DATE', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);
    }

    private function insertDiocese(string $name): int
    {
        $this->db->table('dioceses')->insert([
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
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $userId = (int) $this->db->insertID();

        $this->db->table('auth_identities')->insert([
            'type' => 'email_password',
            'secret' => $username . '@example.com',
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function insertProfile(int $userId, int $parishId, string $firstName, string $lastName): void
    {
        $this->db->table('profile')->insert([
            'user_id' => $userId,
            'parish_id' => $parishId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertAffiliation(int $userId, int $parishId, int $dioceseId, string $status): void
    {
        model(MemberParishAffiliationModel::class)->attachToParish(
            $userId,
            $parishId,
            false,
            $status
        );
    }
}
