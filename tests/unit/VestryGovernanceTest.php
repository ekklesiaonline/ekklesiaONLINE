<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\VestryMeetingModel;
use App\Models\VestryMotionModel;
use App\Models\VestryVoteModel;

/**
 * Unit tests for vestry model configuration (no database required).
 *
 * @internal
 */
final class VestryGovernanceTest extends CIUnitTestCase
{
    public function testVestryMeetingModelHasCorrectTableAndCasts(): void
    {
        $model = new VestryMeetingModel();
        $this->assertSame('vestry_meeting', $model->table);
        $this->assertSame('array', $model->returnType);
        $this->assertTrue($model->useSoftDeletes);
        $this->assertArrayHasKey('parish_id', $model->casts);
        $this->assertSame('?int', $model->casts['parish_id']);
    }

    public function testVestryMotionModelHasCorrectTableAndCasts(): void
    {
        $model = new VestryMotionModel();
        $this->assertSame('vestry_motion', $model->table);
        $this->assertTrue($model->useSoftDeletes);
        $this->assertArrayHasKey('meeting_id', $model->casts);
        $this->assertSame('?int', $model->casts['meeting_id']);
    }

    public function testVestryVoteModelHasCorrectTableAndCasts(): void
    {
        $model = new VestryVoteModel();
        $this->assertSame('vestry_vote', $model->table);
        $this->assertFalse($model->useSoftDeletes);
        $this->assertArrayHasKey('motion_id', $model->casts);
        $this->assertSame('?int', $model->casts['motion_id']);
    }

    public function testRecordVoteRejectsInvalidChoice(): void
    {
        $model = new VestryVoteModel();
        $this->assertFalse($model->recordVote(1, 1, 'invalid'));
    }
}
