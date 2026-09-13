<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillSystemAdminPrimary extends Migration
{
    public function up()
    {
        if (
            ! $this->db->tableExists('system_admin_primary')
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('auth_permissions_users')
            || ! $this->db->tableExists('users')
        ) {
            return;
        }

        // Only backfill if the singleton row is empty.
        $existing = $this->db->table('system_admin_primary')->where('id', 1)->countAllResults();
        if ($existing > 0) {
            return;
        }

        $primaryUser = $this->db->table('auth_groups_users agu')
            ->select('agu.user_id')
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->where('agu.group', 'systemadmin')
            ->where('u.active', 1)
            ->orderBy('agu.user_id', 'ASC')
            ->get()
            ->getRowArray();

        $primaryUserId = (int) ($primaryUser['user_id'] ?? 0);
        if ($primaryUserId <= 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->db->table('system_admin_primary')->insert([
            'id'         => 1,
            'user_id'    => $primaryUserId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissions = [
            'system.primary',
            'system.admin.create',
            'system.admin.update',
            'system.admin.delete',
            'system.admin.reset_password',
        ];

        foreach ($permissions as $permission) {
            $hasPermission = (int) $this->db->table('auth_permissions_users')
                ->where('user_id', $primaryUserId)
                ->where('permission', $permission)
                ->countAllResults() > 0;

            if (! $hasPermission) {
                $this->db->table('auth_permissions_users')->insert([
                    'user_id'    => $primaryUserId,
                    'permission' => $permission,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('auth_permissions_users')) {
            return;
        }

        $this->db->table('auth_permissions_users')
            ->whereIn('permission', [
                'system.primary',
                'system.admin.create',
                'system.admin.update',
                'system.admin.delete',
                'system.admin.reset_password',
            ])
            ->delete();

        if ($this->db->tableExists('system_admin_primary')) {
            $this->db->table('system_admin_primary')->truncate();
        }
    }
}
