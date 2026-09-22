<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * CallTaskModel
 *
 * Represents a manual phone-call follow-up task created when the
 * followup dispatcher encounters a 'call' channel action.
 * Volunteers use the admin view to see pending calls and mark them done.
 */
class CallTaskModel extends Model
{
    protected $table            = 'call_task';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'person_id',
        'log_id',
        'assigned_to',
        'phone',
        'notes',
        'status',
        'scheduled_date',
        'completed_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'parish_id'      => 'required|is_not_unique[parish.id]',
        'person_id'      => 'permit_empty|is_not_unique[person.id]',
        'scheduled_date' => 'required|valid_date',
        'status'         => 'permit_empty|in_list[pending,completed,cancelled]',
        'log_id'         => 'permit_empty|is_not_unique[followup_log.id]',
        'assigned_to'    => 'permit_empty|is_not_unique[users.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // ------------------------------------------------------------------
    // Queries
    // ------------------------------------------------------------------

    /**
     * Return all pending call tasks for a parish, enriched with visitor name.
     */
    public function getPendingForParish(int $parishId): array
    {
        return $this->db->table('call_task ct')
            ->select('ct.id, ct.person_id, ct.log_id, ct.assigned_to, ct.phone, ct.notes, ct.status, ct.scheduled_date, ct.created_at, ct.updated_at, ct.parish_id, p.first_name, p.last_name')
            ->join('person p', 'p.id = ct.person_id', 'left')
            ->where('ct.parish_id', $parishId)
            ->where('ct.status', 'pending')
            ->orderBy('ct.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return all call tasks for a parish (all statuses), newest first.
     */
    public function getAllForParish(int $parishId, int $limit = 100): array
    {
        return $this->db->table('call_task ct')
            ->select('ct.id, ct.person_id, ct.log_id, ct.assigned_to, ct.phone, ct.notes, ct.status, ct.scheduled_date, ct.created_at, ct.updated_at, ct.parish_id, p.first_name, p.last_name')
            ->join('person p', 'p.id = ct.person_id', 'left')
            ->where('ct.parish_id', $parishId)
            ->orderBy('ct.scheduled_date', 'ASC')
            ->orderBy('ct.status', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    /**
     * Mark a call task as completed and record the timestamp.
     */
    public function markCompleted(int $id): bool
    {
        return $this->update($id, [
            'status'       => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark a call task as cancelled.
     */
    public function markCancelled(int $id): bool
    {
        return $this->update($id, ['status' => 'cancelled']);
    }

    /**
     * Count pending tasks for a parish.
     */
    public function countPending(int $parishId): int
    {
        return (int) $this->where('parish_id', $parishId)
            ->where('status', 'pending')
            ->countAllResults();
    }
}
