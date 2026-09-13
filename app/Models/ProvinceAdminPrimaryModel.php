<?php

namespace App\Models;

use CodeIgniter\Model;

class ProvinceAdminPrimaryModel extends Model
{
    protected $table            = 'province_admin_primary';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['province_id', 'user_id', 'created_by', 'created_at', 'updated_at'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get the primary admin for a given province.
     */
    public function getByProvince(int $provinceId): ?array
    {
        return $this->where('province_id', $provinceId)->first() ?: null;
    }

    /**
     * Get the province for which a user is the primary admin.
     */
    public function getByUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first() ?: null;
    }

    /**
     * Set (or transfer) the primary admin for a province and sync the permission.
     */
    public function setPrimary(int $provinceId, int $userId, ?int $createdBy = null): bool
    {
        $db = $this->db;
        $db->transStart();

        try {
            $existing = $this->getByProvince($provinceId);

            $now = date('Y-m-d H:i:s');

            if ($existing) {
                $this->update($existing['id'], [
                    'user_id'    => $userId,
                    'created_by' => $createdBy,
                    'updated_at' => $now,
                ]);
            } else {
                $this->insert([
                    'province_id' => $provinceId,
                    'user_id'    => $userId,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->syncPrimaryPermission($provinceId, $userId);

            $db->transComplete();
            return $db->transStatus();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'ProvinceAdminPrimaryModel::setPrimary failed: {message}', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Revoke the primary permission from any user currently holding it for this province
     * and grant it to the new user.
     */
    private function syncPrimaryPermission(int $provinceId, int $newUserId): void
    {
        if (! $this->db->tableExists('auth_permissions_users')) {
            return;
        }

        $permissions = [
            'province.primary',
            'province.admin.create',
            'province.admin.update',
            'province.admin.delete',
            'province.admin.reset_password',
        ];

        // Find old primary(s) for this province and revoke the primary-scoped permissions.
        $oldPrimaries = $this->db->table('province_admin_primary')
            ->select('user_id')
            ->where('province_id', $provinceId)
            ->where('user_id !=', $newUserId)
            ->get()
            ->getResultArray();

        foreach ($oldPrimaries as $row) {
            $this->db->table('auth_permissions_users')
                ->where('user_id', (int) $row['user_id'])
                ->whereIn('permission', $permissions)
                ->delete();
        }

        // Grant permissions to the new primary if not already present.
        foreach ($permissions as $permission) {
            $exists = (int) $this->db->table('auth_permissions_users')
                ->where('user_id', $newUserId)
                ->where('permission', $permission)
                ->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('auth_permissions_users')->insert([
                    'user_id'    => $newUserId,
                    'permission' => $permission,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
