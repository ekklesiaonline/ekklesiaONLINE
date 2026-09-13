<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rename the 'pastor' group to 'clergy' in auth_groups_users.
 *
 * The Pastor module has been renamed to Clergy.  Any user who was previously
 * assigned the 'pastor' group must be migrated to 'clergy' so that Shield
 * permission checks continue to resolve correctly.
 *
 * The 'clergy' group is already defined in Config\AuthGroups and carries the
 * correct permissions, so no changes to auth_groups or auth_groups_permissions
 * are required — only the user-assignment rows are updated here.
 */
class RenamePastorRoleToClergy extends Migration
{
    public function up(): void
    {
        $this->db->table('auth_groups_users')
            ->where('group', 'pastor')
            ->update(['group' => 'clergy']);
    }

    public function down(): void
    {
        $this->db->table('auth_groups_users')
            ->where('group', 'clergy')
            ->update(['group' => 'pastor']);
    }
}
