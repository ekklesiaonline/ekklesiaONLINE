<?php

namespace App\Models;

use CodeIgniter\Model;

class TeamMemberModel extends Model
{
    protected $table            = 'team_member';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'team_id',
        'user_id',
        'person_id',
        'role',
        'joined_at',
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';

    // Validation
    protected $validationRules      = [
        'team_id' => 'required|is_not_unique[team.id]',
        'user_id' => 'required|is_not_unique[users.id]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get user's teams
     */
    public function getUserTeams($userId)
    {
        return $this->db->table('team_member tm')
            ->select('tm.*, vt.name as team_name, vt.description')
            ->join('team vt', 'vt.id = tm.team_id')
            ->where('tm.user_id', $userId)
            ->get()
            ->getResultArray();
    }

    public function isUserOnTeam(int $teamId, int $userId): bool
    {
        return $this->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first() !== null;
    }

    /**
     * Get all members for a specific team.
     */
    public function getTeamMembers($teamId)
    {
        return $this->db->table('team_member tm')
            ->select('tm.*, COALESCE(p.nickname, p.first_name) AS first_name, p.last_name')
            ->join('profile p', 'p.user_id = tm.user_id', 'left')
            ->where('tm.team_id', $teamId)
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
