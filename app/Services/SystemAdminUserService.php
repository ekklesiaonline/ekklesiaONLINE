<?php

namespace App\Services;

use App\Models\DioceseAdminPrimaryModel;
use App\Models\ParishAdminPrimaryModel;
use App\Models\ProvinceAdminPrimaryModel;
use CodeIgniter\Database\BaseConnection;

/**
 * SystemAdminUserService
 *
 * Data-access layer for system/province/diocese admin user management:
 * hierarchy lookups, group membership, primary-admin operations,
 * cascade deletes, and directory queries. Authorization guards, audit
 * records, and HTTP concerns stay in the controller/trait.
 */
class SystemAdminUserService
{
    private const SCOPE_MAP = [
        'diocese'  => ['column' => 'diocese_id', 'table' => 'diocese_admin_primary'],
        'province' => ['column' => 'province_id', 'table' => 'province_admin_primary'],
        'parish'   => ['column' => 'parish_id', 'table' => 'parish_admin_primary'],
    ];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    // ------------------------------------------------------------------
    // Hierarchy / membership reads
    // ------------------------------------------------------------------

    public function userExists(int $userId): bool
    {
        return $this->db->table('users')->where('id', $userId)->limit(1)->get()->getRow() !== null;
    }

    public function provinceExists(int $provinceId): bool
    {
        return $this->db->table('province')->where('id', $provinceId)->limit(1)->get()->getRow() !== null;
    }

    /**
     * @return array<string, mixed>|null Row with id + province_id.
     */
    public function dioceseRow(int $dioceseId): ?array
    {
        $row = $this->db->table('diocese')
            ->select('id, province_id')
            ->where('id', $dioceseId)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * Whether an email_password identity already exists for the address.
     */
    public function emailIdentityExists(string $email): bool
    {
        return $this->db->table('auth_identities')
            ->where('type', 'email_password')
            ->where('secret', $email)
            ->limit(1)
            ->get()
            ->getRow() !== null;
    }

    /**
     * The user's admin group (first match within the given set), or null.
     */
    public function adminGroupOf(int $userId, array $groups = ['systemadmin', 'provinceadmin', 'dioceseadmin']): ?string
    {
        $row = $this->db->table('auth_groups_users')
            ->select('group')
            ->where('user_id', $userId)
            ->whereIn('group', $groups)
            ->get()
            ->getRowArray();

        return is_array($row) ? (string) ($row['group'] ?? '') : null;
    }

    public function countGroupMembers(string $group): int
    {
        return (int) $this->db->table('auth_groups_users')->where('group', $group)->countAllResults();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function identityForUser(int $userId, string $type): ?array
    {
        $row = $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', $type)
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * The user's scope ID (profile.{column}) for an admin group membership.
     * Used by the grant-*-primary flows.
     */
    public function adminScopeForUser(int $userId, array $groups, string $column): ?int
    {
        $row = $this->db->table('auth_groups_users agu')
            ->select('p.' . $column)
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->where('agu.user_id', $userId)
            ->whereIn('agu.group', $groups)
            ->get()
            ->getRowArray();

        $value = (int) ($row[$column] ?? 0);

        return $value > 0 ? $value : null;
    }

    /**
     * @return array<string, string> permission => description
     */
    public function permissionsForGroups(array $groupNames): array
    {
        if ($groupNames === []) {
            return [];
        }

        $permissions = $this->db->table('auth_groups_permissions agp')
            ->select('agp.permission, ap.description')
            ->join('auth_permissions ap', 'ap.name = agp.permission', 'inner')
            ->whereIn('agp.group', $groupNames)
            ->get()
            ->getResultArray();

        $result = [];
        foreach ($permissions as $perm) {
            $result[$perm['permission']] = $perm['description'];
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Primary-admin operations
    // ------------------------------------------------------------------

    /**
     * The current primary's user_id for a scope, or null when unset.
     */
    public function scopePrimaryUserId(string $scope, int $scopeId): ?int
    {
        $row = match ($scope) {
            'diocese'  => model(DioceseAdminPrimaryModel::class)->getByDiocese($scopeId),
            'province' => model(ProvinceAdminPrimaryModel::class)->getByProvince($scopeId),
            'parish'   => model(ParishAdminPrimaryModel::class)->getByParish($scopeId),
            default    => null,
        };

        return $row !== null ? (int) ($row['user_id'] ?? 0) : null;
    }

    /**
     * Whether the user currently holds the primary flag for the scope.
     */
    public function isScopePrimaryUser(string $scope, int $userId): bool
    {
        return $this->primaryModelFor($scope)?->getByUser($userId) !== null;
    }

    public function setScopePrimary(string $scope, int $scopeId, int $userId, ?int $actorId): bool
    {
        $model = $this->primaryModelFor($scope);

        return $model !== null && $model->setPrimary($scopeId, $userId, $actorId);
    }

    /**
     * Map of scope_id => primary user_id for list views.
     *
     * @return array<int, int>
     */
    public function primaryUserMap(string $scope): array
    {
        $def = self::SCOPE_MAP[$scope] ?? null;
        if ($def === null || ! $this->db->tableExists($def['table'])) {
            return [];
        }

        $map = [];
        foreach ($this->db->table($def['table'])->get()->getResultArray() as $row) {
            $map[(int) $row[$def['column']]] = (int) $row['user_id'];
        }

        return $map;
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Update admin profile fields. Intended to run inside a caller-managed
     * transaction.
     */
    public function updateAdminProfile(string $profileTable, int $userId, array $fields): bool
    {
        return (bool) $this->db->table($profileTable)->where('user_id', $userId)->update($fields);
    }

    /**
     * Cascade-delete an admin user: profile, identities, groups,
     * permissions, then the users row. Owns its transaction.
     */
    public function deleteUserCascade(?string $profileTable, int $userId): bool
    {
        $this->db->transStart();

        try {
            if ($profileTable !== null) {
                $this->db->table($profileTable)->where('user_id', $userId)->delete();
            }
            $this->db->table('auth_identities')->where('user_id', $userId)->delete();
            $this->db->table('auth_groups_users')->where('user_id', $userId)->delete();
            $this->db->table('auth_permissions_users')->where('user_id', $userId)->delete();
            $this->db->table('users')->where('id', $userId)->delete();

            $this->db->transComplete();

            return $this->db->transStatus() !== false;
        } catch (\Throwable) {
            $this->db->transRollback();

            return false;
        }
    }

    /**
     * Replace a user's password identity with a fresh hash.
     */
    public function updatePasswordIdentity(int $userId, string $passwordHash): void
    {
        $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->update([
                'secret2' => $passwordHash,
                'force_reset' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Replace a user's delegable permissions with the granted subset.
     * Owns its transaction.
     *
     * @param list<string> $allowedPermissions Whitelist that may be granted.
     * @param list<string> $grantedPermissions Subset of the whitelist to assign.
     */
    public function replaceUserPermissions(int $userId, array $allowedPermissions, array $grantedPermissions): bool
    {
        $this->db->transStart();

        try {
            $this->db->table('auth_permissions_users')
                ->where('user_id', $userId)
                ->whereIn('permission', $allowedPermissions)
                ->delete();

            foreach ($grantedPermissions as $permission) {
                $this->db->table('auth_permissions_users')->insert([
                    'user_id' => $userId,
                    'permission' => $permission,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->db->transComplete();

            return $this->db->transStatus() !== false;
        } catch (\Throwable) {
            $this->db->transRollback();

            return false;
        }
    }

    // ------------------------------------------------------------------
    // Directory reads (list views)
    // ------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function provincesList(): array
    {
        return $this->db->table('province')
            ->select('id, province_number, name')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('province_number', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function diocesesList(): array
    {
        return $this->db->table('diocese d')
            ->select('d.id, d.name, d.province_id, p.province_number')
            ->join('province p', 'p.id = d.province_id', 'inner')
            ->orderBy('p.sort_order', 'ASC')
            ->orderBy('p.province_number', 'ASC')
            ->orderBy('d.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function systemAdminRows(string $profileTable, string $search, bool $hasPrimaryTable): array
    {
        $query = $this->db->table('auth_groups_users agu')
            ->join('users u', 'u.id = agu.user_id')
            ->join($profileTable . ' p', 'p.user_id = agu.user_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
            ->where('agu.group', 'systemadmin')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC');

        if ($search !== '') {
            $query->groupStart()
                ->like('p.first_name', $search)
                ->orLike('p.last_name', $search)
                ->orLike('ai.secret', $search)
                ->groupEnd();
        }

        if ($hasPrimaryTable) {
            $query->select('agu.user_id, u.active, ai.secret AS email, ai.force_reset, ai.last_used_at, p.first_name, p.last_name, p.phone, CASE WHEN sap.user_id IS NULL THEN 0 ELSE 1 END AS is_primary', false)
                ->join('system_admin_primary sap', 'sap.id = 1 AND sap.user_id = agu.user_id', 'left');
        } else {
            $query->select('agu.user_id, u.active, ai.secret AS email, ai.force_reset, ai.last_used_at, p.first_name, p.last_name, p.phone, 0 AS is_primary', false);
        }

        return $query->get()->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function provinceAdminRows(string $profileTable): array
    {
        return $this->db->table('auth_groups_users agu')
            ->select('agu.user_id, ai.secret AS email, p.first_name, p.last_name, p.phone, p.province_id, pr.name AS province_name, pr.province_number')
            ->join($profileTable . ' p', 'p.user_id = agu.user_id', 'left')
            ->join('province pr', 'pr.id = p.province_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
            ->where('agu.group', 'provinceadmin')
            ->orderBy('pr.sort_order', 'ASC')
            ->orderBy('pr.province_number', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dioceseAdminRows(string $profileTable): array
    {
        return $this->db->table('auth_groups_users agu')
            ->select('agu.user_id, ai.secret AS email, p.first_name, p.last_name, p.phone, p.diocese_id, p.province_id, d.name AS diocese_name, pr.name AS province_name, pr.province_number')
            ->join($profileTable . ' p', 'p.user_id = agu.user_id', 'left')
            ->join('diocese d', 'd.id = p.diocese_id', 'left')
            ->join('province pr', 'pr.id = d.province_id', 'left')
            ->join('auth_identities ai', 'ai.user_id = agu.user_id AND ai.type = "email_password"', 'left')
            ->where('agu.group', 'dioceseadmin')
            ->orderBy('pr.sort_order', 'ASC')
            ->orderBy('pr.province_number', 'ASC')
            ->orderBy('d.name', 'ASC')
            ->orderBy('p.last_name', 'ASC')
            ->orderBy('p.first_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function primaryModelFor(string $scope): DioceseAdminPrimaryModel|ProvinceAdminPrimaryModel|ParishAdminPrimaryModel|null
    {
        return match ($scope) {
            'diocese'  => model(DioceseAdminPrimaryModel::class),
            'province' => model(ProvinceAdminPrimaryModel::class),
            'parish'   => model(ParishAdminPrimaryModel::class),
            default    => null,
        };
    }
}
