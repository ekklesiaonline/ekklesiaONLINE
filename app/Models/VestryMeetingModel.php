<?php

namespace App\Models;

use CodeIgniter\Model;

class VestryMeetingModel extends Model
{
    protected $table            = 'vestry_meeting';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'meeting_date',
        'meeting_type',
        'minutes_text',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected array $casts = [
        'parish_id'   => '?int',
        'created_by'   => '?int',
    ];

    /**
     * Get recent meetings for a parish.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getParishMeetings(int $parishId, int $limit = 20): array
    {
        return $this->where('parish_id', $parishId)
            ->orderBy('meeting_date', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get a meeting with its motions.
     *
     * @return array<string, mixed>|null
     */
    public function getMeetingWithMotions(int $meetingId): ?array
    {
        $meeting = $this->find($meetingId);

        if (!$meeting) {
            return null;
        }

        $meeting['motions'] = model('VestryMotionModel')->getMotionsForMeeting($meetingId);

        return $meeting;
    }

    /**
     * Get a meeting only if it belongs to the given parish.
     *
     * @return array<string, mixed>|null
     */
    public function getScopedMeeting(int $meetingId, int $parishId): ?array
    {
        return $this->where('id', $meetingId)
            ->where('parish_id', $parishId)
            ->first();
    }
}
