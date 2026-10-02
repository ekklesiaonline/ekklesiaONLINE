<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\AuthGroups;

/**
 * ProvinceStaffDirectoryService
 *
 * Read-side staff directory for the province-admin members index:
 * province-level staff (provinceadmin group) and the read-only diocesan
 * staff section (diocesanadmin/parishadmin/clergy within the province,
 * optionally filtered to one diocese). Returns raw rows; the controller
 * applies phone decryption and pending-registration annotation.
 */
class ProvinceStaffDirectoryService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /**
     * Province admin accounts scoped to the given province.
     *
     * @return list<array<string, mixed>>
     */
    public function provinceStaff(string $profileTable, int $provinceId): array
    {
        return $this->db->table($profileTable . ' p')
            ->select('p.id as profile_id, p.user_id, p.first_name, p.last_name, p.phone, ai.secret as email')
            ->join('users u', 'u.id = p.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id AND agu.group = "provinceadmin"')
            ->where('p.province_id', $provinceId)
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Diocesan staff (diocesanadmin, parishadmin, clergy) whose profile
     * or parish resolves into the given province. Optionally limited to
     * one diocese.
     *
     * @return list<array<string, mixed>>
     */
    public function diocesanStaff(string $profileTable, int $provinceId, ?int $dioceseId): array
    {
        $builder = $this->db->table($profileTable . ' p')
            ->select('p.id as profile_id, p.user_id, p.first_name, p.last_name, p.phone, ai.secret as email, agu.group as role, c.id as parish_id, c.name as parish_name, d.id as diocese_id, d.name as diocese_name')
            ->join('users u', 'u.id = p.user_id')
            ->join('auth_identities ai', 'ai.user_id = u.id AND ai.type = "email_password"', 'left')
            ->join('auth_groups_users agu', 'agu.user_id = p.user_id')
            ->join('parish c', 'c.id = p.parish_id', 'left')
            ->join('diocese d', 'd.id = COALESCE(p.diocese_id, c.diocese_id)', 'left')
            ->whereIn('agu.group', [AuthGroups::DIOCESE_ADMIN, 'diocesanadmin', AuthGroups::PARISH_ADMIN, AuthGroups::CLERGY])
            ->where('d.province_id', $provinceId);

        if ($dioceseId !== null) {
            $builder->where('d.id', $dioceseId);
        }

        return $builder
            ->orderBy('agu.group', 'ASC')
            ->orderBy('d.name', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
