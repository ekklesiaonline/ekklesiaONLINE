<?php

namespace App\Models;

use CodeIgniter\Model;

class FollowupSequenceModel extends Model
{
    protected $table            = 'followup_sequence';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'name',
        'description',
        'is_active',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'      => 'required|min_length[3]|max_length[150]',
        'parish_id' => 'permit_empty|is_not_unique[parish.id]',
        'is_active' => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Return all active sequences available to a parish.
     * Includes both global (parish_id IS NULL) and parish-specific rows.
     */
    public function getActiveForParish(int $parishId): array
    {
        return $this->groupStart()
            ->where('parish_id', $parishId)
            ->orWhere('parish_id IS NULL', null, false)
            ->groupEnd()
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Return a sequence with its ordered actions eager-loaded.
     */
    public function getWithActions(int $sequenceId): ?array
    {
        $sequence = $this->find($sequenceId);
        if ($sequence === null) {
            return null;
        }

        $sequence['actions'] = $this->db->table('followup_action fa')
            ->select('fa.*, ft.name AS template_name, ft.channel AS template_channel')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->where('fa.sequence_id', $sequenceId)
            ->orderBy('fa.day_offset', 'ASC')
            ->get()
            ->getResultArray();

        return $sequence;
    }
}
