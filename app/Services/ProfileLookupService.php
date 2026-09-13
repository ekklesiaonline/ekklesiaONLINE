<?php

namespace App\Services;

use App\Models\ProfileModel;

/**
 * Profile lookup helpers that enforce parish scope at the query level.
 */
class ProfileLookupService
{
    public function __construct(private ProfileModel $profileModel)
    {
    }

    /**
     * Find a profile by ID only if its user has an active affiliation with the
     * given parish. This prevents fetching profiles outside the actor's scope.
     */
    public function findProfileInParishScope(int $profileId, int $parishId, bool $activeOnly = true): ?array
    {
        if ($profileId <= 0 || $parishId <= 0) {
            return null;
        }

        $builder = $this->profileModel
            ->select('profile.*')
            ->join('parish_affiliation pa', 'pa.linked_user_id = profile.user_id', 'inner')
            ->where('profile.id', $profileId)
            ->where('pa.parish_id', $parishId);

        if ($activeOnly) {
            $builder->where('pa.is_member', 1);
        }

        return $builder->first();
    }
}
