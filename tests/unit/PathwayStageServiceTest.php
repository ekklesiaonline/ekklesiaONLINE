<?php

use App\Services\PathwayStageService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.5: derived pathway stage on parish_affiliation.
 *
 *   Stage 1  The Anglican Way      — affiliation status 'member'
 *   Stage 2  Rooted in Community   — active small-group placement
 *   Stage 3  Sent to Serve         — ministry team placement
 *
 * Non-member statuses hold no stage; 'inactive' members keep their stage;
 * terminal statuses clear it.
 *
 * @internal
 */
final class PathwayStageServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private PathwayStageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PathwayStageService();
        $this->seedParish(301);
        session()->set('selected_parish_id', 301);
    }

    public function testVisitorHasNoStage(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'visitor', 0);

        $this->assertNull($this->service->stageFor($personId, 301));
    }

    public function testMemberIsStageOne(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'member', 1);

        $this->assertSame(1, $this->service->stageFor($personId, 301));
    }

    public function testGroupPlacementIsStageTwo(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'member', 1);
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $personId);

        $this->assertSame(2, $this->service->stageFor($personId, 301));
    }

    public function testTeamPlacementIsStageThree(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'member', 1);
        $this->insertGroupMembershipAndTeam($personId);

        $this->assertSame(3, $this->service->stageFor($personId, 301));
    }

    public function testTeamPlacementViaUserIdFallback(): void
    {
        // Rows inserted before person_id was populated resolve via user_person.
        $personId = $this->insertPerson();
        $userId = $this->insertUser();
        $this->db->table('user_person')->insert([
            'user_id'   => $userId,
            'person_id' => $personId,
            'is_primary' => 1,
        ]);
        $this->insertAffiliation($personId, 'member', 1);
        $teamId = $this->insertTeam(301);
        $this->db->table('team_member')->insert([
            'team_id' => $teamId,
            'user_id' => $userId,
            'role'    => 'member',
        ]);

        $this->assertSame(3, $this->service->stageFor($personId, 301));
    }

    public function testInactiveMembershipDoesNotCountTowardStage(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'member', 1);
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $personId, 'inactive');

        $this->assertSame(1, $this->service->stageFor($personId, 301));
    }

    public function testInactiveMemberKeepsStage(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'inactive', 0);
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $personId);

        $this->assertSame(2, $this->service->stageFor($personId, 301));
    }

    public function testTerminalStatusClearsStage(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'transferred', 0);
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $personId);

        $this->assertNull($this->service->stageFor($personId, 301));
    }

    public function testPlacementInAnotherParishDoesNotCount(): void
    {
        $this->seedParish(302);
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'member', 1);
        $foreignGroup = $this->insertGroup(302);
        $this->insertMembership($foreignGroup, $personId);

        $this->assertSame(1, $this->service->stageFor($personId, 301));
    }

    public function testRecomputePersistsStageOnAffiliation(): void
    {
        $personId = $this->insertPerson();
        $affiliationId = $this->insertAffiliation($personId, 'member', 1);
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $personId);

        $result = $this->service->recompute($personId, 301);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['changed']);
        $this->assertSame(2, $result['stage']);

        $stage = $this->db->table('parish_affiliation')
            ->where('id', $affiliationId)
            ->get()
            ->getRow('pathway_stage');
        $this->assertSame(2, (int) $stage);
    }

    public function testRecomputeIsIdempotent(): void
    {
        $personId = $this->insertPerson();
        $this->insertAffiliation($personId, 'member', 1);

        $first = $this->service->recompute($personId, 301);
        $second = $this->service->recompute($personId, 301);

        $this->assertTrue($first['changed']);
        $this->assertFalse($second['changed']);
        $this->assertSame(1, $second['stage']);
    }

    public function testRecomputeParishUpdatesAllAffiliations(): void
    {
        $p1 = $this->insertPerson();
        $p2 = $this->insertPerson();
        $this->insertAffiliation($p1, 'member', 1);
        $this->insertAffiliation($p2, 'visitor', 0);
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $p1);

        $changed = $this->service->recomputeParish(301);

        // Other affiliations may be seeded in this parish by fixtures; at
        // minimum our grouped member must have been updated.
        $this->assertGreaterThanOrEqual(1, $changed);
        $this->assertSame(2, $this->service->stageFor($p1, 301));
        $this->assertNull($this->service->stageFor($p2, 301));
    }

    public function testStageMeta(): void
    {
        $this->assertSame('Groups.stage2', PathwayStageService::stageMeta(2)['labelKey']);
        $this->assertNull(PathwayStageService::stageMeta(null));
        $this->assertNull(PathwayStageService::stageMeta(9));
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

    private function insertPerson(): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Test',
            'last_name'  => 'Person',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertUser(): int
    {
        $this->db->table('users')->insert([
            'username'   => 'user' . uniqid(),
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertAffiliation(int $personId, string $status, int $isMember, int $parishId = 301): int
    {
        $this->db->table('parish_affiliation')->insert([
            'person_id'         => $personId,
            'parish_id'         => $parishId,
            'affiliation_type'  => \App\Services\MembershipStatusService::affiliationTypeForStatus($status),
            'membership_status' => $status,
            'is_member'         => $isMember,
            'start_date'        => date('Y-m-d'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertGroup(int $parishId): int
    {
        $this->db->table('small_group')->insert([
            'parish_id'  => $parishId,
            'name'       => 'Test Group',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertMembership(int $groupId, int $personId, string $status = 'active'): int
    {
        $this->db->table('group_membership')->insert([
            'small_group_id' => $groupId,
            'person_id'      => $personId,
            'role'           => 'member',
            'status'         => $status,
            'joined_on'      => date('Y-m-d'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertTeam(int $parishId): int
    {
        $this->db->table('team')->insert([
            'parish_id'  => $parishId,
            'name'       => 'Test Team',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function insertGroupMembershipAndTeam(int $personId): void
    {
        $groupId = $this->insertGroup(301);
        $this->insertMembership($groupId, $personId);

        $teamId = $this->insertTeam(301);
        $this->db->table('team_member')->insert([
            'team_id'   => $teamId,
            'person_id' => $personId,
            'role'      => 'member',
        ]);
    }
}
