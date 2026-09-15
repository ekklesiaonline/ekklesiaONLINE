<?php

use App\Models\ParishPeopleModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for ParishPeopleModel::getParishPeoplePaginated().
 *
 * Covers scope filter (all / members / non_members), status filter,
 * combined scope+status, cross-parish isolation, pagination offset,
 * and per-page edge values up to the model layer.
 *
 * @internal
 */
final class PeopleFilterIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildSchema();
    }

    // ------------------------------------------------------------------ scope

    public function testScopeAllReturnsEveryPersonForParish(): void
    {
        $parishId = $this->insertParish('Scope All Parish');
        $this->insertParishPerson($parishId, 'Alice', 'member', 1);
        $this->insertParishPerson($parishId, 'Bob', 'visitor', 0);
        $this->insertParishPerson($parishId, 'Carol', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', null, 25, 1);

        $this->assertSame(3, (int) $result['total']);
        $this->assertCount(3, $result['rows']);
    }

    public function testScopeMembersReturnsOnlyTaggedMembers(): void
    {
        $parishId = $this->insertParish('Members Only Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'active', 1);
        $this->insertParishPerson($parishId, 'Carol', 'visitor', 0);
        $this->insertParishPerson($parishId, 'Dave', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'members', null, 25, 1);

        $this->assertSame(2, (int) $result['total']);
        $names = array_column($result['rows'], 'first_name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Carol', $names);
        $this->assertNotContains('Dave', $names);
    }

    public function testScopeNonMembersReturnsOnlyUntaggedPeople(): void
    {
        $parishId = $this->insertParish('Non Members Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'visitor', 0);
        $this->insertParishPerson($parishId, 'Carol', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'non_members', null, 25, 1);

        $this->assertSame(2, (int) $result['total']);
        $names = array_column($result['rows'], 'first_name');
        $this->assertContains('Bob', $names);
        $this->assertContains('Carol', $names);
        $this->assertNotContains('Alice', $names);
    }

    public function testInvalidOrNullScopeDefaultsToAll(): void
    {
        $parishId = $this->insertParish('Default Scope Parish');
        $this->insertParishPerson($parishId, 'A', 'active', 1);
        $this->insertParishPerson($parishId, 'B', 'visitor', 0);

        // Null scope (no scope param passed)
        $resultNull = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, null, null, 25, 1);
        $this->assertSame(2, (int) $resultNull['total']);

        // Empty string — model treats neither 'members' nor 'non_members', so returns all
        $resultEmpty = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, '', null, 25, 1);
        $this->assertSame(2, (int) $resultEmpty['total']);
    }

    // ---------------------------------------------------------------- status

    public function testStatusFilterReturnsSingleMatchingStatus(): void
    {
        $parishId = $this->insertParish('Status Filter Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'inactive', 1);
        $this->insertParishPerson($parishId, 'Carol', 'visitor', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', 'active', 25, 1);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Alice', (string) ($result['rows'][0]['first_name'] ?? ''));
    }

    public function testStatusFilterCombinedWithScopeMembers(): void
    {
        $parishId = $this->insertParish('Combined Filter Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'inactive', 1);
        $this->insertParishPerson($parishId, 'Carol', 'active', 0);

        // members + status=active should return only tagged active people
        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'members', 'active', 25, 1);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Alice', (string) ($result['rows'][0]['first_name'] ?? ''));
    }

    public function testNullStatusReturnsAllStatuses(): void
    {
        $parishId = $this->insertParish('Null Status Parish');
        $this->insertParishPerson($parishId, 'A', 'active', 1);
        $this->insertParishPerson($parishId, 'B', 'visitor', 0);
        $this->insertParishPerson($parishId, 'C', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', null, 25, 1);

        $this->assertSame(3, (int) $result['total']);
    }

    // ---------------------------------------------------- cross-parish isolation

    public function testFiltersDoNotLeakAcrossParishes(): void
    {
        $parishA = $this->insertParish('Parish A');
        $parishB = $this->insertParish('Parish B');

        $this->insertParishPerson($parishA, 'Alice', 'active', 1);
        $this->insertParishPerson($parishB, 'Bob', 'active', 1);
        $this->insertParishPerson($parishB, 'Carol', 'active', 1);

        session()->set('selected_parish_id', $parishA);
        $resultA = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishA, 'members', null, 25, 1);
        $this->assertSame(1, (int) $resultA['total']);
        $this->assertSame('Alice', (string) ($resultA['rows'][0]['first_name'] ?? ''));

        session()->set('selected_parish_id', $parishB);
        $resultB = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishB, 'members', null, 25, 1);
        $this->assertSame(2, (int) $resultB['total']);
    }

    // ---------------------------------------------------------- status counts

    public function testStatusCountsAllIncludesEveryPerson(): void
    {
        $parishId = $this->insertParish('Status Counts All Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'inactive', 1);
        $this->insertParishPerson($parishId, 'Carol', 'visitor', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($parishId, 'all');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(3, $total);
    }

    public function testStatusCountsMembersOnlyExcludesNonMembers(): void
    {
        $parishId = $this->insertParish('Status Counts Members Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'inactive', 1);
        $this->insertParishPerson($parishId, 'Carol', 'visitor', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($parishId, 'members');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total, 'Only the two tagged members should be counted');

        $statuses = array_column($counts, 'membership_status');
        $this->assertNotContains('visitor', $statuses);
    }

    public function testStatusCountsNonMembersOnlyExcludesMembers(): void
    {
        $parishId = $this->insertParish('Status Counts Non Members Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'visitor', 0);
        $this->insertParishPerson($parishId, 'Carol', 'pending', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($parishId, 'non_members');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total, 'Only the two untagged people should be counted');
    }

    public function testStatusCountsDefaultsToAllWhenScopeOmitted(): void
    {
        $parishId = $this->insertParish('Status Counts Default Parish');
        $this->insertParishPerson($parishId, 'Alice', 'active', 1);
        $this->insertParishPerson($parishId, 'Bob', 'visitor', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($parishId);

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total);
    }

    // ---------------------------------------------------------------- pagination

    public function testPaginationOffsetReturnsCorrectPage(): void
    {
        $parishId = $this->insertParish('Pagination Parish');
        for ($i = 1; $i <= 5; $i++) {
            $this->insertParishPerson($parishId, 'Person' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'active', 1);
        }

        // perPage=2, page=2 → skip 2, take 2 → Person03, Person04
        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', null, 2, 2);

        $this->assertSame(5, (int) $result['total'], 'total must reflect all rows, not just this page');
        $this->assertCount(2, $result['rows']);
    }

    public function testPageBeyondLastReturnsEmptyRows(): void
    {
        $parishId = $this->insertParish('Overflow Pagination Parish');
        $this->insertParishPerson($parishId, 'Solo', 'active', 1);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', null, 25, 99);

        $this->assertSame(1, (int) $result['total']);
        $this->assertCount(0, $result['rows']);
    }

    public function testEmptyParishReturnsZeroTotal(): void
    {
        $parishId = $this->insertParish('Empty Parish');

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', null, 25, 1);

        $this->assertSame(0, (int) $result['total']);
        $this->assertCount(0, $result['rows']);
    }

    // ---------------------------------------------------------------- sort order

    public function testResultsOrderedByLastNameThenFirstName(): void
    {
        $parishId = $this->insertParish('Sort Parish');
        $this->insertParishPersonFull($parishId, 'Zoe', 'Archer', 'active', 1);
        $this->insertParishPersonFull($parishId, 'Anna', 'Brown', 'active', 1);
        $this->insertParishPersonFull($parishId, 'Mike', 'Archer', 'active', 1);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($parishId, 'all', null, 25, 1);

        $this->assertCount(3, $result['rows']);
        $this->assertSame('Mike', (string) ($result['rows'][0]['first_name'] ?? ''));  // Archer, Mike
        $this->assertSame('Zoe',  (string) ($result['rows'][1]['first_name'] ?? ''));  // Archer, Zoe
        $this->assertSame('Anna', (string) ($result['rows'][2]['first_name'] ?? ''));  // Brown, Anna
    }

    // ----------------------------------------------------------------- schema

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

        // NOTE: `parish_people` / `parish_id` are the legacy pre-rename names.
        // Production has used `parish_people` / `parish_id` since the
        // StrictRenameLegacyParishSchemaObjects migration; ParishPeopleModel's
        // detectSnapshotStorage() prefers `parish_people` whenever it exists
        // (which it always does in real deployments). Building the legacy
        // table here made the model silently query a *different*, stale
        // `parish_people` table left over from other tests' migrations in the
        // same in-memory SQLite connection, rather than the fixtures inserted
        // below. Build the current table name so the model actually reads
        // what this test writes.
        $forge->dropTable('parish_people', true);
        $forge->dropTable('parish', true);

        $forge->addField([
            'id'   => ['type' => 'INTEGER', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('parish', true);

        $forge->addField([
            'id'               => ['type' => 'INTEGER', 'auto_increment' => true],
            'parish_id'        => ['type' => 'INTEGER'],
            'user_id'          => ['type' => 'INTEGER', 'default' => 0],
            'profile_id'       => ['type' => 'INTEGER', 'null' => true],
            'first_name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'last_name'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'email'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone'            => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'portal_access'    => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'none'],
            'membership_status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'is_member'        => ['type' => 'INTEGER', 'default' => 0],
            'is_good_standing' => ['type' => 'INTEGER', 'default' => 0],
            'source'           => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'user_profile'],
            'synced_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'       => ['type' => 'DATETIME', 'null' => true],
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

        $id = (int) $this->db->insertID();

        // Avoids auth()->loggedIn() in BaseTenantModel::currentParishId() which
        // would require a full Shield settings/auth schema in this test.
        session()->set('selected_parish_id', $id);

        return $id;
    }

    private function insertParishPerson(int $parishId, string $firstName, string $status, int $isMember): void
    {
        $this->insertParishPersonFull($parishId, $firstName, 'Lastname', $status, $isMember);
    }

    private function insertParishPersonFull(int $parishId, string $firstName, string $lastName, string $status, int $isMember): void
    {
        $this->db->table('parish_people')->insert([
            'parish_id'         => $parishId,
            'user_id'           => 0,
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'membership_status' => $status,
            'is_member'         => $isMember,
            'source'            => 'test',
            'synced_at'         => date('Y-m-d H:i:s'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }
}
