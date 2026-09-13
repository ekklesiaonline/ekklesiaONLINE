<?php

use App\Services\PeopleDirectoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for PeopleDirectoryService's snapshot-backed "extra filters"
 * (is_baptized, is_confirmed, is_communicant, is_dependent, is_under_16,
 * has_pledge, is_leader) used by the /people checkbox filter panel.
 *
 * @internal
 */
final class PeopleDirectoryServiceExtraFiltersIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildSchema();
    }

    public function testExtraFilterExcludesPeopleWithoutMatchingSnapshotFlag(): void
    {
        $parishId = $this->insertParish('St. Filter');

        $underAge = $this->insertUser('under16.user');
        $adult    = $this->insertUser('adult.user');

        $this->insertProfile($underAge, $parishId, 'Young', 'Person');
        $this->insertProfile($adult, $parishId, 'Grown', 'Person');

        $this->insertAffiliation($underAge, $parishId, 'member', 1);
        $this->insertAffiliation($adult, $parishId, 'member', 1);

        $this->insertSnapshot($parishId, $underAge, ['is_under_16' => 1]);
        $this->insertSnapshot($parishId, $adult, ['is_under_16' => 0]);

        $result = (new PeopleDirectoryService())->listForParish($parishId, [
            'scope'         => 'all',
            'extra_filters' => ['is_under_16' => 1],
        ]);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame($underAge, (int) ($result['items'][0]['user_id'] ?? 0));
    }

    public function testExtraFilterIsLeaderOnlyReturnsLeaders(): void
    {
        $parishId = $this->insertParish('St. Leader');

        $leader   = $this->insertUser('leader.user');
        $follower = $this->insertUser('follower.user');

        $this->insertProfile($leader, $parishId, 'Lead', 'Er');
        $this->insertProfile($follower, $parishId, 'Follow', 'Er');

        $this->insertAffiliation($leader, $parishId, 'member', 1);
        $this->insertAffiliation($follower, $parishId, 'member', 1);

        $this->insertSnapshot($parishId, $leader, ['is_leader' => 1]);
        $this->insertSnapshot($parishId, $follower, ['is_leader' => 0]);

        $result = (new PeopleDirectoryService())->listForParish($parishId, [
            'scope'         => 'all',
            'extra_filters' => ['is_leader' => 1],
        ]);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame($leader, (int) ($result['items'][0]['user_id'] ?? 0));
    }

    public function testMultipleExtraFiltersAreCombinedWithAnd(): void
    {
        $parishId = $this->insertParish('St. Combined');

        $both  = $this->insertUser('both.user');
        $onlyOne = $this->insertUser('one.user');

        $this->insertProfile($both, $parishId, 'Both', 'Flags');
        $this->insertProfile($onlyOne, $parishId, 'One', 'Flag');

        $this->insertAffiliation($both, $parishId, 'member', 1);
        $this->insertAffiliation($onlyOne, $parishId, 'member', 1);

        $this->insertSnapshot($parishId, $both, ['is_baptized' => 1, 'has_pledge' => 1]);
        $this->insertSnapshot($parishId, $onlyOne, ['is_baptized' => 1, 'has_pledge' => 0]);

        $result = (new PeopleDirectoryService())->listForParish($parishId, [
            'scope'         => 'all',
            'extra_filters' => ['is_baptized' => 1, 'has_pledge' => 1],
        ]);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame($both, (int) ($result['items'][0]['user_id'] ?? 0));
    }

    public function testNoExtraFiltersReturnsEveryoneRegardlessOfSnapshot(): void
    {
        $parishId = $this->insertParish('St. NoFilter');

        $withSnapshot    = $this->insertUser('with.snapshot');
        $withoutSnapshot = $this->insertUser('without.snapshot');

        $this->insertProfile($withSnapshot, $parishId, 'With', 'Snapshot');
        $this->insertProfile($withoutSnapshot, $parishId, 'Without', 'Snapshot');

        $this->insertAffiliation($withSnapshot, $parishId, 'member', 1);
        $this->insertAffiliation($withoutSnapshot, $parishId, 'member', 1);

        $this->insertSnapshot($parishId, $withSnapshot, ['is_baptized' => 1]);
        // Intentionally no snapshot row for $withoutSnapshot.

        $result = (new PeopleDirectoryService())->listForParish($parishId, [
            'scope' => 'all',
        ]);

        $this->assertSame(2, (int) $result['total']);
    }

    private function rebuildSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('parish_people', true);
        $forge->dropTable('parish_affiliation', true);
        $forge->dropTable('auth_groups_users', true);
        $forge->dropTable('auth_identities', true);
        $forge->dropTable('profile', true);
        $forge->dropTable('users', true);
        $forge->dropTable('person', true);
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
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'email_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'birthdate'  => ['type' => 'DATE', 'null' => true],
            'is_active'  => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'gender'     => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('person', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'active'     => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users', true);

        $forge->addField([
            'id'                 => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'            => ['type' => 'INTEGER', 'null' => true],
            'person_id'          => ['type' => 'INTEGER', 'null' => true],
            'parish_id'          => ['type' => 'INTEGER', 'null' => true],
            'first_name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nickname'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'profile_picture'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone'              => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'membership_status'  => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('profile', true);

        $forge->addField([
            'id'          => ['type' => 'INTEGER', 'auto_increment' => true],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 63],
            'secret'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'force_reset' => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'user_id'     => ['type' => 'INTEGER'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_identities', true);

        $forge->addField([
            'id'         => ['type' => 'INTEGER', 'auto_increment' => true],
            'group'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_id'    => ['type' => 'INTEGER'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('auth_groups_users', true);

        $forge->addField([
            'id'                 => ['type' => 'INTEGER', 'auto_increment' => true],
            'linked_user_id'     => ['type' => 'INTEGER', 'null' => true],
            'parish_id'          => ['type' => 'INTEGER'],
            'person_id'          => ['type' => 'INTEGER', 'null' => true],
            'affiliation_type'   => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'member'],
            'membership_status'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'guest'],
            'canonical_status'   => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'is_member'          => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'is_good_standing'   => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'source'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'system'],
            'start_date'         => ['type' => 'DATE', 'null' => true],
            'end_date'           => ['type' => 'DATE', 'null' => true],
            'is_primary'         => ['type' => 'INTEGER', 'constraint' => 1, 'default' => 0],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
            'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_affiliation', true);

        $forge->addField([
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id'         => ['type' => 'INTEGER'],
            'user_id'           => ['type' => 'INTEGER', 'default' => 0],
            'profile_id'        => ['type' => 'INTEGER', 'null' => true],
            'first_name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'is_member'         => ['type' => 'INTEGER', 'default' => 0],
            'is_baptized'       => ['type' => 'INTEGER', 'default' => 0],
            'is_confirmed'      => ['type' => 'INTEGER', 'default' => 0],
            'is_communicant'    => ['type' => 'INTEGER', 'default' => 0],
            'is_dependent'      => ['type' => 'INTEGER', 'default' => 0],
            'is_under_16'       => ['type' => 'INTEGER', 'default' => 0],
            'has_pledge'        => ['type' => 'INTEGER', 'default' => 0],
            'is_leader'         => ['type' => 'INTEGER', 'default' => 0],
            'source'            => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'user_profile'],
            'synced_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish_people', true);
    }

    private function insertParish(string $name): int
    {
        $this->db->table('parish')->insert([
            'name'       => $name,
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

    private function insertProfile(int $userId, int $parishId, string $firstName, string $lastName): int
    {
        $this->db->table('person')->insert([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('profile')->insert([
            'user_id'           => $userId,
            'person_id'         => $personId,
            'parish_id'         => $parishId,
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'membership_status' => 'member',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    private function insertAffiliation(int $userId, int $parishId, string $status, int $isMember): void
    {
        $personId = (int) $this->db->table('profile')
            ->select('person_id')
            ->where('user_id', $userId)
            ->where('parish_id', $parishId)
            ->get()
            ->getRow('person_id');

        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
            'parish_id'         => $parishId,
            'person_id'         => $personId,
            'affiliation_type'  => 'member',
            'membership_status' => $status,
            'is_member'         => $isMember,
            'source'            => 'test',
            'start_date'        => date('Y-m-d'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertSnapshot(int $parishId, int $userId, array $flags): void
    {
        $this->db->table('parish_people')->insert(array_merge([
            'parish_id'         => $parishId,
            'user_id'           => $userId,
            'membership_status' => 'member',
            'is_member'         => 1,
            'source'            => 'test',
            'synced_at'         => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ], $flags));
    }
}
