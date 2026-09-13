<?php

namespace App\Services;

use App\Models\PersonMembershipDetailsModel;
use App\Models\ProfileModel;

class PersonMembershipDetailsService
{
    private PersonMembershipDetailsModel $model;
    private ProfileModel $profileModel;
    private PersonStatusHistoryService $history;

    public function __construct(?PersonMembershipDetailsModel $model = null, ?ProfileModel $profileModel = null, ?PersonStatusHistoryService $history = null)
    {
        $this->model        = $model ?? model(PersonMembershipDetailsModel::class);
        $this->profileModel = $profileModel ?? model(ProfileModel::class);
        $this->history      = $history ?? new PersonStatusHistoryService();
    }

    /**
     * Get or create membership details for a person in a parish.
     *
     * @return array<string, mixed>
     */
    public function forPerson(int $personId, ?int $parishId = null): array
    {
        $builder = $this->model->where('person_id', $personId);
        if ($parishId !== null) {
            $builder->where('parish_id', $parishId);
        }

        $row = $builder->first();

        $ageBand = $this->resolveAgeBand($personId);

        if ($row !== null) {
            $row['is_under_16'] = $ageBand;
            return $row;
        }

        $defaults = [
            'person_id'              => $personId,
            'parish_id'              => $parishId,
            'is_baptized'            => 0,
            'is_confirmed'           => 0,
            'is_communicant'         => 0,
            'is_dependent'           => 0,
            'has_pledge'             => 0,
            'pledge_status'          => null,
            'good_standing_override' => 0,
            'is_under_16'            => $ageBand,
        ];

        return $defaults;
    }

    /**
     * Persist membership detail flags for a person.
     *
     * @param array<string, mixed> $data
     */
    public function saveForPerson(int $personId, array $data, ?int $parishId = null, ?string $reason = null): bool
    {
        $existing = $this->model
            ->where('person_id', $personId)
            ->where('parish_id', $parishId)
            ->first();

        $changedBy = auth()->loggedIn() ? (int) auth()->id() : null;

        if ($existing) {
            $result = $this->model->update((int) $existing['id'], $data);
            $this->recordDetailChanges($personId, $parishId, $existing, $data, $changedBy, $reason);
            return $result;
        }

        $data['person_id'] = $personId;
        $data['parish_id'] = $parishId;

        $result = $this->model->insert($data) !== false;
        if ($result) {
            $this->recordDetailChanges($personId, $parishId, [], $data, $changedBy, $reason);
        }

        return $result;
    }

    /**
     * Record changes to milestone attributes in person_status_history.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     */
    private function recordDetailChanges(int $personId, ?int $parishId, array $old, array $new, ?int $changedBy, ?string $reason): void
    {
        $tracked = ['is_baptized', 'is_confirmed', 'is_communicant', 'is_dependent', 'has_pledge'];

        foreach ($tracked as $field) {
            $oldValue = $old[$field] ?? null;
            $newValue = $new[$field] ?? null;

            if ($oldValue === null && $newValue === null) {
                continue;
            }

            if ((int) $oldValue !== (int) $newValue) {
                $this->history->record(
                    $personId,
                    'membership_detail',
                    $oldValue === null ? null : $field . ':' . $oldValue,
                    $field . ':' . $newValue,
                    $parishId,
                    $changedBy,
                    $reason ?? 'Membership details updated'
                );
            }
        }
    }

    /**
     * Determine the age band for a person.
     *
     * Because age-band calculation is intentionally derived from birth_date
     * and the current date, it is not stored in person_membership_details.
     * Child tables/views that need the flag should compute it via the model
     * or use the snapshot's is_under_16 column.
     */
    public function resolveAgeBand(int $personId): ?bool
    {
        $profile = $this->profileModel->find($personId);
        if (!is_array($profile) || empty($profile['birth_date'])) {
            return null;
        }

        $birthDate = (string) $profile['birth_date'];
        if ($birthDate === '' || $birthDate === '0000-00-00') {
            return null;
        }

        return (int) $this->profileModel->db->query(
            'SELECT TIMESTAMPDIFF(YEAR, ?, CURDATE()) < 16 AS is_under_16',
            [$birthDate]
        )->getRow()->is_under_16 === 1;
    }
}
