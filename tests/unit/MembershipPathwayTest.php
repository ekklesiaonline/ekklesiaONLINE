<?php

use App\Services\MembershipPathwayService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.3: membership pathway transitions.
 *
 * Affiliation status changes go through MembershipPathwayService so the
 * canonical status vocabulary, is_member / affiliation_type / end_date
 * consistency, person_status_history audit rows (keyed by person_id), and
 * terminal-status reason requirements are enforced in one place.
 *
 * @internal
 */
final class MembershipPathwayTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private MembershipPathwayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MembershipPathwayService();
        $this->seedParish(301);
        session()->set('selected_parish_id', 301);
    }

    public function testClassifyForwardAndBackward(): void
    {
        $this->assertSame(MembershipPathwayService::MOVE_FORWARD, $this->service->classify('visitor', 'member'));
        $this->assertSame(MembershipPathwayService::MOVE_FORWARD, $this->service->classify('visitor', 'regular_attender'));
        $this->assertSame(MembershipPathwayService::MOVE_BACKWARD, $this->service->classify('member', 'visitor'));
        $this->assertSame(MembershipPathwayService::MOVE_BACKWARD, $this->service->classify('member', 'regular_attender'));
    }

    public function testClassifyLifecycleMoves(): void
    {
        $this->assertSame(MembershipPathwayService::MOVE_DEACTIVATION, $this->service->classify('member', 'inactive'));
        $this->assertSame(MembershipPathwayService::MOVE_REACTIVATION, $this->service->classify('inactive', 'member'));
        $this->assertSame(MembershipPathwayService::MOVE_TERMINAL, $this->service->classify('member', 'deceased'));
        $this->assertSame(MembershipPathwayService::MOVE_TERMINAL, $this->service->classify('visitor', 'transferred'));
        $this->assertSame(MembershipPathwayService::MOVE_CORRECTION, $this->service->classify('deceased', 'member'));
        $this->assertSame(MembershipPathwayService::MOVE_NONE, $this->service->classify('member', 'member'));
    }

    public function testRequiresReasonOnlyAcrossTerminalBoundary(): void
    {
        $this->assertTrue($this->service->requiresReason('member', 'deceased'));
        $this->assertTrue($this->service->requiresReason('visitor', 'transferred'));
        $this->assertTrue($this->service->requiresReason('deceased', 'member'));
        $this->assertFalse($this->service->requiresReason('visitor', 'member'));
        $this->assertFalse($this->service->requiresReason('member', 'inactive'));
        $this->assertFalse($this->service->requiresReason('member', 'member'));
    }

    public function testAllowedTransitionsExcludesCurrentStatus(): void
    {
        $targets = $this->service->allowedTransitions('member');

        $this->assertNotContains('member', $targets);
        $this->assertContains('regular_attender', $targets);
        $this->assertContains('inactive', $targets);
        $this->assertContains('deceased', $targets);
    }

    public function testTransitionUpdatesAffiliationAndRecordsHistoryUnderPersonId(): void
    {
        $affiliationId = $this->insertAffiliation(501, 9001, 'visitor', 0);

        $result = $this->service->transitionForUser(501, 301, 'member', 77, 'Completed new member class');

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(MembershipPathwayService::MOVE_FORWARD, $result['classification']);

        $row = $this->db->table('parish_affiliation')->where('id', $affiliationId)->get()->getRowArray();
        $this->assertSame('member', $row['membership_status']);
        $this->assertSame('member', $row['affiliation_type']);
        $this->assertSame(1, (int) $row['is_member']);

        $history = $this->db->table('person_status_history')
            ->where('person_id', 9001)
            ->where('status_type', 'affiliation')
            ->get()
            ->getResultArray();

        $this->assertCount(1, $history);
        $this->assertSame('visitor', $history[0]['from_status']);
        $this->assertSame('member', $history[0]['to_status']);
        $this->assertSame(301, (int) $history[0]['parish_id']);
        $this->assertSame(77, (int) $history[0]['changed_by']);
        $this->assertSame('Completed new member class', $history[0]['reason']);
    }

    public function testTerminalTransitionRequiresReason(): void
    {
        $affiliationId = $this->insertAffiliation(502, 9002, 'member', 1);

        $result = $this->service->transitionForUser(502, 301, 'deceased', 77, '');

        $this->assertFalse($result['ok']);
        $this->assertSame('reason_required', $result['code']);

        $row = $this->db->table('parish_affiliation')->where('id', $affiliationId)->get()->getRowArray();
        $this->assertSame('member', $row['membership_status']);
    }

    public function testTerminalTransitionSetsEndDate(): void
    {
        $affiliationId = $this->insertAffiliation(503, 9003, 'member', 1);

        $result = $this->service->transitionForUser(503, 301, 'transferred', 77, 'Moved to another parish');

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(MembershipPathwayService::MOVE_TERMINAL, $result['classification']);

        $row = $this->db->table('parish_affiliation')->where('id', $affiliationId)->get()->getRowArray();
        $this->assertSame('transferred', $row['membership_status']);
        $this->assertSame(0, (int) $row['is_member']);
        $this->assertNotEmpty($row['end_date']);
    }

    public function testCorrectionOutOfTerminalAllowedWithReason(): void
    {
        $affiliationId = $this->insertAffiliation(504, 9004, 'deceased', 0);

        $result = $this->service->transitionForUser(504, 301, 'member', 77, 'Recorded in error');

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(MembershipPathwayService::MOVE_CORRECTION, $result['classification']);

        $row = $this->db->table('parish_affiliation')->where('id', $affiliationId)->get()->getRowArray();
        $this->assertSame('member', $row['membership_status']);
        $this->assertNull($row['end_date']);
    }

    public function testInvalidStatusRejected(): void
    {
        $this->insertAffiliation(505, 9005, 'visitor', 0);

        $result = $this->service->transitionForUser(505, 301, 'superstar', 77, 'test');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_status', $result['code']);
    }

    public function testSameStatusIsNoOpWithoutHistory(): void
    {
        $this->insertAffiliation(506, 9006, 'member', 1);

        $result = $this->service->transitionForUser(506, 301, 'member', 77, null);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(MembershipPathwayService::MOVE_NONE, $result['classification']);

        $count = $this->db->table('person_status_history')
            ->where('person_id', 9006)
            ->countAllResults();
        $this->assertSame(0, $count);
    }

    public function testMissingAffiliationReturnsNotFound(): void
    {
        $result = $this->service->transitionForUser(599, 301, 'member', 77, null);

        $this->assertFalse($result['ok']);
        $this->assertSame('affiliation_not_found', $result['code']);
    }

    public function testCrossParishAffiliationIsNotTransitionable(): void
    {
        $this->seedParish(302);
        $affiliationId = $this->insertAffiliation(507, 9007, 'visitor', 0, 302);

        // The model is tenant-scoped: an affiliation in parish 302 is not
        // reachable while operating in parish 301, even when addressed by id.
        $result = $this->service->transition($affiliationId, 'member', 77, null);

        $this->assertFalse($result['ok']);
        $this->assertSame('affiliation_not_found', $result['code']);

        $row = $this->db->table('parish_affiliation')->where('id', $affiliationId)->get()->getRowArray();
        $this->assertSame('visitor', $row['membership_status']);
    }

    private function seedParish(int $parishId): void
    {
        $exists = $this->db->table('parish')->where('id', $parishId)->countAllResults();
        if ($exists > 0) {
            return;
        }

        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function insertAffiliation(int $userId, int $personId, string $status, int $isMember, int $parishId = 301): int
    {
        $this->db->table('parish_affiliation')->insert([
            'linked_user_id'    => $userId,
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
}
