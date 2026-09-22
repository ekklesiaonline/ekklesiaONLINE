<?php

use App\Models\MessageCampaignModel;
use App\Models\MessageRecipientModel;
use App\Services\BulkMessageService;
use App\Services\MessagingService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * T3.6: bulk messaging — audience resolution, compliance skips, dispatch.
 *
 * @internal
 */
final class BulkMessageServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = true;
    protected $refresh = true;

    private BulkMessageService $service;
    private FakeMessagingService $messenger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messenger = new FakeMessagingService();
        $this->service = new BulkMessageService(null, null, null, $this->messenger);

        // Canonical-schema tables live outside the migration set, so
        // DatabaseTestTrait::refresh does not truncate them — rows seeded by
        // other test classes would leak into audience resolution.
        foreach (['person', 'parish_affiliation', 'user_person', 'users',
            'small_group', 'group_membership', 'team', 'team_member',
            'message_campaign', 'message_recipient', 'notification',
            'notification_recipient', 'sms_queue', 'parish'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->emptyTable();
            }
        }

        $this->seedParish(301);
        session()->set('selected_parish_id', 301);
    }

    // --------------------------------------------------------------
    // Audience resolution
    // --------------------------------------------------------------

    public function testStatusAudienceResolvesParishPersons(): void
    {
        $member = $this->seedMember(301, 'member');
        $this->seedMember(301, 'visitor');
        $this->seedParish(302);
        $this->seedMember(302, 'member');

        $recipients = $this->service->resolveAudience(301, ['statuses' => ['member']]);

        $this->assertCount(1, $recipients);
        $this->assertSame($member, $recipients[0]['person_id']);
    }

    public function testGroupAudienceResolvesActiveMembers(): void
    {
        $personId = $this->seedMember(301, 'member');
        $inactive = $this->seedMember(301, 'member');
        $groupId = $this->seedGroup(301);
        $this->seedGroupMembership($groupId, $personId, 'active');
        $this->seedGroupMembership($groupId, $inactive, 'inactive');

        $recipients = $this->service->resolveAudience(301, ['group_ids' => [$groupId]]);

        $this->assertCount(1, $recipients);
        $this->assertSame($personId, $recipients[0]['person_id']);
    }

    public function testTeamAudienceResolvesPersonAndLegacyUserRows(): void
    {
        $personId = $this->seedMember(301, 'member');
        $legacyUser = $this->seedMember(301, 'member'); // team_member row keyed by user_id
        $userId = $this->seedUserPerson($legacyUser);
        $teamId = $this->seedTeam(301);

        $this->db->table('team_member')->insert(['team_id' => $teamId, 'person_id' => $personId]);
        $this->db->table('team_member')->insert(['team_id' => $teamId, 'user_id' => $userId]);

        $recipients = $this->service->resolveAudience(301, ['team_ids' => [$teamId]]);
        $ids = array_map(static fn (array $r): int => (int) $r['person_id'], $recipients);
        sort($ids);

        $this->assertSame([$personId, $legacyUser], $ids);
    }

    public function testGuestAudienceIncludesUnconvertedGuests(): void
    {
        $guestPersonId = $this->seedGuestPerson(301, 'guest@example.test');
        $this->seedGuestPerson(301, 'converted@example.test', date('Y-m-d H:i:s'));

        $recipients = $this->service->resolveAudience(301, ['include_guests' => true]);

        $this->assertCount(1, $recipients);
        $this->assertSame($guestPersonId, $recipients[0]['person_id']);
        $this->assertSame('guest@example.test', $recipients[0]['email']);
    }

    public function testDuplicatePersonAcrossSelectorsResolvesOnce(): void
    {
        $personId = $this->seedMember(301, 'member');
        $groupId = $this->seedGroup(301);
        $this->seedGroupMembership($groupId, $personId, 'active');

        $recipients = $this->service->resolveAudience(301, [
            'statuses'  => ['member'],
            'group_ids' => [$groupId],
        ]);

        $this->assertCount(1, $recipients);
    }

    public function testCrossParishGroupIdsDoNotLeakMembers(): void
    {
        $this->seedParish(302);
        $outsider = $this->seedMember(302, 'member');
        $foreignGroup = $this->seedGroup(302);
        $this->seedGroupMembership($foreignGroup, $outsider, 'active');

        $recipients = $this->service->resolveAudience(301, ['group_ids' => [$foreignGroup]]);

        $this->assertCount(0, $recipients);
    }

    // --------------------------------------------------------------
    // Compliance skips
    // --------------------------------------------------------------

    public function testOptedOutRecipientIsSkipped(): void
    {
        $personId = $this->seedMember(301, 'member', ['opted_out_channels' => '["email"]']);

        $draft = $this->service->createDraft(301, 'email', 'Hi', 'Body', ['statuses' => ['member']], null);
        $this->assertTrue($draft['ok'], json_encode($draft));

        $row = $this->db->table('message_recipient')
            ->where('campaign_id', $draft['id'])
            ->where('person_id', $personId)
            ->get()->getRowArray();

        $this->assertSame('skipped', $row['status']);
        $this->assertSame('opted_out', $row['skip_reason']);
    }

    public function testMissingAddressIsSkipped(): void
    {
        $personId = $this->seedMember(301, 'member', ['phone' => null]);

        $draft = $this->service->createDraft(301, 'sms', '', 'Body', ['statuses' => ['member']], null);
        $this->assertTrue($draft['ok']);

        $row = $this->db->table('message_recipient')
            ->where('campaign_id', $draft['id'])
            ->where('person_id', $personId)
            ->get()->getRowArray();

        $this->assertSame('skipped', $row['status']);
        $this->assertSame('no_address', $row['skip_reason']);
    }

    public function testNotificationSkipsPersonWithoutUser(): void
    {
        $personId = $this->seedMember(301, 'member');

        $draft = $this->service->createDraft(301, 'notification', 'Hi', 'Body', ['statuses' => ['member']], null);
        $this->assertTrue($draft['ok']);

        $row = $this->db->table('message_recipient')
            ->where('campaign_id', $draft['id'])
            ->where('person_id', $personId)
            ->get()->getRowArray();

        $this->assertSame('skipped', $row['status']);
        $this->assertSame('no_user', $row['skip_reason']);
    }

    // --------------------------------------------------------------
    // Draft + send lifecycle
    // --------------------------------------------------------------

    public function testCreateDraftPersistsCampaignAndRecipients(): void
    {
        $this->seedMember(301, 'member');
        $this->seedMember(301, 'member');

        $result = $this->service->createDraft(301, 'email', 'Hello', 'Body {first_name}', ['statuses' => ['member']], null);

        $this->assertTrue($result['ok']);

        $campaign = $this->db->table('message_campaign')->where('id', $result['id'])->get()->getRowArray();
        $this->assertSame('draft', $campaign['status']);
        $this->assertSame('email', $campaign['channel']);
        $this->assertSame(2, (int) $campaign['total_recipients']);

        $this->assertSame(2, (int) $this->db->table('message_recipient')->where('campaign_id', $result['id'])->countAllResults());
    }

    public function testCreateDraftRejectsEmptyAudience(): void
    {
        $result = $this->service->createDraft(301, 'email', 'Hi', 'Body', ['statuses' => ['member']], null);

        $this->assertFalse($result['ok']);
        $this->assertSame('empty_audience', $result['code']);
    }

    public function testSendDispatchesEmailPerRecipient(): void
    {
        $personId = $this->seedMember(301, 'member');
        $draft = $this->service->createDraft(301, 'email', 'Hi {first_name}', 'Dear {name}, from {parish_name}', ['statuses' => ['member']], null);

        $result = $this->service->send($draft['id'], 301);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $this->messenger->emails);
        $this->assertSame('Hi Test', $this->messenger->emails[0]['subject']);
        $this->assertStringContainsString('Dear Test Person', $this->messenger->emails[0]['text']);

        $row = $this->db->table('message_recipient')->where('campaign_id', $draft['id'])->where('person_id', $personId)->get()->getRowArray();
        $this->assertSame('sent', $row['status']);

        $campaign = $this->db->table('message_campaign')->where('id', $draft['id'])->get()->getRowArray();
        $this->assertSame('sent', $campaign['status']);
        $this->assertSame(1, (int) $campaign['sent_count']);
    }

    public function testSendQueuesSmsAndLinksQueueRow(): void
    {
        $this->seedMember(301, 'member');
        $draft = $this->service->createDraft(301, 'sms', '', 'Text body', ['statuses' => ['member']], null);

        $result = $this->service->send($draft['id'], 301);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $this->messenger->sms);

        $row = $this->db->table('message_recipient')->where('campaign_id', $draft['id'])->get()->getRowArray();
        $this->assertSame('queued', $row['status']);
        $this->assertSame(42, (int) $row['queue_id']);
    }

    public function testSendNotificationWritesNotificationRows(): void
    {
        $personId = $this->seedMember(301, 'member');
        $this->seedUserPerson($personId);
        $draft = $this->service->createDraft(301, 'notification', 'Notice', 'Body', ['statuses' => ['member']], null);

        $result = $this->service->send($draft['id'], 301);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, (int) $this->db->table('notification')->countAllResults());
        $this->assertSame(1, (int) $this->db->table('notification_recipient')->countAllResults());

        $row = $this->db->table('message_recipient')->where('campaign_id', $draft['id'])->get()->getRowArray();
        $this->assertSame('sent', $row['status']);
    }

    public function testSendRejectsNonDraft(): void
    {
        $this->seedMember(301, 'member');
        $draft = $this->service->createDraft(301, 'email', 'Hi', 'Body', ['statuses' => ['member']], null);
        $this->service->send($draft['id'], 301);

        $second = $this->service->send($draft['id'], 301);

        $this->assertFalse($second['ok']);
        $this->assertSame('already_sent', $second['code']);
        $this->assertCount(1, $this->messenger->emails);
    }

    public function testSendRejectsForeignParish(): void
    {
        $this->seedMember(301, 'member');
        $draft = $this->service->createDraft(301, 'email', 'Hi', 'Body', ['statuses' => ['member']], null);

        $result = $this->service->send($draft['id'], 302);

        $this->assertFalse($result['ok']);
        $this->assertSame('not_found', $result['code']);
        $this->assertCount(0, $this->messenger->emails);
    }

    public function testCancelOnlyWorksOnDrafts(): void
    {
        $this->seedMember(301, 'member');
        $draft = $this->service->createDraft(301, 'email', 'Hi', 'Body', ['statuses' => ['member']], null);

        $this->assertTrue($this->service->cancel($draft['id'], 301));
        $this->assertFalse($this->service->cancel($draft['id'], 301));
    }

    public function testRenderSubstitutesTokens(): void
    {
        $out = $this->service->render(
            'Hi {first_name} {last_name} ({name}) at {parish_name}',
            ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'name' => 'Ada Lovelace'],
            'St. Mary'
        );

        $this->assertSame('Hi Ada Lovelace (Ada Lovelace) at St. Mary', $out);
    }

    // --------------------------------------------------------------
    // Seeds
    // --------------------------------------------------------------

    private function seedParish(int $id): void
    {
        if ($this->db->table('parish')->where('id', $id)->countAllResults() > 0) {
            return;
        }
        $this->db->table('parish')->insert([
            'id' => $id, 'name' => 'Parish ' . $id,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedMember(int $parishId, string $status, array $personOverrides = []): int
    {
        $this->db->table('person')->insert(array_merge([
            'first_name' => 'Test',
            'last_name'  => 'Person',
            'email'      => 'person' . uniqid() . '@example.test',
            'phone'      => '+1555' . random_int(1000000, 9999999),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $personOverrides));
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'parish_id'         => $parishId,
            'person_id'         => $personId,
            'affiliation_type'  => 'member',
            'membership_status' => $status,
            'is_primary'        => 1,
            'source'            => 'system',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    private function seedGuestPerson(int $parishId, string $email, ?string $convertedAt = null): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Guest',
            'last_name'  => 'Person',
            'email'      => $email,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $personId = (int) $this->db->insertID();

        $this->db->table('parish_affiliation')->insert([
            'parish_id'              => $parishId,
            'person_id'              => $personId,
            'affiliation_type'       => 'guest',
            'membership_status'      => 'visitor',
            'converted_to_member_at' => $convertedAt,
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ]);

        return $personId;
    }

    private function seedUserPerson(int $personId): int
    {
        $this->db->table('users')->insert([
            'email'    => 'user' . uniqid() . '@example.test',
            'username' => 'u' . uniqid(),
        ]);
        $userId = (int) $this->db->insertID();
        $this->db->table('user_person')->insert([
            'user_id' => $userId, 'person_id' => $personId, 'is_primary' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $userId;
    }

    private function seedGroup(int $parishId): int
    {
        $this->db->table('small_group')->insert([
            'parish_id' => $parishId, 'name' => 'G' . uniqid(), 'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function seedGroupMembership(int $groupId, int $personId, string $status): void
    {
        $this->db->table('group_membership')->insert([
            'small_group_id' => $groupId, 'person_id' => $personId,
            'role' => 'member', 'status' => $status,
            'joined_on' => date('Y-m-d'),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedTeam(int $parishId): int
    {
        $this->db->table('team')->insert([
            'parish_id' => $parishId, 'name' => 'Team ' . uniqid(),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }
}

/**
 * Records dispatch calls instead of hitting SMTP/Twilio.
 */
class FakeMessagingService extends MessagingService
{
    /** @var array<int, array{to: string, subject: string, text: string}> */
    public array $emails = [];

    /** @var array<int, array{to: string, body: string}> */
    public array $sms = [];

    public function __construct()
    {
        // Skip parent wiring (SmsService / queue model) — nothing is sent.
    }

    public function sendSms(string $to, string $body, ?string $from = null): array
    {
        $this->sms[] = ['to' => $to, 'body' => $body];

        return ['success' => true, 'message_id' => 'fake', 'error' => ''];
    }

    public function sendEmail($to, string $subject, string $htmlBody, ?string $textBody = null, array $context = []): array
    {
        $this->emails[] = ['to' => is_array($to) ? implode(',', $to) : $to, 'subject' => $subject, 'text' => (string) $textBody];

        return ['success' => true, 'status' => 'sent', 'error' => ''];
    }

    public function queueSms(string $to, string $body, string $scheduledAt, ?string $from = null, ?int $parishId = null, int $maxAttempts = 3): array
    {
        $this->sms[] = ['to' => $to, 'body' => $body];

        return ['success' => true, 'queue_id' => 42, 'error' => ''];
    }
}
