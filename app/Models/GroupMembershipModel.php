<?php

namespace App\Models;

use CodeIgniter\Model;

class GroupMembershipModel extends Model
{
    public const ROLE_MEMBER    = 'member';
    public const ROLE_LEADER    = 'leader';
    public const ROLE_CO_LEADER = 'co_leader';
    public const ROLE_HOST      = 'host';

    public const LEADERSHIP_ROLES = [self::ROLE_LEADER, self::ROLE_CO_LEADER];

    protected $table            = 'group_membership';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'small_group_id',
        'person_id',
        'role',
        'status',
        'joined_on',
        'left_on',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'small_group_id' => 'required|is_not_unique[small_group.id]',
        'person_id'      => 'required|is_not_unique[person.id]',
        'role'           => 'required|in_list[member,leader,co_leader,host]',
        'status'         => 'permit_empty|in_list[active,inactive]',
        'joined_on'      => 'permit_empty|valid_date',
        'left_on'        => 'permit_empty|valid_date',
    ];

    /**
     * @return array<int, string>
     */
    public static function roles(): array
    {
        return [self::ROLE_MEMBER, self::ROLE_LEADER, self::ROLE_CO_LEADER, self::ROLE_HOST];
    }

    /**
     * Active members of a group with person names resolved.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rosterForGroup(int $groupId): array
    {
        return $this->db->table('group_membership gm')
            ->select('gm.*, pe.first_name, pe.last_name, pe.email')
            ->join('person pe', 'pe.id = gm.person_id', 'left')
            ->where('gm.small_group_id', $groupId)
            ->orderBy("CASE gm.role WHEN 'leader' THEN 0 WHEN 'co_leader' THEN 1 WHEN 'host' THEN 2 ELSE 3 END", '', false)
            ->orderBy('pe.last_name', 'ASC')
            ->orderBy('pe.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Active memberships for a person within a parish's groups.
     *
     * @return array<int, array<string, mixed>>
     */
    public function activeForPersonInParish(int $personId, int $parishId): array
    {
        return $this->db->table('group_membership gm')
            ->select('gm.*, sg.name AS group_name, sg.meeting_day, sg.meeting_time, sg.location')
            ->join('small_group sg', 'sg.id = gm.small_group_id')
            ->where('gm.person_id', $personId)
            ->where('gm.status', 'active')
            ->where('sg.parish_id', $parishId)
            ->where('sg.deleted_at IS NULL', null, false)
            ->orderBy('sg.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    public function membershipFor(int $groupId, int $personId): ?array
    {
        $row = $this->where('small_group_id', $groupId)
            ->where('person_id', $personId)
            ->first();

        return $row === null ? null : $row;
    }
}
