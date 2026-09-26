<?php

use App\Models\FollowupLogModel;
use App\Models\PersonVisitModel;
use App\Services\VisitorDirectoryService;
use App\Services\VisitorFollowupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Phase 4: follow-up engine cleanup regression coverage.
 *
 * - PersonVisitModel::afterInsert is the single auto-scheduling point.
 * - scheduleActiveSequencesForPerson writes the fixed canonical columns
 *   (scheduled_date, parish_id, action_id) — no schema probing, no
 *   followup_date dual-write.
 * - visitorAffiliationExists scopes follow-up mutations to
 *   visitor/regular_attender affiliations.
 * - Linked call_task rows surface in the timeline via log_id.
 *
 * @internal
 */
final class VisitorFollowupPipelineTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'followup_log',
            'followup_action',
            'followup_sequence',
            'followup_template',
            'call_task',
            'person_visit',
            'parish_affiliation',
            'person',
            'parish',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
        session()->set('selected_parish_id', 301);
        $this->seedParish(301);
    }

    private function seedParish(int $parishId): void
    {
        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedSequence(int $parishId): array
    {
        $this->db->table('followup_sequence')->insert([
            'parish_id'  => $parishId,
            'name'       => 'Welcome Sequence',
            'is_active'  => 1,
            'is_default' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $sequenceId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id'  => $sequenceId,
            'week_number'  => 1,
            'day_offset'   => 2,
            'channel'      => 'email',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $actionId = (int) $this->db->insertID();

        return ['sequence_id' => $sequenceId, 'action_id' => $actionId];
    }

    private function seedPerson(string $status = 'visitor'): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Pipeline',
            'last_name'  => 'Person',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'person_id'         => $personId,
            'parish_id'         => 301,
            'membership_status' => $status,
            'followed_up'       => 0,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    public function testFirstVisitInsertSchedulesActiveSequences(): void
    {
        $this->seedSequence(301);
        $personId = $this->seedPerson();

        // The model hook is the scheduling point — no explicit call needed.
        model(PersonVisitModel::class)->insert([
            'person_id'  => $personId,
            'parish_id'  => 301,
            'visit_date' => '2026-09-20',
            'visit_type' => 'first',
        ]);

        $rows = $this->db->table('followup_log')
            ->where('person_id', $personId)
            ->get()
            ->getResultArray();

        $this->assertCount(1, $rows);
        $this->assertSame('pending', $rows[0]['status']);
        $this->assertSame('2026-09-22', $rows[0]['scheduled_date']); // visit + 2 days
        $this->assertSame(301, (int) $rows[0]['parish_id']);
        $this->assertGreaterThan(0, (int) $rows[0]['action_id']);
        $this->assertArrayNotHasKey('followup_date', $rows[0]);
    }

    public function testReturnVisitDoesNotReschedule(): void
    {
        $this->seedSequence(301);
        $personId = $this->seedPerson();

        model(PersonVisitModel::class)->insert([
            'person_id'  => $personId,
            'parish_id'  => 301,
            'visit_date' => '2026-09-21',
            'visit_type' => 'return',
        ]);

        $this->assertSame(0, $this->db->table('followup_log')
            ->where('person_id', $personId)
            ->countAllResults());
    }

    public function testSchedulingIsIdempotentAcrossVisits(): void
    {
        $this->seedSequence(301);
        $personId = $this->seedPerson();

        model(PersonVisitModel::class)->insert([
            'person_id'  => $personId,
            'parish_id'  => 301,
            'visit_date' => '2026-09-20',
            'visit_type' => 'first',
        ]);

        // An explicit second call (the pattern the import paths used to
        // duplicate) must not double-book the same actions.
        model(FollowupLogModel::class)->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $this->assertSame(1, $this->db->table('followup_log')
            ->where('person_id', $personId)
            ->countAllResults());
    }

    public function testVisitorAffiliationExistsScopesToVisitorStatuses(): void
    {
        $directory = new VisitorDirectoryService();

        $visitorId   = $this->seedPerson('visitor');
        $attenderId  = $this->seedPerson('regular_attender');
        $memberId    = $this->seedPerson('member');
        $inactiveId  = $this->seedPerson('inactive');

        $this->assertTrue($directory->visitorAffiliationExists($visitorId, 301));
        $this->assertTrue($directory->visitorAffiliationExists($attenderId, 301));
        $this->assertFalse($directory->visitorAffiliationExists($memberId, 301));
        $this->assertFalse($directory->visitorAffiliationExists($inactiveId, 301));
        $this->assertFalse($directory->visitorAffiliationExists($visitorId, 999));
        $this->assertFalse($directory->visitorAffiliationExists(0, 301));
    }

    private function insertLog(int $personId, int $parishId, string $status = 'pending'): int
    {
        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => $parishId,
            'scheduled_date' => '2026-09-22',
            'status'         => $status,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * The parish-dashboard tile uses pendingDistinctPersonsCount() and links
     * to /visitors?followup_stage=pending — the two must describe the same
     * population or the tile number and the detail panel drift apart.
     */
    public function testPendingCountMatchesPendingStageList(): void
    {
        $directory = new VisitorDirectoryService();
        $queries   = new VisitorFollowupService($this->db);

        $pendingVisitor = $this->seedPerson('visitor');
        $this->insertLog($pendingVisitor, 301);
        $this->insertLog($pendingVisitor, 301); // second action — still one person

        // Member affiliation is outside the visitor pipeline.
        $member = $this->seedPerson('member');
        $this->insertLog($member, 301);

        // Pending log belonging to a different parish must not leak in.
        $crossParish = $this->seedPerson('visitor');
        $this->seedParish(302);
        $this->insertLog($crossParish, 302);

        // Sent log is not pending.
        $sentVisitor = $this->seedPerson('visitor');
        $this->insertLog($sentVisitor, 301, 'sent');

        $this->assertSame(1, $queries->pendingDistinctPersonsCount(301));

        $list = $directory->paginateForParish(301, ['followup_stage' => 'pending'], 20, 1);

        $this->assertCount(1, $list['rows']);
        $this->assertSame($pendingVisitor, (int) $list['rows'][0]['person_id']);
    }

    public function testPendingCountExcludesConvertedAndDeleted(): void
    {
        $queries = new VisitorFollowupService($this->db);

        $converted = $this->seedPerson('member');
        $this->db->table('parish_affiliation')
            ->where('person_id', $converted)
            ->update(['converted_to_member_at' => date('Y-m-d H:i:s')]);
        $this->insertLog($converted, 301);

        $deleted = $this->seedPerson('visitor');
        $this->insertLog($deleted, 301);
        $this->db->table('person')
            ->where('id', $deleted)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $detached = $this->seedPerson('visitor');
        $this->insertLog($detached, 301);
        $this->db->table('parish_affiliation')
            ->where('person_id', $detached)
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $this->assertSame(0, $queries->pendingDistinctPersonsCount(301));

        $list = (new VisitorDirectoryService())
            ->paginateForParish(301, ['followup_stage' => 'pending'], 20, 1);

        $this->assertCount(0, $list['rows']);
    }

    public function testTimelineMarksLinkedCallTasks(): void
    {
        $personId = $this->seedPerson();

        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'scheduled_date' => '2026-09-22',
            'status'         => 'pending',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $logId = (int) $this->db->insertID();

        $this->db->table('call_task')->insert([
            'parish_id'      => 301,
            'person_id'      => $personId,
            'log_id'         => $logId,
            'phone'          => '555-0100',
            'status'         => 'pending',
            'scheduled_date' => '2026-09-22',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $timeline = (new VisitorFollowupService($this->db))->timelineForPerson($personId);

        $this->assertCount(1, $timeline);
        $this->assertSame('call_task', $timeline[0]['_source'] ?? null);
        $this->assertSame('call', $timeline[0]['channel']);
    }
}
