<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CentralizeAdminCreationPermissions extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('auth_permissions') || !$this->db->tableExists('auth_groups_permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $newPermissions = [
            'system.province.create' => 'Create province administrator accounts',
            'system.diocese.create'  => 'Create diocese administrator accounts',
        ];

        foreach ($newPermissions as $permission => $description) {
            $exists = (int) $this->db->table('auth_permissions')
                ->where('name', $permission)
                ->countAllResults() > 0;

            if (!$exists) {
                $this->db->table('auth_permissions')->insert([
                    'name'        => $permission,
                    'description' => $description,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            $assigned = (int) $this->db->table('auth_groups_permissions')
                ->where('group', 'systemadmin')
                ->where('permission', $permission)
                ->countAllResults() > 0;

            if (!$assigned) {
                $this->db->table('auth_groups_permissions')->insert([
                    'group'      => 'systemadmin',
                    'permission' => $permission,
                ]);
            }
        }

        // Remove the now-unused province admin diocese-admin management permission.
        $this->db->table('auth_groups_permissions')
            ->where('permission', 'province.diocese_admin.manage')
            ->delete();

        $this->db->table('auth_permissions')
            ->where('name', 'province.diocese_admin.manage')
            ->delete();
    }

    public function down()
    {
        if (!$this->db->tableExists('auth_permissions') || !$this->db->tableExists('auth_groups_permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $permissionsToRemove = ['system.province.create', 'system.diocese.create'];

        $this->db->table('auth_groups_permissions')
            ->whereIn('permission', $permissionsToRemove)
            ->delete();

        $this->db->table('auth_permissions')
            ->whereIn('name', $permissionsToRemove)
            ->delete();

        $provincePermission = 'province.diocese_admin.manage';
        $exists = (int) $this->db->table('auth_permissions')
            ->where('name', $provincePermission)
            ->countAllResults() > 0;

        if (!$exists) {
            $this->db->table('auth_permissions')->insert([
                'name'        => $provincePermission,
                'description' => 'Manage diocesan administrator accounts within the assigned province',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $assigned = (int) $this->db->table('auth_groups_permissions')
            ->where('group', 'provinceadmin')
            ->where('permission', $provincePermission)
            ->countAllResults() > 0;

        if (!$assigned) {
            $this->db->table('auth_groups_permissions')->insert([
                'group'      => 'provinceadmin',
                'permission' => $provincePermission,
            ]);
        }
    }
}
