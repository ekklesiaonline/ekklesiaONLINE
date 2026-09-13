<?php

namespace App\Models;

use CodeIgniter\Model;

class FollowupActionModel extends Model
{
    protected $table            = 'followup_action';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'sequence_id',
        'week_number',
        'day_offset',
        'channel',
        'template_id',
        'is_automated',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'sequence_id'  => 'required|is_not_unique[followup_sequence.id]',
        'week_number'  => 'required|is_natural_no_zero|less_than_equal_to[52]',
        'day_offset'   => 'required|is_natural|less_than_equal_to[365]',
        'channel'      => 'required|in_list[text,email,call,mail,gift]',
        'template_id'  => 'permit_empty|is_not_unique[followup_template.id]',
        'is_automated' => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Return all actions for a sequence ordered by execution day.
     */
    public function getBySequence(int $sequenceId): array
    {
        return $this->where('sequence_id', $sequenceId)
            ->orderBy('day_offset', 'ASC')
            ->findAll();
    }

    /**
     * Return actions for a sequence with template details joined.
     */
    public function getBySequenceWithTemplates(int $sequenceId): array
    {
        return $this->db->table('followup_action fa')
            ->select('fa.*, ft.name AS template_name, ft.subject, ft.body_text, ft.body_html, ft.variables AS template_variables')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->where('fa.sequence_id', $sequenceId)
            ->orderBy('fa.day_offset', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Return automated actions only — used by the cron dispatcher.
     */
    public function getAutomatedBySequence(int $sequenceId): array
    {
        return $this->where('sequence_id', $sequenceId)
            ->where('is_automated', 1)
            ->orderBy('day_offset', 'ASC')
            ->findAll();
    }
}
