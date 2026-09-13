<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillDioceseAdminPrimary extends Migration
{
    public function up()
    {
        if (
            ! $this->db->tableExists('diocese_admin_primary')
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('profile')
            || ! $this->db->tableExists('auth_permissions_users')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Find the first diocesan admin for each diocese that does not already have a primary.
        $existingDioceses = $this->db->table('diocese_admin_primary')
            ->select('diocese_id')
            ->get()
            ->getResultArray();

        $existingDioceseIds = array_map(static fn($r) => (int) $r['diocese_id'], $existingDioceses);

        $builder = $this->db->table('auth_groups_users agu')
            ->select('agu.user_id, p.diocese_id')
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->where('agu.group', 'dioceseadmin')
            ->where('p.diocese_id IS NOT NULL')
            ->where('p.diocese_id >', 0)
            ->orderBy('agu.user_id', 'ASC');

        if ($existingDioceseIds !== []) {
            $builder->whereNotIn('p.diocese_id', $existingDioceseIds);
        }

        $rows = $builder->get()->getResultArray();

        $firstByDiocese = [];
        foreach ($rows as $row) {
            $dioceseId = (int) $row['diocese_id'];
            if (! isset($firstByDiocese[$dioceseId])) {
                $firstByDiocese[$dioceseId] = (int) $row['user_id'];
            }
        }

        $permissions = [
            'diocese.primary',
            'diocese.admin.create',
            'diocese.admin.update',
            'diocese.admin.delete',
            'diocese.admin.reset_password',
        ];

        foreach ($firstByDiocese as $dioceseId => $userId) {
            $this->db->table('diocese_admin_primary')->insert([
                'diocese_id' => $dioceseId,
                'user_id'    => $userId,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($permissions as $permission) {
                $hasPermission = (int) $this->db->table('auth_permissions_users')
                    ->where('user_id', $userId)
                    ->where('permission', $permission)
                    ->countAllResults() > 0;

                if (! $hasPermission) {
                    $this->db->table('auth_permissions_users')->insert([
                        'user_id'    => $userId,
                        'permission' => $permission,
                        'created_at' => $now,
                    ]);
                }
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
                'diocese.primary',
                'diocese.admin.create',
                'diocese.admin.update',
                'diocese.admin.delete',
                'diocese.admin.reset_password',
            ])
            ->delete();

        if ($this->db->tableExists('diocese_admin_primary')) {
            $this->db->table('diocese_admin_primary')->truncate();
        }
    }
}
