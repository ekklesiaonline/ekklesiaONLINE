<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeedDioceseAdminPrimaryPermissions extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('auth_permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $permissions = [
            'diocese.primary'            => 'Primary diocesan administrator',
            'diocese.admin.create'       => 'Create diocesan administrator accounts',
            'diocese.admin.update'       => 'Edit diocesan administrator accounts',
            'diocese.admin.delete'       => 'Delete diocesan administrator accounts',
            'diocese.admin.reset_password' => 'Reset diocesan administrator passwords',
        ];

        foreach ($permissions as $name => $description) {
            $exists = (int) $this->db->table('auth_permissions')
                ->where('name', $name)
                ->countAllResults() > 0;

            if (! $exists) {
                $this->db->table('auth_permissions')->insert([
                    'name'        => $name,
                    'description' => $description,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('auth_permissions')) {
            return;
        }

        $this->db->table('auth_permissions')
            ->whereIn('name', [
                'diocese.primary',
                'diocese.admin.create',
                'diocese.admin.update',
                'diocese.admin.delete',
                'diocese.admin.reset_password',
            ])
            ->delete();
    }
}
