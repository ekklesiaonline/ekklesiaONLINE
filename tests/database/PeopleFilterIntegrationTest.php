<?php

use App\Services\PeopleDirectoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for PeopleDirectoryService::listForParish() — the
 * canonical replacement for the retired ParishPeopleModel snapshot queries.
 *
 * Covers scope filter (all / members / non_members), status filter,
 * combined scope+status, cross-parish isolation, pagination offset,
 * per-page edge values, and status tallies via statusCountsForParish().
 *
 * @internal
 */
final class PeopleFilterIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private PeopleDirectoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
        $this->service = new PeopleDirectoryService($this->db);
    }

    // ------------------------------------------------------------------ scope

    public function testScopeAllReturnsEveryPersonForParish(): void
    {
        $parishId = $this->insertParish('Scope All Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'visitor');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');

        $result = $this->service->listForParish($parishId, ['scope' => 'all']);

        $this->assertSame(3, (int) $result['total']);
        $this->assertCount(3, $result['items']);
    }

    public function testScopeMembersReturnsOnlyTaggedMembers(): void
    {
        $parishId = $this->insertParish('Members Only Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'member');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');
        $this->insertParishPerson($parishId, 'Dave', 'visitor');

        $result = $this->service->listForParish($parishId, ['scope' => 'members']);

        $this->assertSame(2, (int) $result['total']);
        $names = array_column($result['items'], 'first_name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Carol', $names);
        $this->assertNotContains('Dave', $names);
    }

    public function testScopeNonMembersReturnsOnlyUntaggedPeople(): void
    {
        $parishId = $this->insertParish('Non Members Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'visitor');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');

        $result = $this->service->listForParish($parishId, ['scope' => 'non_members']);

        $this->assertSame(2, (int) $result['total']);
        $names = array_column($result['items'], 'first_name');
        $this->assertContains('Bob', $names);
        $this->assertContains('Carol', $names);
        $this->assertNotContains('Alice', $names);
    }

    public function testInvalidOrNullScopeDefaultsToAll(): void
    {
        $parishId = $this->insertParish('Default Scope Parish');
        $this->insertParishPerson($parishId, 'A', 'member');
        $this->insertParishPerson($parishId, 'B', 'visitor');

        // No scope key at all
        $resultNull = $this->service->listForParish($parishId, []);
        $this->assertSame(2, (int) $resultNull['total']);

        // Empty string scope
        $resultEmpty = $this->service->listForParish($parishId, ['scope' => '']);
        $this->assertSame(2, (int) $resultEmpty['total']);
    }

    // ---------------------------------------------------------------- status

    public function testStatusFilterReturnsSingleMatchingStatus(): void
    {
        $parishId = $this->insertParish('Status Filter Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'inactive');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');

        $result = $this->service->listForParish($parishId, ['scope' => 'all', 'status' => 'member']);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Alice', (string) ($result['items'][0]['first_name'] ?? ''));
    }

    public function testStatusFilterCombinedWithScopeMembers(): void
    {
        $parishId = $this->insertParish('Combined Filter Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'inactive');
        $this->insertParishPerson($parishId, 'Carol', 'regular_attender');

        // members + status=member should return only active members —
        // Bob is on the register but inactive; Carol is off-register
        $result = $this->service->listForParish($parishId, ['scope' => 'members', 'status' => 'member']);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Alice', (string) ($result['items'][0]['first_name'] ?? ''));
    }

    public function testNullStatusReturnsAllStatuses(): void
    {
        $parishId = $this->insertParish('Null Status Parish');
        $this->insertParishPerson($parishId, 'A', 'member');
        $this->insertParishPerson($parishId, 'B', 'visitor');
        $this->insertParishPerson($parishId, 'C', 'inactive');

        $result = $this->service->listForParish($parishId, ['scope' => 'all']);

        $this->assertSame(3, (int) $result['total']);
    }

    // ---------------------------------------------------- cross-parish isolation

    public function testFiltersDoNotLeakAcrossParishes(): void
    {
        $parishA = $this->insertParish('Parish A');
        $parishB = $this->insertParish('Parish B');

        $this->insertParishPerson($parishA, 'Alice', 'member');
        $this->insertParishPerson($parishB, 'Bob', 'member');
        $this->insertParishPerson($parishB, 'Carol', 'member');

        $resultA = $this->service->listForParish($parishA, ['scope' => 'members']);
        $this->assertSame(1, (int) $resultA['total']);
        $this->assertSame('Alice', (string) ($resultA['items'][0]['first_name'] ?? ''));

        $resultB = $this->service->listForParish($parishB, ['scope' => 'members']);
        $this->assertSame(2, (int) $resultB['total']);
    }

    // ---------------------------------------------------------- status counts

    public function testStatusCountsAllIncludesEveryPerson(): void
    {
        $parishId = $this->insertParish('Status Counts All Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'inactive');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');

        $counts = $this->service->statusCountsForParish($parishId, 'all');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(3, $total);
    }

    public function testStatusCountsMembersOnlyExcludesNonMembers(): void
    {
        $parishId = $this->insertParish('Status Counts Members Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'inactive');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');

        $counts = $this->service->statusCountsForParish($parishId, 'members');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total, 'Only the two tagged members should be counted');

        $statuses = array_column($counts, 'membership_status');
        $this->assertNotContains('visitor', $statuses);
    }

    public function testStatusCountsNonMembersOnlyExcludesMembers(): void
    {
        $parishId = $this->insertParish('Status Counts Non Members Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'visitor');
        $this->insertParishPerson($parishId, 'Carol', 'visitor');

        $counts = $this->service->statusCountsForParish($parishId, 'non_members');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total, 'Only the two untagged people should be counted');
    }

    public function testStatusCountsDefaultsToAllWhenScopeOmitted(): void
    {
        $parishId = $this->insertParish('Status Counts Default Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member');
        $this->insertParishPerson($parishId, 'Bob', 'visitor');

        $counts = $this->service->statusCountsForParish($parishId);

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total);
    }

    // ---------------------------------------------------------------- pagination

    public function testPaginationOffsetReturnsCorrectPage(): void
    {
        $parishId = $this->insertParish('Pagination Parish');
        for ($i = 1; $i <= 5; $i++) {
            $this->insertParishPerson($parishId, 'Person' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'member');
        }

        // limit=2, offset=2 → second page of 2
        $result = $this->service->listForParish($parishId, ['scope' => 'all', 'limit' => 2, 'offset' => 2]);

        $this->assertSame(5, (int) $result['total'], 'total must reflect all rows, not just this page');
        $this->assertCount(2, $result['items']);
    }

    public function testPageBeyondLastReturnsEmptyRows(): void
    {
        $parishId = $this->insertParish('Overflow Pagination Parish');
        $this->insertParishPerson($parishId, 'Solo', 'member');

        $result = $this->service->listForParish($parishId, ['scope' => 'all', 'limit' => 25, 'offset' => 2475]);

        $this->assertSame(1, (int) $result['total']);
        $this->assertCount(0, $result['items']);
    }

    public function testEmptyParishReturnsZeroTotal(): void
    {
        $parishId = $this->insertParish('Empty Parish');

        $result = $this->service->listForParish($parishId, ['scope' => 'all']);

        $this->assertSame(0, (int) $result['total']);
        $this->assertCount(0, $result['items']);
    }

    // ---------------------------------------------------------------- sort order

    public function testResultsOrderedByLastNameThenFirstName(): void
    {
        $parishId = $this->insertParish('Sort Parish');
        $this->insertParishPersonFull($parishId, 'Zoe', 'Archer', 'member');
        $this->insertParishPersonFull($parishId, 'Anna', 'Brown', 'member');
        $this->insertParishPersonFull($parishId, 'Mike', 'Archer', 'member');

        $result = $this->service->listForParish($parishId, ['scope' => 'all']);

        $this->assertCount(3, $result['items']);
        $this->assertSame('Mike', (string) ($result['items'][0]['first_name'] ?? ''));  // Archer, Mike
        $this->assertSame('Zoe',  (string) ($result['items'][1]['first_name'] ?? ''));  // Archer, Zoe
        $this->assertSame('Anna', (string) ($result['items'][2]['first_name'] ?? ''));  // Brown, Anna
    }

    // ----------------------------------------------------------------- schema

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

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
            'id'                => ['type' => 'INTEGER', 'auto_increment' => true],
            'user_id'           => ['type' => 'INTEGER', 'null' => true],
            'person_id'         => ['type' => 'INTEGER', 'null' => true],
            'parish_id'         => ['type' => 'INTEGER', 'null' => true],
            'first_name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'nickname'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'profile_picture'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone'             => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'portal_access'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'none'],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
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
            'membership_status'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'visitor'],
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

    private function insertParishPerson(int $parishId, string $firstName, string $status): void
    {
        $this->insertParishPersonFull($parishId, $firstName, 'Lastname', $status);
    }

    private function insertParishPersonFull(int $parishId, string $firstName, string $lastName, string $status): void
    {
        $this->db->table('person')->insert([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => $parishId,
            'person_id'         => $personId,
            'affiliation_type'  => 'member',
            'membership_status' => $status,
            'source'            => 'test',
            'start_date'        => date('Y-m-d'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }
}
