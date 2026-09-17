<?php

namespace App\Database\Seeds;

use Config\AuthGroups;
use CodeIgniter\Database\Seeder;

/**
 * PermissionsAndRolesSeeder
 *
 * Seeds the Shield auth_permissions and auth_groups (roles) tables with
 * the permissions and roles required by ekklesiaRD.
 */
class PermissionsAndRolesSeeder extends Seeder
{
    public function run()
    {
        $authGroups = config(AuthGroups::class);

        $permissions = [];
        foreach ($authGroups->permissions as $name => $description) {
            $permissions[] = [
                'name' => $name,
                'description' => $description,
            ];
        }

        // Insert permissions
        foreach ($permissions as $permission) {
            $exists = $this->db->table('auth_permissions')
                ->where('name', $permission['name'])
                ->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('auth_permissions')->insert($permission);
            }
        }

        // Create groups (roles)
        $groups = [];
        foreach ($authGroups->groups as $name => $group) {
            $groups[] = [
                'name' => $name,
                'title' => (string) ($group['title'] ?? $name),
            ];
        }

        foreach ($groups as $group) {
            $exists = $this->db->table('auth_groups')
                ->where('name', $group['name'])
                ->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('auth_groups')->insert($group);
            }
        }

        // Assign permissions to groups
        $this->assignPermissionsToRoles($authGroups->matrix, array_keys($authGroups->permissions));
    }

    private function assignPermissionsToRoles(array $rolePerms, array $availablePermissions)
    {
        $availableLookup = array_fill_keys($availablePermissions, true);

        foreach ($rolePerms as $roleName => $permNames) {
            $role = $this->db->table('auth_groups')->where('name', $roleName)->get()->getFirstRow('array');
            if (!$role) {
                continue;
            }

            foreach ($permNames as $permName) {
                foreach ($this->expandPermissionPattern($permName, $availableLookup) as $resolvedPermName) {
                    $perm = $this->db->table('auth_permissions')->where('name', $resolvedPermName)->get()->getFirstRow('array');
                    if (!$perm) {
                        continue;
                    }

                    $exists = $this->db->table('auth_groups_permissions')
                        ->where('group', $role['name'])
                        ->where('permission', $perm['name'])
                        ->countAllResults() > 0;
                    if (! $exists) {
                        $this->db->table('auth_groups_permissions')->insert([
                            'group' => $role['name'],
                            'permission' => $perm['name'],
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Expand wildcard permissions such as "member.*" to concrete permission keys.
     *
     * @param array<string, bool> $availableLookup
     * @return list<string>
     */
    private function expandPermissionPattern(string $permission, array $availableLookup): array
    {
        if (!str_ends_with($permission, '.*')) {
            return isset($availableLookup[$permission]) ? [$permission] : [];
        }

        $prefix = substr($permission, 0, -1);
        $resolved = [];
        foreach ($availableLookup as $name => $_) {
            if (str_starts_with($name, $prefix)) {
                $resolved[] = $name;
            }
        }

        return $resolved;
    }
}
