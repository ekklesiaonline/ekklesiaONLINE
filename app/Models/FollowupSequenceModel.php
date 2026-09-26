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
        'diocese_id',
        'source_sequence_id',
        'name',
        'description',
        'is_active',
        'is_default',
        'is_global_default',
        'is_diocese_default',
        'is_customized',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'                => 'required|min_length[3]|max_length[150]',
        'parish_id'           => 'permit_empty|is_not_unique[parish.id]',
        'diocese_id'          => 'permit_empty|is_not_unique[diocese.id]',
        'source_sequence_id'  => 'permit_empty|is_not_unique[followup_sequence.id]',
        'is_active'           => 'permit_empty|in_list[0,1]',
        'is_default'          => 'permit_empty|in_list[0,1]',
        'is_global_default'   => 'permit_empty|in_list[0,1]',
        'is_diocese_default'  => 'permit_empty|in_list[0,1]',
        'is_customized'       => 'permit_empty|in_list[0,1]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Return all active sequences available to a parish: parish rows plus
     * upstream rows (global and the parish's diocese). Upstream rows are
     * clone templates — see FollowupCloneService.
     */
    public function getActiveForParish(int $parishId): array
    {
        $dioceseId = $this->parishDioceseId($parishId);

        return $this->groupStart()
            ->where('parish_id', $parishId)
            ->orGroupStart()
                ->where('parish_id IS NULL', null, false)
                ->where('diocese_id IS NULL', null, false)
            ->groupEnd()
            ->when($dioceseId > 0, static fn($q) => $q->orWhere('diocese_id', $dioceseId))
            ->groupEnd()
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /**
     * Return the active default sequence for a parish.
     */
    public function findDefaultForParish(int $parishId): ?array
    {
        $row = $this->where('parish_id', $parishId)
            ->where('is_active', 1)
            ->where('is_default', 1)
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * Return the active global default sequence (template for new parishes).
     */
    public function findGlobalDefault(): ?array
    {
        $row = $this->where('parish_id IS NULL', null, false)
            ->where('diocese_id IS NULL', null, false)
            ->where('is_active', 1)
            ->where('is_global_default', 1)
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * Return the active diocesan default sequence for a diocese.
     */
    public function findDioceseDefault(int $dioceseId): ?array
    {
        $row = $this->where('diocese_id', $dioceseId)
            ->where('is_active', 1)
            ->where('is_diocese_default', 1)
            ->first();

        return is_array($row) ? $row : null;
    }

    /**
     * The diocese a parish belongs to (0 when unaffiliated).
     */
    public function parishDioceseId(int $parishId): int
    {
        $parish = $this->db->table('parish')
            ->select('diocese_id')
            ->where('id', $parishId)
            ->get()
            ->getRowArray();

        return (int) ($parish['diocese_id'] ?? 0);
    }

    /**
     * Scope classifier for a sequence row: 'global', 'diocese', or 'parish'.
     */
    public static function scopeOf(array $sequence): string
    {
        if (!empty($sequence['parish_id'])) {
            return 'parish';
        }
        if (!empty($sequence['diocese_id'])) {
            return 'diocese';
        }

        return 'global';
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
