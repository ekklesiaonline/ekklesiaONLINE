<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Models\VestryMotionModel;
use App\Models\VestryVoteModel;

/**
 * Database tests for vestry governance workflow — vote recording,
 * tallying, and motion closing.
 *
 * @internal
 */
final class VestryGovernanceDatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTestSchema();
        $this->db = \Config\Database::connect();
    }

    private function rebuildTestSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('vestry_vote', true);
        $forge->dropTable('vestry_motion', true);
        $forge->dropTable('vestry_meeting', true);

        // vestry_meeting
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'parish_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'meeting_date' => ['type' => 'DATE'],
            'meeting_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'regular'],
            'minutes_text' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INTEGER', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('parish_id');
        $forge->createTable('vestry_meeting', true);

        // vestry_motion
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'meeting_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 255],
            'body_text' => ['type' => 'TEXT', 'null' => true],
            'moved_by_person_id' => ['type' => 'INTEGER', 'null' => true],
            'seconded_by_person_id' => ['type' => 'INTEGER', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('meeting_id');
        $forge->createTable('vestry_motion', true);

        // vestry_vote
        $forge->addField([
            'id' => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'motion_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'user_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'vote' => ['type' => 'VARCHAR', 'constraint' => 10],
            'voted_at' => ['type' => 'DATETIME'],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('motion_id');
        $forge->addKey('user_id');
        $forge->addUniqueKey(['motion_id', 'user_id']);
        $forge->createTable('vestry_vote', true);
    }

    private function createMeetingAndMotion(string $motionTitle = 'Test Motion'): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('vestry_meeting')->insert([
            'parish_id' => 1,
            'meeting_date' => date('Y-m-d'),
            'meeting_type' => 'regular',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $meetingId = (int) $this->db->insertID();

        $this->db->table('vestry_motion')->insert([
            'meeting_id' => $meetingId,
            'title' => $motionTitle,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function cleanupMotion(int $motionId): void
    {
        $this->db->table('vestry_vote')->where('motion_id', $motionId)->delete();
        $this->db->table('vestry_motion')->where('id', $motionId)->delete();
        $motion = $this->db->table('vestry_motion')->where('id', $motionId)->get()->getRowArray();
        if ($motion) {
            $this->db->table('vestry_meeting')->where('id', $motion['meeting_id'])->delete();
        }
    }

    public function testRecordVoteAcceptsValidChoices(): void
    {
        $motionId = $this->createMeetingAndMotion('Vote Test Motion');

        $voteModel = new VestryVoteModel();
        $this->assertTrue($voteModel->recordVote($motionId, 99999, 'yea'));

        $tally = $voteModel->tallyVotes($motionId);
        $this->assertSame(1, $tally['yea']);
        $this->assertSame(0, $tally['nay']);
        $this->assertSame(1, $tally['total']);

        // Test upsert (changing vote)
        $this->assertTrue($voteModel->recordVote($motionId, 99999, 'nay'));
        $tally = $voteModel->tallyVotes($motionId);
        $this->assertSame(0, $tally['yea']);
        $this->assertSame(1, $tally['nay']);
        $this->assertSame(1, $tally['total']);

        $this->cleanupMotion($motionId);
    }

    public function testCloseMotionSetsPassedStatus(): void
    {
        $motionId = $this->createMeetingAndMotion('Pass Motion Test');

        $voteModel = new VestryVoteModel();
        $voteModel->recordVote($motionId, 10001, 'yea');
        $voteModel->recordVote($motionId, 10002, 'yea');
        $voteModel->recordVote($motionId, 10003, 'nay');

        $motionModel = new VestryMotionModel();
        $result = $motionModel->closeMotion($motionId);

        $this->assertSame('passed', $result['status']);
        $this->assertSame(2, $result['tally']['yea']);
        $this->assertSame(1, $result['tally']['nay']);

        $this->cleanupMotion($motionId);
    }

    public function testCloseMotionSetsFailedStatus(): void
    {
        $motionId = $this->createMeetingAndMotion('Fail Motion Test');

        $voteModel = new VestryVoteModel();
        $voteModel->recordVote($motionId, 20001, 'nay');
        $voteModel->recordVote($motionId, 20002, 'nay');
        $voteModel->recordVote($motionId, 20003, 'yea');

        $motionModel = new VestryMotionModel();
        $result = $motionModel->closeMotion($motionId);

        $this->assertSame('failed', $result['status']);

        $this->cleanupMotion($motionId);
    }

    public function testCloseMotionSetsTabledStatusOnTie(): void
    {
        $motionId = $this->createMeetingAndMotion('Tie Motion Test');

        $voteModel = new VestryVoteModel();
        $voteModel->recordVote($motionId, 30001, 'yea');
        $voteModel->recordVote($motionId, 30002, 'nay');

        $motionModel = new VestryMotionModel();
        $result = $motionModel->closeMotion($motionId);

        $this->assertSame('tabled', $result['status']);

        $this->cleanupMotion($motionId);
    }
}
