<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillProvinceAdminPrimary extends Migration
{
    public function up()
    {
        if (
            ! $this->db->tableExists('province_admin_primary')
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('auth_permissions_users')
            || ! $this->db->tableExists('profile')
            || ! $this->db->tableExists('users')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Find the first active province admin for each province that does not already have a primary.
        $existingProvinces = $this->db->table('province_admin_primary')
            ->select('province_id')
            ->get()
            ->getResultArray();

        $existingProvinceIds = array_map(static fn($r) => (int) $r['province_id'], $existingProvinces);

        $builder = $this->db->table('auth_groups_users agu')
            ->select('agu.user_id, p.province_id')
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->where('agu.group', 'provinceadmin')
            ->where('u.active', 1)
            ->where('p.province_id IS NOT NULL')
            ->where('p.province_id >', 0)
            ->orderBy('agu.user_id', 'ASC');

        if ($existingProvinceIds !== []) {
            $builder->whereNotIn('p.province_id', $existingProvinceIds);
        }

        $rows = $builder->get()->getResultArray();

        $firstByProvince = [];
        foreach ($rows as $row) {
            $provinceId = (int) $row['province_id'];
            if (! isset($firstByProvince[$provinceId])) {
                $firstByProvince[$provinceId] = (int) $row['user_id'];
            }
        }

        $permissions = [
            'province.primary',
            'province.admin.create',
            'province.admin.update',
            'province.admin.delete',
            'province.admin.reset_password',
        ];

        foreach ($firstByProvince as $provinceId => $userId) {
            $this->db->table('province_admin_primary')->insert([
                'province_id' => $provinceId,
                'user_id'     => $userId,
                'created_by'  => null,
                'created_at'  => $now,
                'updated_at'  => $now,
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
                'province.primary',
                'province.admin.create',
                'province.admin.update',
                'province.admin.delete',
                'province.admin.reset_password',
            ])
            ->delete();

        if ($this->db->tableExists('province_admin_primary')) {
            $this->db->table('province_admin_primary')->truncate();
        }
    }
}
