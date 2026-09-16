<?php

namespace App\Models;

use CodeIgniter\Model;

class VestryVoteModel extends Model
{
    protected $table            = 'vestry_vote';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'motion_id',
        'user_id',
        'vote',
        'voted_at',
    ];

    protected $useTimestamps = false;

    protected array $casts = [
        'motion_id' => '?int',
        'user_id'   => '?int',
    ];

    /**
     * Get all votes for a motion.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVotesForMotion(int $motionId): array
    {
        return $this->where('motion_id', $motionId)
            ->orderBy('voted_at', 'ASC')
            ->findAll();
    }

    /**
     * Tally votes for a motion.
     *
     * M6: Uses SQL SUM instead of loading all vote rows and counting in PHP.
     *
     * @return array{yea: int, nay: int, abstain: int, total: int}
     */
    public function tallyVotes(int $motionId): array
    {
        $result = $this->select(
            "SUM(CASE WHEN vote = 'yea' THEN 1 ELSE 0 END) AS yea,
             SUM(CASE WHEN vote = 'nay' THEN 1 ELSE 0 END) AS nay,
             SUM(CASE WHEN vote = 'abstain' THEN 1 ELSE 0 END) AS abstain,
             COUNT(*) AS total"
        )
            ->where('motion_id', $motionId)
            ->get()
            ->getRowArray();

        if ($result === null) {
            return ['yea' => 0, 'nay' => 0, 'abstain' => 0, 'total' => 0];
        }

        return [
            'yea'     => (int) ($result['yea'] ?? 0),
            'nay'     => (int) ($result['nay'] ?? 0),
            'abstain' => (int) ($result['abstain'] ?? 0),
            'total'   => (int) ($result['total'] ?? 0),
        ];
    }

    /**
     * Record a vote (upsert on the unique motion_id + user_id constraint).
     */
    public function recordVote(int $motionId, int $userId, string $vote): bool
    {
        if (!in_array($vote, ['yea', 'nay', 'abstain'], true)) {
            return false;
        }

        $existing = $this->where('motion_id', $motionId)
            ->where('user_id', $userId)
            ->first();

        $now = date('Y-m-d H:i:s');

        if ($existing) {
            return $this->update((int) $existing['id'], [
                'vote' => $vote,
                'voted_at' => $now,
            ]) !== false;
        }

        return $this->insert([
            'motion_id' => $motionId,
            'user_id' => $userId,
            'vote' => $vote,
            'voted_at' => $now,
        ]) !== false;
    }
}
