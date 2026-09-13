<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSystemAdminDelegationPermissions extends Migration
{
    private const PERMISSIONS = [
        'system.admin.create',
        'system.admin.update',
        'system.admin.delete',
        'system.admin.reset_password',
    ];

    public function up()
    {
        if (
            ! $this->db->tableExists('auth_permissions')
            || ! $this->db->tableExists('auth_permissions_users')
            || ! $this->db->tableExists('system_admin_primary')
            || ! $this->db->tableExists('auth_groups_users')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach (self::PERMISSIONS as $permission) {
            $exists = (int) $this->db->table('auth_permissions')
                ->where('name', $permission)
                ->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('auth_permissions')->insert([
                    'name' => $permission,
                    'description' => 'Delegated system admin management permission',
                    'created_at' => $now,
                ]);
            }
        }

        $row = $this->db->table('system_admin_primary')
            ->where('id', 1)
            ->get()
            ->getRowArray();

        $primaryUserId = (int) ($row['user_id'] ?? 0);
        if ($primaryUserId <= 0) {
            return;
        }

        $isSystemAdmin = (int) $this->db->table('auth_groups_users')
            ->where('user_id', $primaryUserId)
            ->where('group', 'systemadmin')
            ->countAllResults() > 0;

        if (! $isSystemAdmin) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            $hasPermission = (int) $this->db->table('auth_permissions_users')
                ->where('user_id', $primaryUserId)
                ->where('permission', $permission)
                ->countAllResults() > 0;

            if (! $hasPermission) {
                $this->db->table('auth_permissions_users')->insert([
                    'user_id' => $primaryUserId,
                    'permission' => $permission,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('auth_permissions_users')) {
            $this->db->table('auth_permissions_users')
                ->whereIn('permission', self::PERMISSIONS)
                ->delete();
        }

        if ($this->db->tableExists('auth_permissions')) {
            $this->db->table('auth_permissions')
                ->whereIn('name', self::PERMISSIONS)
                ->delete();
        }
    }
}
