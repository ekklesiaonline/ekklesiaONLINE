<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizePermissionNames extends Migration
{
    public function up()
    {
        $db = db_connect();

        // Update auth_permissions table if legacy permission names exist
        try {
            $db->table('auth_permissions')->where('name', 'admin.diocese')->update(['name' => 'diocese.admin']);
        } catch (\Throwable $e) {
            // ignore if table/column missing
        }

        try {
            $db->table('auth_permissions')->where('name', 'admin.province')->update(['name' => 'province.admin']);
        } catch (\Throwable $e) {
            // ignore
        }

        // Update auth_groups_permissions junction
        try {
            $db->table('auth_groups_permissions')->where('permission', 'admin.diocese')->update(['permission' => 'diocese.admin']);
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $db->table('auth_groups_permissions')->where('permission', 'admin.province')->update(['permission' => 'province.admin']);
        } catch (\Throwable $e) {
            // ignore
        }

        // Update per-user permission grants
        try {
            $db->table('auth_permissions_users')->where('permission', 'admin.diocese')->update(['permission' => 'diocese.admin']);
        } catch (\Throwable $e) {
        }

        try {
            $db->table('auth_permissions_users')->where('permission', 'admin.province')->update(['permission' => 'province.admin']);
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        $db = db_connect();
        try {
            $db->table('auth_permissions')->where('name', 'diocese.admin')->update(['name' => 'admin.diocese']);
        } catch (\Throwable $e) {
        }

        try {
            $db->table('auth_permissions')->where('name', 'province.admin')->update(['name' => 'admin.province']);
        } catch (\Throwable $e) {
        }

        try {
            $db->table('auth_groups_permissions')->where('permission', 'diocese.admin')->update(['permission' => 'admin.diocese']);
        } catch (\Throwable $e) {
        }

        try {
            $db->table('auth_groups_permissions')->where('permission', 'province.admin')->update(['permission' => 'admin.province']);
        } catch (\Throwable $e) {
        }

        try {
            $db->table('auth_permissions_users')->where('permission', 'diocese.admin')->update(['permission' => 'admin.diocese']);
        } catch (\Throwable $e) {
        }

        try {
            $db->table('auth_permissions_users')->where('permission', 'province.admin')->update(['permission' => 'admin.province']);
        } catch (\Throwable $e) {
        }
    }
}
