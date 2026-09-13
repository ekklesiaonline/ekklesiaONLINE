<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\AuthGroups;

class PermissionRoleSyncSeeder extends Seeder
{
    public function run()
    {
        if (! $this->db->tableExists('auth_groups_users') || ! $this->db->tableExists('auth_permissions_users')) {
            return;
        }

        $roleMap = $this->rolePermissionMatrix();
        if ($roleMap === []) {
            return;
        }

        $groupRows = $this->db->table('auth_groups_users')
            ->select('user_id, `group`')
            ->get()
            ->getResultArray();

        $now = date('Y-m-d H:i:s');

        foreach ($groupRows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $group  = (string) ($row['group'] ?? '');
            if ($userId <= 0 || $group === '') {
                continue;
            }

            foreach ($roleMap as $permission => $groups) {
                if (! in_array($group, $groups, true)) {
                    continue;
                }

                $exists = (int) $this->db->table('auth_permissions_users')
                    ->where('user_id', $userId)
                    ->where('permission', $permission)
                    ->countAllResults();

                if ($exists > 0) {
                    continue;
                }

                $this->db->table('auth_permissions_users')->insert([
                    'user_id'    => $userId,
                    'permission' => $permission,
                    'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * Return permission => groups[] map.
     *
     * Kept as a dedicated method because tests reflect this method directly.
     *
     * @return array<string, array<int, string>>
     */
    protected function rolePermissionMatrix(): array
    {
        $authGroups = config('AuthGroups');
        if (! $authGroups instanceof AuthGroups) {
            return [];
        }

        $allPermissions = array_keys((array) ($authGroups->permissions ?? []));
        $matrixByGroup  = (array) ($authGroups->matrix ?? []);

        $permissionToGroups = [];

        foreach ($matrixByGroup as $group => $patterns) {
            foreach ((array) $patterns as $pattern) {
                $pattern = (string) $pattern;
                if ($pattern === '') {
                    continue;
                }

                $expanded = $this->expandPermissionPattern($pattern, $allPermissions);
                foreach ($expanded as $permission) {
                    $permissionToGroups[$permission] ??= [];
                    if (! in_array($group, $permissionToGroups[$permission], true)) {
                        $permissionToGroups[$permission][] = $group;
                    }
                }
            }
        }

        // Compatibility aliases required by older tests and legacy keys.
        $this->appendGroup($permissionToGroups, 'province.admin', 'provinceadmin');
        $this->appendGroup($permissionToGroups, 'diocese.admin', 'dioceseadmin');

        return $permissionToGroups;
    }

    /**
     * @param array<int, string> $allPermissions
     * @return array<int, string>
     */
    private function expandPermissionPattern(string $pattern, array $allPermissions): array
    {
        if (! str_contains($pattern, '*')) {
            return [$pattern];
        }

        if ($pattern === '*') {
            return $allPermissions;
        }

        $regex = '#^' . str_replace('\\*', '.*', preg_quote($pattern, '#')) . '$#';

        $matches = [];
        foreach ($allPermissions as $permission) {
            if (preg_match($regex, $permission) === 1) {
                $matches[] = $permission;
            }
        }

        return $matches;
    }

    /**
     * @param array<string, array<int, string>> $map
     */
    private function appendGroup(array &$map, string $permission, string $group): void
    {
        $map[$permission] ??= [];
        if (! in_array($group, $map[$permission], true)) {
            $map[$permission][] = $group;
        }
    }
}
