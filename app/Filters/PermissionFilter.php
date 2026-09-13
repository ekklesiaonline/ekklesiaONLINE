<?php

namespace App\Filters;

use CodeIgniter\Shield\Filters\PermissionFilter as ShieldPermissionFilter;

class PermissionFilter extends ShieldPermissionFilter
{
    protected function isAuthorized(array $arguments): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        foreach ($arguments as $permission) {
            if ($user->can($permission)) {
                return true;
            }

            if ($permission === 'system.admin' && $user->inGroup('systemadmin')) {
                return true;
            }

            if ($permission === 'province.admin' && $user->inGroup('provinceadmin')) {
                return true;
            }

            if ($permission === 'diocese.admin' && $user->inGroup('dioceseadmin')) {
                return true;
            }
        }

        return false;
    }
}
