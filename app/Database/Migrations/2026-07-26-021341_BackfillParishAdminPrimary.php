<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillParishAdminPrimary extends Migration
{
    public function up()
    {
        if (
            ! $this->db->tableExists('parish_admin_primary')
            || ! $this->db->tableExists('auth_groups_users')
            || ! $this->db->tableExists('auth_permissions_users')
            || ! $this->db->tableExists('profile')
            || ! $this->db->tableExists('users')
            || ! $this->db->tableExists('parish')
        ) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // Find parishes that do not already have a primary admin.
        $existingParishes = $this->db->table('parish_admin_primary')
            ->select('parish_id')
            ->get()
            ->getResultArray();

        $existingParishIds = array_map(static fn($r) => (int) $r['parish_id'], $existingParishes);

        $parishIds = $this->db->table('parish')
            ->select('id')
            ->get()
            ->getResultArray();

        $neededParishIds = [];
        foreach ($parishIds as $row) {
            $parishId = (int) $row['id'];
            if (! in_array($parishId, $existingParishIds, true)) {
                $neededParishIds[] = $parishId;
            }
        }

        if ($neededParishIds === []) {
            return;
        }

        // First pass: active parishadmin users for the needed parishes.
        $adminCandidates = $this->db->table('auth_groups_users agu')
            ->select('agu.user_id, p.parish_id')
            ->join('profile p', 'p.user_id = agu.user_id', 'inner')
            ->join('users u', 'u.id = agu.user_id', 'inner')
            ->where('agu.group', 'parishadmin')
            ->where('u.active', 1)
            ->whereIn('p.parish_id', $neededParishIds)
            ->orderBy('p.parish_id', 'ASC')
            ->orderBy('agu.user_id', 'ASC')
            ->get()
            ->getResultArray();

        $firstByParish = [];
        foreach ($adminCandidates as $row) {
            $parishId = (int) $row['parish_id'];
            if (! isset($firstByParish[$parishId])) {
                $firstByParish[$parishId] = (int) $row['user_id'];
            }
        }

        // Second pass: active clergy fallback for parishes still missing a primary.
        $missingForClergy = array_diff($neededParishIds, array_keys($firstByParish));
        if ($missingForClergy !== []) {
            $clergyCandidates = $this->db->table('auth_groups_users agu')
                ->select('agu.user_id, p.parish_id')
                ->join('profile p', 'p.user_id = agu.user_id', 'inner')
                ->join('users u', 'u.id = agu.user_id', 'inner')
                ->where('agu.group', 'clergy')
                ->where('u.active', 1)
                ->whereIn('p.parish_id', $missingForClergy)
                ->orderBy('p.parish_id', 'ASC')
                ->orderBy('agu.user_id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($clergyCandidates as $row) {
                $parishId = (int) $row['parish_id'];
                if (! isset($firstByParish[$parishId])) {
                    $firstByParish[$parishId] = (int) $row['user_id'];
                }
            }
        }

        $permissions = [
            'parish.primary',
            'parish.admin.create',
            'parish.admin.update',
            'parish.admin.delete',
            'parish.admin.reset_password',
        ];

        foreach ($firstByParish as $parishId => $userId) {
            $this->db->table('parish_admin_primary')->insert([
                'parish_id'  => $parishId,
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
                'parish.primary',
                'parish.admin.create',
                'parish.admin.update',
                'parish.admin.delete',
                'parish.admin.reset_password',
            ])
            ->delete();

        if ($this->db->tableExists('parish_admin_primary')) {
            $this->db->table('parish_admin_primary')->truncate();
        }
    }
}
