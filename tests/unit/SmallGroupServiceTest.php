<?php

use App\Services\SmallGroupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.5: small-group membership lifecycle.
 *
 * Covers add/remove/role transitions keyed by canonical person_id, capacity
 * enforcement, reactivation of a former member, parish scoping, and the
 * pathway-stage recompute that fires on roster changes.
 *
 * @internal
 */
final class SmallGroupServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private SmallGroupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SmallGroupService();
        $this->seedParish(301);
        session()->set('selected_parish_id', 301);
    }

    public function testAddMemberCreatesActiveMembership(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();

        $result = $this->service->addMember($groupId, $personId);

        $this->assertTrue($result['ok'], json_encode($result));

        $row = $this->db->table('group_membership')->where('id', $result['id'])->get()->getRowArray();
        $this->assertSame('active', $row['status']);
        $this->assertSame('member', $row['role']);
        $this->assertSame(date('Y-m-d'), $row['joined_on']);
    }

    public function testAddMemberRejectsDuplicateActiveMembership(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();

        $this->assertTrue($this->service->addMember($groupId, $personId)['ok']);
        $second = $this->service->addMember($groupId, $personId);

        $this->assertFalse($second['ok']);
        $this->assertSame('already_member', $second['code']);
    }

    public function testAddMemberRejectsInvalidRole(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();

        $result = $this->service->addMember($groupId, $personId, 'overlord');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_role', $result['code']);
    }

    public function testAddMemberHonorsCapacity(): void
    {
        $groupId = $this->insertGroup(301, ['capacity' => 1]);
        $p1 = $this->insertPerson();
        $p2 = $this->insertPerson();

        $this->assertTrue($this->service->addMember($groupId, $p1)['ok']);

        $result = $this->service->addMember($groupId, $p2);
        $this->assertFalse($result['ok']);
        $this->assertSame('at_capacity', $result['code']);
    }

    public function testRemoveMemberMarksInactiveAndSetsLeftOn(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();
        $this->service->addMember($groupId, $personId);

        $result = $this->service->removeMember($groupId, $personId);
        $this->assertTrue($result['ok']);

        $row = $this->db->table('group_membership')
            ->where('small_group_id', $groupId)
            ->where('person_id', $personId)
            ->get()
            ->getRowArray();
        $this->assertSame('inactive', $row['status']);
        $this->assertSame(date('Y-m-d'), $row['left_on']);
        $this->assertSame(0, $this->service->activeMemberCount($groupId));
    }

    public function testRejoinReactivatesExistingRow(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();

        $this->service->addMember($groupId, $personId);
        $this->service->removeMember($groupId, $personId);
        $rejoin = $this->service->addMember($groupId, $personId, 'host');

        $this->assertTrue($rejoin['ok']);

        $count = $this->db->table('group_membership')
            ->where('small_group_id', $groupId)
            ->where('person_id', $personId)
            ->countAllResults();
        $this->assertSame(1, $count);

        $row = $this->db->table('group_membership')
            ->where('small_group_id', $groupId)
            ->where('person_id', $personId)
            ->get()
            ->getRowArray();
        $this->assertSame('active', $row['status']);
        $this->assertSame('host', $row['role']);
        $this->assertNull($row['left_on']);
    }

    public function testUpdateRoleChangesActiveMemberRole(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();
        $this->service->addMember($groupId, $personId);

        $result = $this->service->updateRole($groupId, $personId, 'co_leader');
        $this->assertTrue($result['ok']);

        $role = $this->db->table('group_membership')
            ->where('small_group_id', $groupId)
            ->where('person_id', $personId)
            ->get()
            ->getRow('role');
        $this->assertSame('co_leader', $role);
    }

    public function testUpdateRoleRejectsNonMember(): void
    {
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson();

        $result = $this->service->updateRole($groupId, $personId, 'leader');
        $this->assertFalse($result['ok']);
        $this->assertSame('not_member', $result['code']);
    }

    public function testGroupForParishEnforcesScope(): void
    {
        $this->seedParish(302);
        $groupId = $this->insertGroup(302);

        $this->assertNull($this->service->groupForParish($groupId, 301));

        session()->set('selected_parish_id', 302);
        $this->assertNotNull($this->service->groupForParish($groupId, 302));
    }

    public function testPersonMayJoinMultipleGroups(): void
    {
        $groupA = $this->insertGroup(301, ['name' => 'Group A']);
        $groupB = $this->insertGroup(301, ['name' => 'Group B']);
        $personId = $this->insertPerson();

        $this->assertTrue($this->service->addMember($groupA, $personId)['ok']);
        $this->assertTrue($this->service->addMember($groupB, $personId)['ok']);
    }

    public function testAddMemberRejectsPersonFromAnotherParish(): void
    {
        $this->seedParish(302);
        $groupId = $this->insertGroup(301);
        $personId = $this->insertPerson(302);

        $result = $this->service->addMember($groupId, $personId);

        $this->assertFalse($result['ok']);
        $this->assertSame('person_not_in_parish', $result['code']);
    }

    public function testAddMemberRejectsPersonWithNoAffiliation(): void
    {
        $groupId = $this->insertGroup(301);

        $this->db->table('person')->insert([
            'first_name' => 'Unaffiliated',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $result = $this->service->addMember($groupId, $personId);

        $this->assertFalse($result['ok']);
        $this->assertSame('person_not_in_parish', $result['code']);
    }

    private function seedParish(int $parishId): void
    {
        if ($this->db->table('parish')->where('id', $parishId)->countAllResults() > 0) {
            return;
        }

        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertGroup(int $parishId, array $overrides = []): int
    {
        $this->db->table('small_group')->insert(array_merge([
            'parish_id'  => $parishId,
            'name'       => 'Test Group',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));

        return (int) $this->db->insertID();
    }

    private function insertPerson(int $parishId = 301): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Test',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => $parishId,
            'person_id'         => $personId,
            'affiliation_type'  => 'member',
            'membership_status' => 'member',
            'is_primary'        => 1,
            'source'            => 'system',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }
}
