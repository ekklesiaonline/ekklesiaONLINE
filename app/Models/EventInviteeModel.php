<?php

namespace App\Models;

use CodeIgniter\Model;

class EventInviteeModel extends Model
{
    protected $table            = 'event_invitee';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'event_id',
        'user_id',
        'created_at',
    ];

    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';

    protected $validationRules = [
        'event_id' => 'required|is_not_unique[event.id]',
        'user_id' => 'required|is_not_unique[users.id]',
    ];

    /**
     * @return array<int,int>
     */
    public function getInviteeUserIdsForEvent(int $eventId): array
    {
        $rows = $this->where('event_id', $eventId)->findAll();

        return array_values(array_map(static fn(array $row): int => (int) ($row['user_id'] ?? 0), $rows));
    }

    public function isUserInvited(int $eventId, int $userId): bool
    {
        return $this->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->first() !== null;
    }

    /**
     * @param array<int,int> $userIds
     */
    public function syncInvitees(int $eventId, array $userIds): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));

        $this->where('event_id', $eventId)->delete();

        if ($userIds === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($userIds as $userId) {
            $this->insert([
                'event_id' => $eventId,
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }
}
