<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishRoleAuditModel extends Model
{
    protected $table            = 'parish_role_audit_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'parish_id',
        'actor_user_id',
        'target_user_id',
        'action',
        'role',
        'details',
        'created_at',
    ];

    protected $useTimestamps = false;

    public function getRecentByParish(int $parishId, int $limit = 30): array
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        return $this->db->table('parish_role_audit_log l')
            ->select('l.*, 
                CONCAT(COALESCE(ap.nickname, ap.first_name, ""), " ", COALESCE(ap.last_name, "")) as actor_name,
                CONCAT(COALESCE(tp.nickname, tp.first_name, ""), " ", COALESCE(tp.last_name, "")) as target_name')
            ->join($profileTable . ' ap', 'ap.user_id = l.actor_user_id', 'left')
            ->join($profileTable . ' tp', 'tp.user_id = l.target_user_id', 'left')
            ->where('l.parish_id', $parishId)
            ->orderBy('l.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function getRecentByDiocese(int $dioceseId, int $limit = 30): array
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        return $this->db->table('parish_role_audit_log l')
            ->select('l.*, c.name as parish_name,
                CONCAT(COALESCE(ap.nickname, ap.first_name, ""), " ", COALESCE(ap.last_name, "")) as actor_name,
                CONCAT(COALESCE(tp.nickname, tp.first_name, ""), " ", COALESCE(tp.last_name, "")) as target_name')
            ->join($profileTable . ' tp', 'tp.user_id = l.target_user_id', 'left')
            ->join($profileTable . ' ap', 'ap.user_id = l.actor_user_id', 'left')
            ->join('parish c', 'c.id = l.parish_id', 'left')
            ->groupStart()
            ->where('tp.diocese_id', $dioceseId)
            ->orWhere('c.diocese_id', $dioceseId)
            ->groupEnd()
            ->orderBy('l.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    public function getRecentNetwork(int $limit = 30): array
    {
        $profileTable = $this->resolveExistingProfileTable();
        if ($profileTable === null) {
            return [];
        }

        return $this->db->table('parish_role_audit_log l')
            ->select('l.*, c.name as parish_name,
                CONCAT(COALESCE(ap.nickname, ap.first_name, ""), " ", COALESCE(ap.last_name, "")) as actor_name,
                CONCAT(COALESCE(tp.nickname, tp.first_name, ""), " ", COALESCE(tp.last_name, "")) as target_name')
            ->join('parish c', 'c.id = l.parish_id', 'left')
            ->join($profileTable . ' ap', 'ap.user_id = l.actor_user_id', 'left')
            ->join($profileTable . ' tp', 'tp.user_id = l.target_user_id', 'left')
            ->orderBy('l.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }

    private function resolveExistingProfileTable(): ?string
    {
        $candidates = [
            $this->db->getPrefix() . 'profile',
            'profile',
        ];

        foreach (array_values(array_unique($candidates)) as $table) {
            try {
                $columns = $this->db->getFieldNames($table);
                if ($columns !== []) {
                    return $table;
                }
            } catch (\Throwable $e) {
                // Try next table-name variant.
            }
        }

        return null;
    }
}
