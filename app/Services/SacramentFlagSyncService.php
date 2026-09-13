<?php

namespace App\Services;

use App\Models\ProfileModel;
use App\Models\SacramentalRecordModel;

/**
 * Keeps baptism/confirmation status consistent across the three places
 * where it is stored:
 *   1. sacramental_record   (canonical event log)
 *   2. person_membership_details (boolean flags used by dashboards)
 *   3. profile.baptism_date / confirmation_date / is_confirmed
 *
 * sacramental_record is the canonical source. Whenever a sacrament row is
 * added/edited/deleted, {@see applyForPerson()} recomputes the derived
 * flags + profile dates from the records and upserts the other two stores.
 */
class SacramentFlagSyncService
{
    private SacramentalRecordModel $recordModel;
    private ProfileModel $profileModel;
    private PersonMembershipDetailsService $membershipDetails;

    public function __construct(
        ?SacramentalRecordModel $recordModel = null,
        ?ProfileModel $profileModel = null,
        ?PersonMembershipDetailsService $membershipDetails = null
    ) {
        $this->recordModel      = $recordModel ?? model(SacramentalRecordModel::class);
        $this->profileModel     = $profileModel ?? model(ProfileModel::class);
        $this->membershipDetails = $membershipDetails ?? new PersonMembershipDetailsService();
    }

    /**
     * Recompute the canonical baptism/confirmation status from sacramental_record.
     *
     * @return array{
     *   is_baptized: bool,
     *   is_confirmed: bool,
     *   baptism_date: string|null,
     *   confirmation_date: string|null,
     * }
     */
    public function recomputeForPerson(int $personId, int $parishId): array
    {
        $records = $this->recordModel->getRecordsForPerson($personId, $parishId);

        $baptismDates    = [];
        $confirmationDates = [];

        foreach ($records as $row) {
            $type = strtolower(trim((string) ($row['sacrament_type'] ?? '')));
            $date = $row['sacrament_date'] ?? null;
            $date = ($date !== null && $date !== '' && $date !== '0000-00-00') ? (string) $date : null;

            if ($type === 'baptism' && $date !== null) {
                $baptismDates[] = $date;
            } elseif ($type === 'baptism') {
                $baptismDates[] = null;
            }

            if ($type === 'confirmation' && $date !== null) {
                $confirmationDates[] = $date;
            } elseif ($type === 'confirmation') {
                $confirmationDates[] = null;
            }
        }

        $isBaptized   = $this->hasType($records, 'baptism');
        $isConfirmed  = $this->hasType($records, 'confirmation');

        $baptismDate       = $this->earliestNonEmpty($baptismDates);
        $confirmationDate  = $this->earliestNonEmpty($confirmationDates);

        // If a baptism/confirmation record exists but has no date, keep any
        // existing profile date rather than wiping it (the record still
        // establishes that the sacrament happened). applyForPerson handles
        // the "only write when changed" guard.
        return [
            'is_baptized'      => $isBaptized,
            'is_confirmed'     => $isConfirmed,
            'baptism_date'     => $baptismDate,
            'confirmation_date'=> $confirmationDate,
        ];
    }

    /**
     * Recompute from sacramental_record and upsert the derived flags + profile
     * dates. Only writes when a value actually changes.
     *
     * @return array<string, mixed> The recomputed values that were applied.
     */
    public function applyForPerson(int $personId, int $parishId, ?int $changedBy = null, ?string $reason = null): array
    {
        $computed = $this->recomputeForPerson($personId, $parishId);

        // 1. Sync person_membership_details flags (preserve the other flags).
        $existing = $this->membershipDetails->forPerson($personId, $parishId);
        $flagPayload = [
            'is_baptized'  => $computed['is_baptized'] ? 1 : 0,
            'is_confirmed' => $computed['is_confirmed'] ? 1 : 0,
            // Preserve unchanged fields so saveForPerson does not reset them.
            'is_communicant'         => (int) ($existing['is_communicant'] ?? 0),
            'is_dependent'           => (int) ($existing['is_dependent'] ?? 0),
            'has_pledge'             => (int) ($existing['has_pledge'] ?? 0),
            'good_standing_override' => (int) ($existing['good_standing_override'] ?? 0),
        ];

        $flagChanged = ((int) ($existing['is_baptized'] ?? 0) !== $flagPayload['is_baptized'])
            || ((int) ($existing['is_confirmed'] ?? 0) !== $flagPayload['is_confirmed']);

        if ($flagChanged) {
            $this->membershipDetails->saveForPerson($personId, $flagPayload, $parishId, $reason ?? 'Synced from sacramental record');
        }

        // 2. Sync profile.baptism_date / confirmation_date / is_confirmed.
        $profile = $this->profileModel->where('person_id', $personId)->first();

        if ($profile) {
            $profileUpdate = [];
            $currentBaptism       = $profile['baptism_date'] ?? null;
            $currentConfirmation  = $profile['confirmation_date'] ?? null;
            $currentIsConfirmed   = (int) ($profile['is_confirmed'] ?? 0);

            // Baptism date: only set if we have a date; only clear if we have
            // no baptism record AND the existing date is set.
            if ($computed['baptism_date'] !== null && $currentBaptism !== $computed['baptism_date']) {
                $profileUpdate['baptism_date'] = $computed['baptism_date'];
            } elseif (! $computed['is_baptized'] && $currentBaptism !== null && $currentBaptism !== '') {
                $profileUpdate['baptism_date'] = null;
            }

            if ($computed['confirmation_date'] !== null && $currentConfirmation !== $computed['confirmation_date']) {
                $profileUpdate['confirmation_date'] = $computed['confirmation_date'];
            } elseif (! $computed['is_confirmed'] && $currentConfirmation !== null && $currentConfirmation !== '') {
                $profileUpdate['confirmation_date'] = null;
            }

            $newIsConfirmed = $computed['is_confirmed'] ? 1 : 0;
            if ($currentIsConfirmed !== $newIsConfirmed) {
                $profileUpdate['is_confirmed'] = $newIsConfirmed;
            }

            if ($profileUpdate !== []) {
                $this->profileModel->update((int) $profile['id'], $profileUpdate);
            }
        }

        return $computed;
    }

    /**
     * Compare sacramental_record against person_membership_details + profile
     * and return a list of human-readable discrepancy descriptors.
     *
     * @return array<int, array{key: string, message: string}>
     */
    public function discrepanciesForPerson(int $personId, int $parishId): array
    {
        $computed = $this->recomputeForPerson($personId, $parishId);
        $details  = $this->membershipDetails->forPerson($personId, $parishId);
        $profile  = $this->profileModel->where('person_id', $personId)->first();

        $discrepancies = [];

        $flagBaptized = (bool) ($details['is_baptized'] ?? 0);
        if ($flagBaptized !== $computed['is_baptized']) {
            if ($computed['is_baptized']) {
                $discrepancies[] = [
                    'key' => 'is_baptized',
                    'message' => 'A Baptism sacrament record exists but the "Baptized" membership flag is OFF.',
                ];
            } else {
                $discrepancies[] = [
                    'key' => 'is_baptized',
                    'message' => 'The "Baptized" membership flag is ON but no Baptism sacrament record exists.',
                ];
            }
        }

        $flagConfirmed = (bool) ($details['is_confirmed'] ?? 0);
        if ($flagConfirmed !== $computed['is_confirmed']) {
            if ($computed['is_confirmed']) {
                $discrepancies[] = [
                    'key' => 'is_confirmed',
                    'message' => 'A Confirmation sacrament record exists but the "Confirmed" membership flag is OFF.',
                ];
            } else {
                $discrepancies[] = [
                    'key' => 'is_confirmed',
                    'message' => 'The "Confirmed" membership flag is ON but no Confirmation sacrament record exists.',
                ];
            }
        }

        if ($profile) {
            $profileBaptism = $profile['baptism_date'] ?? null;
            if ($computed['is_baptized'] && ($profileBaptism === null || $profileBaptism === '') && $computed['baptism_date'] === null) {
                // Record exists without a date and profile has no date — not a discrepancy per se,
                // but flag if profile date is set while no record exists.
            } elseif (! $computed['is_baptized'] && $profileBaptism !== null && $profileBaptism !== '') {
                $discrepancies[] = [
                    'key' => 'baptism_date',
                    'message' => 'profile.baptism_date is set but no Baptism sacrament record exists.',
                ];
            }

            $profileConfirmation = $profile['confirmation_date'] ?? null;
            if (! $computed['is_confirmed'] && $profileConfirmation !== null && $profileConfirmation !== '') {
                $discrepancies[] = [
                    'key' => 'confirmation_date',
                    'message' => 'profile.confirmation_date is set but no Confirmation sacrament record exists.',
                ];
            }

            $profileIsConfirmed = (int) ($profile['is_confirmed'] ?? 0) === 1;
            if ($profileIsConfirmed !== $computed['is_confirmed']) {
                $discrepancies[] = [
                    'key' => 'profile_is_confirmed',
                    'message' => $computed['is_confirmed']
                        ? 'A Confirmation sacrament record exists but profile.is_confirmed is OFF.'
                        : 'profile.is_confirmed is ON but no Confirmation sacrament record exists.',
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     */
    private function hasType(array $records, string $type): bool
    {
        foreach ($records as $row) {
            if (strtolower(trim((string) ($row['sacrament_type'] ?? ''))) === $type) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int, string|null> $dates
     */
    private function earliestNonEmpty(array $dates): ?string
    {
        $nonEmpty = array_filter($dates, static fn ($d) => $d !== null && $d !== '' && $d !== '0000-00-00');
        if ($nonEmpty === []) {
            return null;
        }
        sort($nonEmpty);
        return (string) reset($nonEmpty);
    }
}
