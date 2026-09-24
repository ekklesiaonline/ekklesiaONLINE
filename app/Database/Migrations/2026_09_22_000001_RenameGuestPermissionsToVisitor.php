<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Rename guest.* permissions to visitor.* across Shield's permission
 * tables. `guest.followup` is dropped outright — no route ever gated on
 * it (follow-up endpoints use followup.*).
 */
class RenameGuestPermissionsToVisitor extends Migration
{
    private const MAP = [
        'guest.view'    => 'visitor.view',
        'guest.create'  => 'visitor.create',
        'guest.edit'    => 'visitor.edit',
        'guest.delete'  => 'visitor.delete',
        'guest.manage'  => 'visitor.manage',
    ];

    public function up()
    {
        $db = db_connect();

        foreach (self::MAP as $old => $new) {
            try {
                $db->table('auth_permissions')->where('name', $old)->update(['name' => $new]);
            } catch (\Throwable $e) {
                // ignore if table/column missing
            }

            try {
                $db->table('auth_groups_permissions')->where('permission', $old)->update(['permission' => $new]);
            } catch (\Throwable $e) {
                // ignore
            }

            try {
                $db->table('auth_permissions_users')->where('permission', $old)->update(['permission' => $new]);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Drop grants for the retired guest.followup permission.
        foreach (['auth_permissions', 'auth_groups_permissions', 'auth_permissions_users'] as $table) {
            $column = $table === 'auth_permissions' ? 'name' : 'permission';
            try {
                $db->table($table)->where($column, 'guest.followup')->delete();
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down()
    {
        $db = db_connect();

        foreach (self::MAP as $old => $new) {
            try {
                $db->table('auth_permissions')->where('name', $new)->update(['name' => $old]);
            } catch (\Throwable $e) {
            }

            try {
                $db->table('auth_groups_permissions')->where('permission', $new)->update(['permission' => $old]);
            } catch (\Throwable $e) {
            }

            try {
                $db->table('auth_permissions_users')->where('permission', $new)->update(['permission' => $old]);
            } catch (\Throwable $e) {
            }
        }
    }
}
