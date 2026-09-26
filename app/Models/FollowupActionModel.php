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
        'assigned_to',
        'instructions',
        'source_action_id',
        'is_customized',
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
        'channel'      => 'required|in_list[text,email,call,mail,gift,manual]',
        'template_id'  => 'permit_empty|is_not_unique[followup_template.id]',
        'assigned_to'  => 'permit_empty|is_not_unique[users.id]',
        'instructions'     => 'permit_empty|max_length[255]',
        'source_action_id' => 'permit_empty|is_not_unique[followup_action.id]',
        'is_customized'    => 'permit_empty|in_list[0,1]',
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
            ->select('fa.*, ft.name AS template_name, ft.subject, ft.body_text, ft.body_html, ft.variables AS template_variables,'
                . ' TRIM(CONCAT(COALESCE(ap.first_name, ""), " ", COALESCE(ap.last_name, ""))) AS assignee_name')
            ->join('followup_template ft', 'ft.id = fa.template_id', 'left')
            ->join('users au', 'au.id = fa.assigned_to', 'left')
            ->join('profile ap', 'ap.user_id = au.id AND ap.deleted_at IS NULL', 'left')
            ->where('fa.sequence_id', $sequenceId)
            ->orderBy('fa.day_offset', 'ASC')
            ->get()
            ->getResultArray();
    }

}
