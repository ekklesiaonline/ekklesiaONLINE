<?php

namespace App\Services;

use App\Models\FollowupLogModel;

class FollowupSchedulingService
{
    /**
     * Schedule active follow-up sequence entries for a person.
     *
     * @return int Number of followup_log rows inserted
     */
    public static function scheduleForPerson(int $personId, int $parishId, string $visitDate, string $timezone = ''): int
    {
        return model(FollowupLogModel::class)->scheduleActiveSequencesForPerson($personId, $parishId, $visitDate, $timezone);
    }

    /**
     * Schedule active follow-up sequence entries for a guest.
     *
     * @return int Number of followup_log rows inserted
     */
    public static function scheduleForGuest(int $guestId, int $parishId, string $visitDate, string $timezone = ''): int
    {
        return model(FollowupLogModel::class)->scheduleActiveSequencesForGuest($guestId, $parishId, $visitDate, $timezone);
    }
}
