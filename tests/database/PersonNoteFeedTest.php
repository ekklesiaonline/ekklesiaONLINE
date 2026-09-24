<?php

use App\Services\PersonNoteService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * PersonNoteService feed coverage.
 *
 * The visitor record's Notes tab aggregates staff notes (person_note),
 * intake/visit notes (person_visit.notes), and follow-up outcome notes
 * (followup_log.notes / call_task.notes) into one chronological feed.
 * Task notes that merely mirror the linked log's notes are deduped.
 *
 * @internal
 */
final class PersonNoteFeedTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        CanonicalTestSchema::ensure($this->db);
        foreach ([
            'person_note',
            'followup_log',
            'followup_action',
            'call_task',
            'person_visit',
            'parish_affiliation',
            'person',
            'profile',
            'auth_identities',
            'users',
            'parish',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
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

    private function seedPerson(int $parishId = 301): int
    {
        $this->db->table('person')->insert([
            'first_name' => 'Noted',
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

    public function testAddNotePersistsAndFeedsBack(): void
    {
        $personId = $this->seedPerson();
        $userId   = $this->seedUser('scribe');
        $service  = new PersonNoteService($this->db);

        $id = $service->addNote($personId, 301, $userId, 'Prefers evening services.');

        $this->assertNotFalse($id);

        $feed = $service->feedForPerson($personId, 301);
        $this->assertCount(1, $feed);
        $this->assertSame('note', $feed[0]['source']);
        $this->assertSame('Prefers evening services.', $feed[0]['body']);
        $this->assertSame('scribe', $feed[0]['author']);
    }

    public function testFeedAggregatesVisitAndFollowupNotes(): void
    {
        $personId = $this->seedPerson();

        $this->db->table('followup_action')->insert([
            'sequence_id'  => 1,
            'week_number'  => 1,
            'day_offset'   => 1,
            'channel'      => 'call',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $actionId = (int) $this->db->insertID();

        $this->db->table('person_visit')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'visit_date'     => '2026-09-20',
            'visit_type'     => 'first',
            'attended_event' => 'Sunday Service',
            'notes'          => 'Intake: came with family.',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'action_id'      => $actionId,
            'scheduled_date' => '2026-09-22',
            'executed_at'    => '2026-09-22 18:30:00',
            'status'         => 'sent',
            'notes'          => 'Called — left voicemail.',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $feed = (new PersonNoteService($this->db))->feedForPerson($personId, 301);

        $this->assertCount(2, $feed);
        // Newest first: the call outcome (Sep 22) precedes the intake note (Sep 20)
        $this->assertSame('followup', $feed[0]['source']);
        $this->assertSame('call', $feed[0]['meta']['channel']);
        $this->assertSame('Called — left voicemail.', $feed[0]['body']);
        $this->assertSame('visit', $feed[1]['source']);
        $this->assertSame('Sunday Service', $feed[1]['meta']['service']);
    }

    public function testFeedDedupesTaskNotesMirroringLogNotes(): void
    {
        $personId = $this->seedPerson();

        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'scheduled_date' => '2026-09-22',
            'status'         => 'sent',
            'notes'          => 'Shared note text',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $logId = (int) $this->db->insertID();

        $this->db->table('call_task')->insert([
            'parish_id'      => 301,
            'person_id'      => $personId,
            'log_id'         => $logId,
            'task_type'      => 'call',
            'status'         => 'completed',
            'notes'          => 'Shared note text',
            'scheduled_date' => '2026-09-22',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $feed = (new PersonNoteService($this->db))->feedForPerson($personId, 301);

        $this->assertCount(1, $feed);
        $this->assertSame('followup', $feed[0]['source']);
    }

    public function testFeedKeepsDistinctTaskNotes(): void
    {
        $personId = $this->seedPerson();

        $this->db->table('followup_log')->insert([
            'person_id'      => $personId,
            'parish_id'      => 301,
            'scheduled_date' => '2026-09-22',
            'status'         => 'sent',
            'notes'          => null,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $logId = (int) $this->db->insertID();

        $this->db->table('call_task')->insert([
            'parish_id'      => 301,
            'person_id'      => $personId,
            'log_id'         => $logId,
            'task_type'      => 'call',
            'status'         => 'completed',
            'notes'          => 'Queue note: visitor asked for a call back.',
            'scheduled_date' => '2026-09-22',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $feed = (new PersonNoteService($this->db))->feedForPerson($personId, 301);

        $this->assertCount(1, $feed);
        $this->assertSame('task', $feed[0]['source']);
        $this->assertSame('Queue note: visitor asked for a call back.', $feed[0]['body']);
    }

    public function testFeedIsParishScoped(): void
    {
        $this->seedParish(302);
        $personId = $this->seedPerson(301);
        $userId   = $this->seedUser('other');
        $service  = new PersonNoteService($this->db);

        $service->addNote($personId, 302, $userId, 'Wrong-parish note');

        $this->assertCount(0, $service->feedForPerson($personId, 301));
        $this->assertCount(1, $service->feedForPerson($personId, 302));
    }
}
