<?php

namespace App\Models;

class SmallGroupModel extends BaseTenantModel
{
    protected $table            = 'small_group';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'name',
        'description',
        'meeting_day',
        'meeting_time',
        'location',
        'capacity',
        'is_active',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    protected $validationRules = [
        'parish_id'   => 'required|is_not_unique[parish.id]',
        'name'        => 'required|max_length[120]',
        'meeting_day' => 'permit_empty|max_length[20]',
        'location'    => 'permit_empty|max_length[255]',
        'capacity'    => 'permit_empty|integer|greater_than[0]',
        'is_active'   => 'permit_empty|in_list[0,1]',
    ];

    /**
     * Groups for a parish with their active member counts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groupsWithCounts(int $parishId): array
    {
        return $this->db->table('small_group sg')
            ->select('sg.*, COALESCE(m.member_count, 0) AS member_count, COALESCE(m.leader_count, 0) AS leader_count')
            ->join(
                "(SELECT small_group_id,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS member_count,
                        SUM(CASE WHEN status = 'active' AND role IN ('leader','co_leader') THEN 1 ELSE 0 END) AS leader_count
                 FROM group_membership GROUP BY small_group_id) m",
                'm.small_group_id = sg.id',
                'left'
            )
            ->where('sg.parish_id', $parishId)
            ->where('sg.deleted_at IS NULL', null, false)
            ->orderBy('sg.name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
