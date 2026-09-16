<?php

use App\Models\VestryMeetingModel;
use App\Models\VestryMotionModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Regression guard for C6: vestry meeting and motion lookups must be scoped
 * by parish. A user from parish A must not be able to view, create motions on,
 * vote on, or close motions belonging to parish B by submitting foreign IDs.
 *
 * @internal
 */
final class VestryParishScopingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTestSchema();
        $this->db = \Config\Database::connect();
    }

    protected function tearDown(): void
    {
        $forge = \Config\Database::forge();
        $forge->dropTable('vestry_vote', true);
        $forge->dropTable('vestry_motion', true);
        $forge->dropTable('vestry_meeting', true);
        parent::tearDown();
    }

    public function testGetScopedMeetingReturnsMeetingForCorrectParish(): void
    {
        $parishA = 5001;
        $parishB = 5002;

        $meetingAId = $this->insertMeeting($parishA);
        $meetingBId = $this->insertMeeting($parishB);

        $model = new VestryMeetingModel();

        // Parish A can see its own meeting.
        $result = $model->getScopedMeeting($meetingAId, $parishA);
        $this->assertNotNull($result);
        $this->assertSame($parishA, (int) $result['parish_id']);

        // Parish A cannot see parish B's meeting.
        $crossResult = $model->getScopedMeeting($meetingBId, $parishA);
        $this->assertNull($crossResult, 'Parish A must not access parish B meeting via getScopedMeeting.');

        // Parish B cannot see parish A's meeting.
        $crossResult2 = $model->getScopedMeeting($meetingAId, $parishB);
        $this->assertNull($crossResult2, 'Parish B must not access parish A meeting via getScopedMeeting.');
    }

    public function testGetScopedMotionReturnsMotionOnlyForOwningParish(): void
    {
        $parishA = 6001;
        $parishB = 6002;

        $meetingAId = $this->insertMeeting($parishA);
        $meetingBId = $this->insertMeeting($parishB);

        $motionAId = $this->insertMotion($meetingAId);
        $motionBId = $this->insertMotion($meetingBId);

        $model = new VestryMotionModel();

        // Parish A can access its own motion.
        $result = $model->getScopedMotion($motionAId, $parishA);
        $this->assertNotNull($result);
        $this->assertSame($motionAId, (int) $result['id']);

        // Parish A cannot access parish B's motion.
        $crossResult = $model->getScopedMotion($motionBId, $parishA);
        $this->assertNull($crossResult, 'Parish A must not access parish B motion via getScopedMotion.');

        // Parish B cannot access parish A's motion.
        $crossResult2 = $model->getScopedMotion($motionAId, $parishB);
        $this->assertNull($crossResult2, 'Parish B must not access parish A motion via getScopedMotion.');
    }

    public function testGetScopedMotionReturnsNullForNonexistentMotion(): void
    {
        $model = new VestryMotionModel();
        $this->assertNull($model->getScopedMotion(999999, 7001));
    }

    public function testGetScopedMeetingReturnsNullForNonexistentMeeting(): void
    {
        $model = new VestryMeetingModel();
        $this->assertNull($model->getScopedMeeting(999999, 7001));
    }

    private function rebuildTestSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->dropTable('vestry_vote', true);
        $forge->dropTable('vestry_motion', true);
        $forge->dropTable('vestry_meeting', true);

        $forge->addField([
            'id'           => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'parish_id'    => ['type' => 'INTEGER', 'unsigned' => true],
            'meeting_date'  => ['type' => 'DATE'],
            'meeting_type'  => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'regular'],
            'minutes_text'  => ['type' => 'TEXT', 'null' => true],
            'created_by'    => ['type' => 'INTEGER', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('parish_id');
        $forge->createTable('vestry_meeting', true);

        $forge->addField([
            'id'                    => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'meeting_id'            => ['type' => 'INTEGER', 'unsigned' => true],
            'title'                 => ['type' => 'VARCHAR', 'constraint' => 255],
            'body_text'             => ['type' => 'TEXT', 'null' => true],
            'moved_by_person_id'    => ['type' => 'INTEGER', 'null' => true],
            'seconded_by_person_id' => ['type' => 'INTEGER', 'null' => true],
            'status'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'open'],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('meeting_id');
        $forge->createTable('vestry_motion', true);

        $forge->addField([
            'id'        => ['type' => 'INTEGER', 'auto_increment' => true, 'unsigned' => true],
            'motion_id' => ['type' => 'INTEGER', 'unsigned' => true],
            'user_id'   => ['type' => 'INTEGER', 'unsigned' => true],
            'vote'      => ['type' => 'VARCHAR', 'constraint' => 10],
            'voted_at'  => ['type' => 'DATETIME'],
        ]);
        $forge->addKey('id', true);
        $forge->addKey('motion_id');
        $forge->addKey('user_id');
        $forge->addUniqueKey(['motion_id', 'user_id']);
        $forge->createTable('vestry_vote', true);
    }

    private function insertMeeting(int $parishId): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('vestry_meeting')->insert([
            'parish_id'    => $parishId,
            'meeting_date'  => date('Y-m-d'),
            'meeting_type'  => 'regular',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return (int) $this->db->insertID();
    }

    private function insertMotion(int $meetingId): int
    {
        $now = date('Y-m-d H:i:s');

        $this->db->table('vestry_motion')->insert([
            'meeting_id'  => $meetingId,
            'title'       => 'Test Motion for meeting ' . $meetingId,
            'status'      => 'open',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        return (int) $this->db->insertID();
    }
}
