<?php

namespace App\Models;

use CodeIgniter\Model;

class VestryMotionModel extends Model
{
    protected $table            = 'vestry_motion';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'meeting_id',
        'title',
        'body_text',
        'moved_by_person_id',
        'seconded_by_person_id',
        'status',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected array $casts = [
        'meeting_id'              => '?int',
        'moved_by_person_id'      => '?int',
        'seconded_by_person_id'   => '?int',
    ];

    /**
     * Get motions for a meeting, with vote tallies.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMotionsForMeeting(int $meetingId): array
    {
        $motions = $this->where('meeting_id', $meetingId)
            ->orderBy('created_at', 'ASC')
            ->findAll();

        $voteModel = model('VestryVoteModel');

        foreach ($motions as &$motion) {
            $motion['votes'] = $voteModel->getVotesForMotion((int) ($motion['id'] ?? 0));
            $motion['vote_tally'] = $voteModel->tallyVotes((int) ($motion['id'] ?? 0));
        }

        return $motions;
    }

    /**
     * Get a motion with its votes.
     *
     * @return array<string, mixed>|null
     */
    public function getMotionWithVotes(int $motionId): ?array
    {
        $motion = $this->find($motionId);

        if (!$motion) {
            return null;
        }

        $motion['votes'] = model('VestryVoteModel')->getVotesForMotion($motionId);
        $motion['vote_tally'] = model('VestryVoteModel')->tallyVotes($motionId);

        return $motion;
    }

    /**
     * Close a motion by tallying votes and setting the status.
     */
    public function closeMotion(int $motionId): array
    {
        $tally = model('VestryVoteModel')->tallyVotes($motionId);

        $status = 'failed';
        if ($tally['yea'] > $tally['nay']) {
            $status = 'passed';
        } elseif ($tally['yea'] === $tally['nay'] && $tally['yea'] > 0) {
            $status = 'tabled';
        }

        $this->update($motionId, ['status' => $status]);

        return ['status' => $status, 'tally' => $tally];
    }
}
