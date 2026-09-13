<?php

namespace App\Models;

use App\Libraries\DataEncryption;
use CodeIgniter\Model;

class TeamModel extends Model
{
    protected $table            = 'team';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'name',
        'description',
        'leader_id',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'parish_id' => 'required|is_not_unique[parish.id]',
        'name'      => 'required|min_length[3]|max_length[255]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get team with members
     */
    public function getTeamWithMembers($teamId)
    {
        $team = $this->find($teamId);
        if (!$team) {
            return null;
        }

        $preferredNameExpr = $this->getPreferredProfileNameExpr('p');

        $members = $this->db->table('team_member tm')
            ->select('tm.*, ' . $preferredNameExpr . ' AS first_name, p.last_name, p.phone, ai.secret as email', false)
            ->join('profile p', 'p.user_id = tm.user_id')
            ->join('users u', 'u.id = tm.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->where('tm.team_id', $teamId)
            ->get()
            ->getResultArray();

        $members = array_map(static function (array $row): array {
            $row['phone'] = DataEncryption::decrypt($row['phone'] ?? null);
            return $row;
        }, $members);

        $team['members'] = $members;
        return $team;
    }

    /**
     * Get parish teams
     */
    public function getParishTeams($parishId)
    {
        $hasLeader = $this->hasLeaderColumn();

        $select = $hasLeader
            ? 'vt.*, ' . $this->getPreferredProfileNameExpr('p') . ' AS leader_first_name, p.last_name as leader_last_name, COUNT(tm.id) as member_count'
            : 'vt.*, COUNT(tm.id) as member_count';

        $builder = $this->db->table('team vt')
            ->select($select, false)
            ->join('team_member tm', 'tm.team_id = vt.id', 'left')
            ->where('vt.parish_id', $parishId);

        if ($hasLeader) {
            $builder->join('profile p', 'p.user_id = vt.leader_id', 'left');
        }

        return $builder->groupBy('vt.id')
            ->orderBy('vt.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Backward-compatible alias used by Volunteers controller.
     */
    public function getParishTeamsWithCounts($parishId)
    {
        return $this->getParishTeams($parishId);
    }

    /**
     * Paginated teams for parish-level listing.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getParishTeamsPaginated(int $parishId, int $perPage = 25, int $page = 1): array
    {
        $preferredNameExpr = $this->getPreferredProfileNameExpr('p');

        $baseBuilder = $this->db->table('team vt')
            ->select('vt.id')
            ->where('vt.parish_id', $parishId);

        $countBuilder = clone $baseBuilder;
        $total = (int) $countBuilder->countAllResults();

        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $hasLeader = $this->hasLeaderColumn();

        $select = $hasLeader
            ? 'vt.*, ' . $preferredNameExpr . ' AS leader_first_name, p.last_name as leader_last_name, COUNT(tm.id) as member_count'
            : 'vt.*, COUNT(tm.id) as member_count';

        $rowsBuilder = $this->db->table('team vt')
            ->select($select, false)
            ->join('team_member tm', 'tm.team_id = vt.id', 'left')
            ->where('vt.parish_id', $parishId);

        if ($hasLeader) {
            $rowsBuilder->join('profile p', 'p.user_id = vt.leader_id', 'left');
        }

        $rows = $rowsBuilder->groupBy('vt.id')
            ->orderBy('vt.name', 'ASC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return [
            'rows' => $rows,
            'total' => $total,
        ];
    }

    private function hasLeaderColumn(): bool
    {
        try {
            return $this->db->fieldExists('leader_id', 'team');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getPreferredProfileNameExpr(string $alias): string
    {
        return $alias . '.first_name';
    }
}
