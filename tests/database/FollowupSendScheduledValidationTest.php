<?php

namespace Tests\Database;

use App\Commands\SendFollowups;
use App\Models\FollowupLogModel;
use App\Models\SmsQueueModel;
use CodeIgniter\Config\Services;
use CodeIgniter\Email\Email;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Email as EmailConfig;
use Tests\Support\Database\CanonicalTestSchema;
use Tests\Support\Database\SystemSettingsTestHelper;

/**
 * Phase 5: Visitor Follow-up Scheduled Email & Dispatch Validation.
 *
 * Validates:
 * - FollowupLogModel::getPendingDue() cutoff logic (past, today, future).
 * - Soft-deleted persons and ended affiliations are excluded from pending due.
 * - SendFollowups command channel dispatch transitions (sent, skipped, failed).
 * - Execution idempotency (second run is a no-op).
 * - Scoping by --parish <id>.
 * - Dry-run mode (--dry-run) does not mutate database records.
 *
 * @internal
 */
final class FollowupSendScheduledValidationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    private object $mockEmail;

    protected function setUp(): void
    {
        parent::setUp();
        CanonicalTestSchema::ensure($this->db);
        SystemSettingsTestHelper::createSystemSettingsTableAndSeed($this->db);
        SystemSettingsTestHelper::createAuditLogTable($this->db);

        foreach ([
            'followup_log',
            'followup_action',
            'followup_sequence',
            'followup_template',
            'call_task',
            'sms_queue',
            'person_visit',
            'parish_affiliation',
            'person',
            'parish',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }

        $this->seedParish(301, 'St. Jude Parish');
        $this->seedParish(302, 'Grace Church');

        // Set up email test double
        $this->mockEmail = new class (new EmailConfig()) extends Email {
            /** @var array<int, array<string, mixed>> */
            public array $sentMessages = [];
            public bool $shouldSucceed = true;
            public string $debuggerOutput = 'Simulated SMTP error';
            public string $lastSubject = '';

            public function setSubject($subject)
            {
                $this->lastSubject = (string) $subject;
                return parent::setSubject($subject);
            }

            public function send($autoClear = true): bool
            {
                if (! $this->shouldSucceed) {
                    return false;
                }

                $this->sentMessages[] = [
                    'to'      => $this->recipients,
                    'subject' => $this->lastSubject,
                    'body'    => $this->body,
                ];

                return true;
            }

            public function printDebugger($include = ['headers', 'subject', 'body']): string
            {
                return $this->debuggerOutput;
            }
        };

        Services::injectMock('email', $this->mockEmail);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('email');
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Seed Helpers
    // -----------------------------------------------------------------------

    private function seedParish(int $id, string $name): void
    {
        $this->db->table('parish')->insert([
            'id'         => $id,
            'name'       => $name,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedPerson(int $parishId, array $overrides = []): int
    {
        $personData = array_merge([
            'first_name'         => 'Test',
            'last_name'          => 'Visitor',
            'email'              => 'visitor@example.com',
            'phone'              => '555-0199',
            'opted_out_channels' => null,
            'is_active'          => 1,
            'created_at'         => date('Y-m-d H:i:s'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->db->table('person')->insert($personData);
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

    private function seedTemplate(int $parishId, string $subject, string $bodyText, string $channel = 'email'): int
    {
        $this->db->table('followup_template')->insert([
            'parish_id'  => $parishId,
            'channel'    => $channel,
            'name'       => 'Test ' . ucfirst($channel) . ' Template',
            'subject'    => $subject,
            'body_text'  => $bodyText,
            'body_html'  => '<p>' . esc($bodyText) . '</p>',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedSequenceAndAction(int $parishId, string $channel, ?int $templateId = null): array
    {
        $this->db->table('followup_sequence')->insert([
            'parish_id'  => $parishId,
            'name'       => 'Default Welcome Sequence',
            'is_active'  => 1,
            'is_default' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $sequenceId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id'  => $sequenceId,
            'week_number'  => 1,
            'day_offset'   => 1,
            'channel'      => $channel,
            'template_id'  => $templateId,
            'instructions' => $channel === 'manual' ? 'Call visitor {first_name}' : null,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $actionId = (int) $this->db->insertID();

        return ['sequence_id' => $sequenceId, 'action_id' => $actionId];
    }

    private function seedLog(int $personId, int $parishId, int $actionId, string $scheduledDate, string $status = 'pending'): int
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => $parishId,
            'action_id'      => $actionId,
            'scheduled_date' => $scheduledDate,
            'status'         => $status,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int) $this->db->insertID();
    }

    // -----------------------------------------------------------------------
    // Tests: Due Date & Cutoff Logic
    // -----------------------------------------------------------------------

    public function testPendingDueFiltersPastAndTodayExcludesFuture(): void
    {
        $templateId = $this->seedTemplate(301, 'Hello {first_name}', 'We are glad you visited!');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $personId   = $this->seedPerson(301);

        $pastDate   = date('Y-m-d', strtotime('-3 days'));
        $todayDate  = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime('+3 days'));

        $pastLogId   = $this->seedLog($personId, 301, $action['action_id'], $pastDate);
        $todayLogId  = $this->seedLog($personId, 301, $action['action_id'], $todayDate);
        $futureLogId = $this->seedLog($personId, 301, $action['action_id'], $futureDate);

        /** @var FollowupLogModel $logModel */
        $logModel = model(FollowupLogModel::class);
        $due = $logModel->getPendingDue($todayDate);

        $dueIds = array_map(static fn ($r) => (int) $r['id'], $due);

        $this->assertContains($pastLogId, $dueIds, 'Past scheduled log must be included in pending due.');
        $this->assertContains($todayLogId, $dueIds, 'Today scheduled log must be included in pending due.');
        $this->assertNotContains($futureLogId, $dueIds, 'Future scheduled log must NOT be included in pending due.');

        // Verify sorted by scheduled_date ASC
        $this->assertSame($pastLogId, (int) $due[0]['id'], 'Earliest scheduled log must appear first.');
    }

    public function testPendingDueExcludesDeletedPersonAndEndedAffiliation(): void
    {
        $templateId = $this->seedTemplate(301, 'Hello', 'Welcome');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $todayDate  = date('Y-m-d');

        // Active person with active affiliation
        $activePersonId = $this->seedPerson(301);
        $activeLogId    = $this->seedLog($activePersonId, 301, $action['action_id'], $todayDate);

        // Soft-deleted person
        $deletedPersonId = $this->seedPerson(301);
        $deletedLogId    = $this->seedLog($deletedPersonId, 301, $action['action_id'], $todayDate);
        $this->db->table('person')->where('id', $deletedPersonId)->update(['deleted_at' => date('Y-m-d H:i:s')]);

        // Ended parish affiliation
        $endedPersonId = $this->seedPerson(301);
        $endedLogId    = $this->seedLog($endedPersonId, 301, $action['action_id'], $todayDate);
        $this->db->table('parish_affiliation')->where('person_id', $endedPersonId)->update([
            'end_date' => date('Y-m-d', strtotime('-1 day')),
        ]);

        /** @var FollowupLogModel $logModel */
        $logModel = model(FollowupLogModel::class);
        $due = $logModel->getPendingDue($todayDate);

        $dueIds = array_map(static fn ($r) => (int) $r['id'], $due);

        $this->assertContains($activeLogId, $dueIds);
        $this->assertNotContains($deletedLogId, $dueIds, 'Soft-deleted person must be excluded.');
        $this->assertNotContains($endedLogId, $dueIds, 'Ended parish affiliation must be excluded.');
    }

    // -----------------------------------------------------------------------
    // Tests: Command Channel Dispatching & Status Transitions
    // -----------------------------------------------------------------------

    public function testSendFollowupsDispatchesEmailAndMarksSent(): void
    {
        $templateId = $this->seedTemplate(301, 'Welcome {first_name}', 'Hi {first_name}, thanks for visiting {parish_name}!');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $personId   = $this->seedPerson(301, [
            'first_name' => 'Alice',
            'email'      => 'alice@example.com',
        ]);
        $logId = $this->seedLog($personId, 301, $action['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));
        $cmd->run(['parish' => 301]);

        $log = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log['status']);
        $this->assertNotEmpty($log['executed_at']);

        // Check email was sent via mock
        $this->assertCount(1, $this->mockEmail->sentMessages);
        $sent = $this->mockEmail->sentMessages[0];
        $this->assertContains('alice@example.com', (array) $sent['to']);
        $this->assertSame('Welcome Alice', $sent['subject']);
        $this->assertStringContainsString('thanks for visiting St. Jude Parish', (string) $sent['body']);
    }

    public function testSendFollowupsEnqueuesSmsAndMarksSent(): void
    {
        $templateId = $this->seedTemplate(301, 'SMS', 'Hello {first_name}!', 'text');
        $action     = $this->seedSequenceAndAction(301, 'text', $templateId);
        $personId   = $this->seedPerson(301, [
            'first_name' => 'Bob',
            'phone'      => '8095551234',
        ]);
        $logId = $this->seedLog($personId, 301, $action['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));
        $cmd->run(['parish' => 301]);

        $log = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log['status']);
        $this->assertNotEmpty($log['executed_at']);

        // Check SMS was enqueued in sms_queue table
        $smsQueue = model(SmsQueueModel::class);
        $queued = $smsQueue->where('parish_id', 301)->first();
        $this->assertNotNull($queued);
        $this->assertSame('8095551234', $queued['to_number']);
        $this->assertSame('Hello Bob!', $queued['body']);
        $this->assertSame('pending', $queued['status']);
    }

    public function testSendFollowupsHandlesOptOutAsSkipped(): void
    {
        $templateId = $this->seedTemplate(301, 'Welcome', 'Welcome!');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $personId   = $this->seedPerson(301, [
            'first_name'         => 'OptedOut',
            'email'              => 'optout@example.com',
            'opted_out_channels' => json_encode(['email', 'text']),
        ]);
        $logId = $this->seedLog($personId, 301, $action['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));
        $cmd->run(['parish' => 301]);

        $log = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertSame('skipped', $log['status']);
        $this->assertNotEmpty($log['executed_at']);

        $meta = json_decode($log['response_metadata'] ?? '{}', true);
        $this->assertTrue($meta['skipped'] ?? false);
        $this->assertSame('opted_out', $meta['reason'] ?? null);

        // Verify email was not sent
        $this->assertCount(0, $this->mockEmail->sentMessages);
    }

    public function testSendFollowupsHandlesMissingContactAsFailed(): void
    {
        $templateId = $this->seedTemplate(301, 'Welcome', 'Welcome!');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $personId   = $this->seedPerson(301, [
            'first_name' => 'NoEmail',
            'email'      => '', // Missing email
        ]);
        $logId = $this->seedLog($personId, 301, $action['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));
        $cmd->run(['parish' => 301]);

        $log = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log['status']);

        $meta = json_decode($log['response_metadata'] ?? '{}', true);
        $this->assertStringContainsString('Visitor has no email address', $meta['error'] ?? '');
    }

    // -----------------------------------------------------------------------
    // Tests: Idempotency & Parish Scoping & Dry-Run
    // -----------------------------------------------------------------------

    public function testSendFollowupsIdempotencySecondRunIsNoOp(): void
    {
        $templateId = $this->seedTemplate(301, 'Subject', 'Body');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $personId   = $this->seedPerson(301, ['email' => 'idempotent@example.com']);
        $logId      = $this->seedLog($personId, 301, $action['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));

        // First run
        $cmd->run(['parish' => 301]);
        $this->assertCount(1, $this->mockEmail->sentMessages);

        $logFirst = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertSame('sent', $logFirst['status']);
        $firstExecutedAt = $logFirst['executed_at'];

        // Second run
        $cmd->run(['parish' => 301]);

        // Still only 1 sent email; log status and executed_at unmodified
        $this->assertCount(1, $this->mockEmail->sentMessages, 'Second run must not send duplicate emails.');
        $logSecond = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertSame($firstExecutedAt, $logSecond['executed_at']);
    }

    public function testSendFollowupsParishScoping(): void
    {
        $template301 = $this->seedTemplate(301, 'Parish 301 Subject', 'Body');
        $action301   = $this->seedSequenceAndAction(301, 'email', $template301);
        $person301   = $this->seedPerson(301, ['email' => 'parish301@example.com']);
        $log301      = $this->seedLog($person301, 301, $action301['action_id'], date('Y-m-d'));

        $template302 = $this->seedTemplate(302, 'Parish 302 Subject', 'Body');
        $action302   = $this->seedSequenceAndAction(302, 'email', $template302);
        $person302   = $this->seedPerson(302, ['email' => 'parish302@example.com']);
        $log302      = $this->seedLog($person302, 302, $action302['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));
        $cmd->run(['parish' => 301]);

        // Parish 301 must be sent
        $row301 = $this->db->table('followup_log')->where('id', $log301)->get()->getRowArray();
        $this->assertSame('sent', $row301['status']);

        // Parish 302 must remain pending
        $row302 = $this->db->table('followup_log')->where('id', $log302)->get()->getRowArray();
        $this->assertSame('pending', $row302['status']);
        $this->assertNull($row302['executed_at']);
    }

    public function testSendFollowupsDryRunDoesNotMutateDb(): void
    {
        $templateId = $this->seedTemplate(301, 'Subject', 'Body');
        $action     = $this->seedSequenceAndAction(301, 'email', $templateId);
        $personId   = $this->seedPerson(301, ['email' => 'dryrun@example.com']);
        $logId      = $this->seedLog($personId, 301, $action['action_id'], date('Y-m-d'));

        $cmd = new SendFollowups(service('logger'), service('commands'));
        $cmd->run(['parish' => 301, 'dry-run' => null]);

        $log = $this->db->table('followup_log')->where('id', $logId)->get()->getRowArray();
        $this->assertSame('pending', $log['status']);
        $this->assertNull($log['executed_at']);
        $this->assertCount(0, $this->mockEmail->sentMessages, 'Dry-run must not send emails.');
    }
}
