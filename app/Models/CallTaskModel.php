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
        'task_type',
        'title',
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
        'task_type'      => 'permit_empty|in_list[call,manual]',
        'title'          => 'permit_empty|max_length[255]',
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
     * Return all pending tasks for a parish, enriched with visitor name
     * and assignee name. $taskType narrows to 'call' or 'manual'.
     */
    public function getPendingForParish(int $parishId, ?string $taskType = null): array
    {
        $builder = $this->pendingBuilder($parishId);

        if ($taskType !== null && in_array($taskType, ['call', 'manual'], true)) {
            $builder->where('ct.task_type', $taskType);
        }

        return $builder
            ->orderBy('ct.scheduled_date', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return all tasks for a parish (all statuses), oldest scheduled first.
     */
    public function getAllForParish(int $parishId, int $limit = 100, ?string $taskType = null): array
    {
        $builder = $this->baseBuilder()
            ->where('ct.parish_id', $parishId);

        if ($taskType !== null && in_array($taskType, ['call', 'manual'], true)) {
            $builder->where('ct.task_type', $taskType);
        }

        return $builder
            ->orderBy('ct.scheduled_date', 'ASC')
            ->orderBy('ct.status', 'ASC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function baseBuilder(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table('call_task ct')
            ->select('ct.id, ct.person_id, ct.log_id, ct.assigned_to, ct.task_type, ct.title, ct.phone, ct.notes, ct.status, ct.scheduled_date, ct.completed_at, ct.created_at, ct.updated_at, ct.parish_id, p.first_name, p.last_name,'
                . ' au.username AS assignee_username,'
                . ' TRIM(CONCAT(COALESCE(ap.first_name, ""), " ", COALESCE(ap.last_name, ""))) AS assignee_name')
            ->join('person p', 'p.id = ct.person_id', 'left')
            ->join('users au', 'au.id = ct.assigned_to', 'left')
            ->join('profile ap', 'ap.user_id = au.id AND ap.deleted_at IS NULL', 'left');
    }

    private function pendingBuilder(int $parishId): \CodeIgniter\Database\BaseBuilder
    {
        // Pending tasks are actionable work — apply the same live-affiliation
        // gate getPendingDue() uses so detached/removed visitors do not stay
        // queued even though their log rows may linger.
        $paTable = $this->db->prefixTable('parish_affiliation');
        $today   = $this->db->escape(date('Y-m-d'));

        return $this->baseBuilder()
            ->where('ct.parish_id', $parishId)
            ->where('ct.status', 'pending')
            ->where("EXISTS (SELECT 1 FROM {$paTable} pa WHERE pa.person_id = ct.person_id AND pa.parish_id = ct.parish_id AND pa.deleted_at IS NULL AND (pa.end_date IS NULL OR pa.end_date >= {$today}))", null, false);
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
        return (int) $this->pendingBuilder($parishId)
            ->select('ct.id')
            ->countAllResults();
    }
}
