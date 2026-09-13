<?php

namespace App\Models;

use CodeIgniter\Model;

class DioceseAdminPrimaryModel extends Model
{
    protected $table            = 'diocese_admin_primary';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['diocese_id', 'user_id', 'created_by', 'created_at', 'updated_at'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get the primary admin for a given diocese.
     */
    public function getByDiocese(int $dioceseId): ?array
    {
        return $this->where('diocese_id', $dioceseId)->first() ?: null;
    }

    /**
     * Get the diocese for which a user is the primary admin.
     */
    public function getByUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first() ?: null;
    }

    /**
     * Set (or transfer) the primary admin for a diocese and sync the permission.
     */
    public function setPrimary(int $dioceseId, int $userId, ?int $createdBy = null): bool
    {
        $db = $this->db;
        $db->transStart();

        try {
            $existing = $this->getByDiocese($dioceseId);

            $now = date('Y-m-d H:i:s');

            if ($existing) {
                $this->update($existing['id'], [
                    'user_id'    => $userId,
                    'created_by' => $createdBy,
                    'updated_at' => $now,
                ]);
            } else {
                $this->insert([
                    'diocese_id' => $dioceseId,
                    'user_id'    => $userId,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->syncPrimaryPermission($dioceseId, $userId);

            $db->transComplete();
            return $db->transStatus();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'DioceseAdminPrimaryModel::setPrimary failed: {message}', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Revoke the primary permission from any user currently holding it for this diocese
     * and grant it to the new user.
     */
    private function syncPrimaryPermission(int $dioceseId, int $newUserId): void
    {
        if (! $this->db->tableExists('auth_permissions_users')) {
            return;
        }

        $permissions = [
            'diocese.primary',
            'diocese.admin.create',
            'diocese.admin.update',
            'diocese.admin.delete',
            'diocese.admin.reset_password',
        ];

        // Find old primary(s) for this diocese and revoke the primary-scoped permissions.
        $oldPrimaries = $this->db->table('diocese_admin_primary')
            ->select('user_id')
            ->where('diocese_id', $dioceseId)
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
