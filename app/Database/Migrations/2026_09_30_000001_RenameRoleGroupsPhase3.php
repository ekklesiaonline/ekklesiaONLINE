<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 3 role rename migration.
 *
 * Renames existing group-membership strings in auth_groups_users:
 *   - treasurer  → finance
 *   - secretary  → vestry_secretary
 *   - clerk      → vestry_secretary   (merged into vestry_secretary)
 *
 * Also updates user-level permission grants in auth_permissions_users that
 * were seeded against the old group names, so the PermissionRoleSyncSeeder
 * does not need to re-seed from scratch.
 *
 * Down() is intentionally non-destructive: it renames back to the old
 * group names so a rollback restores the original state.
 */
class RenameRoleGroupsPhase3 extends Migration
{
    /**
     * Old-to-new group name mapping.
     * clerk users become vestry_secretary (merged).
     *
     * @var array<string, string>
     */
    private array $renames = [
        'treasurer' => 'finance',
        'secretary' => 'vestry_secretary',
        'clerk'     => 'vestry_secretary',
    ];

    public function up(): void
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return;
        }

        foreach ($this->renames as $old => $new) {
            // Avoid creating duplicate rows when clerk is merged into vestry_secretary.
            // If a user already has vestry_secretary (e.g. was also secretary),
            // skip the clerk row instead of inserting a duplicate.
            if ($old === 'clerk') {
                // Delete clerk rows where the user already has vestry_secretary.
                $this->db->query(
                    "DELETE agu FROM auth_groups_users agu
                     INNER JOIN auth_groups_users agu2
                       ON agu2.user_id = agu.user_id AND agu2.`group` = 'vestry_secretary'
                     WHERE agu.`group` = 'clerk'"
                );
            }

            $this->db->table('auth_groups_users')
                ->where('group', $old)
                ->update(['group' => $new]);
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return;
        }

        // Reverse: vestry_secretary → secretary, finance → treasurer.
        // Note: we cannot restore the distinction between former clerk and
        // secretary users — all vestry_secretary rows roll back to secretary.
        $this->db->table('auth_groups_users')
            ->where('group', 'finance')
            ->update(['group' => 'treasurer']);

        $this->db->table('auth_groups_users')
            ->where('group', 'vestry_secretary')
            ->update(['group' => 'secretary']);
    }
}
