<?php

namespace App\Models;

use CodeIgniter\Model;

class ParishAdminPrimaryModel extends Model
{
    protected $table            = 'parish_admin_primary';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['parish_id', 'user_id', 'created_by', 'created_at', 'updated_at'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get the primary admin for a given parish.
     */
    public function getByParish(int $parishId): ?array
    {
        return $this->where('parish_id', $parishId)->first() ?: null;
    }

    /**
     * Get the parish for which a user is the primary admin.
     */
    public function getByUser(int $userId): ?array
    {
        return $this->where('user_id', $userId)->first() ?: null;
    }

    /**
     * Set (or transfer) the primary admin for a parish and sync the permission.
     */
    public function setPrimary(int $parishId, int $userId, ?int $createdBy = null): bool
    {
        $db = $this->db;
        $db->transStart();

        try {
            $existing = $this->getByParish($parishId);

            $now = date('Y-m-d H:i:s');

            if ($existing) {
                $this->update($existing['id'], [
                    'user_id'    => $userId,
                    'created_by' => $createdBy,
                    'updated_at' => $now,
                ]);
            } else {
                $this->insert([
                    'parish_id' => $parishId,
                    'user_id'    => $userId,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->syncPrimaryPermission($parishId, $userId);

            $db->transComplete();
            return $db->transStatus();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'ParishAdminPrimaryModel::setPrimary failed: {message}', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Revoke the primary permission from any user currently holding it for this parish
     * and grant it to the new user.
     */
    private function syncPrimaryPermission(int $parishId, int $newUserId): void
    {
        if (! $this->db->tableExists('auth_permissions_users')) {
            return;
        }

        $permissions = [
            'parish.primary',
            'parish.admin.create',
            'parish.admin.update',
            'parish.admin.delete',
            'parish.admin.reset_password',
        ];

        // Find old primary(s) for this parish and revoke the primary-scoped permissions.
        $oldPrimaries = $this->db->table('parish_admin_primary')
            ->select('user_id')
            ->where('parish_id', $parishId)
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
