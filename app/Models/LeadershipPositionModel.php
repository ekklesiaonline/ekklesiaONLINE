<?php

namespace App\Models;

use CodeIgniter\Model;

class LeadershipPositionModel extends Model
{
    protected $table            = 'leadership_position';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'person_id',
        'scope_type',
        'parish_id',
        'deanery_id',
        'diocese_id',
        'province_id',
        'title',
        'role_code',
        'employment_type',
        'role_category',
        'clergy_order',
        'pastoral_in_charge',
        'active',
        'started_at',
        'ended_at',
        'notes',
        'appointed_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'approval_evidence_reference',
        'revoked_at',
    ];

    protected array $casts = [
        'user_id' => '?int', 'person_id' => '?int', 'parish_id' => '?int',
        'deanery_id' => '?int', 'diocese_id' => '?int', 'province_id' => '?int',
        'approved_by_user_id' => '?int',
        'pastoral_in_charge' => '?bool',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'user_id'       => 'required|is_not_unique[users.id]',
        'scope_type'    => 'required|in_list[parish,deanery,diocese,province]',
        'parish_id'     => 'permit_empty|is_not_unique[parish.id]',
        'deanery_id'    => 'permit_empty|is_not_unique[deanery.id]',
        'diocese_id'    => 'permit_empty|is_not_unique[diocese.id]',
        'province_id'   => 'permit_empty|is_not_unique[province.id]',
        'title'         => 'required|max_length[150]',
        'role_code'     => 'permit_empty|max_length[100]',
        'started_at'    => 'permit_empty|valid_date',
        'ended_at'      => 'permit_empty|valid_date',
        'appointed_by_user_id' => 'permit_empty|is_not_unique[users.id]',
        'employment_type' => 'permit_empty|in_list[full_time,part_time,non_stipendiary,unpaid]',
        'role_category'   => 'permit_empty|in_list[clergy,lay_staff]',
        'clergy_order'    => 'permit_empty|in_list[bishop,priest,deacon]',
        'pastoral_in_charge' => 'permit_empty|in_list[0,1]',
        'approved_by_user_id' => 'permit_empty|is_not_unique[users.id]',
        'approval_evidence_reference' => 'permit_empty|max_length[255]',
    ];

    public function getActivePositionsForUser(int $userId): array
    {
        return $this->db->table('leadership_position lp')
            ->select('lp.*, c.name as parish_name, d.name as diocese_name, pz.name as province_name')
            ->join('parish c', 'c.id = lp.parish_id', 'left')
            ->join('diocese d', 'd.id = lp.diocese_id', 'left')
            ->join('province pz', 'pz.id = lp.province_id', 'left')
            ->where('lp.user_id', $userId)
            ->where('lp.active', 1)
            ->orderBy('lp.scope_type', 'ASC')
            ->orderBy('lp.title', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getAllPositionsForUser(int $userId): array
    {
        return $this->db->table('leadership_position lp')
            ->select('lp.*, c.name as parish_name, d.name as diocese_name, pz.name as province_name')
            ->join('parish c', 'c.id = lp.parish_id', 'left')
            ->join('diocese d', 'd.id = lp.diocese_id', 'left')
            ->join('province pz', 'pz.id = lp.province_id', 'left')
            ->where('lp.user_id', $userId)
            ->orderBy('lp.active', 'DESC')
            ->orderBy('lp.title', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function getActivePositionsForScope(string $scopeType, int $scopeId): array
    {
        $builder = $this->db->table('leadership_position lp')
            ->select('lp.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, ai.secret as email')
            ->join('profile p', 'p.user_id = lp.user_id', 'left')
            ->join('users u', 'u.id = lp.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('lp.scope_type', $scopeType)
            ->where('lp.active', 1);

        if ($scopeType === 'parish') {
            $builder->where('lp.parish_id', $scopeId);
        } elseif ($scopeType === 'deanery') {
            $builder->where('lp.deanery_id', $scopeId);
        } elseif ($scopeType === 'diocese') {
            $builder->where('lp.diocese_id', $scopeId);
        } else {
            $builder->where('lp.province_id', $scopeId);
        }

        return $builder
            ->orderBy('lp.title', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Parish leaders eligible for follow-up task assignment: an active
     * leadership_position row in this parish whose started_at/ended_at
     * window (when defined) covers today. One row per user.
     */
    public function getAssignableParishLeaders(int $parishId): array
    {
        $rows = $this->assignableLeaderBuilder($parishId)
            ->select('lp.user_id, lp.title, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name, ai.secret AS email')
            ->join('profile p', 'p.user_id = lp.user_id', 'left')
            ->join('users u', 'u.id = lp.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('lp.title', 'ASC')
            ->get()
            ->getResultArray();

        $byUser = [];
        foreach ($rows as $row) {
            $byUser[(int) $row['user_id']] ??= $row;
        }

        return array_values($byUser);
    }

    /**
     * Whether the user currently holds an active, in-window leadership
     * position scoped to this parish.
     */
    public function isActiveParishLeader(int $userId, int $parishId): bool
    {
        return $this->assignableLeaderBuilder($parishId)
            ->where('lp.user_id', $userId)
            ->countAllResults() > 0;
    }

    private function assignableLeaderBuilder(int $parishId): \CodeIgniter\Database\BaseBuilder
    {
        $today = date('Y-m-d');

        return $this->db->table('leadership_position lp')
            ->where('lp.scope_type', 'parish')
            ->where('lp.parish_id', $parishId)
            ->where('lp.active', 1)
            ->groupStart()
                ->where('lp.started_at IS NULL', null, false)
                ->orWhere('lp.started_at <=', $today)
            ->groupEnd()
            ->groupStart()
                ->where('lp.ended_at IS NULL', null, false)
                ->orWhere('lp.ended_at >=', $today)
            ->groupEnd();
    }

    public function countActiveForParish(int $parishId): int
    {
        $row = $this->db->table($this->table . ' lp')
            ->select('COUNT(DISTINCT lp.user_id) AS count', false)
            ->join('profile p', 'p.user_id = lp.user_id', 'inner')
            ->where('lp.scope_type', 'parish')
            ->where('lp.parish_id', $parishId)
            ->where('lp.active', 1)
            ->get()
            ->getRowArray();

        return (int) ($row['count'] ?? 0);
    }

    public function countActiveForDiocese(int $dioceseId): int
    {
        $row = $this->builder()
            ->select('COUNT(DISTINCT user_id) AS count', false)
            ->where('scope_type', 'diocese')
            ->where('diocese_id', $dioceseId)
            ->where('active', 1)
            ->get()
            ->getRowArray();

        return (int) ($row['count'] ?? 0);
    }

    public function countActiveForProvince(int $provinceId): int
    {
        $row = $this->builder()
            ->select('COUNT(DISTINCT user_id) AS count', false)
            ->where('scope_type', 'province')
            ->where('province_id', $provinceId)
            ->where('active', 1)
            ->get()
            ->getRowArray();

        return (int) ($row['count'] ?? 0);
    }
}
