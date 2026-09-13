<?php

namespace App\Models;

use CodeIgniter\Model;

class EventRegistrationModel extends Model
{
    protected $table            = 'event_registration';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'event_id',
        'parish_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'status',
        'notes',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'event_id' => 'required|is_not_unique[event.id]',
        'parish_id' => 'required|is_not_unique[parish.id]',
        'status' => 'required|in_list[registered,checked_in,cancelled]',
    ];

    public function getEventRegistrations(int $eventId): array
    {
        return $this->db->table('event_registration er')
            ->select('er.*, p.first_name, p.last_name')
            ->join('profile p', 'p.user_id = er.user_id', 'left')
            ->where('er.event_id', $eventId)
            ->orderBy('er.created_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function registrationExistsForMember(int $eventId, int $userId): bool
    {
        return $this->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->where('status !=', 'cancelled')
            ->first() !== null;
    }

    public function hasActiveRegistrationForUser(int $eventId, int $userId): bool
    {
        return $this->where('event_id', $eventId)
            ->where('user_id', $userId)
            ->whereIn('status', ['registered', 'checked_in'])
            ->first() !== null;
    }
}
