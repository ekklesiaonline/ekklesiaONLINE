<?php

namespace App\Services;

use CodeIgniter\Shield\Entities\User;
use Config\AuthGroups;

/**
 * Centralized parish-scope authorization helper.
 *
 * Determines whether the currently authenticated actor may access or mutate
 * data for a given parish, respecting system/province/diocese/parishadmin
 * boundaries.
 */
class ParishScopeValidator
{
    /**
     * Return true if the actor may access the given parish.
     */
    public function canActorAccessParish(?User $user, int $parishId): bool
    {
        if (!$user || $parishId <= 0) {
            return false;
        }

        if ($user->inGroup(AuthGroups::SYSTEM_ADMIN)) {
            return true;
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();

        // Parish admin: only their own home parish.
        if ($user->inGroup(AuthGroups::PARISH_ADMIN) && !$user->inGroup(AuthGroups::DIOCESE_ADMIN) && !$user->inGroup(AuthGroups::PROVINCE_ADMIN)) {
            return (int) ($profile['parish_id'] ?? 0) === $parishId;
        }

        // Diocesan admin: any parish in their diocese.
        if ($user->inGroup(AuthGroups::DIOCESE_ADMIN)) {
            $dioceseId = (int) ($profile['diocese_id'] ?? 0);
            if ($dioceseId <= 0 && !empty($profile['parish_id'])) {
                $parish = model('ParishModel')->find((int) $profile['parish_id']);
                $dioceseId = (int) ($parish['diocese_id'] ?? 0);
            }

            if ($dioceseId <= 0) {
                return false;
            }

            $targetParish = model('ParishModel')->find($parishId);
            return $targetParish !== null && (int) ($targetParish['diocese_id'] ?? 0) === $dioceseId;
        }

        // Province admin: any parish in their province.
        if ($user->inGroup(AuthGroups::PROVINCE_ADMIN)) {
            $provinceId = (int) ($profile['province_id'] ?? 0);
            if ($provinceId <= 0 && !empty($profile['diocese_id'])) {
                $diocese = db_connect()->table('diocese')->where('id', (int) $profile['diocese_id'])->get()->getRowArray();
                $provinceId = (int) ($diocese['province_id'] ?? 0);
            }
            if ($provinceId <= 0 && !empty($profile['parish_id'])) {
                $parish = model('ParishModel')->find((int) $profile['parish_id']);
                $dioceseId = (int) ($parish['diocese_id'] ?? 0);
                if ($dioceseId > 0) {
                    $diocese = db_connect()->table('diocese')->where('id', $dioceseId)->get()->getRowArray();
                    $provinceId = (int) ($diocese['province_id'] ?? 0);
                }
            }

            if ($provinceId <= 0) {
                return false;
            }

            $targetParish = model('ParishModel')->find($parishId);
            if (!$targetParish) {
                return false;
            }

            $dioceseId = (int) ($targetParish['diocese_id'] ?? 0);
            if ($dioceseId <= 0) {
                return false;
            }

            $diocese = db_connect()->table('diocese')->where('id', $dioceseId)->get()->getRowArray();
            return (int) ($diocese['province_id'] ?? 0) === $provinceId;
        }

        return false;
    }

    /**
     * Return the parish IDs the actor is allowed to access.
     *
     * Returns an empty array for system admins, which represents
     * unrestricted access. Callers should check hasUnrestrictedAccess()
     * before interpreting an empty result as "no access".
     *
     * @return list<int>
     */
    public function getAccessibleParishIds(?User $user): array
    {
        if (!$user) {
            return [];
        }

        if ($user->inGroup(AuthGroups::SYSTEM_ADMIN)) {
            return [];
        }

        $profile = model('ProfileModel')->where('user_id', (int) $user->id)->first();

        if ($user->inGroup(AuthGroups::PARISH_ADMIN) && !$user->inGroup(AuthGroups::DIOCESE_ADMIN) && !$user->inGroup(AuthGroups::PROVINCE_ADMIN)) {
            $parishId = (int) ($profile['parish_id'] ?? 0);
            return $parishId > 0 ? [$parishId] : [];
        }

        $dioceseId = null;
        if ($user->inGroup(AuthGroups::DIOCESE_ADMIN)) {
            $dioceseId = (int) ($profile['diocese_id'] ?? 0);
            if ($dioceseId <= 0 && !empty($profile['parish_id'])) {
                $parish = model('ParishModel')->find((int) $profile['parish_id']);
                $dioceseId = (int) ($parish['diocese_id'] ?? 0);
            }
        }

        $provinceId = null;
        if ($user->inGroup(AuthGroups::PROVINCE_ADMIN)) {
            $provinceId = (int) ($profile['province_id'] ?? 0);
        }

        if ($dioceseId <= 0 && $provinceId <= 0) {
            return [];
        }

        $builder = db_connect()->table('parish')->select('id');
        if ($dioceseId > 0) {
            $builder->where('diocese_id', $dioceseId);
        }
        if ($provinceId > 0) {
            $builder->whereIn('diocese_id', function ($builder) use ($provinceId) {
                $builder->select('id')->from('diocese')->where('province_id', $provinceId);
            });
        }

        return array_map(static fn(array $row): int => (int) $row['id'], $builder->get()->getResultArray());
    }

    /**
     * Return true if the actor has unrestricted (system-admin) access to all parishes.
     *
     * Callers should check this before interpreting getAccessibleParishIds()
     * returning [] as "no access" — for system admins, [] means "all parishes".
     */
    public function hasUnrestrictedAccess(?User $user): bool
    {
        return $user !== null && $user->inGroup(AuthGroups::SYSTEM_ADMIN);
    }
}
