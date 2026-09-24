<?php

use App\Models\CallTaskModel;
use App\Models\FollowupLogModel;
use App\Models\LeadershipPositionModel;
use App\Services\CallTaskSyncService;
use App\Services\FollowupDispatchService;
use App\Services\VisitorDirectoryService;
use App\Services\VisitorFollowupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Manual follow-up task channel coverage.
 *
 * - Parish-scoped leader eligibility (getAssignableParishLeaders /
 *   isActiveParishLeader) respects active flag + started/ended window.
 * - Scheduling stamps followup_log.assigned_to from the action.
 * - dispatch() 'manual' creates a call_task row with task_type='manual',
 *   the var-substituted instructions as title, and the assignee.
 * - The task queue model exposes task_type filtering + assignee names.
 * - The timeline surfaces manual task rows with type + title.
 *
 * @internal
 */
final class FollowupManualTaskTest extends CIUnitTestCase
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
            'call_task',
            'person_visit',
            'parish_affiliation',
            'person',
            'leadership_position',
            'profile',
            'auth_identities',
            'users',
            'parish',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
        session()->set('selected_parish_id', 301);
        $this->seedParish(301);
    }

    // ------------------------------------------------------------------
    // Seeds
    // ------------------------------------------------------------------

    private function seedParish(int $parishId): void
    {
        $this->db->table('parish')->insert([
            'id'         => $parishId,
            'name'       => 'Test Parish ' . $parishId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedUser(string $username): int
    {
        $this->db->table('users')->insert([
            'username'   => $username,
            'email'      => $username . '@example.com',
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedLeader(int $userId, int $parishId, array $overrides = []): void
    {
        $this->db->table('leadership_position')->insert(array_merge([
            'user_id'    => $userId,
            'scope_type' => 'parish',
            'parish_id'  => $parishId,
            'title'      => 'Senior Warden',
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    private function seedPerson(int $parishId = 301): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Manual',
            'last_name'  => 'Visitor',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'person_id'         => $personId,
            'parish_id'         => $parishId,
            'membership_status' => 'visitor',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    private function seedManualAction(int $parishId, ?int $assignedTo = null): int
    {
        $this->db->table('followup_sequence')->insert([
            'parish_id'  => $parishId,
            'name'       => 'Manual Sequence',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $sequenceId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id'  => $sequenceId,
            'week_number'  => 1,
            'day_offset'   => 1,
            'channel'      => 'manual',
            'assigned_to'  => $assignedTo,
            'instructions' => 'Send {first_name} a social media invitation',
            'is_automated' => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedCallAction(int $parishId, ?int $assignedTo = null): int
    {
        $this->db->table('followup_sequence')->insert([
            'parish_id'  => $parishId,
            'name'       => 'Call Sequence',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $sequenceId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id'  => $sequenceId,
            'week_number'  => 1,
            'day_offset'   => 1,
            'channel'      => 'call',
            'assigned_to'  => $assignedTo,
            'is_automated' => 1,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    // ------------------------------------------------------------------
    // Leader eligibility
    // ------------------------------------------------------------------

    public function testAssignableLeadersAreParishScopedAndDateValid(): void
    {
        $model = model(LeadershipPositionModel::class);

        $leader   = $this->seedUser('warden');
        $otherP   = $this->seedUser('otherparish');
        $inactive = $this->seedUser('inactive');
        $ended    = $this->seedUser('ended');
        $future   = $this->seedUser('future');

        $this->seedParish(302);
        $this->seedLeader($leader, 301);
        $this->seedLeader($otherP, 302);
        $this->seedLeader($inactive, 301, ['active' => 0]);
        $this->seedLeader($ended, 301, ['ended_at' => '2020-01-01']);
        $this->seedLeader($future, 301, ['started_at' => '2999-01-01']);

        $leaders = $model->getAssignableParishLeaders(301);
        $ids     = array_map('intval', array_column($leaders, 'user_id'));

        $this->assertSame([$leader], $ids);
        $this->assertSame('Senior Warden', $leaders[0]['title']);

        $this->assertTrue($model->isActiveParishLeader($leader, 301));
        $this->assertFalse($model->isActiveParishLeader($leader, 302));
        $this->assertFalse($model->isActiveParishLeader($otherP, 301));
        $this->assertFalse($model->isActiveParishLeader($inactive, 301));
        $this->assertFalse($model->isActiveParishLeader($ended, 301));
        $this->assertFalse($model->isActiveParishLeader($future, 301));
    }

    public function testLeaderWithTwoPositionsDedupesInPicker(): void
    {
        $model  = model(LeadershipPositionModel::class);
        $leader = $this->seedUser('multi');

        $this->seedLeader($leader, 301, ['title' => 'Treasurer']);
        $this->seedLeader($leader, 301, ['title' => 'Warden']);

        $this->assertCount(1, $model->getAssignableParishLeaders(301));
        $this->assertTrue($model->isActiveParishLeader($leader, 301));
    }

    // ------------------------------------------------------------------
    // Scheduling stamps the assignee onto the log row
    // ------------------------------------------------------------------

    public function testSchedulingStampsAssignedToOnLog(): void
    {
        $leader = $this->seedUser('warden');
        $this->seedLeader($leader, 301);
        $actionId = $this->seedManualAction(301, $leader);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('person_id', $personId)
            ->where('action_id', $actionId)
            ->get()->getRowArray();

        $this->assertNotNull($log);
        $this->assertSame($leader, (int) $log['assigned_to']);
        $this->assertSame('pending', $log['status']);
    }

    // ------------------------------------------------------------------
    // Dispatch creates an assigned manual task, nothing is "sent"
    // ------------------------------------------------------------------

    public function testDispatchManualCreatesAssignedTask(): void
    {
        $leader   = $this->seedUser('warden');
        $personId = $this->seedPerson();

        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'assigned_to'    => $leader,
            'scheduled_date' => '2026-09-22',
            'status'         => 'pending',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $logId = (int) $this->db->insertID();

        $logRow = [
            'id'             => $logId,
            'person_id'      => $personId,
            'assigned_to'    => $leader,
            'channel'        => 'manual',
            'instructions'   => 'Send {first_name} a social media invitation',
            'scheduled_date' => '2026-09-22',
        ];
        $visitor = [
            'person_id'          => $personId,
            'parish_id'          => 301,
            'first_name'         => 'Manual',
            'last_name'          => 'Visitor',
            'opted_out_channels' => null,
        ];

        $result = (new FollowupDispatchService())->dispatch($logRow, null, $visitor);

        $this->assertTrue($result['success'], $result['error']);
        $this->assertSame('manual', $result['metadata']['channel']);
        $this->assertSame($leader, (int) $result['metadata']['assigned_to']);

        $task = $this->db->table('call_task')->get()->getRowArray();
        $this->assertSame('manual', $task['task_type']);
        $this->assertSame($leader, (int) $task['assigned_to']);
        $this->assertSame($logId, (int) $task['log_id']);
        $this->assertSame($personId, (int) $task['person_id']);
        $this->assertSame('pending', $task['status']);
        // {first_name} was var-substituted at dispatch
        $this->assertSame('Send Manual a social media invitation', $task['title']);
    }

    // ------------------------------------------------------------------
    // Schedule-time task materialization (call_task keeps step with
    // followup_log so pending/overdue calls are tracked before dispatch)
    // ------------------------------------------------------------------

    public function testSchedulingMaterializesPendingManualTask(): void
    {
        $leader   = $this->seedUser('warden');
        $actionId = $this->seedManualAction(301, $leader);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('action_id', $actionId)
            ->get()->getRowArray();
        $this->assertNotNull($log);

        $task = $this->db->table('call_task')
            ->where('log_id', (int) $log['id'])
            ->get()->getRowArray();

        $this->assertNotNull($task);
        $this->assertSame('manual', $task['task_type']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame($leader, (int) $task['assigned_to']);
        $this->assertSame($personId, (int) $task['person_id']);
        $this->assertSame($log['scheduled_date'], $task['scheduled_date']);
        $this->assertSame('Send Manual a social media invitation', $task['title']);
    }

    public function testSchedulingMaterializesPendingCallTask(): void
    {
        $actionId = $this->seedCallAction(301);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('action_id', $actionId)
            ->get()->getRowArray();
        $this->assertNotNull($log);

        $task = $this->db->table('call_task')
            ->where('log_id', (int) $log['id'])
            ->get()->getRowArray();

        $this->assertNotNull($task);
        $this->assertSame('call', $task['task_type']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame(301, (int) $task['parish_id']);
    }

    public function testDispatchDoesNotDuplicateMaterializedTask(): void
    {
        $leader   = $this->seedUser('warden');
        $actionId = $this->seedManualAction(301, $leader);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('action_id', $actionId)
            ->get()->getRowArray();

        $logRow = array_merge($log, [
            'channel'      => 'manual',
            'instructions' => 'Send {first_name} a social media invitation',
        ]);
        $visitor = [
            'person_id'          => $personId,
            'parish_id'          => 301,
            'first_name'         => 'Manual',
            'last_name'          => 'Visitor',
            'opted_out_channels' => null,
        ];

        $result = (new FollowupDispatchService())->dispatch($logRow, null, $visitor);

        $this->assertTrue($result['success'], $result['error']);
        $this->assertTrue($result['metadata']['existing'] ?? false);

        $tasks = $this->db->table('call_task')
            ->where('log_id', (int) $log['id'])
            ->get()->getResultArray();
        $this->assertCount(1, $tasks);
    }

    public function testResolveForLogTransitionsPendingTask(): void
    {
        $actionId = $this->seedCallAction(301);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('action_id', $actionId)
            ->get()->getRowArray();
        $logId = (int) $log['id'];

        (new CallTaskSyncService($this->db))->resolveForLog($logId, 'completed', 'Called — left voicemail');

        $task = $this->db->table('call_task')->where('log_id', $logId)->get()->getRowArray();
        $this->assertSame('completed', $task['status']);
        $this->assertNotEmpty($task['completed_at']);
        $this->assertSame('Called — left voicemail', $task['notes']);
    }

    public function testResolveForLogCreatesTerminalTaskWhenMissing(): void
    {
        $actionId = $this->seedCallAction(301);
        $personId = $this->seedPerson();

        // Historical shape: log resolved manually before tasks existed.
        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'action_id'      => $actionId,
            'scheduled_date' => '2026-09-22',
            'executed_at'    => '2026-09-22 18:30:00',
            'status'         => 'sent',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $logId = (int) $this->db->insertID();

        (new CallTaskSyncService($this->db))->resolveForLog($logId, 'completed');

        $task = $this->db->table('call_task')->where('log_id', $logId)->get()->getRowArray();
        $this->assertNotNull($task);
        $this->assertSame('call', $task['task_type']);
        $this->assertSame('completed', $task['status']);
        $this->assertSame('2026-09-22 18:30:00', $task['completed_at']);
    }

    public function testRescheduleMovesPendingTaskDate(): void
    {
        $actionId = $this->seedCallAction(301);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('action_id', $actionId)
            ->get()->getRowArray();
        $logId = (int) $log['id'];

        (new CallTaskSyncService($this->db))->rescheduleForLog($logId, '2026-10-01');

        $task = $this->db->table('call_task')->where('log_id', $logId)->get()->getRowArray();
        $this->assertSame('2026-10-01', $task['scheduled_date']);
        $this->assertSame('pending', $task['status']);
    }

    public function testDeleteVisitorCancelsPendingTasks(): void
    {
        $actionId = $this->seedManualAction(301);
        $personId = $this->seedPerson();

        model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson($personId, 301, '2026-09-20');

        $log = $this->db->table('followup_log')
            ->where('action_id', $actionId)
            ->get()->getRowArray();
        $logId = (int) $log['id'];

        $this->assertSame('pending', $this->db->table('call_task')
            ->where('log_id', $logId)->get()->getRowArray()['status'] ?? null);

        $deleted = (new VisitorDirectoryService())->delete($personId, 301);

        $this->assertTrue($deleted);
        $this->assertSame('skipped', $this->db->table('followup_log')
            ->where('id', $logId)->get()->getRowArray()['status'] ?? null);
        $this->assertSame('cancelled', $this->db->table('call_task')
            ->where('log_id', $logId)->get()->getRowArray()['status'] ?? null);
    }

    // ------------------------------------------------------------------
    // Queue model: type filter + assignee name
    // ------------------------------------------------------------------

    public function testPendingForParishFiltersByTaskType(): void
    {
        $leader   = $this->seedUser('warden');
        $personId = $this->seedPerson();

        foreach ([
            ['task_type' => 'call', 'assigned_to' => null],
            ['task_type' => 'manual', 'assigned_to' => $leader],
        ] as $row) {
            $this->db->table('call_task')->insert(array_merge([
                'parish_id'      => 301,
                'person_id'      => $personId,
                'status'         => 'pending',
                'scheduled_date' => '2026-09-22',
                'created_at'     => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], $row));
        }

        $model = model(CallTaskModel::class);

        $this->assertCount(2, $model->getPendingForParish(301));
        $this->assertCount(1, $model->getPendingForParish(301, 'call'));
        $this->assertCount(1, $model->getPendingForParish(301, 'manual'));

        $manual = $model->getPendingForParish(301, 'manual')[0];
        $this->assertSame('manual', $manual['task_type']);
        // assignee_name falls back to profile-less row (username still joins)
        $this->assertSame('warden', $manual['assignee_username']);
    }

    public function testPendingQueueExcludesEndedAffiliations(): void
    {
        $personId = $this->seedPerson();

        $this->db->table('call_task')->insert([
            'parish_id'      => 301,
            'person_id'      => $personId,
            'task_type'      => 'call',
            'status'         => 'pending',
            'scheduled_date' => '2026-09-22',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $model = model(CallTaskModel::class);
        $this->assertSame(1, $model->countPending(301));
        $this->assertCount(1, $model->getPendingForParish(301));

        // Detach the visitor — the task must drop out of the actionable
        // queue just like its log drops out of getPendingDue().
        $this->db->table('parish_affiliation')
            ->where('person_id', $personId)
            ->where('parish_id', 301)
            ->update(['end_date' => '2020-01-01']);

        $this->assertSame(0, $model->countPending(301));
        $this->assertCount(0, $model->getPendingForParish(301));
    }

    // ------------------------------------------------------------------
    // Timeline surfaces manual task rows
    // ------------------------------------------------------------------

    public function testTimelineMarksManualTasksWithTypeAndTitle(): void
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
            'task_type'      => 'manual',
            'title'          => 'Send Manual a social media invitation',
            'status'         => 'pending',
            'scheduled_date' => '2026-09-22',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $timeline = (new VisitorFollowupService($this->db))->timelineForPerson($personId);

        $this->assertCount(1, $timeline);
        $this->assertSame('call_task', $timeline[0]['_source'] ?? null);
        $this->assertSame('manual', $timeline[0]['call_task_type']);
        $this->assertSame('Send Manual a social media invitation', $timeline[0]['call_task_title']);
    }
}
