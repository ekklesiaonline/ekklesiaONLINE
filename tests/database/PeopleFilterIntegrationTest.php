<?php

use App\Models\ParishPeopleModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Integration tests for ParishPeopleModel::getParishPeoplePaginated().
 *
 * Covers scope filter (all / members / non_members), status filter,
 * combined scope+status, cross-church isolation, pagination offset,
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

    public function testScopeAllReturnsEveryPersonForChurch(): void
    {
        $churchId = $this->insertChurch('Scope All Church');
        $this->insertChurchPerson($churchId, 'Alice', 'member', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'visitor', 0);
        $this->insertChurchPerson($churchId, 'Carol', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', null, 25, 1);

        $this->assertSame(3, (int) $result['total']);
        $this->assertCount(3, $result['rows']);
    }

    public function testScopeMembersReturnsOnlyTaggedMembers(): void
    {
        $churchId = $this->insertChurch('Members Only Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'active', 1);
        $this->insertChurchPerson($churchId, 'Carol', 'visitor', 0);
        $this->insertChurchPerson($churchId, 'Dave', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'members', null, 25, 1);

        $this->assertSame(2, (int) $result['total']);
        $names = array_column($result['rows'], 'first_name');
        $this->assertContains('Alice', $names);
        $this->assertContains('Bob', $names);
        $this->assertNotContains('Carol', $names);
        $this->assertNotContains('Dave', $names);
    }

    public function testScopeNonMembersReturnsOnlyUntaggedPeople(): void
    {
        $churchId = $this->insertChurch('Non Members Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'visitor', 0);
        $this->insertChurchPerson($churchId, 'Carol', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'non_members', null, 25, 1);

        $this->assertSame(2, (int) $result['total']);
        $names = array_column($result['rows'], 'first_name');
        $this->assertContains('Bob', $names);
        $this->assertContains('Carol', $names);
        $this->assertNotContains('Alice', $names);
    }

    public function testInvalidOrNullScopeDefaultsToAll(): void
    {
        $churchId = $this->insertChurch('Default Scope Church');
        $this->insertChurchPerson($churchId, 'A', 'active', 1);
        $this->insertChurchPerson($churchId, 'B', 'visitor', 0);

        // Null scope (no scope param passed)
        $resultNull = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, null, null, 25, 1);
        $this->assertSame(2, (int) $resultNull['total']);

        // Empty string — model treats neither 'members' nor 'non_members', so returns all
        $resultEmpty = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, '', null, 25, 1);
        $this->assertSame(2, (int) $resultEmpty['total']);
    }

    // ---------------------------------------------------------------- status

    public function testStatusFilterReturnsSingleMatchingStatus(): void
    {
        $churchId = $this->insertChurch('Status Filter Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'inactive', 1);
        $this->insertChurchPerson($churchId, 'Carol', 'visitor', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', 'active', 25, 1);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Alice', (string) ($result['rows'][0]['first_name'] ?? ''));
    }

    public function testStatusFilterCombinedWithScopeMembers(): void
    {
        $churchId = $this->insertChurch('Combined Filter Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'inactive', 1);
        $this->insertChurchPerson($churchId, 'Carol', 'active', 0);

        // members + status=active should return only tagged active people
        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'members', 'active', 25, 1);

        $this->assertSame(1, (int) $result['total']);
        $this->assertSame('Alice', (string) ($result['rows'][0]['first_name'] ?? ''));
    }

    public function testNullStatusReturnsAllStatuses(): void
    {
        $churchId = $this->insertChurch('Null Status Church');
        $this->insertChurchPerson($churchId, 'A', 'active', 1);
        $this->insertChurchPerson($churchId, 'B', 'visitor', 0);
        $this->insertChurchPerson($churchId, 'C', 'pending', 0);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', null, 25, 1);

        $this->assertSame(3, (int) $result['total']);
    }

    // ---------------------------------------------------- cross-church isolation

    public function testFiltersDoNotLeakAcrossChurches(): void
    {
        $churchA = $this->insertChurch('Church A');
        $churchB = $this->insertChurch('Church B');

        $this->insertChurchPerson($churchA, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchB, 'Bob', 'active', 1);
        $this->insertChurchPerson($churchB, 'Carol', 'active', 1);

        session()->set('selected_parish_id', $churchA);
        $resultA = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchA, 'members', null, 25, 1);
        $this->assertSame(1, (int) $resultA['total']);
        $this->assertSame('Alice', (string) ($resultA['rows'][0]['first_name'] ?? ''));

        session()->set('selected_parish_id', $churchB);
        $resultB = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchB, 'members', null, 25, 1);
        $this->assertSame(2, (int) $resultB['total']);
    }

    // ---------------------------------------------------------- status counts

    public function testStatusCountsAllIncludesEveryPerson(): void
    {
        $churchId = $this->insertChurch('Status Counts All Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'inactive', 1);
        $this->insertChurchPerson($churchId, 'Carol', 'visitor', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($churchId, 'all');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(3, $total);
    }

    public function testStatusCountsMembersOnlyExcludesNonMembers(): void
    {
        $churchId = $this->insertChurch('Status Counts Members Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'inactive', 1);
        $this->insertChurchPerson($churchId, 'Carol', 'visitor', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($churchId, 'members');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total, 'Only the two tagged members should be counted');

        $statuses = array_column($counts, 'membership_status');
        $this->assertNotContains('visitor', $statuses);
    }

    public function testStatusCountsNonMembersOnlyExcludesMembers(): void
    {
        $churchId = $this->insertChurch('Status Counts Non Members Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'visitor', 0);
        $this->insertChurchPerson($churchId, 'Carol', 'pending', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($churchId, 'non_members');

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total, 'Only the two untagged people should be counted');
    }

    public function testStatusCountsDefaultsToAllWhenScopeOmitted(): void
    {
        $churchId = $this->insertChurch('Status Counts Default Church');
        $this->insertChurchPerson($churchId, 'Alice', 'active', 1);
        $this->insertChurchPerson($churchId, 'Bob', 'visitor', 0);

        $counts = model(ParishPeopleModel::class)->getStatusCounts($churchId);

        $total = array_sum(array_column($counts, 'count'));
        $this->assertSame(2, $total);
    }

    // ---------------------------------------------------------------- pagination

    public function testPaginationOffsetReturnsCorrectPage(): void
    {
        $churchId = $this->insertChurch('Pagination Church');
        for ($i = 1; $i <= 5; $i++) {
            $this->insertChurchPerson($churchId, 'Person' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'active', 1);
        }

        // perPage=2, page=2 → skip 2, take 2 → Person03, Person04
        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', null, 2, 2);

        $this->assertSame(5, (int) $result['total'], 'total must reflect all rows, not just this page');
        $this->assertCount(2, $result['rows']);
    }

    public function testPageBeyondLastReturnsEmptyRows(): void
    {
        $churchId = $this->insertChurch('Overflow Pagination Church');
        $this->insertChurchPerson($churchId, 'Solo', 'active', 1);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', null, 25, 99);

        $this->assertSame(1, (int) $result['total']);
        $this->assertCount(0, $result['rows']);
    }

    public function testEmptyChurchReturnsZeroTotal(): void
    {
        $churchId = $this->insertChurch('Empty Church');

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', null, 25, 1);

        $this->assertSame(0, (int) $result['total']);
        $this->assertCount(0, $result['rows']);
    }

    // ---------------------------------------------------------------- sort order

    public function testResultsOrderedByLastNameThenFirstName(): void
    {
        $churchId = $this->insertChurch('Sort Church');
        $this->insertChurchPersonFull($churchId, 'Zoe', 'Archer', 'active', 1);
        $this->insertChurchPersonFull($churchId, 'Anna', 'Brown', 'active', 1);
        $this->insertChurchPersonFull($churchId, 'Mike', 'Archer', 'active', 1);

        $result = model(ParishPeopleModel::class)
            ->getParishPeoplePaginated($churchId, 'all', null, 25, 1);

        $this->assertCount(3, $result['rows']);
        $this->assertSame('Mike', (string) ($result['rows'][0]['first_name'] ?? ''));  // Archer, Mike
        $this->assertSame('Zoe',  (string) ($result['rows'][1]['first_name'] ?? ''));  // Archer, Zoe
        $this->assertSame('Anna', (string) ($result['rows'][2]['first_name'] ?? ''));  // Brown, Anna
    }

    // ----------------------------------------------------------------- schema

    private function buildSchema(): void
    {
        $forge = \Config\Database::forge();

        // NOTE: `church_people` / `church_id` are the legacy pre-rename names.
        // Production has used `parish_people` / `parish_id` since the
        // StrictRenameLegacyChurchSchemaObjects migration; ParishPeopleModel's
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

    private function insertChurch(string $name): int
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

    private function insertChurchPerson(int $churchId, string $firstName, string $status, int $isMember): void
    {
        $this->insertChurchPersonFull($churchId, $firstName, 'Lastname', $status, $isMember);
    }

    private function insertChurchPersonFull(int $churchId, string $firstName, string $lastName, string $status, int $isMember): void
    {
        $this->db->table('parish_people')->insert([
            'parish_id'         => $churchId,
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
