<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProvinceDioceseAdminManagementPermission extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('auth_permissions') || !$this->db->tableExists('auth_groups_permissions')) {
            return;
        }

        $permission = 'province.diocese_admin.manage';
        if ($this->db->table('auth_permissions')->where('name', $permission)->countAllResults() === 0) {
            $this->db->table('auth_permissions')->insert([
                'name' => $permission,
                'description' => 'Manage diocesan administrator accounts within the assigned province',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($this->db->table('auth_groups_permissions')->where('group', 'provinceadmin')->where('permission', $permission)->countAllResults() === 0) {
            $this->db->table('auth_groups_permissions')->insert([
                'group' => 'provinceadmin',
                'permission' => $permission,
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('auth_groups_permissions') || !$this->db->tableExists('auth_permissions')) {
            return;
        }

        $permission = 'province.diocese_admin.manage';
        $this->db->table('auth_groups_permissions')->where('group', 'provinceadmin')->where('permission', $permission)->delete();
        $this->db->table('auth_permissions')->where('name', $permission)->delete();
    }
}
