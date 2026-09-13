<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillSystemPrimaryPermission extends Migration
{
    public function up()
    {
        if (
            ! $this->db->tableExists('auth_permissions_users')
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('system_admin_primary')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Remove stale primary permission rows before re-assigning.
        $this->db->table('auth_permissions_users')
            ->where('permission', 'system.primary')
            ->delete();

        $row = $this->db->table('system_admin_primary')
            ->where('id', 1)
            ->get()
            ->getRowArray();

        $primaryUserId = (int) ($row['user_id'] ?? 0);
        if ($primaryUserId <= 0) {
            return;
        }

        // Only backfill if the user is actually a system admin.
        $isSystemAdmin = (int) $this->db->table('auth_groups_users')
            ->where('user_id', $primaryUserId)
            ->where('group', 'systemadmin')
            ->countAllResults() > 0;

        if (! $isSystemAdmin) {
            return;
        }

        $this->db->table('auth_permissions_users')->insert([
            'user_id' => $primaryUserId,
            'permission' => 'system.primary',
            'created_at' => $now,
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('auth_permissions_users')) {
            $this->db->table('auth_permissions_users')
                ->where('permission', 'system.primary')
                ->delete();
        }
    }
}
