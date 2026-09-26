<?php

namespace Tests\Database;

use App\Models\FollowupLogModel;
use App\Services\FollowupCloneService;
use App\Services\FollowupSequenceService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\CanonicalTestSchema;

/**
 * Default-sequence resolution, global-default cloning, and dispatch metadata.
 *
 * @internal
 */
final class FollowupDefaultSequenceTest extends CIUnitTestCase
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
            'parish_affiliation',
            'parish_admin_primary',
            'person',
            'users',
            'parish',
            'diocese',
        ] as $table) {
            CanonicalTestSchema::reset($this->db, $table);
        }
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

    private function seedPerson(int $personId, int $parishId): void
    {
        $this->db->table('person')->insert([
            'id'         => $personId,
            'first_name' => 'Test',
            'last_name'  => 'Visitor',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('parish_affiliation')->insert([
            'person_id'         => $personId,
            'parish_id'         => $parishId,
            'membership_status' => 'visitor',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    private function seedSequence(int $parishId, bool $isDefault = false): int
    {
        $this->db->table('followup_sequence')->insert([
            'parish_id'  => $parishId,
            'name'       => $isDefault ? 'Default Sequence' : 'Optional Sequence',
            'is_active'  => 1,
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $sequenceId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id' => $sequenceId,
            'week_number' => 1,
            'day_offset'  => $isDefault ? 1 : 3,
            'channel'     => 'email',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        return $sequenceId;
    }

    public function testSchedulingUsesDefaultSequenceOnly(): void
    {
        $this->seedParish(401);
        $defaultSeq   = $this->seedSequence(401, true);
        $optionalSeq  = $this->seedSequence(401, false);
        $this->seedPerson(501, 401);

        $count = model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson(501, 401, '2026-09-20');

        $this->assertSame(1, $count);

        $rows = $this->db->table('followup_log')
            ->where('person_id', 501)
            ->get()
            ->getResultArray();

        $this->assertCount(1, $rows);
        $this->assertSame($defaultSeq, (int) $rows[0]['action_id']);
    }

    public function testNoDefaultSchedulesNothing(): void
    {
        $this->seedParish(401);
        $this->seedSequence(401, false);
        $this->seedPerson(501, 401);

        $count = model(FollowupLogModel::class)
            ->scheduleActiveSequencesForPerson(501, 401, '2026-09-20');

        $this->assertSame(0, $count);
    }

    public function testServiceFindsDefaultForParish(): void
    {
        $this->seedParish(401);
        $defaultSeq = $this->seedSequence(401, true);
        $this->seedSequence(401, false);

        $service = new FollowupSequenceService();
        $this->assertTrue($service->hasDefaultForParish(401));

        $default = $service->getDefaultForParish(401);
        $this->assertNotNull($default);
        $this->assertSame($defaultSeq, (int) $default['id']);
    }

    public function testSetDefaultForSequenceReplacesExistingDefault(): void
    {
        $this->seedParish(401);
        $first  = $this->seedSequence(401, true);
        $second = $this->seedSequence(401, false);

        $service = new FollowupSequenceService();
        $service->setDefaultForSequence($second, 401);

        $default = $service->getDefaultForParish(401);
        $this->assertNotNull($default);
        $this->assertSame($second, (int) $default['id']);

        $firstRow = $this->db->table('followup_sequence')->where('id', $first)->get()->getRowArray();
        $this->assertSame(0, (int) $firstRow['is_default']);
    }

    public function testCloneServiceCopiesGlobalDefaultIntoParish(): void
    {
        $this->seedParish(401);
        $this->seedParish(402);

        // Global default sequence with a template and action
        $this->db->table('followup_sequence')->insert([
            'parish_id'         => null,
            'name'              => 'Global Default',
            'is_active'         => 1,
            'is_global_default' => 1,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        $globalSeq = (int) $this->db->insertID();

        $this->db->table('followup_template')->insert([
            'parish_id' => null,
            'channel'   => 'email',
            'name'      => 'Global Welcome',
            'body_text' => 'Hello {first_name}',
            'variables' => '["first_name"]',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $templateId = (int) $this->db->insertID();

        $this->db->table('followup_action')->insert([
            'sequence_id' => $globalSeq,
            'week_number' => 1,
            'day_offset'  => 1,
            'channel'     => 'email',
            'template_id' => $templateId,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $cloneService = new FollowupCloneService();
        $newSeqId = $cloneService->cloneGlobalDefaultIntoParish(401);

        $this->assertNotNull($newSeqId);
        $this->assertGreaterThan(0, $newSeqId);

        $clone = $this->db->table('followup_sequence')->where('id', $newSeqId)->get()->getRowArray();
        $this->assertSame(401, (int) $clone['parish_id']);
        $this->assertSame(1, (int) $clone['is_default']);
        $this->assertSame(0, (int) $clone['is_global_default']);

        $actions = $this->db->table('followup_action')
            ->where('sequence_id', $newSeqId)
            ->get()
            ->getResultArray();
        $this->assertCount(1, $actions);

        $templates = $this->db->table('followup_template')
            ->where('parish_id', 401)
            ->get()
            ->getResultArray();
        $this->assertCount(1, $templates);
    }

    /**
     * Seed a global default sequence with one action per supplied channel.
     */
    private function seedGlobalDefaultWithChannels(array $channels): int
    {
        $this->db->table('followup_sequence')->insert([
            'parish_id'         => null,
            'name'              => 'Global Default',
            'is_active'         => 1,
            'is_global_default' => 1,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
        $globalSeq = (int) $this->db->insertID();

        foreach ($channels as $i => $channel) {
            $this->db->table('followup_action')->insert([
                'sequence_id'  => $globalSeq,
                'week_number'  => 1,
                'day_offset'   => $i + 1,
                'channel'      => $channel,
                'instructions' => $channel === 'manual' ? 'Follow up with {first_name}' : null,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        return $globalSeq;
    }

    private function seedPrimaryAdmin(int $parishId, int $userId): void
    {
        $this->db->table('users')->insert([
            'id'         => $userId,
            'username'   => 'primary' . $userId,
            'email'      => 'primary' . $userId . '@example.com',
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->table('parish_admin_primary')->insert([
            'parish_id'  => $parishId,
            'user_id'    => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testCloneResolvesHumanChannelAssigneeToPrimaryAdmin(): void
    {
        $this->seedParish(401);
        $this->seedPrimaryAdmin(401, 77);
        $this->seedGlobalDefaultWithChannels(['call', 'manual', 'email']);

        $newSeqId = (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);
        $this->assertNotNull($newSeqId);

        $actions = $this->db->table('followup_action')
            ->where('sequence_id', $newSeqId)
            ->orderBy('day_offset', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertSame(77, (int) $actions[0]['assigned_to']); // call
        $this->assertSame(77, (int) $actions[1]['assigned_to']); // manual
        $this->assertNull($actions[2]['assigned_to']);           // email
    }

    public function testCloneLeavesAssigneeNullWithoutPrimaryAdmin(): void
    {
        $this->seedParish(401);
        $this->seedGlobalDefaultWithChannels(['call', 'manual']);

        $newSeqId = (new FollowupCloneService())->cloneGlobalDefaultIntoParish(401);
        $this->assertNotNull($newSeqId);

        $actions = $this->db->table('followup_action')
            ->where('sequence_id', $newSeqId)
            ->get()
            ->getResultArray();

        foreach ($actions as $action) {
            $this->assertNull($action['assigned_to']);
        }
    }

    public function testCloningSameSourceIntoParishTwiceReusesClone(): void
    {
        $this->seedParish(401);
        $globalSeq = $this->seedGlobalDefaultWithChannels(['email']);

        $cloneService = new FollowupCloneService();
        $firstId      = $cloneService->cloneSequenceIntoParish($globalSeq, 401);
        $secondId     = $cloneService->cloneSequenceIntoParish($globalSeq, 401);

        $this->assertNotNull($firstId);
        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, (int) $this->db->table('followup_sequence')
            ->where('parish_id', 401)
            ->countAllResults());
    }

    public function testReusedCloneIsPromotedToDefaultWhenParishHasNone(): void
    {
        $this->seedParish(401);
        $globalSeq = $this->seedGlobalDefaultWithChannels(['email']);

        // An existing default means the first clone is adopted as a
        // non-default suggestion.
        $this->seedSequence(401, true);
        $cloneService = new FollowupCloneService();
        $cloneId      = $cloneService->cloneSequenceIntoParish($globalSeq, 401);

        $clone = $this->db->table('followup_sequence')->where('id', $cloneId)->get()->getRowArray();
        $this->assertSame(0, (int) $clone['is_default']);

        // Parish loses its default; re-cloning reuses the row and the
        // forced default applies to it.
        $this->db->table('followup_sequence')->where('parish_id', 401)->update(['is_default' => 0]);

        $reusedId = $cloneService->cloneUpstreamDefaultIntoParish(401);

        $this->assertSame($cloneId, $reusedId);
        $clone = $this->db->table('followup_sequence')->where('id', $cloneId)->get()->getRowArray();
        $this->assertSame(1, (int) $clone['is_default']);
    }

    public function testCloningSameSourceIntoDioceseTwiceReusesClone(): void
    {
        $this->db->table('diocese')->insert([
            'id'         => 10,
            'name'       => 'Test Diocese',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $globalSeq = $this->seedGlobalDefaultWithChannels(['email']);

        $cloneService = new FollowupCloneService();
        $firstId      = $cloneService->cloneSequenceIntoDiocese($globalSeq, 10);
        $secondId     = $cloneService->cloneSequenceIntoDiocese($globalSeq, 10);

        $this->assertNotNull($firstId);
        $this->assertSame($firstId, $secondId);
        $this->assertSame(1, (int) $this->db->table('followup_sequence')
            ->where('diocese_id', 10)
            ->countAllResults());
    }
}
